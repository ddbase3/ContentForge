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

use ContentForge\Api\IContentForgeArtifactService;
use ContentForge\Api\IContentForgeDecisionService;
use ContentForge\Api\IContentForgeJsonStorageService;
use ContentForge\Api\IContentForgeMaterialIntakeService;
use ContentForge\Api\IContentForgeProposalService;
use ContentForge\Api\IContentForgeProjectService;
use ContentForge\Api\IContentForgeWorkflowDefinitionService;
use ContentForge\Api\IContentForgeWorkflowNodeHandlerRegistry;
use ContentForge\Api\IContentForgeWorkflowRunnerService;
use ContentForge\Model\ContentForgeDecision;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeStepResult;
use ContentForge\Model\ContentForgeStepRun;
use ContentForge\Model\ContentForgeWorkflowContext;
use ContentForge\Model\ContentForgeWorkflowDefinition;
use ContentForge\Model\ContentForgeWorkflowInstance;

class ContentForgeWorkflowRunnerService implements IContentForgeWorkflowRunnerService {

	public function __construct(
		private readonly IContentForgeJsonStorageService $storage,
		private readonly IContentForgeProjectService $projectService,
		private readonly IContentForgeWorkflowDefinitionService $definitionService,
		private readonly IContentForgeWorkflowNodeHandlerRegistry $handlerRegistry,
		private readonly IContentForgeMaterialIntakeService $materialService,
		private readonly IContentForgeProposalService $proposalService,
		private readonly IContentForgeDecisionService $decisionService,
		private readonly IContentForgeArtifactService $artifactService
	) {}

	public static function getName(): string {
		return 'contentforgeworkflowrunnerservice';
	}

	public function startWorkflow(ContentForgeProject $project, ContentForgeWorkflowDefinition $definition): ContentForgeWorkflowInstance {
		$instance = ContentForgeWorkflowInstance::create($project, $definition);
		$this->storage->upsert('workflow_instances', $instance->id, $instance->toArray());

		return $instance;
	}

	public function getWorkflowInstance(string $id): ?ContentForgeWorkflowInstance {
		$data = $this->storage->get('workflow_instances', $id);

		return $data ? ContentForgeWorkflowInstance::fromArray($data) : null;
	}

	public function runCurrentNode(ContentForgeWorkflowInstance $instance): ContentForgeStepRun {
		$definition = $this->definitionService->getDefinition($instance->workflowDefinitionId) ?? $this->definitionService->getDefaultDefinition();
		$node = $definition->getNode($instance->currentNodeId);

		if ($node === null) {
			return $this->saveStepRun($instance, '', '', ContentForgeStepResult::error('Current workflow node not found.'));
		}

		$handler = $this->handlerRegistry->getHandler($node->handlerName);

		if ($handler === null) {
			return $this->saveStepRun($instance, $node->id, $node->handlerName, ContentForgeStepResult::error('Workflow node handler not found: ' . $node->handlerName));
		}

		$project = $this->projectService->getProject($instance->projectId) ?? new ContentForgeProject($instance->projectId, 'Unknown project');
		$stepRunId = ContentForgeProject::newId('steprun');
		$context = new ContentForgeWorkflowContext(
			$project,
			$instance,
			$this->materialService->getProjectMaterials($project->id),
			$this->artifactService->getProjectArtifacts($project->id),
			$this->artifactService->getProjectRevisions($project->id),
			$this->decisionService->getProjectDecisions($project->id),
			['stepRunId' => $stepRunId]
		);

		$result = $handler->handle($context, $node);
		$stepRun = ContentForgeStepRun::create($instance, $node, $result, $stepRunId);

		foreach ($result->proposals as $proposal) {
			$this->proposalService->saveProposal($proposal);
		}

		$this->storage->upsert('step_runs', $stepRun->id, $stepRun->toArray());

		if ($result->status === 'success' && $node->reviewPolicy === 'none') {
			$nextNodeId = $node->resolveNext();
			$status = $nextNodeId === '' ? 'finished' : 'running';
			$this->saveWorkflowInstance($instance->moveTo($nextNodeId, $status));
		} elseif ($result->status === 'success') {
			$this->saveWorkflowInstance($instance->moveTo($node->id, 'awaiting_review', ['lastStepRunId' => $stepRun->id]));
		}

		return $stepRun;
	}

	public function submitDecision(ContentForgeWorkflowInstance $instance, ContentForgeDecision $decision): ContentForgeWorkflowInstance {
		$definition = $this->definitionService->getDefinition($instance->workflowDefinitionId) ?? $this->definitionService->getDefaultDefinition();
		$node = $definition->getNode($instance->currentNodeId);

		$this->decisionService->processDecision($instance, $decision);

		if ($node === null) {
			return $instance->moveTo('', 'error');
		}

		$nextNodeId = $node->resolveNext($decision->type);
		$status = $nextNodeId === '' ? 'finished' : 'running';
		$updated = $instance->moveTo($nextNodeId, $status, ['lastDecisionId' => $decision->id]);
		$this->saveWorkflowInstance($updated);

		return $updated;
	}

	protected function saveWorkflowInstance(ContentForgeWorkflowInstance $instance): void {
		$this->storage->upsert('workflow_instances', $instance->id, $instance->toArray());
	}

	protected function saveStepRun(ContentForgeWorkflowInstance $instance, string $nodeId, string $handlerName, ContentForgeStepResult $result): ContentForgeStepRun {
		$stepRun = new ContentForgeStepRun(
			ContentForgeProject::newId('steprun'),
			$instance->projectId,
			$instance->id,
			$nodeId,
			$handlerName,
			$result->status,
			$result->toArray(),
			gmdate('c')
		);

		$this->storage->upsert('step_runs', $stepRun->id, $stepRun->toArray());

		return $stepRun;
	}
}
