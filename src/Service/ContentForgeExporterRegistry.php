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

	protected function getClassMapExporters(): array {
		try {
			$exporters =& $this->classMap->getInstancesByInterface(IContentForgeExporter::class);

			return is_array($exporters) ? $exporters : [];
		} catch (\Throwable) {
			return [];
		}
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
