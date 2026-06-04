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

class ContentForgeArtifactRevision {
	public function __construct(
		public readonly string $id,
		public readonly string $artifactId,
		public readonly string $projectId,
		public readonly string $type,
		public readonly array $content,
		public readonly string $status = 'accepted',
		public readonly string $source = 'proposal',
		public readonly string $createdFromProposalId = '',
		public readonly string $createdFromDecisionId = '',
		public readonly string $createdAt = ''
	) {}

	public static function fromArray(array $data): self {
		return new self(
			(string) ($data['id'] ?? ContentForgeProject::newId('revision')),
			(string) ($data['artifactId'] ?? ''),
			(string) ($data['projectId'] ?? ''),
			(string) ($data['type'] ?? 'generic'),
			is_array($data['content'] ?? null) ? $data['content'] : [],
			(string) ($data['status'] ?? 'accepted'),
			(string) ($data['source'] ?? 'proposal'),
			(string) ($data['createdFromProposalId'] ?? ''),
			(string) ($data['createdFromDecisionId'] ?? ''),
			(string) ($data['createdAt'] ?? gmdate('c'))
		);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'artifactId' => $this->artifactId,
			'projectId' => $this->projectId,
			'type' => $this->type,
			'content' => $this->content,
			'status' => $this->status,
			'source' => $this->source,
			'createdFromProposalId' => $this->createdFromProposalId,
			'createdFromDecisionId' => $this->createdFromDecisionId,
			'createdAt' => $this->createdAt
		];
	}
}
