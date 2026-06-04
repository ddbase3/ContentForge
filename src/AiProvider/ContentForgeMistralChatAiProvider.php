<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of ContentForge for BASE3 Framework.
 *
 * ContentForge provides a generic human-in-the-loop artifact production
 * architecture with workflow steps, review decisions and export targets.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 **********************************************************************/

namespace ContentForge\AiProvider;

use ContentForge\Api\IContentForgeAiProvider;
use ContentForge\Model\ContentForgeAiRequest;
use ContentForge\Model\ContentForgeAiResult;

class ContentForgeMistralChatAiProvider implements IContentForgeAiProvider {

	public static function getName(): string {
		return 'contentforgemistralchataiprovider';
	}

	public function supports(array $serviceSettings, array $connectionSettings): bool {
		return (string) ($serviceSettings['driver'] ?? '') === 'mistral-chat'
			&& (string) ($connectionSettings['type'] ?? '') === 'http'
			&& trim((string) ($connectionSettings['baseUrl'] ?? '')) !== '';
	}

	public function generateStructured(ContentForgeAiRequest $request, array $serviceSettings, array $connectionSettings): ContentForgeAiResult {
		$model = trim((string) ($serviceSettings['model'] ?? ''));

		if ($model === '') {
			return ContentForgeAiResult::error('Mistral service has no model configured.', self::getName(), $model);
		}

		$secret = $this->getBearerSecret($connectionSettings);

		if ($secret === '') {
			return ContentForgeAiResult::error('Mistral connection has no bearer secret configured.', self::getName(), $model);
		}

		$url = rtrim((string) $connectionSettings['baseUrl'], '/') . '/v1/chat/completions';
		$options = is_array($serviceSettings['options'] ?? null) ? $serviceSettings['options'] : [];
		$requestOptions = $request->options;

		$payload = [
			'model' => $model,
			'messages' => [
				[
					'role' => 'system',
					'content' => $request->systemPrompt
				],
				[
					'role' => 'user',
					'content' => $request->userPrompt
				]
			],
			'temperature' => (float) ($requestOptions['temperature'] ?? $options['temperature'] ?? 0.3),
			'max_tokens' => (int) ($requestOptions['maxTokens'] ?? $options['maxTokens'] ?? 4000),
			'top_p' => (float) ($requestOptions['topP'] ?? $options['topP'] ?? 1),
			'response_format' => [
				'type' => 'json_object'
			]
		];

		$response = $this->postJson($url, $payload, $secret, (int) ($connectionSettings['timeoutSeconds'] ?? 60));

		if (!$response['ok']) {
			return ContentForgeAiResult::error((string) ($response['error'] ?? 'Mistral request failed.'), self::getName(), $model, [
				'httpStatus' => $response['status'] ?? 0,
				'endpoint' => $url
			]);
		}

		$data = json_decode((string) $response['body'], true);

		if (!is_array($data)) {
			return ContentForgeAiResult::error('Mistral response was not valid JSON.', self::getName(), $model, [
				'httpStatus' => $response['status'] ?? 0,
				'bodyPreview' => substr((string) $response['body'], 0, 1000)
			]);
		}

		$text = (string) ($data['choices'][0]['message']['content'] ?? '');

		if ($text === '') {
			return ContentForgeAiResult::error('Mistral response did not contain message content.', self::getName(), $model, [
				'httpStatus' => $response['status'] ?? 0
			]);
		}

		$content = $this->decodeJsonObject($text);

		if ($content === null) {
			return ContentForgeAiResult::error('Mistral message content was not a JSON object.', self::getName(), $model, [
				'rawTextPreview' => substr($text, 0, 1000)
			]);
		}

		return ContentForgeAiResult::success($content, $text, self::getName(), $model, [
			'httpStatus' => $response['status'] ?? 0,
			'usage' => is_array($data['usage'] ?? null) ? $data['usage'] : []
		]);
	}

	protected function getBearerSecret(array $connectionSettings): string {
		$auth = is_array($connectionSettings['auth'] ?? null) ? $connectionSettings['auth'] : [];

		if ((string) ($auth['type'] ?? '') !== 'bearer') {
			return '';
		}

		return trim((string) ($auth['secretValue'] ?? ''));
	}

	protected function postJson(string $url, array $payload, string $secret, int $timeoutSeconds): array {
		$body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($body === false) {
			return [
				'ok' => false,
				'error' => 'Could not encode Mistral request payload.',
				'status' => 0,
				'body' => ''
			];
		}

		if (function_exists('curl_init')) {
			return $this->postJsonWithCurl($url, $body, $secret, $timeoutSeconds);
		}

		return $this->postJsonWithStream($url, $body, $secret, $timeoutSeconds);
	}

	protected function postJsonWithCurl(string $url, string $body, string $secret, int $timeoutSeconds): array {
		$curl = curl_init($url);

		if ($curl === false) {
			return [
				'ok' => false,
				'error' => 'Could not initialize cURL.',
				'status' => 0,
				'body' => ''
			];
		}

		curl_setopt_array($curl, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Accept: application/json',
				'Authorization: Bearer ' . $secret
			],
			CURLOPT_POSTFIELDS => $body
		]);

		$responseBody = curl_exec($curl);
		$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if ($responseBody === false) {
			return [
				'ok' => false,
				'error' => $error !== '' ? $error : 'cURL request failed.',
				'status' => $status,
				'body' => ''
			];
		}

		if ($status < 200 || $status >= 300) {
			return [
				'ok' => false,
				'error' => 'Mistral returned HTTP ' . $status . ': ' . substr((string) $responseBody, 0, 1000),
				'status' => $status,
				'body' => (string) $responseBody
			];
		}

		return [
			'ok' => true,
			'error' => '',
			'status' => $status,
			'body' => (string) $responseBody
		];
	}

	protected function postJsonWithStream(string $url, string $body, string $secret, int $timeoutSeconds): array {
		$context = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => implode("\r\n", [
					'Content-Type: application/json',
					'Accept: application/json',
					'Authorization: Bearer ' . $secret
				]),
				'content' => $body,
				'timeout' => max(1, $timeoutSeconds),
				'ignore_errors' => true
			]
		]);

		$responseBody = @file_get_contents($url, false, $context);
		$status = $this->extractStatusFromHeaders($http_response_header ?? []);

		if ($responseBody === false) {
			return [
				'ok' => false,
				'error' => 'HTTP stream request failed.',
				'status' => $status,
				'body' => ''
			];
		}

		if ($status < 200 || $status >= 300) {
			return [
				'ok' => false,
				'error' => 'Mistral returned HTTP ' . $status . ': ' . substr((string) $responseBody, 0, 1000),
				'status' => $status,
				'body' => (string) $responseBody
			];
		}

		return [
			'ok' => true,
			'error' => '',
			'status' => $status,
			'body' => (string) $responseBody
		];
	}

	protected function extractStatusFromHeaders(array $headers): int {
		foreach ($headers as $header) {
			if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $header, $match)) {
				return (int) $match[1];
			}
		}

		return 0;
	}

	protected function decodeJsonObject(string $text): ?array {
		$decoded = json_decode(trim($text), true);

		if (is_array($decoded)) {
			return $decoded;
		}

		$start = strpos($text, '{');
		$end = strrpos($text, '}');

		if ($start === false || $end === false || $end <= $start) {
			return null;
		}

		$decoded = json_decode(substr($text, $start, $end - $start + 1), true);

		return is_array($decoded) ? $decoded : null;
	}
}
