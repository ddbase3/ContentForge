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
use ContentForge\Api\IContentForgeProposalService;
use ContentForge\Model\ContentForgeDecision;
use ContentForge\Model\ContentForgeWorkflowInstance;

class ContentForgeDecisionService implements IContentForgeDecisionService {

	public function __construct(
		private readonly IContentForgeJsonStorageService $storage,
		private readonly IContentForgeProposalService $proposalService,
		private readonly IContentForgeArtifactService $artifactService
	) {}

	public static function getName(): string {
		return 'contentforgedecisionservice';
	}

	public function saveDecision(ContentForgeDecision $decision): ContentForgeDecision {
		$this->storage->upsert('decisions', $decision->id, $decision->toArray());

		return $decision;
	}

	public function processDecision(ContentForgeWorkflowInstance $instance, ContentForgeDecision $decision): void {
		$this->saveDecision($decision);

		$proposal = $this->proposalService->getProposal($decision->proposalId);

		if ($proposal === null) {
			return;
		}

		if (in_array($decision->type, ['accept', 'accept_with_changes'], true)) {
			$this->artifactService->commitProposal($proposal, $decision->id, $decision->editedContent);
			$this->proposalService->updateStatus($proposal->id, 'accepted');
			return;
		}

		if ($decision->type === 'reject') {
			$this->proposalService->updateStatus($proposal->id, 'rejected');
			return;
		}

		if ($decision->type === 'skip') {
			$this->proposalService->updateStatus($proposal->id, 'skipped');
			return;
		}

		$this->proposalService->updateStatus($proposal->id, 'changes_requested');
	}

	public function getProjectDecisions(string $projectId): array {
		$result = [];

		foreach ($this->storage->loadCollection('decisions') as $data) {
			$decision = ContentForgeDecision::fromArray((array) $data);

			if ($decision->projectId === $projectId) {
				$result[] = $decision;
			}
		}

		return $result;
	}
}
