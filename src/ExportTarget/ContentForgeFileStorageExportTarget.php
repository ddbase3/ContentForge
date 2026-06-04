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

namespace ContentForge\ExportTarget;

use ContentForge\Api\IContentForgeExportTarget;
use ContentForge\Model\ContentForgeDeliveredExport;
use ContentForge\Model\ContentForgeExportRequest;
use ContentForge\Model\ContentForgeExportResult;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeWorkflowContext;
use ZipArchive;

class ContentForgeFileStorageExportTarget implements IContentForgeExportTarget {

	public static function getName(): string {
		return 'contentforgefilestorageexporttarget';
	}

	public function deliver(ContentForgeExportResult $result, ContentForgeWorkflowContext $context, ContentForgeExportRequest $request): ContentForgeDeliveredExport {
		$dir = $this->getExportPath($request);

		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new \RuntimeException('Export directory cannot be created: ' . $dir);
		}

		if (!is_writable($dir)) {
			throw new \RuntimeException('Export directory is not writable: ' . $dir);
		}

		$baseName = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($result->title)) ?: 'contentforge-export';
		$baseName = trim($baseName, '-_') ?: 'contentforge-export';

		if ($result->type === 'pdf_document' && isset($result->files['document.pdf'])) {
			$path = $dir . '/' . substr($baseName, 0, 80) . '-' . date('Ymd-His') . '.pdf';
			file_put_contents($path, (string) $result->files['document.pdf']);
		} else {
			$path = $dir . '/' . substr($baseName, 0, 80) . '-' . date('Ymd-His') . '.zip';

			if (class_exists(ZipArchive::class)) {
				$zip = new ZipArchive();

				if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
					foreach ($result->files as $name => $content) {
						$zip->addFromString((string) $name, (string) $content);
					}

					$zip->close();
				}
			}
		}

		if (!is_file($path)) {
			$path = $dir . '/' . substr($baseName, 0, 80) . '-' . date('Ymd-His') . '.json';
			file_put_contents($path, json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}

		return new ContentForgeDeliveredExport(
			ContentForgeProject::newId('delivered'),
			$result->type,
			$path,
			'',
			['fileCount' => count($result->files), 'target' => self::getName()]
		);
	}

	protected function getExportPath(ContentForgeExportRequest $request): string {
		$config = is_array($request->config['targetConfig'] ?? null) ? $request->config['targetConfig'] : [];
		$configuredPath = trim((string) ($config['path'] ?? ''));

		if ($configuredPath !== '') {
			return $configuredPath;
		}

		if (defined('DIR_PLUGIN')) {
			return rtrim((string) DIR_PLUGIN, '/\\') . '/ContentForge/var/exports';
		}

		return dirname(__DIR__, 2) . '/var/exports';
	}
}
