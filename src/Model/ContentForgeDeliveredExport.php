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

class ContentForgeDeliveredExport {
	public function __construct(
		public readonly string $id,
		public readonly string $type,
		public readonly string $path,
		public readonly string $url = '',
		public readonly array $meta = []
	) {}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'type' => $this->type,
			'path' => $this->path,
			'url' => $this->url,
			'meta' => $this->meta
		];
	}
}
