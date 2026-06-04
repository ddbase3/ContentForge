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
use ContentForge\Api\IContentForgeProposalService;
use ContentForge\Model\ContentForgeProposal;

class ContentForgeProposalService implements IContentForgeProposalService {

	public function __construct(private readonly IContentForgeJsonStorageService $storage) {}

	public static function getName(): string {
		return 'contentforgeproposalservice';
	}

	public function saveProposal(ContentForgeProposal $proposal): ContentForgeProposal {
		$this->storage->upsert('proposals', $proposal->id, $proposal->toArray());

		return $proposal;
	}

	public function getProposal(string $id): ?ContentForgeProposal {
		$data = $this->storage->get('proposals', $id);

		return $data ? ContentForgeProposal::fromArray($data) : null;
	}

	public function updateStatus(string $id, string $status): void {
		$proposal = $this->getProposal($id);

		if ($proposal === null) {
			return;
		}

		$this->saveProposal($proposal->withStatus($status));
	}

	public function getProjectProposals(string $projectId): array {
		$result = [];

		foreach ($this->storage->loadCollection('proposals') as $data) {
			$proposal = ContentForgeProposal::fromArray((array) $data);

			if ($proposal->projectId === $projectId) {
				$result[] = $proposal;
			}
		}

		return $result;
	}

	public function getPendingProposals(string $projectId): array {
		return array_values(array_filter($this->getProjectProposals($projectId), fn(ContentForgeProposal $proposal) => $proposal->status === 'pending'));
	}
}
