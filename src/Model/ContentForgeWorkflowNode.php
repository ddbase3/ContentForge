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

class ContentForgeWorkflowNode {
	public function __construct(
		public readonly string $id,
		public readonly string $type,
		public readonly string $handlerName,
		public readonly string $outputType = 'generic',
		public readonly string $reviewPolicy = 'none',
		public readonly string $next = '',
		public readonly array $config = [],
		public readonly array $transitions = []
	) {}

	public static function fromArray(array $data): self {
		return new self(
			(string) ($data['id'] ?? ''),
			(string) ($data['type'] ?? 'step'),
			(string) ($data['handlerName'] ?? ''),
			(string) ($data['outputType'] ?? 'generic'),
			(string) ($data['reviewPolicy'] ?? 'none'),
			(string) ($data['next'] ?? ''),
			is_array($data['config'] ?? null) ? $data['config'] : [],
			is_array($data['transitions'] ?? null) ? $data['transitions'] : []
		);
	}

	public function resolveNext(string $decisionType = ''): string {
		if ($decisionType !== '' && isset($this->transitions[$decisionType])) {
			return (string) $this->transitions[$decisionType];
		}

		return $this->next;
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'type' => $this->type,
			'handlerName' => $this->handlerName,
			'outputType' => $this->outputType,
			'reviewPolicy' => $this->reviewPolicy,
			'next' => $this->next,
			'config' => $this->config,
			'transitions' => $this->transitions
		];
	}
}
