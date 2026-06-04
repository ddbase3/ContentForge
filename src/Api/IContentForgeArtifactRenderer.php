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

use Base3\Api\IBase;
use ContentForge\Model\ContentForgeArtifactRevision;
use ContentForge\Model\ContentForgeProposal;

interface IContentForgeArtifactRenderer extends IBase {
	public function supports(string $type): bool;
	public function renderProposal(ContentForgeProposal $proposal): string;
	public function renderRevision(ContentForgeArtifactRevision $revision): string;
}
