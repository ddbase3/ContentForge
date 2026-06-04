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

namespace ContentForge\Service;

use ContentForge\Api\IContentForgeJsonStorageService;
use ContentForge\Api\IContentForgeMaterialIntakeService;
use ContentForge\Model\ContentForgeMaterial;

class ContentForgeMaterialIntakeService implements IContentForgeMaterialIntakeService {

	private const MAX_WEB_BYTES = 1048576;

	public function __construct(private readonly IContentForgeJsonStorageService $storage) {}

	public static function getName(): string {
		return 'contentforgematerialintakeservice';
	}

	public function createTextMaterial(string $projectId, string $name, string $content, array $meta = []): ContentForgeMaterial {
		$material = ContentForgeMaterial::create($projectId, $name, $content, 'text/plain', array_merge([
			'materialType' => 'text'
		], $meta));
		$this->storage->upsert('materials', $material->id, $material->toArray());

		return $material;
	}

	public function createWebLinkMaterial(string $projectId, string $name, string $url, array $meta = []): ContentForgeMaterial {
		$url = $this->normalizeWebUrl($url);
		$fetched = $this->fetchWebUrl($url);
		$title = $this->resolveMaterialTitle($name, $fetched['title'], $url);
		$content = $this->buildWebMaterialText($url, $fetched);

		$material = ContentForgeMaterial::create($projectId, $title, $content, 'text/plain', array_merge([
			'materialType' => 'web_url',
			'sourceUrl' => $url,
			'sourceTitle' => $fetched['title'],
			'sourceMimeType' => $fetched['mimeType'],
			'sourceStatus' => $fetched['status'],
			'extractedLength' => strlen($fetched['text']),
			'fetchedAt' => gmdate('c')
		], $meta));
		$this->storage->upsert('materials', $material->id, $material->toArray());

		return $material;
	}

	public function prepareWebLinkMaterial(string $name, string $url): array {
		$url = $this->normalizeWebUrl($url);
		$fetched = $this->fetchWebUrl($url);
		$title = $this->resolveMaterialTitle($name, $fetched['title'], $url);
		$content = $this->buildWebMaterialText($url, $fetched);

		return [
			'name' => $title,
			'type' => 'web_url',
			'url' => $url,
			'content' => $content,
			'contentPreview' => substr($content, 0, 1200),
			'contentLength' => strlen($content),
			'meta' => [
				'materialType' => 'web_url',
				'sourceUrl' => $url,
				'sourceTitle' => $fetched['title'],
				'sourceMimeType' => $fetched['mimeType'],
				'sourceStatus' => $fetched['status'],
				'extractedLength' => strlen($fetched['text']),
				'fetchedAt' => gmdate('c')
			]
		];
	}

	public function saveMaterial(ContentForgeMaterial $material): ContentForgeMaterial {
		$this->storage->upsert('materials', $material->id, $material->toArray());

		return $material;
	}

	public function deleteMaterial(string $id): bool {
		$material = $this->getMaterial($id);

		if ($material === null) {
			return false;
		}

		$this->storage->delete('materials', $id);

		return true;
	}

	public function getMaterial(string $id): ?ContentForgeMaterial {
		$data = $this->storage->get('materials', $id);

		return $data ? ContentForgeMaterial::fromArray($data) : null;
	}

	public function getProjectMaterials(string $projectId): array {
		$result = [];

		foreach ($this->storage->loadCollection('materials') as $data) {
			$material = ContentForgeMaterial::fromArray((array) $data);

			if ($material->projectId === $projectId) {
				$result[] = $material;
			}
		}

		return $result;
	}

	protected function resolveMaterialTitle(string $name, string $sourceTitle, string $fallback): string {
		$name = trim($name);
		$genericNames = ['material', 'main material', 'web link', 'text material'];

		if ($name !== '' && !in_array(strtolower($name), $genericNames, true)) {
			return $name;
		}

		$sourceTitle = trim($sourceTitle);

		return $sourceTitle !== '' ? $sourceTitle : $fallback;
	}

	protected function normalizeWebUrl(string $url): string {
		$url = trim($url);

		if ($url === '') {
			throw new \InvalidArgumentException('Web link material requires a URL.');
		}

		$parts = parse_url($url);
		$scheme = strtolower((string) ($parts['scheme'] ?? ''));
		$host = strtolower((string) ($parts['host'] ?? ''));

		if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
			throw new \InvalidArgumentException('Only http and https web links are supported.');
		}

		$this->assertPublicHost($host);

		return $url;
	}

	protected function assertPublicHost(string $host): void {
		if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
			throw new \InvalidArgumentException('Local web links are not allowed for material intake.');
		}

		$ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

		foreach ($ips as $ip) {
			if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				throw new \InvalidArgumentException('Private or reserved web link hosts are not allowed for material intake.');
			}
		}
	}

	protected function fetchWebUrl(string $url): array {
		if (function_exists('curl_init')) {
			return $this->fetchWebUrlWithCurl($url);
		}

		return $this->fetchWebUrlWithStreams($url);
	}

	protected function fetchWebUrlWithCurl(string $url): array {
		$body = '';
		$handle = curl_init($url);

		if ($handle === false) {
			throw new \RuntimeException('Could not initialize HTTP client.');
		}

		curl_setopt_array($handle, [
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 3,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_USERAGENT => 'ContentForge/0.1',
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_HEADER => false,
			CURLOPT_WRITEFUNCTION => function($handle, string $chunk) use (&$body): int {
				$remaining = self::MAX_WEB_BYTES - strlen($body);

				if ($remaining <= 0) {
					return 0;
				}

				$body .= substr($chunk, 0, $remaining);

				return strlen($chunk);
			}
		]);

		curl_exec($handle);
		$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		$mimeType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
		$error = curl_error($handle);
		curl_close($handle);

		if ($error !== '' && $body === '') {
			throw new \RuntimeException('Could not download web link: ' . $error);
		}

		if ($status >= 400 || $status === 0) {
			throw new \RuntimeException('Web link returned HTTP status ' . $status . '.');
		}

		return $this->extractWebText($body, $mimeType, $status);
	}

	protected function fetchWebUrlWithStreams(string $url): array {
		$context = stream_context_create([
			'http' => [
				'timeout' => 20,
				'follow_location' => 1,
				'max_redirects' => 3,
				'header' => "User-Agent: ContentForge/0.1\r\n"
			]
		]);
		$body = @file_get_contents($url, false, $context, 0, self::MAX_WEB_BYTES);

		if (!is_string($body) || $body === '') {
			throw new \RuntimeException('Could not download web link.');
		}

		$status = 200;
		$mimeType = '';

		foreach (($http_response_header ?? []) as $header) {
			if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $header, $match)) {
				$status = (int) $match[1];
			}

			if (stripos($header, 'Content-Type:') === 0) {
				$mimeType = trim(substr($header, strlen('Content-Type:')));
			}
		}

		if ($status >= 400) {
			throw new \RuntimeException('Web link returned HTTP status ' . $status . '.');
		}

		return $this->extractWebText($body, $mimeType, $status);
	}

	protected function extractWebText(string $body, string $mimeType, int $status): array {
		$title = '';

		if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $match)) {
			$title = $this->cleanExtractedText($match[1]);
		}

		$text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $body) ?? $body;
		$text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text) ?? $text;
		$text = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', ' ', $text) ?? $text;
		$text = preg_replace('/<(nav|header|footer|form|aside)\b[^>]*>.*?<\/\1>/is', ' ', $text) ?? $text;
		$text = preg_replace('/<(br|p|div|section|article|li|h[1-6])\b[^>]*>/i', "\n", $text) ?? $text;
		$text = strip_tags($text);
		$text = $this->cleanExtractedText($text);

		if ($text === '') {
			throw new \RuntimeException('Web link did not contain extractable text.');
		}

		return [
			'title' => $title,
			'text' => $text,
			'mimeType' => $mimeType,
			'status' => $status
		];
	}

	protected function cleanExtractedText(string $text): string {
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
		$text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;

		return trim($text);
	}

	protected function buildWebMaterialText(string $url, array $fetched): string {
		$parts = [
			'Source URL: ' . $url
		];

		if ($fetched['title'] !== '') {
			$parts[] = 'Source title: ' . $fetched['title'];
		}

		$parts[] = '';
		$parts[] = $fetched['text'];

		return trim(implode("\n", $parts));
	}
}
