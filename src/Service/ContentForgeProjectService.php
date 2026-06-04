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

namespace ContentForge\Service;

use ContentForge\Api\IContentForgeJsonStorageService;
use ContentForge\Api\IContentForgeProjectService;
use ContentForge\Model\ContentForgeProject;

class ContentForgeProjectService implements IContentForgeProjectService {

	public function __construct(private readonly IContentForgeJsonStorageService $storage) {}

	public static function getName(): string {
		return 'contentforgeprojectservice';
	}

	public function createProject(string $title, string $description = '', array $data = []): ContentForgeProject {
		$project = ContentForgeProject::create($title, $description, $data);
		$this->storage->upsert('projects', $project->id, $project->toArray());

		return $project;
	}

	public function getProject(string $id): ?ContentForgeProject {
		$data = $this->storage->get('projects', $id);

		return $data ? ContentForgeProject::fromArray($data) : null;
	}

	public function getProjects(): array {
		return array_map(fn(array $data) => ContentForgeProject::fromArray($data), array_values($this->storage->loadCollection('projects')));
	}
}
