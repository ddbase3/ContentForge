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
use ContentForge\Api\IContentForgeArtifactRenderer;
use ContentForge\Api\IContentForgeArtifactRendererRegistry;

class ContentForgeArtifactRendererRegistry implements IContentForgeArtifactRendererRegistry {

	/** @param IContentForgeArtifactRenderer[] $localRenderers */
	public function __construct(
		private readonly IClassMap $classMap,
		private readonly array $localRenderers = []
	) {}

	public static function getName(): string {
		return 'contentforgeartifactrendererregistry';
	}

	public function getRenderer(string $type): ?IContentForgeArtifactRenderer {
		foreach ($this->getRenderers() as $renderer) {
			if ($renderer->supports($type)) {
				return $renderer;
			}
		}

		return null;
	}

	public function getRenderers(): array {
		$renderers = $this->getClassMapRenderers();
		$renderers = array_merge($this->localRenderers, $renderers);

		return $this->uniqueRenderers($renderers);
	}

	protected function getClassMapRenderers(): array {
		try {
			$renderers =& $this->classMap->getInstancesByInterface(IContentForgeArtifactRenderer::class);

			return is_array($renderers) ? $renderers : [];
		} catch (\Throwable) {
			return [];
		}
	}

	protected function uniqueRenderers(array $renderers): array {
		$result = [];

		foreach ($renderers as $renderer) {
			if (!$renderer instanceof IContentForgeArtifactRenderer) {
				continue;
			}

			$name = $renderer::getName();

			if (isset($result[$name])) {
				continue;
			}

			$result[$name] = $renderer;
		}

		return array_values($result);
	}
}
