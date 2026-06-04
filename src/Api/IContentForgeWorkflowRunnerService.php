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

namespace ContentForge\Api;

use ContentForge\Model\ContentForgeDecision;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeStepRun;
use ContentForge\Model\ContentForgeWorkflowDefinition;
use ContentForge\Model\ContentForgeWorkflowInstance;

interface IContentForgeWorkflowRunnerService {
	public function startWorkflow(ContentForgeProject $project, ContentForgeWorkflowDefinition $definition): ContentForgeWorkflowInstance;
	public function getWorkflowInstance(string $id): ?ContentForgeWorkflowInstance;
	public function runCurrentNode(ContentForgeWorkflowInstance $instance): ContentForgeStepRun;
	public function submitDecision(ContentForgeWorkflowInstance $instance, ContentForgeDecision $decision): ContentForgeWorkflowInstance;
}
