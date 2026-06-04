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

class ContentForgeAiResult {
	public function __construct(
		public readonly bool $ok,
		public readonly array $content = [],
		public readonly string $rawText = '',
		public readonly string $provider = '',
		public readonly string $model = '',
		public readonly bool $usedFallback = false,
		public readonly array $errors = [],
		public readonly array $warnings = [],
		public readonly array $metadata = []
	) {}

	public static function success(array $content, string $rawText = '', string $provider = '', string $model = '', array $metadata = []): self {
		return new self(true, $content, $rawText, $provider, $model, false, [], [], $metadata);
	}

	public static function error(string $message, string $provider = '', string $model = '', array $metadata = []): self {
		return new self(false, [], '', $provider, $model, false, [$message], [], $metadata);
	}

	public function withFallback(array $content, string $message = ''): self {
		$warnings = $this->warnings;

		if ($message !== '') {
			$warnings[] = $message;
		}

		return new self(true, $content, $this->rawText, $this->provider, $this->model, true, $this->errors, $warnings, $this->metadata);
	}

	public function toArray(): array {
		return [
			'ok' => $this->ok,
			'content' => $this->content,
			'rawText' => $this->rawText,
			'provider' => $this->provider,
			'model' => $this->model,
			'usedFallback' => $this->usedFallback,
			'errors' => $this->errors,
			'warnings' => $this->warnings,
			'metadata' => $this->metadata
		];
	}
}
