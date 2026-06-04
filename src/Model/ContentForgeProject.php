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

class ContentForgeProject {
	public function __construct(
		public readonly string $id,
		public readonly string $title,
		public readonly string $description = '',
		public readonly array $data = [],
		public readonly string $createdAt = ''
	) {}

	public static function create(string $title, string $description = '', array $data = []): self {
		return new self(self::newId('project'), $title, $description, $data, gmdate('c'));
	}

	public static function fromArray(array $data): self {
		return new self(
			(string) ($data['id'] ?? self::newId('project')),
			(string) ($data['title'] ?? 'Untitled project'),
			(string) ($data['description'] ?? ''),
			is_array($data['data'] ?? null) ? $data['data'] : [],
			(string) ($data['createdAt'] ?? gmdate('c'))
		);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'description' => $this->description,
			'data' => $this->data,
			'createdAt' => $this->createdAt
		];
	}

	public static function newId(string $prefix): string {
		return $prefix . '_' . bin2hex(random_bytes(8));
	}
}
