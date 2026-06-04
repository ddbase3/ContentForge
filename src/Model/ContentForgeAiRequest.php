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

class ContentForgeAiRequest {
	public function __construct(
		public readonly string $serviceName,
		public readonly string $systemPrompt,
		public readonly string $userPrompt,
		public readonly array $schema = [],
		public readonly array $context = [],
		public readonly array $options = []
	) {}
}
