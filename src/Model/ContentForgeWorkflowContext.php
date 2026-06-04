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

namespace ContentForge\Model;

class ContentForgeWorkflowContext {
	/**
	 * @param ContentForgeMaterial[] $materials
	 * @param ContentForgeArtifact[] $artifacts
	 * @param ContentForgeArtifactRevision[] $revisions
	 * @param ContentForgeDecision[] $decisions
	 */
	public function __construct(
		public readonly ContentForgeProject $project,
		public readonly ContentForgeWorkflowInstance $instance,
		public readonly array $materials = [],
		public readonly array $artifacts = [],
		public readonly array $revisions = [],
		public readonly array $decisions = [],
		public readonly array $data = []
	) {}

	public function getLatestFeedback(): string {
		$decisions = $this->decisions;
		$latest = end($decisions);

		return $latest instanceof ContentForgeDecision ? $latest->feedback : '';
	}

	public function getMaterialText(): string {
		$parts = [];

		foreach ($this->materials as $material) {
			if ($material instanceof ContentForgeMaterial && trim($material->content) !== '') {
				$parts[] = trim($material->content);
			}
		}

		return trim(implode("

", $parts));
	}
}
