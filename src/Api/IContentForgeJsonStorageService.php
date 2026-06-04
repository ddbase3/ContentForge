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

interface IContentForgeJsonStorageService {
	public function loadCollection(string $collection): array;
	public function saveCollection(string $collection, array $items): void;
	public function get(string $collection, string $id): ?array;
	public function upsert(string $collection, string $id, array $item): void;
	public function delete(string $collection, string $id): void;
	public function describe(): array;
}
