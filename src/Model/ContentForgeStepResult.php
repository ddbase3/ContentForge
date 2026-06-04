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

class ContentForgeStepResult {
	/** @param ContentForgeProposal[] $proposals */
	public function __construct(
		public readonly string $status = 'success',
		public readonly array $proposals = [],
		public readonly array $artifacts = [],
		public readonly array $reports = [],
		public readonly array $events = [],
		public readonly string $nextAction = '',
		public readonly array $errors = [],
		public readonly array $warnings = []
	) {}

	public static function success(array $proposals = [], array $reports = [], string $nextAction = ''): self {
		return new self('success', $proposals, [], $reports, [], $nextAction);
	}

	public static function error(string $message): self {
		return new self('error', [], [], [], [], '', [$message]);
	}

	public function toArray(): array {
		return [
			'status' => $this->status,
			'proposals' => array_map(fn($proposal) => method_exists($proposal, 'toArray') ? $proposal->toArray() : $proposal, $this->proposals),
			'artifacts' => array_map(fn($artifact) => method_exists($artifact, 'toArray') ? $artifact->toArray() : $artifact, $this->artifacts),
			'reports' => $this->reports,
			'events' => $this->events,
			'nextAction' => $this->nextAction,
			'errors' => $this->errors,
			'warnings' => $this->warnings
		];
	}
}
