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

class ContentForgeWorkflowInstance {
	public function __construct(
		public readonly string $id,
		public readonly string $projectId,
		public readonly string $workflowDefinitionId,
		public readonly string $currentNodeId,
		public readonly string $status = 'running',
		public readonly array $data = [],
		public readonly string $createdAt = '',
		public readonly string $updatedAt = ''
	) {}

	public static function create(ContentForgeProject $project, ContentForgeWorkflowDefinition $definition): self {
		$now = gmdate('c');
		return new self(ContentForgeProject::newId('workflow'), $project->id, $definition->id, $definition->startNodeId, 'running', [], $now, $now);
	}

	public static function fromArray(array $data): self {
		return new self(
			(string) ($data['id'] ?? ContentForgeProject::newId('workflow')),
			(string) ($data['projectId'] ?? ''),
			(string) ($data['workflowDefinitionId'] ?? 'html_micro_module'),
			(string) ($data['currentNodeId'] ?? ''),
			(string) ($data['status'] ?? 'running'),
			is_array($data['data'] ?? null) ? $data['data'] : [],
			(string) ($data['createdAt'] ?? gmdate('c')),
			(string) ($data['updatedAt'] ?? gmdate('c'))
		);
	}

	public function moveTo(string $nodeId, string $status = 'running', array $data = []): self {
		return new self($this->id, $this->projectId, $this->workflowDefinitionId, $nodeId, $status, array_merge($this->data, $data), $this->createdAt, gmdate('c'));
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'workflowDefinitionId' => $this->workflowDefinitionId,
			'currentNodeId' => $this->currentNodeId,
			'status' => $this->status,
			'data' => $this->data,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt
		];
	}
}
