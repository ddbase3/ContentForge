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

use ContentForge\Api\IContentForgeArtifactService;
use ContentForge\Api\IContentForgeJsonStorageService;
use ContentForge\Model\ContentForgeArtifact;
use ContentForge\Model\ContentForgeArtifactRevision;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeProposal;

class ContentForgeArtifactService implements IContentForgeArtifactService {

	public function __construct(private readonly IContentForgeJsonStorageService $storage) {}

	public static function getName(): string {
		return 'contentforgeartifactservice';
	}

	public function commitProposal(ContentForgeProposal $proposal, string $decisionId, ?array $editedContent = null): ContentForgeArtifactRevision {
		$artifactId = ContentForgeProject::newId('artifact');
		$revisionId = ContentForgeProject::newId('revision');
		$content = $editedContent ?? $proposal->content;

		$artifact = new ContentForgeArtifact($artifactId, $proposal->projectId, $proposal->type, $revisionId, 'current', gmdate('c'));
		$revision = new ContentForgeArtifactRevision(
			$revisionId,
			$artifactId,
			$proposal->projectId,
			$proposal->type,
			$content,
			'accepted',
			'proposal',
			$proposal->id,
			$decisionId,
			gmdate('c')
		);

		$this->storage->upsert('artifacts', $artifact->id, $artifact->toArray());
		$this->storage->upsert('artifact_revisions', $revision->id, $revision->toArray());

		return $revision;
	}

	public function getArtifact(string $id): ?ContentForgeArtifact {
		$data = $this->storage->get('artifacts', $id);

		return $data ? ContentForgeArtifact::fromArray($data) : null;
	}

	public function getRevision(string $id): ?ContentForgeArtifactRevision {
		$data = $this->storage->get('artifact_revisions', $id);

		return $data ? ContentForgeArtifactRevision::fromArray($data) : null;
	}

	public function getProjectArtifacts(string $projectId): array {
		$result = [];

		foreach ($this->storage->loadCollection('artifacts') as $data) {
			$artifact = ContentForgeArtifact::fromArray((array) $data);

			if ($artifact->projectId === $projectId) {
				$result[] = $artifact;
			}
		}

		return $result;
	}

	public function getProjectRevisions(string $projectId): array {
		$result = [];

		foreach ($this->storage->loadCollection('artifact_revisions') as $data) {
			$revision = ContentForgeArtifactRevision::fromArray((array) $data);

			if ($revision->projectId === $projectId) {
				$result[] = $revision;
			}
		}

		return $result;
	}
}
