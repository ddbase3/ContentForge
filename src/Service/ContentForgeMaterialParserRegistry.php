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

use Base3\Api\IClassMap;
use ContentForge\Api\IContentForgeMaterialParser;
use ContentForge\Api\IContentForgeMaterialParserRegistry;
use ContentForge\Model\ContentForgeMaterial;

class ContentForgeMaterialParserRegistry implements IContentForgeMaterialParserRegistry {

	/** @param IContentForgeMaterialParser[] $localParsers */
	public function __construct(
		private readonly IClassMap $classMap,
		private readonly array $localParsers = []
	) {}

	public static function getName(): string {
		return 'contentforgematerialparserregistry';
	}

	public function getParser(ContentForgeMaterial $material): ?IContentForgeMaterialParser {
		foreach ($this->getParsers() as $parser) {
			if ($parser->supports($material)) {
				return $parser;
			}
		}

		return null;
	}

	public function getParsers(): array {
		$parsers = $this->getClassMapParsers();
		$parsers = array_merge($this->localParsers, $parsers);

		return $this->uniqueParsers($parsers);
	}

	protected function getClassMapParsers(): array {
		try {
			$parsers =& $this->classMap->getInstancesByInterface(IContentForgeMaterialParser::class);

			return is_array($parsers) ? $parsers : [];
		} catch (\Throwable) {
			return [];
		}
	}

	protected function uniqueParsers(array $parsers): array {
		$result = [];

		foreach ($parsers as $parser) {
			if (!$parser instanceof IContentForgeMaterialParser) {
				continue;
			}

			$name = $parser::getName();

			if (isset($result[$name])) {
				continue;
			}

			$result[$name] = $parser;
		}

		return array_values($result);
	}
}
