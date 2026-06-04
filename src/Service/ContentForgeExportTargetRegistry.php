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
use ContentForge\Api\IContentForgeExportTarget;
use ContentForge\Api\IContentForgeExportTargetRegistry;

class ContentForgeExportTargetRegistry implements IContentForgeExportTargetRegistry {

	/** @param IContentForgeExportTarget[] $localTargets */
	public function __construct(
		private readonly IClassMap $classMap,
		private readonly array $localTargets = []
	) {}

	public static function getName(): string {
		return 'contentforgeexporttargetregistry';
	}

	public function getTarget(string $name): ?IContentForgeExportTarget {
		foreach ($this->getTargets() as $target) {
			if ($target::getName() === $name) {
				return $target;
			}
		}

		return null;
	}

	public function getTargets(): array {
		$targets = $this->getClassMapTargets();
		$targets = array_merge($this->localTargets, $targets);

		return $this->uniqueTargets($targets);
	}

	protected function getClassMapTargets(): array {
		try {
			$targets =& $this->classMap->getInstancesByInterface(IContentForgeExportTarget::class);

			return is_array($targets) ? $targets : [];
		} catch (\Throwable) {
			return [];
		}
	}

	protected function uniqueTargets(array $targets): array {
		$result = [];

		foreach ($targets as $target) {
			if (!$target instanceof IContentForgeExportTarget) {
				continue;
			}

			$name = $target::getName();

			if (isset($result[$name])) {
				continue;
			}

			$result[$name] = $target;
		}

		return array_values($result);
	}
}
