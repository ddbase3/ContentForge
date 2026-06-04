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

class ContentForgeWorkflowDefinition {
	/** @param ContentForgeWorkflowNode[] $nodes */
	public function __construct(
		public readonly string $id,
		public readonly string $title,
		public readonly string $description,
		public readonly string $startNodeId,
		public readonly array $nodes
	) {}

	public static function fromArray(array $data): self {
		$nodes = [];

		foreach (($data['nodes'] ?? []) as $nodeData) {
			if (!is_array($nodeData)) {
				continue;
			}

			$node = ContentForgeWorkflowNode::fromArray($nodeData);
			if ($node->id !== '') {
				$nodes[$node->id] = $node;
			}
		}

		$startNodeId = (string) ($data['startNodeId'] ?? array_key_first($nodes) ?? '');

		return new self(
			(string) ($data['id'] ?? 'default'),
			(string) ($data['title'] ?? 'Default Workflow'),
			(string) ($data['description'] ?? ''),
			$startNodeId,
			$nodes
		);
	}

	public function getNode(string $id): ?ContentForgeWorkflowNode {
		return $this->nodes[$id] ?? null;
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'description' => $this->description,
			'startNodeId' => $this->startNodeId,
			'nodes' => array_map(fn(ContentForgeWorkflowNode $node) => $node->toArray(), array_values($this->nodes))
		];
	}
}
