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

class ContentForgeStepRun {
	public function __construct(
		public readonly string $id,
		public readonly string $projectId,
		public readonly string $workflowInstanceId,
		public readonly string $nodeId,
		public readonly string $handlerName,
		public readonly string $status,
		public readonly array $result = [],
		public readonly string $createdAt = ''
	) {}

	public static function create(ContentForgeWorkflowInstance $instance, ContentForgeWorkflowNode $node, ContentForgeStepResult $result, ?string $id = null): self {
		return new self(
			$id ?? ContentForgeProject::newId('steprun'),
			$instance->projectId,
			$instance->id,
			$node->id,
			$node->handlerName,
			$result->status,
			$result->toArray(),
			gmdate('c')
		);
	}

	public static function fromArray(array $data): self {
		return new self(
			(string) ($data['id'] ?? ContentForgeProject::newId('steprun')),
			(string) ($data['projectId'] ?? ''),
			(string) ($data['workflowInstanceId'] ?? ''),
			(string) ($data['nodeId'] ?? ''),
			(string) ($data['handlerName'] ?? ''),
			(string) ($data['status'] ?? 'success'),
			is_array($data['result'] ?? null) ? $data['result'] : [],
			(string) ($data['createdAt'] ?? gmdate('c'))
		);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'workflowInstanceId' => $this->workflowInstanceId,
			'nodeId' => $this->nodeId,
			'handlerName' => $this->handlerName,
			'status' => $this->status,
			'result' => $this->result,
			'createdAt' => $this->createdAt
		];
	}
}
