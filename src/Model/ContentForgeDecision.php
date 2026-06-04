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

class ContentForgeDecision {
	public function __construct(
		public readonly string $id,
		public readonly string $projectId,
		public readonly string $workflowInstanceId,
		public readonly string $stepRunId,
		public readonly string $proposalId,
		public readonly string $type,
		public readonly string $feedback = '',
		public readonly ?array $editedContent = null,
		public readonly array $attachedMaterialIds = [],
		public readonly string $createdBy = '',
		public readonly string $createdAt = ''
	) {}

	public static function create(string $projectId, string $workflowInstanceId, string $stepRunId, string $proposalId, string $type, string $feedback = '', ?array $editedContent = null, array $attachedMaterialIds = [], string $createdBy = ''): self {
		return new self(ContentForgeProject::newId('decision'), $projectId, $workflowInstanceId, $stepRunId, $proposalId, $type, $feedback, $editedContent, $attachedMaterialIds, $createdBy, gmdate('c'));
	}

	public static function fromArray(array $data): self {
		return new self(
			(string) ($data['id'] ?? ContentForgeProject::newId('decision')),
			(string) ($data['projectId'] ?? ''),
			(string) ($data['workflowInstanceId'] ?? ''),
			(string) ($data['stepRunId'] ?? ''),
			(string) ($data['proposalId'] ?? ''),
			(string) ($data['type'] ?? 'request_changes'),
			(string) ($data['feedback'] ?? ''),
			is_array($data['editedContent'] ?? null) ? $data['editedContent'] : null,
			is_array($data['attachedMaterialIds'] ?? null) ? $data['attachedMaterialIds'] : [],
			(string) ($data['createdBy'] ?? ''),
			(string) ($data['createdAt'] ?? gmdate('c'))
		);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'workflowInstanceId' => $this->workflowInstanceId,
			'stepRunId' => $this->stepRunId,
			'proposalId' => $this->proposalId,
			'type' => $this->type,
			'feedback' => $this->feedback,
			'editedContent' => $this->editedContent,
			'attachedMaterialIds' => $this->attachedMaterialIds,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt
		];
	}
}
