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

use Base3\Api\IClassMap;
use ContentForge\Api\IContentForgeExporter;
use ContentForge\Api\IContentForgeExporterRegistry;

class ContentForgeExporterRegistry implements IContentForgeExporterRegistry {

	/** @param IContentForgeExporter[] $localExporters */
	public function __construct(
		private readonly IClassMap $classMap,
		private readonly array $localExporters = []
	) {}

	public static function getName(): string {
		return 'contentforgeexporterregistry';
	}

	public function getExporter(string $name): ?IContentForgeExporter {
		$name = $this->normalizeName($name);

		foreach ($this->localExporters as $exporter) {
			if ($exporter instanceof IContentForgeExporter && $exporter::getName() === $name) {
				return $exporter;
			}
		}

		$exporter = $this->getClassMapExporter($name);

		if ($exporter !== null) {
			return $exporter;
		}

		foreach ($this->getExporters() as $exporter) {
			if ($exporter::getName() === $name) {
				return $exporter;
			}
		}

		return null;
	}

	public function getExporters(): array {
		$exporters = $this->getClassMapExporters();
		$exporters = array_merge($this->localExporters, $exporters);

		return $this->uniqueExporters($exporters);
	}

	public function getExportOptions(): array {
		$options = [];

		foreach ($this->getExporters() as $exporter) {
			$name = $exporter::getName();
			$options[] = [
				'template' => $this->getExportTemplateForExporter($name),
				'exporter' => $name,
				'type' => $this->getExportTypeForExporter($name),
				'label' => $this->getExportLabelForExporter($name),
				'local' => $this->isLocalExporter($name)
			];
		}

		usort($options, fn($a, $b) => [$this->getOptionSort((string) $a['template']), (string) $a['label']] <=> [$this->getOptionSort((string) $b['template']), (string) $b['label']]);

		return $options;
	}

	protected function getOptionSort(string $template): int {
		return match ($template) {
			'html_package' => 10,
			'scorm12' => 20,
			'pdf_document' => 30,
			'docx_document' => 40,
			'pptx_presentation' => 50,
			default => 100
		};
	}

	protected function getClassMapExporter(string $name): ?IContentForgeExporter {
		try {
			$exporter =& $this->classMap->getInstanceByInterfaceName(IContentForgeExporter::class, $name);

			return $exporter instanceof IContentForgeExporter ? $exporter : null;
		} catch (\Throwable) {
			return null;
		}
	}

	protected function getClassMapExporters(): array {
		try {
			$exporters =& $this->classMap->getInstancesByInterface(IContentForgeExporter::class);

			return is_array($exporters) ? $exporters : [];
		} catch (\Throwable) {
			return [];
		}
	}


	protected function getExportTemplateForExporter(string $name): string {
		return match ($name) {
			'contentforgehtmlpackageexporter' => 'html_package',
			'contentforgescorm12exporter' => 'scorm12',
			'contentforgepdfdocumentexporter' => 'pdf_document',
			'contentforgedocxdocumentexporter' => 'docx_document',
			'contentforgepptxpresentationexporter' => 'pptx_presentation',
			default => $name
		};
	}

	protected function getExportTypeForExporter(string $name): string {
		return match ($name) {
			'contentforgehtmlpackageexporter' => 'html_package',
			'contentforgescorm12exporter' => 'scorm12_package',
			'contentforgepdfdocumentexporter' => 'pdf_document',
			'contentforgedocxdocumentexporter' => 'docx_document',
			'contentforgepptxpresentationexporter' => 'pptx_presentation',
			default => $name
		};
	}

	protected function getExportLabelForExporter(string $name): string {
		return match ($name) {
			'contentforgehtmlpackageexporter' => 'HTML package',
			'contentforgescorm12exporter' => 'SCORM 1.2 package',
			'contentforgepdfdocumentexporter' => 'PDF document',
			'contentforgedocxdocumentexporter' => 'DOCX document',
			'contentforgepptxpresentationexporter' => 'PPTX presentation',
			default => $this->humanizeName($name)
		};
	}

	protected function isLocalExporter(string $name): bool {
		foreach ($this->localExporters as $exporter) {
			if ($exporter instanceof IContentForgeExporter && $exporter::getName() === $name) {
				return true;
			}
		}

		return false;
	}

	protected function humanizeName(string $name): string {
		$name = preg_replace('/^contentforge/', '', $name) ?? $name;
		$name = preg_replace('/exporter$/', '', $name) ?? $name;
		$name = trim((string) preg_replace('/[_-]+/', ' ', $name));

		return $name !== '' ? ucwords($name) : 'Custom exporter';
	}

	protected function normalizeName(string $name): string {
		$name = strtolower(trim($name));

		return preg_replace('/[^a-z0-9._-]+/', '', $name) ?? '';
	}

	protected function uniqueExporters(array $exporters): array {
		$result = [];

		foreach ($exporters as $exporter) {
			if (!$exporter instanceof IContentForgeExporter) {
				continue;
			}

			$name = $exporter::getName();

			if (isset($result[$name])) {
				continue;
			}

			$result[$name] = $exporter;
		}

		return array_values($result);
	}
}
