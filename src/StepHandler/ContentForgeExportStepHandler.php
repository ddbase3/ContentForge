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

namespace ContentForge\StepHandler;

use ContentForge\Api\IContentForgeExportService;
use ContentForge\Api\IContentForgeWorkflowNodeHandler;
use ContentForge\Model\ContentForgeExportRequest;
use ContentForge\Model\ContentForgeProposal;
use ContentForge\Model\ContentForgeStepResult;
use ContentForge\Model\ContentForgeWorkflowContext;
use ContentForge\Model\ContentForgeWorkflowNode;

class ContentForgeExportStepHandler implements IContentForgeWorkflowNodeHandler {

	public function __construct(private readonly IContentForgeExportService $exportService) {}

	public static function getName(): string {
		return 'contentforgeexportstephandler';
	}

	public function handle(ContentForgeWorkflowContext $context, ContentForgeWorkflowNode $node): ContentForgeStepResult {
		$exportConfig = $this->resolveExportConfig($context, $node->config);
		$request = new ContentForgeExportRequest(
			$context->project->id,
			$exportConfig['type'],
			$exportConfig['exporter'],
			$exportConfig['target'],
			[],
			array_merge($node->config, [
				'exportTemplate' => $exportConfig['template'],
				'targetConfig' => $exportConfig['targetConfig']
			])
		);

		$delivered = $this->exportService->export($request, $context);

		$proposal = ContentForgeProposal::create(
			$context->project->id,
			$context->instance->id,
			(string) ($context->data['stepRunId'] ?? ''),
			'export_report',
			'Export result',
			$delivered->toArray(),
			['handler' => self::getName()]
		);

		return ContentForgeStepResult::success([$proposal], [$delivered->toArray()], 'exported');
	}

	protected function resolveExportConfig(ContentForgeWorkflowContext $context, array $nodeConfig): array {
		$content = $this->getLatestRevisionContent($context);
		$export = is_array($content['export'] ?? null) ? $content['export'] : [];
		$template = strtolower(trim((string) ($content['exportTemplate'] ?? $export['template'] ?? $nodeConfig['exportTemplate'] ?? 'html_package')));
		$target = $this->normalizeTargetName((string) ($export['target'] ?? $nodeConfig['target'] ?? 'contentforgedownloadexporttarget'));
		$targetConfig = is_array($export['targetConfig'] ?? null) ? $export['targetConfig'] : (is_array($nodeConfig['targetConfig'] ?? null) ? $nodeConfig['targetConfig'] : []);

		$map = [
			'html_package' => ['html_package', 'html_package', 'contentforgehtmlpackageexporter'],
			'scorm12' => ['scorm12', 'scorm12_package', 'contentforgescorm12exporter'],
			'pdf_document' => ['pdf_document', 'pdf_document', 'contentforgepdfdocumentexporter'],
			'docx_document' => ['docx_document', 'docx_document', 'contentforgedocxdocumentexporter'],
			'pptx_presentation' => ['pptx_presentation', 'pptx_presentation', 'contentforgepptxpresentationexporter']
		];

		[$resolvedTemplate, $type, $exporter] = $map[$template] ?? [$template, $template, $template];

		if (($nodeConfig['exporter'] ?? '') !== '' && $template === 'html_package') {
			$exporter = (string) $nodeConfig['exporter'];
		}

		return [
			'template' => $resolvedTemplate,
			'type' => $type,
			'exporter' => $this->normalizeExporterName($exporter),
			'target' => $target,
			'targetConfig' => $targetConfig
		];
	}



	protected function normalizeExporterName(string $value): string {
		$value = strtolower(trim($value));
		$value = preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';

		return $value !== '' ? $value : 'contentforgehtmlpackageexporter';
	}

	protected function normalizeTargetName(string $value): string {
		$value = strtolower(trim($value));
		$value = preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';

		return $value !== '' ? $value : 'contentforgedownloadexporttarget';
	}

	protected function getLatestRevisionContent(ContentForgeWorkflowContext $context): array {
		$revisions = $context->revisions;
		$latest = end($revisions);

		return $latest && is_array($latest->content ?? null) ? $latest->content : [];
	}
}
