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

use Base3\Api\IOutput;
use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;
use ContentForge\Api\IContentForgeJsonStorageService;

class ContentForgeExportDownloadService implements IOutput {

	public function __construct(
		private readonly IContentForgeJsonStorageService $storage,
		private readonly IRequest $request,
		private readonly ?ILogger $logger = null
	) {}

	public static function getName(): string {
		return 'contentforgeexportdownloadservice';
	}

	public function getOutput(string $out = 'download', bool $final = false): string {
		$id = $this->normalizeId((string) $this->request->request('id', ''));

		if ($id === '') {
			$this->sendErrorHeaders(400);
			return 'Missing export id.';
		}

		$record = $this->storage->get('delivered_exports', $id);

		if (!is_array($record)) {
			$this->sendErrorHeaders(404);
			return 'Export not found.';
		}

		$path = (string) ($record['path'] ?? '');

		if (!$this->isAllowedExportPath($path) || !is_file($path) || !is_readable($path)) {
			$this->logError('ContentForge download file is not readable.', [
				'id' => $id,
				'path' => $path
			]);
			$this->sendErrorHeaders(404);
			return 'Export file not found.';
		}

		$filename = $this->sanitizeFilename((string) ($record['filename'] ?? basename($path)));
		$mimeType = trim((string) ($record['mimeType'] ?? 'application/octet-stream')) ?: 'application/octet-stream';

		if (!headers_sent()) {
			header('Content-Type: ' . $mimeType);
			header('Content-Disposition: attachment; filename="' . addcslashes($filename, '"\\') . '"');
			header('Content-Length: ' . (string) filesize($path));
			header('X-Content-Type-Options: nosniff');
		}

		$content = file_get_contents($path);

		return is_string($content) ? $content : '';
	}

	protected function normalizeId(string $id): string {
		return preg_replace('/[^a-zA-Z0-9_-]+/', '', trim($id)) ?? '';
	}

	protected function sanitizeFilename(string $filename): string {
		$filename = basename($filename);
		$filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename) ?: 'contentforge-export.bin';

		return trim($filename, '-_') ?: 'contentforge-export.bin';
	}

	protected function isAllowedExportPath(string $path): bool {
		if ($path === '') {
			return false;
		}

		$realPath = realpath($path);
		$exportPath = realpath($this->getExportPath());

		if ($realPath === false || $exportPath === false) {
			return false;
		}

		return str_starts_with($realPath, rtrim($exportPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
	}

	protected function getExportPath(): string {
		if (defined('DIR_PLUGIN')) {
			return rtrim((string) DIR_PLUGIN, '/\\') . '/ContentForge/var/exports';
		}

		return dirname(__DIR__, 2) . '/var/exports';
	}

	protected function sendErrorHeaders(int $status): void {
		if (headers_sent()) {
			return;
		}

		header('Content-Type: text/plain; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');

		if ($status === 400) {
			header('HTTP/1.1 400 Bad Request');
		} elseif ($status === 404) {
			header('HTTP/1.1 404 Not Found');
		}
	}

	protected function logError(string $message, array $context = []): void {
		if ($this->logger === null) {
			return;
		}

		try {
			$json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$this->logger->log('contentforge', '[error] ' . $message . ($json ? ' context=' . $json : ''));
		} catch (\Throwable) {
			// Download logging must not break response delivery.
		}
	}
}
