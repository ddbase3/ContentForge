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
use ContentForge\Api\IContentForgeCapabilityService;
use ContentForge\Api\IContentForgeJsonStorageService;
use ContentForge\Api\IContentForgeMaterialIntakeService;
use ContentForge\Api\IContentForgeProjectService;
use ContentForge\Api\IContentForgeProposalService;
use ContentForge\Api\IContentForgeSectionTemplateRegistry;
use ContentForge\Api\IContentForgeWorkflowDefinitionService;
use ContentForge\Api\IContentForgeWorkflowRunnerService;
use ContentForge\Model\ContentForgeDecision;
use ContentForge\Model\ContentForgeMaterial;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeProposal;
use ContentForge\Model\ContentForgeWorkflowInstance;

class ContentForgeWorkbenchService implements IOutput {

	private string $requestId = '';
	private array $warnings = [];
	private ?array $requestData = null;

	public function __construct(
		private readonly IContentForgeProjectService $projectService,
		private readonly IContentForgeWorkflowDefinitionService $definitionService,
		private readonly IContentForgeWorkflowRunnerService $runnerService,
		private readonly IContentForgeMaterialIntakeService $materialService,
		private readonly IContentForgeProposalService $proposalService,
		private readonly IContentForgeCapabilityService $capabilityService,
		private readonly IContentForgeSectionTemplateRegistry $sectionTemplateRegistry,
		private readonly IContentForgeJsonStorageService $storage,
		private readonly IRequest $request,
		private readonly ?ILogger $logger = null
	) {}

	public static function getName(): string {
		return 'contentforgeworkbenchservice';
	}

	public function getOutput(string $out = 'json', bool $final = false): string {
		$bufferLevel = ob_get_level();
		ob_start();

		$this->requestId = ContentForgeProject::newId('request');
		$this->warnings = [];
		$this->requestData = null;
		$action = 'snapshot';

		try {
			$action = $this->input('action', 'snapshot');
			$payload = match ($action) {
				'start_widget' => $this->startWidget(),
				'request_widget_changes' => $this->requestWidgetChanges(),
				'accept_widget' => $this->acceptWidget(),
				'accept_widget_changes' => $this->acceptWidget(true),
				'apply_widget_edits' => $this->applyWidgetEdits(),
				'create_project' => $this->createProject(),
				'add_material' => $this->addMaterial(),
				'preview_material' => $this->previewMaterial(),
				'save_material' => $this->saveMaterial(),
				'delete_material' => $this->deleteMaterial(),
				'run_step' => $this->runStep(),
				'decide' => $this->decide(),
				'capabilities' => $this->capabilities(),
				default => $this->snapshot()
			};
		} catch (\Throwable $e) {
			$payload = $this->failException($e, $action);
		}

		$unexpectedOutput = '';

		if (ob_get_level() > $bufferLevel) {
			$unexpectedOutput = trim((string) ob_get_clean());
		}

		if ($unexpectedOutput !== '') {
			$this->warnings[] = 'Unexpected PHP output was captured before the JSON response.';
			$payload['diagnostics']['unexpectedOutput'] = substr($unexpectedOutput, 0, 4000);
			$this->logError('ContentForge captured unexpected service output before JSON response.', [
				'action' => $action,
				'unexpectedOutput' => substr($unexpectedOutput, 0, 4000)
			], $this->storage->describe());
		}

		$payload = $this->decoratePayload($payload, $action);
		$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $json !== false ? $json : '{"ok":false,"error":"ContentForge could not encode JSON response."}';
	}

	protected function snapshot(): array {
		$projects = array_map(fn($project) => $project->toArray(), $this->projectService->getProjects());

		return [
			'ok' => true,
			'action' => 'snapshot',
			'projects' => $projects,
			'capabilities' => $this->capabilityService->getCapabilities()
		];
	}

	protected function startWidget(): array {
		$title = $this->input('title', 'ContentForge Test Project');
		$generatorTemplate = $this->normalizeGeneratorTemplate($this->input('generatorTemplate', 'micro_learning'));
		$targetSectionCount = $this->normalizeTargetSectionCount($this->input('targetSectionCount', 'auto'));
		$materialInputs = $this->getWidgetMaterialInputs();

		if ($materialInputs === []) {
			return $this->fail('At least one material item is required.', [
				'expectedField' => 'materialsJson'
			]);
		}

		$project = $this->projectService->createProject($title, 'Created from ContentForge Step Widget.');
		$instance = $this->runnerService->startWorkflow($project, $this->definitionService->getDefaultDefinition());
		$generationMeta = [
			'generatorTemplate' => $generatorTemplate,
			'targetSectionCount' => $targetSectionCount
		];
		$materials = [];

		foreach ($materialInputs as $index => $input) {
			$meta = array_merge($generationMeta, $this->safeMaterialMeta($input['meta'] ?? []), [
				'widgetMaterialIndex' => $index,
				'widgetMaterialType' => $input['type']
			]);

			try {
				if ($input['type'] === 'web_url' && trim((string) ($input['content'] ?? '')) !== '') {
					$materials[] = $this->materialService->createTextMaterial($project->id, $input['name'], (string) $input['content'], array_merge($meta, [
						'materialType' => 'web_url',
						'sourceUrl' => (string) ($input['url'] ?? '')
					]));
				} elseif ($input['type'] === 'web_url') {
					$materials[] = $this->materialService->createWebLinkMaterial($project->id, $input['name'], $input['url'], $meta);
				} else {
					$materials[] = $this->materialService->createTextMaterial($project->id, $input['name'], $input['content'], $meta);
				}
			} catch (\Throwable $e) {
				return $this->fail('Material could not be processed: ' . $e->getMessage(), [
					'materialIndex' => $index,
					'materialType' => $input['type'],
					'materialName' => $input['name'],
					'url' => $input['url'] ?? ''
				]);
			}
		}

		$stepRun = $this->runnerService->runCurrentNode($instance);
		$updated = $this->runnerService->getWorkflowInstance($instance->id) ?? $instance;

		return [
			'ok' => true,
			'action' => 'start_widget',
			'project' => $project->toArray(),
			'workflowInstance' => $updated->toArray(),
			'materials' => array_map(fn($material) => $this->materialPreview($material->toArray()), $materials),
			'stepRun' => $stepRun->toArray(),
			'pendingProposals' => $this->proposalList($project->id, 'html_micro_module')
		];
	}

	protected function requestWidgetChanges(): array {
		$proposal = $this->getRequestedProposal();
		$instance = $this->getRequestedWorkflowInstance($proposal);

		if ($proposal === null) {
			return $this->fail('Proposal not found.', $this->requestLookupContext());
		}

		if ($instance === null) {
			return $this->fail('Workflow instance not found.', $this->requestLookupContext($proposal));
		}

		$this->syncWidgetMaterialsForProject($proposal);

		$feedback = $this->buildWidgetFeedback($proposal);
		$decision = ContentForgeDecision::create($proposal->projectId, $instance->id, $proposal->stepRunId, $proposal->id, 'request_changes', $feedback);
		$updated = $this->runnerService->submitDecision($instance, $decision);
		$stepRun = $this->runnerService->runCurrentNode($updated);
		$updated = $this->runnerService->getWorkflowInstance($updated->id) ?? $updated;

		return [
			'ok' => true,
			'action' => 'request_widget_changes',
			'decision' => $decision->toArray(),
			'workflowInstance' => $updated->toArray(),
			'stepRun' => $stepRun->toArray(),
			'materials' => $this->getProjectMaterialPreviews($proposal->projectId),
			'pendingProposals' => $this->proposalList($proposal->projectId, 'html_micro_module')
		];
	}

	protected function acceptWidget(bool $withChanges = false): array {
		$proposal = $this->getRequestedProposal();
		$instance = $this->getRequestedWorkflowInstance($proposal);

		if ($proposal === null) {
			return $this->fail('Proposal not found.', $this->requestLookupContext());
		}

		if ($instance === null) {
			return $this->fail('Workflow instance not found.', $this->requestLookupContext($proposal));
		}

		$editedContent = null;
		$decisionType = 'accept';
		$exportTemplate = $this->normalizeExportTemplate($this->input('exportTemplate', 'html_package'));
		$exportTarget = $this->normalizeExportTarget($this->input('exportTarget', 'contentforgedownloadexporttarget'));
		$exportTargetConfig = $this->decodeExportTargetConfig($this->input('exportTargetConfigJson'));

		if ($withChanges) {
			$editedContent = $this->getEditedContentFromRequest();

			if ($editedContent === null) {
				return $this->fail('Edited content is missing or invalid.', $this->requestLookupContext($proposal));
			}

			$decisionType = 'accept_with_changes';
		} else {
			$editedContent = $proposal->content;
		}

		$editedContent = $this->withExportSelection($editedContent, $exportTemplate, $exportTarget, $exportTargetConfig);

		$decision = ContentForgeDecision::create($proposal->projectId, $instance->id, $proposal->stepRunId, $proposal->id, $decisionType, '', $editedContent);
		$updated = $this->runnerService->submitDecision($instance, $decision);
		$stepRun = null;

		if ($updated->status === 'running' && $updated->currentNodeId !== '') {
			$stepRun = $this->runnerService->runCurrentNode($updated);
			$updated = $this->runnerService->getWorkflowInstance($updated->id) ?? $updated;
		}

		return [
			'ok' => true,
			'action' => $withChanges ? 'accept_widget_changes' : 'accept_widget',
			'decision' => $decision->toArray(),
			'workflowInstance' => $updated->toArray(),
			'stepRun' => $stepRun ? $stepRun->toArray() : null,
			'pendingProposals' => $this->proposalList($proposal->projectId, 'export_report')
		];
	}

	protected function applyWidgetEdits(): array {
		$proposal = $this->getRequestedProposal();
		$instance = $this->getRequestedWorkflowInstance($proposal);

		if ($proposal === null) {
			return $this->fail('Proposal not found.', $this->requestLookupContext());
		}

		if ($instance === null) {
			return $this->fail('Workflow instance not found.', $this->requestLookupContext($proposal));
		}

		$editedContent = $this->getEditedContentFromRequest();

		if ($editedContent === null) {
			return $this->fail('Edited content is missing or invalid.', $this->requestLookupContext($proposal));
		}

		$editedContent['review'] = is_array($editedContent['review'] ?? null) ? $editedContent['review'] : [];
		$editedContent['review']['status'] = 'manual_revision_ready_for_review';
		$editedContent['review']['manualEditApplied'] = true;
		$editedContent['review']['manualEditAt'] = gmdate('c');

		$nextProposal = ContentForgeProposal::create(
			$proposal->projectId,
			$instance->id,
			$proposal->stepRunId,
			$proposal->type,
			(string) ($editedContent['title'] ?? $proposal->title),
			$editedContent,
			array_merge($proposal->meta, [
				'manualEdit' => true,
				'previousProposalId' => $proposal->id
			])
		);

		$this->proposalService->updateStatus($proposal->id, 'manually_revised');
		$this->proposalService->saveProposal($nextProposal);

		return [
			'ok' => true,
			'action' => 'apply_widget_edits',
			'workflowInstance' => $instance->toArray(),
			'proposal' => $nextProposal->toArray(),
			'pendingProposals' => $this->proposalList($proposal->projectId, 'html_micro_module')
		];
	}

	protected function previewMaterial(): array {
		$type = strtolower(trim($this->input('materialType', 'text')));
		$name = $this->input('name', 'Material');

		if ($type === 'url' || $type === 'link' || $type === 'web') {
			$type = 'web_url';
		}

		try {
			if ($type === 'web_url') {
				$url = $this->input('url');

				if ($url === '') {
					return $this->fail('Web link URL is required.', ['materialType' => $type]);
				}

				$prepared = $this->materialService->prepareWebLinkMaterial($name, $url);

				return [
					'ok' => true,
					'action' => 'preview_material',
					'material' => $prepared
				];
			}

			$content = $this->input('content');

			return [
				'ok' => true,
				'action' => 'preview_material',
				'material' => [
					'name' => $name !== '' ? $name : 'Text material',
					'type' => 'text',
					'content' => $content,
					'contentPreview' => substr($content, 0, 1200),
					'contentLength' => strlen($content),
					'meta' => [
						'materialType' => 'text'
					]
				]
			];
		} catch (\Throwable $e) {
			return $this->fail('Material preview failed: ' . $e->getMessage(), [
				'materialType' => $type,
				'name' => $name,
				'url' => $this->input('url')
			]);
		}
	}


	protected function saveMaterial(): array {
		$projectId = $this->input('projectId');

		if ($projectId === '') {
			return $this->fail('projectId is required to save material.', [
				'projectId' => $projectId
			]);
		}

		$input = $this->normalizeWidgetMaterialInput([
			'id' => $this->input('materialId'),
			'type' => $this->input('materialType', 'text'),
			'name' => $this->input('name', 'Material'),
			'url' => $this->input('url'),
			'content' => $this->input('content'),
			'meta' => $this->decodeMaterialMeta($this->input('metaJson'))
		]);

		if ($input === null) {
			return $this->fail('Material is empty or invalid.', [
				'projectId' => $projectId,
				'materialType' => $this->input('materialType', 'text'),
				'hasContent' => $this->input('content') !== '',
				'hasUrl' => $this->input('url') !== ''
			]);
		}

		$material = $this->saveWidgetMaterialInput($projectId, $input, $this->safeMaterialMeta($input['meta'] ?? []));

		return [
			'ok' => true,
			'action' => 'save_material',
			'material' => $this->materialPreview($material->toArray()),
			'materials' => $this->getProjectMaterialPreviews($projectId)
		];
	}

	protected function deleteMaterial(): array {
		$projectId = $this->input('projectId');
		$materialId = $this->input('materialId');

		if ($projectId === '' || $materialId === '') {
			return $this->fail('projectId and materialId are required to delete material.', [
				'projectId' => $projectId,
				'materialId' => $materialId
			]);
		}

		$material = $this->materialService->getMaterial($materialId);

		if ($material === null || $material->projectId !== $projectId) {
			return $this->fail('Material not found for this project.', [
				'projectId' => $projectId,
				'materialId' => $materialId
			]);
		}

		$this->materialService->deleteMaterial($materialId);

		return [
			'ok' => true,
			'action' => 'delete_material',
			'deletedMaterialId' => $materialId,
			'materials' => $this->getProjectMaterialPreviews($projectId)
		];
	}

	protected function decodeMaterialMeta(string $json): array {
		if ($json === '') {
			return [];
		}

		$decoded = json_decode($json, true);

		return is_array($decoded) ? $this->safeMaterialMeta($decoded) : [];
	}

	protected function getWidgetMaterialInputs(): array {
		$items = [];
		$json = $this->input('materialsJson');

		if ($json !== '') {
			$decoded = json_decode($json, true);

			if (is_array($decoded)) {
				foreach ($decoded as $item) {
					$normalized = $this->normalizeWidgetMaterialInput(is_array($item) ? $item : []);

					if ($normalized !== null) {
						$items[] = $normalized;
					}
				}
			}
		}

		if ($items === []) {
			$legacyMaterial = $this->input('material');

			if ($legacyMaterial !== '') {
				$items[] = [
					'type' => 'text',
					'name' => 'Widget material',
					'content' => $legacyMaterial
				];
			}
		}

		return $items;
	}

	protected function syncWidgetMaterialsForProject(ContentForgeProposal $proposal): array {
		$json = $this->input('materialsJson');

		if ($json === '') {
			return $this->getProjectMaterialPreviews($proposal->projectId);
		}

		$inputs = $this->getWidgetMaterialInputs();
		$seenIds = [];
		$generationMeta = $this->getGenerationMetaForProposal($proposal);
		$materials = [];

		foreach ($inputs as $index => $input) {
			$meta = array_merge($generationMeta, $this->safeMaterialMeta($input['meta'] ?? []), [
				'widgetMaterialIndex' => $index,
				'widgetMaterialType' => $input['type']
			]);

			$material = $this->saveWidgetMaterialInput($proposal->projectId, $input, $meta);
			$seenIds[] = $material->id;
			$materials[] = $material;
		}

		foreach ($this->materialService->getProjectMaterials($proposal->projectId) as $material) {
			if (in_array($material->id, $seenIds, true)) {
				continue;
			}

			if (isset($material->meta['widgetMaterialType'])) {
				$this->storage->delete('materials', $material->id);
			}
		}

		return array_map(fn(ContentForgeMaterial $material) => $this->materialPreview($material->toArray()), $materials);
	}

	protected function saveWidgetMaterialInput(string $projectId, array $input, array $meta): ContentForgeMaterial {
		$id = (string) ($input['id'] ?? '');

		if ($input['type'] === 'web_url' && trim((string) ($input['content'] ?? '')) === '') {
			$prepared = $this->materialService->prepareWebLinkMaterial($input['name'], $input['url']);
			$input['content'] = (string) ($prepared['content'] ?? '');
			$input['name'] = (string) ($prepared['name'] ?? $input['name']);
			$meta = array_merge($meta, is_array($prepared['meta'] ?? null) ? $this->safeMaterialMeta($prepared['meta']) : []);
		}

		if ($id !== '') {
			$existing = $this->materialService->getMaterial($id);

			if ($existing !== null && $existing->projectId === $projectId) {
				$material = new ContentForgeMaterial(
					$existing->id,
					$projectId,
					(string) ($input['name'] ?? $existing->name),
					'text/plain',
					(string) ($input['content'] ?? $existing->content),
					array_merge($existing->meta, $meta, [
						'materialType' => $input['type'],
						'sourceUrl' => (string) ($input['url'] ?? ($existing->meta['sourceUrl'] ?? ''))
					]),
					$existing->createdAt
				);
				$this->storage->upsert('materials', $material->id, $material->toArray());

				return $material;
			}
		}

		if ($input['type'] === 'web_url' && trim((string) ($input['content'] ?? '')) !== '') {
			return $this->materialService->createTextMaterial($projectId, $input['name'], (string) $input['content'], array_merge($meta, [
				'materialType' => 'web_url',
				'sourceUrl' => (string) ($input['url'] ?? '')
			]));
		}

		if ($input['type'] === 'web_url') {
			return $this->materialService->createWebLinkMaterial($projectId, $input['name'], $input['url'], $meta);
		}

		return $this->materialService->createTextMaterial($projectId, $input['name'], $input['content'], $meta);
	}

	protected function getGenerationMetaForProposal(ContentForgeProposal $proposal): array {
		$meta = [
			'generatorTemplate' => (string) ($proposal->content['generatorTemplate'] ?? 'micro_learning')
		];
		$materials = $this->materialService->getProjectMaterials($proposal->projectId);
		$latest = end($materials);

		if ($latest instanceof ContentForgeMaterial && isset($latest->meta['targetSectionCount'])) {
			$meta['targetSectionCount'] = (string) $latest->meta['targetSectionCount'];
		}

		return $meta;
	}

	protected function getProjectMaterialPreviews(string $projectId): array {
		return array_map(
			fn(ContentForgeMaterial $material) => $this->materialPreview($material->toArray()),
			$this->materialService->getProjectMaterials($projectId)
		);
	}

	protected function normalizeWidgetMaterialInput(array $item): ?array {
		$type = strtolower(trim((string) ($item['type'] ?? 'text')));
		$name = trim((string) ($item['name'] ?? ''));
		$id = preg_replace('/[^a-z0-9_-]+/i', '', trim((string) ($item['id'] ?? ''))) ?: '';

		if ($type === 'url' || $type === 'link' || $type === 'web') {
			$type = 'web_url';
		}

		if ($type === 'web_url') {
			$url = trim((string) ($item['url'] ?? ''));
			$content = trim((string) ($item['content'] ?? ''));

			if ($url === '' && $content === '') {
				$url = trim((string) ($item['content'] ?? ''));
			}

			if ($url === '') {
				return null;
			}

			return [
				'id' => $id,
				'type' => 'web_url',
				'name' => $name !== '' ? $name : 'Web link',
				'url' => $url,
				'content' => $content,
				'meta' => is_array($item['meta'] ?? null) ? $this->safeMaterialMeta($item['meta']) : []
			];
		}

		$content = trim((string) ($item['content'] ?? ''));

		if ($content === '') {
			return null;
		}

		return [
			'id' => $id,
			'type' => 'text',
			'name' => $name !== '' ? $name : 'Text material',
			'content' => $content
		];
	}

	protected function safeMaterialMeta(array $meta): array {
		$allowed = [
			'materialType',
			'sourceUrl',
			'sourceTitle',
			'sourceMimeType',
			'sourceStatus',
			'extractedLength',
			'fetchedAt',
			'generatorTemplate',
			'targetSectionCount',
			'widgetMaterialIndex',
			'widgetMaterialType'
		];
		$result = [];

		foreach ($allowed as $key) {
			if (array_key_exists($key, $meta) && is_scalar($meta[$key])) {
				$result[$key] = $meta[$key];
			}
		}

		return $result;
	}

	protected function materialPreview(array $material): array {
		$content = trim((string) ($material['content'] ?? ''));
		$meta = is_array($material['meta'] ?? null) ? $material['meta'] : [];

		return [
			'id' => (string) ($material['id'] ?? ''),
			'name' => (string) ($material['name'] ?? 'Material'),
			'type' => (string) ($meta['materialType'] ?? 'text'),
			'sourceUrl' => (string) ($meta['sourceUrl'] ?? ''),
			'sourceTitle' => (string) ($meta['sourceTitle'] ?? ''),
			'contentPreview' => substr($content, 0, 1200),
			'contentLength' => strlen($content),
			'meta' => $meta
		];
	}

	protected function createProject(): array {
		$title = $this->input('title', 'ContentForge Test Project');
		$description = $this->input('description', 'Created from ContentForge Workbench.');
		$project = $this->projectService->createProject($title, $description);
		$instance = $this->runnerService->startWorkflow($project, $this->definitionService->getDefaultDefinition());

		return [
			'ok' => true,
			'project' => $project->toArray(),
			'workflowInstance' => $instance->toArray()
		];
	}

	protected function addMaterial(): array {
		$projectId = $this->input('projectId');
		$name = $this->input('name', 'Input material');
		$content = $this->input('content');

		if ($projectId === '' || $content === '') {
			return $this->fail('projectId and content are required.', [
				'projectId' => $projectId,
				'hasContent' => $content !== ''
			]);
		}

		$material = $this->materialService->createTextMaterial($projectId, $name, $content);

		return [
			'ok' => true,
			'material' => $material->toArray()
		];
	}

	protected function runStep(): array {
		$instanceId = $this->input('workflowInstanceId');
		$instance = $this->runnerService->getWorkflowInstance($instanceId);

		if ($instance === null) {
			return $this->fail('Workflow instance not found.', $this->requestLookupContext());
		}

		$stepRun = $this->runnerService->runCurrentNode($instance);
		$updated = $this->runnerService->getWorkflowInstance($instance->id) ?? $instance;

		return [
			'ok' => true,
			'stepRun' => $stepRun->toArray(),
			'workflowInstance' => $updated->toArray(),
			'pendingProposals' => array_map(fn($proposal) => $proposal->toArray(), $this->proposalService->getPendingProposals($instance->projectId))
		];
	}

	protected function decide(): array {
		$proposal = $this->getRequestedProposal();
		$instance = $this->getRequestedWorkflowInstance($proposal);
		$type = $this->input('type', 'request_changes');
		$feedback = $this->input('feedback');

		if ($proposal === null) {
			return $this->fail('Proposal not found.', $this->requestLookupContext());
		}

		if ($instance === null) {
			return $this->fail('Workflow instance not found.', $this->requestLookupContext($proposal));
		}

		$decision = ContentForgeDecision::create($proposal->projectId, $instance->id, $proposal->stepRunId, $proposal->id, $type, $feedback);
		$updated = $this->runnerService->submitDecision($instance, $decision);

		return [
			'ok' => true,
			'decision' => $decision->toArray(),
			'workflowInstance' => $updated->toArray()
		];
	}

	protected function capabilities(): array {
		return [
			'ok' => true,
			'capabilities' => $this->capabilityService->getCapabilities(),
			'sectionTemplates' => $this->sectionTemplateRegistry->getClientDefinitions()
		];
	}


	protected function normalizeGeneratorTemplate(string $template): string {
		return $this->sectionTemplateRegistry->normalizeTemplateKey($template);
	}

	protected function normalizeTargetSectionCount(string $count): string {
		$count = strtolower(trim($count));

		if ($count === '' || $count === 'auto') {
			return 'auto';
		}

		if (ctype_digit($count)) {
			$value = max(1, min(12, (int) $count));

			return (string) $value;
		}

		return 'auto';
	}

	protected function getRequestedWorkflowInstance(?ContentForgeProposal $proposal = null): ?ContentForgeWorkflowInstance {
		$instanceId = $this->input('workflowInstanceId');
		$proposal ??= $this->getRequestedProposal();

		if ($instanceId !== '') {
			$instance = $this->runnerService->getWorkflowInstance($instanceId);

			if ($instance !== null) {
				return $instance;
			}

			$this->warnings[] = 'Requested workflow instance id was not found: ' . $instanceId;
		}

		if ($proposal !== null && $proposal->workflowInstanceId !== '') {
			$instance = $this->runnerService->getWorkflowInstance($proposal->workflowInstanceId);

			if ($instance !== null) {
				$this->warnings[] = 'Workflow instance was recovered from proposal id.';

				return $instance;
			}
		}

		if ($proposal !== null) {
			$instance = $this->findWorkflowInstanceByProject($proposal->projectId);

			if ($instance !== null) {
				$this->warnings[] = 'Workflow instance was recovered by project id.';

				return $instance;
			}

			if ($proposal->workflowInstanceId !== '') {
				return $this->recreateWorkflowInstanceFromProposal($proposal);
			}
		}

		return null;
	}

	protected function findWorkflowInstanceByProject(string $projectId): ?ContentForgeWorkflowInstance {
		foreach ($this->storage->loadCollection('workflow_instances') as $data) {
			if (!is_array($data)) {
				continue;
			}

			$instance = ContentForgeWorkflowInstance::fromArray($data);

			if ($instance->projectId === $projectId) {
				return $instance;
			}
		}

		return null;
	}

	protected function recreateWorkflowInstanceFromProposal(ContentForgeProposal $proposal): ContentForgeWorkflowInstance {
		$definition = $this->definitionService->getDefaultDefinition();
		$now = gmdate('c');
		$instance = new ContentForgeWorkflowInstance(
			$proposal->workflowInstanceId,
			$proposal->projectId,
			$definition->id,
			$definition->startNodeId,
			'awaiting_review',
			[
				'recovered' => true,
				'recoveredAt' => $now,
				'recoveredFromProposalId' => $proposal->id,
				'lastStepRunId' => $proposal->stepRunId
			],
			$now,
			$now
		);

		$this->storage->upsert('workflow_instances', $instance->id, $instance->toArray());
		$this->warnings[] = 'Workflow instance was recreated from proposal because the original state record was missing.';
		$this->logLegacy('warning', 'ContentForge recreated a missing workflow instance from proposal state.', [
			'requestId' => $this->requestId,
			'proposalId' => $proposal->id,
			'workflowInstanceId' => $instance->id,
			'projectId' => $proposal->projectId
		]);

		return $instance;
	}

	protected function getRequestedProposal(): ?ContentForgeProposal {
		$proposalId = $this->input('proposalId');

		if ($proposalId === '') {
			return null;
		}

		return $this->proposalService->getProposal($proposalId);
	}

	protected function buildWidgetFeedback(ContentForgeProposal $proposal): string {
		$feedback = $this->input('feedback');
		$content = $proposal->content;
		$sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
		$selectedIndexes = $this->parseSelectedSectionIndexes(
			$this->input('selectedSectionIndexes'),
			$this->input('selectedSectionIndex')
		);
		$providedTitles = $this->parseSelectedSectionTitles($this->input('selectedSectionTitles'));
		$selectedTitles = [];
		$selectedSections = [];

		foreach ($selectedIndexes as $position => $index) {
			if (isset($sections[$index]) && is_array($sections[$index])) {
				$selectedSections[] = $sections[$index];
				$selectedTitles[] = trim((string) ($providedTitles[$position] ?? ($sections[$index]['title'] ?? ('Section ' . ($index + 1)))));
			}
		}

		$selectedTitles = array_values(array_filter($selectedTitles, fn(string $title) => $title !== ''));

		$parts = [];

		if ($selectedIndexes !== []) {
			$parts[] = 'SelectedSectionIndexes: ' . implode(',', $selectedIndexes);
		}

		if ($selectedTitles !== []) {
			$parts[] = 'SelectedSectionTitles:' . "\n" . implode("\n", $selectedTitles);
		}

		if ($selectedSections !== []) {
			$parts[] = 'SelectedSectionsJson:' . "\n" . $this->encodeFeedbackJson($selectedSections);
		}

		$parts[] = 'CurrentProposalJson:' . "\n" . $this->encodeFeedbackJson($content);

		if ($feedback !== '') {
			$parts[] = 'UserFeedback:' . "\n" . $feedback;
		}

		return trim(implode("\n", $parts));
	}

	protected function parseSelectedSectionIndexes(string $indexes, string $fallbackIndex): array {
		$values = [];

		foreach (explode(',', $indexes) as $value) {
			$value = trim($value);

			if ($value !== '' && ctype_digit($value)) {
				$values[] = (int) $value;
			}
		}

		if ($values === [] && $fallbackIndex !== '' && ctype_digit($fallbackIndex)) {
			$values[] = (int) $fallbackIndex;
		}

		$values = array_values(array_unique(array_filter($values, fn(int $value) => $value >= 0)));
		sort($values);

		return $values;
	}

	protected function parseSelectedSectionTitles(string $titles): array {
		$result = [];
		$lines = preg_split('/\R+/', $titles) ?: [];

		foreach ($lines as $index => $title) {
			$title = trim((string) $title);

			if ($title !== '') {
				$result[$index] = $title;
			}
		}

		return $result;
	}

	protected function encodeFeedbackJson(array $data): string {
		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $json !== false ? $json : '{}';
	}

	protected function getEditedContentFromRequest(): ?array {
		$json = $this->input('editedContentJson');

		if ($json === '') {
			return null;
		}

		$decoded = json_decode($json, true);

		if (!is_array($decoded)) {
			return null;
		}

		$errors = $this->sectionTemplateRegistry->validateContent($decoded);

		if ($errors !== []) {
			$this->warnings[] = 'Edited content failed section template validation: ' . implode(' | ', $errors);
			return null;
		}

		return $decoded;
	}


	protected function withExportSelection(array $content, string $exportTemplate, string $exportTarget, array $exportTargetConfig = []): array {
		$content['exportTemplate'] = $exportTemplate;
		$content['export'] = is_array($content['export'] ?? null) ? $content['export'] : [];
		$content['export']['template'] = $exportTemplate;
		$content['export']['target'] = $exportTarget;
		$content['export']['targetConfig'] = $exportTargetConfig;
		$content['export']['selectedAt'] = gmdate('c');

		return $content;
	}


	protected function decodeExportTargetConfig(string $json): array {
		if ($json === '') {
			return [];
		}

		$decoded = json_decode($json, true);

		return is_array($decoded) ? $this->safeScalarArray($decoded) : [];
	}

	protected function safeScalarArray(array $value): array {
		$result = [];

		foreach ($value as $key => $item) {
			$key = preg_replace('/[^a-zA-Z0-9_.-]+/', '', (string) $key) ?? '';

			if ($key === '') {
				continue;
			}

			if (is_array($item)) {
				$result[$key] = $this->safeScalarArray($item);
				continue;
			}

			if (is_scalar($item) || $item === null) {
				$result[$key] = $item;
			}
		}

		return $result;
	}

	protected function normalizeExportTemplate(string $value): string {
		$value = strtolower(trim($value));
		$value = preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';

		return $value !== '' ? $value : 'html_package';
	}

	protected function normalizeExportTarget(string $value): string {
		$value = strtolower(trim($value));
		$value = preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';

		return $value !== '' ? $value : 'contentforgedownloadexporttarget';
	}

	protected function proposalList(string $projectId, string $type = ''): array {
		$proposals = $this->proposalService->getPendingProposals($projectId);

		if ($type !== '') {
			$proposals = array_values(array_filter($proposals, fn(ContentForgeProposal $proposal) => $proposal->type === $type));
		}

		return array_map(fn(ContentForgeProposal $proposal) => $proposal->toArray(), $proposals);
	}

	protected function requestLookupContext(?ContentForgeProposal $proposal = null): array {
		return [
			'workflowInstanceId' => $this->input('workflowInstanceId'),
			'proposalId' => $this->input('proposalId'),
			'projectId' => $this->input('projectId'),
			'proposal' => $proposal ? $proposal->toArray() : null,
			'context' => $this->safeRequestContext(),
			'requestKeys' => array_keys($this->getRequestData()),
			'postKeys' => array_keys($this->safeArrayRequestCall('allPost')),
			'getKeys' => array_keys($this->safeArrayRequestCall('allGet')),
			'jsonKeys' => array_keys($this->safeArrayRequestCall('getJsonBody'))
		];
	}

	protected function failException(\Throwable $e, string $action): array {
		$context = [
			'action' => $action,
			'exception' => $e::class,
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'previous' => $this->formatPreviousException($e),
			'trace' => $this->formatTrace($e),
			'request' => $this->requestLookupContext()
		];

		return $this->fail('ContentForge could not complete the request. The technical details were logged.', $context);
	}

	protected function formatPreviousException(\Throwable $e): array {
		$previous = $e->getPrevious();

		if ($previous === null) {
			return [];
		}

		return [
			'exception' => $previous::class,
			'message' => $previous->getMessage(),
			'file' => $previous->getFile(),
			'line' => $previous->getLine()
		];
	}

	protected function formatTrace(\Throwable $e): array {
		$trace = [];

		foreach (array_slice($e->getTrace(), 0, 8) as $item) {
			$trace[] = [
				'file' => (string) ($item['file'] ?? ''),
				'line' => (int) ($item['line'] ?? 0),
				'class' => (string) ($item['class'] ?? ''),
				'function' => (string) ($item['function'] ?? '')
			];
		}

		return $trace;
	}

	protected function fail(string $message, array $context = []): array {
		$storage = $this->storage->describe();
		$payload = [
			'ok' => false,
			'error' => $message,
			'diagnostics' => [
				'context' => $context,
				'storage' => $storage
			]
		];

		$this->logError($message, $context, $storage);

		return $payload;
	}

	protected function logLegacy(string $level, string $message, array $context = []): void {
		if ($this->logger === null) {
			return;
		}

		try {
			$this->logger->log('contentforge', '[' . $level . '] ' . $message . $this->formatLogContext($context));
		} catch (\Throwable) {}
	}

	protected function logError(string $message, array $context, array $storage): void {
		$logMessage = $this->buildLogMessage($message, $context, $storage);
		$logged = false;

		if ($this->logger !== null) {
			try {
				$logged = $this->logger->log('contentforge', '[error] ' . $logMessage);
			} catch (\Throwable $e) {
				$logMessage .= ' | loggerFailure=' . $e::class . ': ' . $e->getMessage();
			}
		}

		if (!$logged) {
			$this->writeFallbackLog('error', $logMessage);
		}
	}

	protected function writeFallbackLog(string $level, string $message): void {
		$dir = $this->getFallbackLogDir();

		if ($dir === '') {
			return;
		}

		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			return;
		}

		if (!is_writable($dir)) {
			return;
		}

		$line = gmdate('c') . "\t" . $level . "\t" . $message . "\n";
		@file_put_contents($dir . '/contentforge.log', $line, FILE_APPEND | LOCK_EX);
	}

	protected function getFallbackLogDir(): string {
		if (defined('DIR_PLUGIN')) {
			return rtrim((string) DIR_PLUGIN, '/\\') . '/ContentForge/var/log';
		}

		return dirname(__DIR__, 2) . '/var/log';
	}

	protected function buildLogMessage(string $message, array $context, array $storage): string {
		$parts = [
			$message,
			'requestId=' . $this->requestId
		];

		foreach (['action', 'exception', 'message', 'file', 'line'] as $key) {
			if (isset($context[$key]) && $context[$key] !== '') {
				$parts[] = $key . '=' . (is_scalar($context[$key]) ? (string) $context[$key] : json_encode($context[$key]));
			}
		}

		if (isset($context['request']) && is_array($context['request'])) {
			$parts[] = 'request=' . json_encode($context['request'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} elseif ($context !== []) {
			$parts[] = 'context=' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$parts[] = 'storage=' . json_encode($storage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return implode(' | ', $parts);
	}

	protected function decoratePayload(array $payload, string $action): array {
		$payload['requestId'] = $this->requestId;
		$payload['action'] = $payload['action'] ?? $action;

		if (!isset($payload['sectionTemplates'])) {
			$payload['sectionTemplates'] = $this->sectionTemplateRegistry->getClientDefinitions();
		}

		if ($this->warnings !== []) {
			$payload['warnings'] = $this->warnings;
		}

		if (empty($payload['ok']) && !isset($payload['diagnostics'])) {
			$payload['diagnostics'] = [
				'context' => $this->requestLookupContext(),
				'storage' => $this->storage->describe()
			];
		}

		return $payload;
	}

	protected function input(string $key, string $default = ''): string {
		$data = $this->getRequestData();
		$value = $data[$key] ?? $default;

		if (is_array($value) || is_object($value)) {
			return $default;
		}

		return trim((string) $value);
	}

	protected function getRequestData(): array {
		if ($this->requestData !== null) {
			return $this->requestData;
		}

		$data = $this->safeArrayRequestCall('allRequest');
		$json = $this->safeArrayRequestCall('getJsonBody');

		if ($json !== []) {
			$data = array_merge($data, $json);
		}

		$this->requestData = $data;

		return $this->requestData;
	}

	protected function safeArrayRequestCall(string $method): array {
		try {
			$value = $this->request->{$method}();
		} catch (\Throwable $e) {
			$this->warnings[] = 'IRequest::' . $method . '() failed: ' . $e->getMessage();
			return [];
		}

		return is_array($value) ? $value : [];
	}

	protected function safeRequestContext(): string {
		try {
			return $this->request->getContext();
		} catch (\Throwable $e) {
			$this->warnings[] = 'IRequest::getContext() failed: ' . $e->getMessage();
			return '';
		}
	}
}
