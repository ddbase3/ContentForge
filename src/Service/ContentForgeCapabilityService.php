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

use ContentForge\Api\IContentForgeArtifactRendererRegistry;
use ContentForge\Api\IContentForgeCapabilityService;
use ContentForge\Api\IContentForgeExporterRegistry;
use ContentForge\Api\IContentForgeExportTargetRegistry;
use ContentForge\Api\IContentForgeMaterialParserRegistry;
use ContentForge\Api\IContentForgeSectionTemplateRegistry;
use ContentForge\Api\IContentForgeWorkflowDefinitionService;
use ContentForge\Api\IContentForgeWorkflowNodeHandlerRegistry;

class ContentForgeCapabilityService implements IContentForgeCapabilityService {

	public function __construct(
		private readonly IContentForgeWorkflowDefinitionService $definitionService,
		private readonly IContentForgeWorkflowNodeHandlerRegistry $handlerRegistry,
		private readonly IContentForgeMaterialParserRegistry $parserRegistry,
		private readonly IContentForgeArtifactRendererRegistry $rendererRegistry,
		private readonly IContentForgeExporterRegistry $exporterRegistry,
		private readonly IContentForgeExportTargetRegistry $targetRegistry,
		private readonly IContentForgeSectionTemplateRegistry $sectionTemplateRegistry
	) {}

	public static function getName(): string {
		return 'contentforgecapabilityservice';
	}

	public function getCapabilities(): array {
		return [
			'plugin' => 'ContentForge',
			'version' => $this->getVersion(),
			'workflowDefinitions' => array_map(fn($definition) => $definition->toArray(), $this->definitionService->getDefinitions()),
			'stepHandlers' => array_map(fn($handler) => $handler::getName(), $this->handlerRegistry->getHandlers()),
			'parsers' => array_map(fn($parser) => $parser::getName(), $this->parserRegistry->getParsers()),
			'renderers' => array_map(fn($renderer) => $renderer::getName(), $this->rendererRegistry->getRenderers()),
			'exporters' => array_map(fn($exporter) => $exporter::getName(), $this->exporterRegistry->getExporters()),
			'exportOptions' => $this->exporterRegistry->getExportOptions(),
			'exportTargets' => array_map(fn($target) => $target::getName(), $this->targetRegistry->getTargets()),
			'artifactTypes' => ['text', 'json', 'html_micro_module', 'html_package', 'scorm12_package', 'pdf_document', 'docx_document', 'pptx_presentation', 'quality_report'],
			'materialTypes' => ['text', 'web_url'],
			'exportTemplates' => array_map(fn($option) => $option['template'], $this->exporterRegistry->getExportOptions()),
			'generatorTemplates' => array_map(fn($template) => $template->getKey(), $this->sectionTemplateRegistry->getTemplates()),
			'sectionTemplates' => $this->sectionTemplateRegistry->getClientDefinitions(),
			'decisionTypes' => ['accept', 'accept_with_changes', 'request_changes', 'reject', 'skip', 'branch']
		];
	}

	protected function getVersion(): string {
		$file = dirname(__DIR__, 2) . '/VERSION';

		return is_file($file) ? trim((string) file_get_contents($file)) : '0.1.1-patch002';
	}
}
