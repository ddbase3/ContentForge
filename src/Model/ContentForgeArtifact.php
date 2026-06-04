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

class ContentForgeArtifact {
	public function __construct(
		public readonly string $id,
		public readonly string $projectId,
		public readonly string $type,
		public readonly string $currentRevisionId,
		public readonly string $status = 'current',
		public readonly string $createdAt = ''
	) {}

	public static function fromArray(array $data): self {
		return new self(
			(string) ($data['id'] ?? ContentForgeProject::newId('artifact')),
			(string) ($data['projectId'] ?? ''),
			(string) ($data['type'] ?? 'generic'),
			(string) ($data['currentRevisionId'] ?? ''),
			(string) ($data['status'] ?? 'current'),
			(string) ($data['createdAt'] ?? gmdate('c'))
		);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'type' => $this->type,
			'currentRevisionId' => $this->currentRevisionId,
			'status' => $this->status,
			'createdAt' => $this->createdAt
		];
	}
}
