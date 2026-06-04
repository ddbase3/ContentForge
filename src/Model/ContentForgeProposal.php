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

class ContentForgeProposal {
	public function __construct(
		public readonly string $id,
		public readonly string $projectId,
		public readonly string $workflowInstanceId,
		public readonly string $stepRunId,
		public readonly string $type,
		public readonly string $title,
		public readonly array $content,
		public readonly string $status = 'pending',
		public readonly array $meta = [],
		public readonly string $createdAt = ''
	) {}

	public static function create(string $projectId, string $workflowInstanceId, string $stepRunId, string $type, string $title, array $content, array $meta = []): self {
		return new self(ContentForgeProject::newId('proposal'), $projectId, $workflowInstanceId, $stepRunId, $type, $title, $content, 'pending', $meta, gmdate('c'));
	}

	public static function fromArray(array $data): self {
		return new self(
			(string) ($data['id'] ?? ContentForgeProject::newId('proposal')),
			(string) ($data['projectId'] ?? ''),
			(string) ($data['workflowInstanceId'] ?? ''),
			(string) ($data['stepRunId'] ?? ''),
			(string) ($data['type'] ?? 'generic'),
			(string) ($data['title'] ?? 'Proposal'),
			is_array($data['content'] ?? null) ? $data['content'] : [],
			(string) ($data['status'] ?? 'pending'),
			is_array($data['meta'] ?? null) ? $data['meta'] : [],
			(string) ($data['createdAt'] ?? gmdate('c'))
		);
	}

	public function withStatus(string $status): self {
		return new self($this->id, $this->projectId, $this->workflowInstanceId, $this->stepRunId, $this->type, $this->title, $this->content, $status, $this->meta, $this->createdAt);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'workflowInstanceId' => $this->workflowInstanceId,
			'stepRunId' => $this->stepRunId,
			'type' => $this->type,
			'title' => $this->title,
			'content' => $this->content,
			'status' => $this->status,
			'meta' => $this->meta,
			'createdAt' => $this->createdAt
		];
	}
}
