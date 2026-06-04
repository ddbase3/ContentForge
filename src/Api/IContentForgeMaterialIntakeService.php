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

use ContentForge\Model\ContentForgeMaterial;

interface IContentForgeMaterialIntakeService {
	public function createTextMaterial(string $projectId, string $name, string $content, array $meta = []): ContentForgeMaterial;
	public function createWebLinkMaterial(string $projectId, string $name, string $url, array $meta = []): ContentForgeMaterial;
	public function prepareWebLinkMaterial(string $name, string $url): array;
	public function saveMaterial(ContentForgeMaterial $material): ContentForgeMaterial;
	public function deleteMaterial(string $id): bool;
	public function getMaterial(string $id): ?ContentForgeMaterial;
	/** @return ContentForgeMaterial[] */
	public function getProjectMaterials(string $projectId): array;
}
