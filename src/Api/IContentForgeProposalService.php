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

use ContentForge\Model\ContentForgeProposal;

interface IContentForgeProposalService {
	public function saveProposal(ContentForgeProposal $proposal): ContentForgeProposal;
	public function getProposal(string $id): ?ContentForgeProposal;
	public function updateStatus(string $id, string $status): void;
	/** @return ContentForgeProposal[] */
	public function getProjectProposals(string $projectId): array;
	/** @return ContentForgeProposal[] */
	public function getPendingProposals(string $projectId): array;
}
