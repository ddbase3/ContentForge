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

use Base3\LinkTarget\Api\ILinkTargetService;
use ContentForge\Api\IContentForgeExportTarget;
use ContentForge\Api\IContentForgeJsonStorageService;
use ContentForge\Model\ContentForgeDeliveredExport;
use ContentForge\Model\ContentForgeExportRequest;
use ContentForge\Model\ContentForgeExportResult;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeWorkflowContext;
use ContentForge\Service\ContentForgeExportDownloadService;
use ZipArchive;

class ContentForgeDownloadExportTarget implements IContentForgeExportTarget {

	public function __construct(
		private readonly ILinkTargetService $linkTargetService,
		private readonly IContentForgeJsonStorageService $storage
	) {}

	public static function getName(): string {
		return 'contentforgedownloadexporttarget';
	}

	public function deliver(ContentForgeExportResult $result, ContentForgeWorkflowContext $context, ContentForgeExportRequest $request): ContentForgeDeliveredExport {
		$file = $this->writeExportFile($result, $request);
		$id = ContentForgeProject::newId('download');
		$downloadName = $this->buildDownloadName($result, $file['extension']);
		$url = $this->linkTargetService->getLink([
			'name' => ContentForgeExportDownloadService::getName(),
			'out' => 'download'
		], [
			'id' => $id
		]);

		$record = [
			'id' => $id,
			'type' => $result->type,
			'path' => $file['path'],
			'url' => $url,
			'filename' => $downloadName,
			'mimeType' => $this->resolveMimeType($result->type, $file['extension']),
			'createdAt' => gmdate('c'),
			'sourceProjectId' => $context->project->id,
			'exporter' => $request->exporterName,
			'target' => self::getName(),
			'fileCount' => count($result->files)
		];

		$this->storage->upsert('delivered_exports', $id, $record);

		return new ContentForgeDeliveredExport(
			$id,
			$result->type,
			$file['path'],
			$url,
			[
				'fileCount' => count($result->files),
				'filename' => $downloadName,
				'mimeType' => $record['mimeType'],
				'target' => self::getName()
			]
		);
	}

	protected function writeExportFile(ContentForgeExportResult $result, ContentForgeExportRequest $request): array {
		$dir = $this->getExportPath($request);

		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new \RuntimeException('Export directory cannot be created: ' . $dir);
		}

		if (!is_writable($dir)) {
			throw new \RuntimeException('Export directory is not writable: ' . $dir);
		}

		$baseName = $this->buildBaseName($result->title);

		$singleFile = $this->getDirectExportFile($result);

		if ($singleFile !== null) {
			$path = $dir . '/' . $baseName . '-' . date('Ymd-His') . '.' . $singleFile['extension'];
			file_put_contents($path, (string) $singleFile['content']);

			if (is_file($path)) {
				return ['path' => $path, 'extension' => $singleFile['extension']];
			}
		}

		$path = $dir . '/' . $baseName . '-' . date('Ymd-His') . '.zip';

		if (class_exists(ZipArchive::class)) {
			$zip = new ZipArchive();

			if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
				foreach ($result->files as $name => $content) {
					$zip->addFromString((string) $name, (string) $content);
				}

				$zip->close();
			}
		}

		if (is_file($path)) {
			return ['path' => $path, 'extension' => 'zip'];
		}

		$path = $dir . '/' . $baseName . '-' . date('Ymd-His') . '.json';
		file_put_contents($path, json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		return ['path' => $path, 'extension' => 'json'];
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
		$targetConfig = $this->getTargetConfig($request);
		$configuredPath = trim((string) ($targetConfig['path'] ?? ''));

		if ($configuredPath !== '') {
			return $configuredPath;
		}

		if (defined('DIR_PLUGIN')) {
			return rtrim((string) DIR_PLUGIN, '/\\') . '/ContentForge/var/exports';
		}

		return dirname(__DIR__, 2) . '/var/exports';
	}

	protected function getTargetConfig(ContentForgeExportRequest $request): array {
		$config = $request->config['targetConfig'] ?? [];

		return is_array($config) ? $config : [];
	}

	protected function buildBaseName(string $title): string {
		$title = strtolower(trim($title));
		$title = preg_replace('/[^a-z0-9_-]+/i', '-', $title) ?: 'contentforge-export';
		$title = trim($title, '-_');

		return $title !== '' ? substr($title, 0, 80) : 'contentforge-export';
	}

	protected function buildDownloadName(ContentForgeExportResult $result, string $extension): string {
		return $this->buildBaseName($result->title) . '.' . $extension;
	}

	protected function resolveMimeType(string $type, string $extension): string {
		if ($extension === 'zip') {
			return 'application/zip';
		}

		if ($extension === 'pdf' || $type === 'pdf_document') {
			return 'application/pdf';
		}

		if ($extension === 'docx' || $type === 'docx_document') {
			return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
		}

		if ($extension === 'pptx' || $type === 'pptx_presentation') {
			return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
		}

		return 'application/octet-stream';
	}
}
