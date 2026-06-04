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

use ContentForge\Api\IContentForgeExporterRegistry;
use ContentForge\Api\IContentForgeExportService;
use ContentForge\Api\IContentForgeExportTargetRegistry;
use ContentForge\Model\ContentForgeDeliveredExport;
use ContentForge\Model\ContentForgeExportRequest;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeWorkflowContext;

class ContentForgeExportService implements IContentForgeExportService {

	public function __construct(
		private readonly IContentForgeExporterRegistry $exporterRegistry,
		private readonly IContentForgeExportTargetRegistry $targetRegistry
	) {}

	public static function getName(): string {
		return 'contentforgeexportservice';
	}

	public function export(ContentForgeExportRequest $request, ContentForgeWorkflowContext $context): ContentForgeDeliveredExport {
		$exporter = $this->exporterRegistry->getExporter($request->exporterName);
		$target = $this->targetRegistry->getTarget($request->targetName);

		if ($exporter === null || !$exporter->supports($request)) {
			return new ContentForgeDeliveredExport(ContentForgeProject::newId('delivered'), 'error', '', '', ['error' => 'Exporter not available.']);
		}

		if ($target === null) {
			return new ContentForgeDeliveredExport(ContentForgeProject::newId('delivered'), 'error', '', '', ['error' => 'Export target not available.']);
		}

		return $target->deliver($exporter->export($request, $context), $context, $request);
	}
}
