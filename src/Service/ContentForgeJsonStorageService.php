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
use ContentForge\Api\IContentForgeJsonStorageService;

class ContentForgeJsonStorageService implements IContentForgeJsonStorageService {

	public function __construct(private readonly ?ILogger $logger = null) {}

	public static function getName(): string {
		return 'contentforgejsonstorageservice';
	}

	public function loadCollection(string $collection): array {
		$file = $this->getFile($collection);

		if (!is_file($file)) {
			return [];
		}

		$content = file_get_contents($file);

		if ($content === false) {
			throw new \RuntimeException('ContentForge storage file cannot be read: ' . $file);
		}

		$data = json_decode($content, true);

		return is_array($data) ? $data : [];
	}

	public function saveCollection(string $collection, array $items): void {
		$file = $this->getFile($collection);
		$dir = dirname($file);

		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new \RuntimeException('ContentForge storage directory cannot be created: ' . $dir);
		}

		if (!is_writable($dir)) {
			throw new \RuntimeException('ContentForge storage directory is not writable: ' . $dir);
		}

		$json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($json === false) {
			throw new \RuntimeException('ContentForge storage data cannot be encoded for collection: ' . $collection);
		}

		if (file_put_contents($file, $json, LOCK_EX) === false) {
			throw new \RuntimeException('ContentForge storage file cannot be written: ' . $file);
		}
	}

	public function get(string $collection, string $id): ?array {
		$items = $this->loadCollection($collection);

		return isset($items[$id]) && is_array($items[$id]) ? $items[$id] : null;
	}

	public function upsert(string $collection, string $id, array $item): void {
		$items = $this->loadCollection($collection);
		$items[$id] = $item;
		$this->saveCollection($collection, $items);
	}

	public function delete(string $collection, string $id): void {
		$items = $this->loadCollection($collection);
		unset($items[$id]);
		$this->saveCollection($collection, $items);
	}

	public function describe(): array {
		$basePath = $this->getBasePath();
		$dataPath = $basePath . '/var/data';

		return [
			'basePath' => $basePath,
			'dataPath' => $dataPath,
			'dataPathExists' => is_dir($dataPath),
			'dataPathWritable' => is_dir($dataPath) && is_writable($dataPath),
			'collections' => $this->describeCollections($dataPath)
		];
	}

	protected function describeCollections(string $dataPath): array {
		if (!is_dir($dataPath)) {
			return [];
		}

		$collections = [];

		foreach (glob($dataPath . '/*.json') ?: [] as $file) {
			$collections[basename($file, '.json')] = [
				'file' => $file,
				'size' => is_file($file) ? filesize($file) : 0,
				'writable' => is_writable($file)
			];
		}

		return $collections;
	}

	protected function logDebug(string $message, array $context = []): void {
		if ($this->logger === null) {
			return;
		}

		try {
			$this->logger->log('contentforge', '[debug] ' . $message . $this->formatLogContext($context));
		} catch (\Throwable) {
			// Storage logging must not break storage access.
		}
	}

	protected function formatLogContext(array $context): string {
		if ($context === []) {
			return '';
		}

		$json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $json !== false ? ' context=' . $json : '';
	}

	protected function getFile(string $collection): string {
		$collection = preg_replace('/[^a-z0-9_-]+/i', '', $collection) ?: 'default';

		return $this->getBasePath() . '/var/data/' . $collection . '.json';
	}

	protected function getBasePath(): string {
		if (defined('DIR_PLUGIN')) {
			return rtrim((string) DIR_PLUGIN, '/\\') . '/ContentForge';
		}

		return dirname(__DIR__, 2);
	}
}
