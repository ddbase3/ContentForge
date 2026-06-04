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

use ContentForge\Model\ContentForgeProject;

interface IContentForgeProjectService {
	public function createProject(string $title, string $description = '', array $data = []): ContentForgeProject;
	public function getProject(string $id): ?ContentForgeProject;
	/** @return ContentForgeProject[] */
	public function getProjects(): array;
}
