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
use ContentForge\Model\ContentForgeWorkflowInstance;

interface IContentForgeDecisionService {
	public function saveDecision(ContentForgeDecision $decision): ContentForgeDecision;
	public function processDecision(ContentForgeWorkflowInstance $instance, ContentForgeDecision $decision): void;
	/** @return ContentForgeDecision[] */
	public function getProjectDecisions(string $projectId): array;
}
