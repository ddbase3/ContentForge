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

use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use ContentForge\Api\IContentForgeAiProvider;
use ContentForge\Api\IContentForgeAiService;
use ContentForge\Model\ContentForgeAiRequest;
use ContentForge\Model\ContentForgeAiResult;

class ContentForgeAiService implements IContentForgeAiService {

	/** @param IContentForgeAiProvider[] $providers */
	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly array $providers = [],
		private readonly ?ILogger $logger = null
	) {}

	public static function getName(): string {
		return 'contentforgeaiservice';
	}

	public function generateStructured(ContentForgeAiRequest $request): ContentForgeAiResult {
		$settings = $this->getServiceSettings($request->serviceName);

		if ($settings === []) {
			return ContentForgeAiResult::error('LLM service settings not found: ' . $this->normalizeServiceName($request->serviceName));
		}

		if (empty($settings['enabled'])) {
			return ContentForgeAiResult::error('LLM service is disabled: ' . $this->normalizeServiceName($request->serviceName));
		}

		$connectionName = trim((string) ($settings['connection'] ?? ''));
		$connection = $connectionName !== '' ? $this->getConnectionSettings($connectionName) : [];

		if ($connectionName !== '' && $connection === []) {
			return ContentForgeAiResult::error('LLM connection settings not found: ' . $connectionName, '', (string) ($settings['model'] ?? ''));
		}

		$provider = $this->findProvider($settings, $connection);

		if ($provider === null) {
			return ContentForgeAiResult::error('No ContentForge AI provider supports service driver: ' . (string) ($settings['driver'] ?? ''), '', (string) ($settings['model'] ?? ''));
		}

		$this->logDebug('ContentForge AI request started: service=' . $this->normalizeServiceName($request->serviceName) . ' provider=' . $provider::getName() . ' model=' . (string) ($settings['model'] ?? ''), [
			'service' => $this->normalizeServiceName($request->serviceName),
			'provider' => $provider::getName(),
			'model' => (string) ($settings['model'] ?? '')
		]);

		try {
			$result = $provider->generateStructured($request, $settings, $connection);
		} catch (\Throwable $e) {
			$this->logError('ContentForge AI provider failed: ' . $e->getMessage(), [
				'provider' => $provider::getName(),
				'exception' => $e::class,
				'file' => $e->getFile(),
				'line' => $e->getLine()
			]);

			return ContentForgeAiResult::error($e->getMessage(), $provider::getName(), (string) ($settings['model'] ?? ''));
		}

		if ($result->ok) {
			$this->logDebug('ContentForge AI request finished: provider=' . $provider::getName() . ' model=' . $result->model, [
				'provider' => $provider::getName(),
				'model' => $result->model
			]);
		} else {
			$this->logError('ContentForge AI request failed: ' . implode(' | ', $result->errors), [
				'provider' => $provider::getName(),
				'model' => $result->model,
				'errors' => $result->errors
			]);
		}

		return $result;
	}

	public function isAvailable(string $serviceName = ''): bool {
		$status = $this->getStatus($serviceName);

		return !empty($status['available']);
	}

	public function getStatus(string $serviceName = ''): array {
		$serviceName = $this->normalizeServiceName($serviceName);
		$settings = $this->getServiceSettings($serviceName);
		$connectionName = trim((string) ($settings['connection'] ?? ''));
		$connection = $connectionName !== '' ? $this->getConnectionSettings($connectionName) : [];
		$provider = $settings !== [] ? $this->findProvider($settings, $connection) : null;

		return [
			'available' => $settings !== [] && !empty($settings['enabled']) && $provider !== null,
			'serviceName' => $serviceName,
			'serviceFound' => $settings !== [],
			'serviceEnabled' => !empty($settings['enabled']),
			'connectionName' => $connectionName,
			'connectionFound' => $connectionName === '' || $connection !== [],
			'driver' => (string) ($settings['driver'] ?? ''),
			'model' => (string) ($settings['model'] ?? ''),
			'provider' => $provider ? $provider::getName() : ''
		];
	}

	protected function normalizeServiceName(string $serviceName): string {
		$serviceName = trim($serviceName);

		return $serviceName !== '' ? $serviceName : 'mistral_default';
	}

	protected function getServiceSettings(string $serviceName): array {
		$settings = $this->settingsStore->get('service-llm', $this->normalizeServiceName($serviceName), []);

		return $this->normalizeSettings($settings);
	}

	protected function getConnectionSettings(string $connectionName): array {
		$settings = $this->settingsStore->get('connection', $connectionName, []);

		return $this->normalizeSettings($settings);
	}

	protected function normalizeSettings(mixed $settings): array {
		if (is_array($settings)) {
			return $settings;
		}

		if (!is_string($settings) || trim($settings) === '') {
			return [];
		}

		$decoded = json_decode($settings, true);

		return is_array($decoded) ? $decoded : [];
	}

	protected function findProvider(array $serviceSettings, array $connectionSettings): ?IContentForgeAiProvider {
		foreach ($this->providers as $provider) {
			if ($provider instanceof IContentForgeAiProvider && $provider->supports($serviceSettings, $connectionSettings)) {
				return $provider;
			}
		}

		return null;
	}

	protected function logDebug(string $message, array $context = []): void {
		if ($this->logger === null) {
			return;
		}

		try {
			$this->logger->log('contentforge', '[debug] ' . $message . $this->formatLogContext($context));
		} catch (\Throwable) {}
	}

	protected function logError(string $message, array $context = []): void {
		if ($this->logger === null) {
			return;
		}

		try {
			$this->logger->log('contentforge', '[error] ' . $message . $this->formatLogContext($context));
		} catch (\Throwable) {}
	}

	protected function formatLogContext(array $context): string {
		if ($context === []) {
			return '';
		}

		$json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $json !== false ? ' context=' . $json : '';
	}
}
