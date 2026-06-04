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

class ContentForgeMaterial {
	public function __construct(
		public readonly string $id,
		public readonly string $projectId,
		public readonly string $name,
		public readonly string $mimeType,
		public readonly string $content,
		public readonly array $meta = [],
		public readonly string $createdAt = ''
	) {}

	public static function create(string $projectId, string $name, string $content, string $mimeType = 'text/plain', array $meta = []): self {
		return new self(ContentForgeProject::newId('material'), $projectId, $name, $mimeType, $content, $meta, gmdate('c'));
	}

	public static function fromArray(array $data): self {
		return new self(
			(string) ($data['id'] ?? ContentForgeProject::newId('material')),
			(string) ($data['projectId'] ?? ''),
			(string) ($data['name'] ?? 'Material'),
			(string) ($data['mimeType'] ?? 'text/plain'),
			(string) ($data['content'] ?? ''),
			is_array($data['meta'] ?? null) ? $data['meta'] : [],
			(string) ($data['createdAt'] ?? gmdate('c'))
		);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'name' => $this->name,
			'mimeType' => $this->mimeType,
			'content' => $this->content,
			'meta' => $this->meta,
			'createdAt' => $this->createdAt
		];
	}
}
