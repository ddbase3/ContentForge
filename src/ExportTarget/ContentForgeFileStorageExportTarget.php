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

		$singleFile = $this->getDirectExportFile($result);

		if ($singleFile !== null) {
			$path = $dir . '/' . substr($baseName, 0, 80) . '-' . date('Ymd-His') . '.' . $singleFile['extension'];
			file_put_contents($path, (string) $singleFile['content']);
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

	/**
	 * Return a generated single-file office/document export directly.
	 *
	 * DOCX and PPTX are internally ZIP-based formats, but they are final user
	 * files and must not be wrapped in another ZIP archive.
	 */
	protected function getDirectExportFile(ContentForgeExportResult $result): ?array {
		$preferredNames = [
			'pdf_document' => ['document.pdf'],
			'docx_document' => ['document.docx'],
			'pptx_presentation' => ['presentation.pptx']
		];

		foreach (($preferredNames[$result->type] ?? []) as $name) {
			if (!array_key_exists($name, $result->files)) {
				continue;
			}

			$extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

			if (in_array($extension, ['pdf', 'docx', 'pptx'], true)) {
				return [
					'name' => $name,
					'extension' => $extension,
					'content' => $result->files[$name]
				];
			}
		}

		foreach ($result->files as $name => $content) {
			$extension = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));

			if (in_array($extension, ['pdf', 'docx', 'pptx'], true)) {
				return [
					'name' => (string) $name,
					'extension' => $extension,
					'content' => $content
				];
			}
		}

		return null;
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
