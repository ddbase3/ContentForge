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
use ContentForge\Api\IContentForgeWorkflowNodeHandler;
use ContentForge\Api\IContentForgeWorkflowNodeHandlerRegistry;

class ContentForgeWorkflowNodeHandlerRegistry implements IContentForgeWorkflowNodeHandlerRegistry {

	/** @param IContentForgeWorkflowNodeHandler[] $localHandlers */
	public function __construct(
		private readonly IClassMap $classMap,
		private readonly array $localHandlers = []
	) {}

	public static function getName(): string {
		return 'contentforgeworkflownodehandlerregistry';
	}

	public function getHandler(string $name): ?IContentForgeWorkflowNodeHandler {
		foreach ($this->getHandlers() as $handler) {
			if ($handler::getName() === $name) {
				return $handler;
			}
		}

		return null;
	}

	public function getHandlers(): array {
		$handlers = $this->getClassMapHandlers();
		$handlers = array_merge($this->localHandlers, $handlers);

		return $this->uniqueHandlers($handlers);
	}

	protected function getClassMapHandlers(): array {
		try {
			$handlers =& $this->classMap->getInstancesByInterface(IContentForgeWorkflowNodeHandler::class);

			return is_array($handlers) ? $handlers : [];
		} catch (\Throwable) {
			return [];
		}
	}

	protected function uniqueHandlers(array $handlers): array {
		$result = [];

		foreach ($handlers as $handler) {
			if (!$handler instanceof IContentForgeWorkflowNodeHandler) {
				continue;
			}

			$name = $handler::getName();

			if (isset($result[$name])) {
				continue;
			}

			$result[$name] = $handler;
		}

		return array_values($result);
	}
}
