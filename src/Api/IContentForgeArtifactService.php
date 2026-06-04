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

use ContentForge\Model\ContentForgeArtifact;
use ContentForge\Model\ContentForgeArtifactRevision;
use ContentForge\Model\ContentForgeProposal;

interface IContentForgeArtifactService {
	public function commitProposal(ContentForgeProposal $proposal, string $decisionId, ?array $editedContent = null): ContentForgeArtifactRevision;
	public function getArtifact(string $id): ?ContentForgeArtifact;
	public function getRevision(string $id): ?ContentForgeArtifactRevision;
	/** @return ContentForgeArtifact[] */
	public function getProjectArtifacts(string $projectId): array;
	/** @return ContentForgeArtifactRevision[] */
	public function getProjectRevisions(string $projectId): array;
}
