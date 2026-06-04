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

use ContentForge\Api\IContentForgeWorkflowDefinitionService;
use ContentForge\Model\ContentForgeWorkflowDefinition;

class ContentForgeWorkflowDefinitionService implements IContentForgeWorkflowDefinitionService {

	public static function getName(): string {
		return 'contentforgeworkflowdefinitionservice';
	}

	public function getDefaultDefinition(): ContentForgeWorkflowDefinition {
		return $this->getDefinition('html_micro_module') ?? ContentForgeWorkflowDefinition::fromArray($this->getBuiltInDefinition());
	}

	public function getDefinition(string $id): ?ContentForgeWorkflowDefinition {
		foreach ($this->getDefinitions() as $definition) {
			if ($definition->id === $id) {
				return $definition;
			}
		}

		return null;
	}

	public function getDefinitions(): array {
		$definitions = [];

		foreach ($this->loadRecipeFiles() as $recipe) {
			$definition = ContentForgeWorkflowDefinition::fromArray($recipe);
			$definitions[$definition->id] = $definition;
		}

		if (!isset($definitions['html_micro_module'])) {
			$definition = ContentForgeWorkflowDefinition::fromArray($this->getBuiltInDefinition());
			$definitions[$definition->id] = $definition;
		}

		return array_values($definitions);
	}

	protected function loadRecipeFiles(): array {
		$path = $this->getRecipePath();

		if (!is_dir($path)) {
			return [];
		}

		$recipes = [];

		foreach (glob($path . '/*.json') ?: [] as $file) {
			$data = json_decode((string) file_get_contents($file), true);

			if (is_array($data)) {
				$recipes[] = $data;
			}
		}

		return $recipes;
	}

	protected function getRecipePath(): string {
		if (defined('DIR_PLUGIN')) {
			return rtrim((string) DIR_PLUGIN, '/\\') . '/ContentForge/config/recipes';
		}

		return dirname(__DIR__, 2) . '/config/recipes';
	}

	protected function getBuiltInDefinition(): array {
		return [
			'id' => 'html_micro_module',
			'title' => 'HTML Micro Module',
			'description' => 'Built-in fallback workflow for the first ContentForge generator.',
			'startNodeId' => 'generate_micro_module',
			'nodes' => [
				[
					'id' => 'generate_micro_module',
					'type' => 'generate',
					'handlerName' => 'contentforgehtmlmicromodulestephandler',
					'outputType' => 'html_micro_module',
					'reviewPolicy' => 'required_until_accepted',
					'next' => 'export_html_package',
					'transitions' => [
						'accept' => 'export_html_package',
						'accept_with_changes' => 'export_html_package',
						'request_changes' => 'generate_micro_module',
						'reject' => 'generate_micro_module',
						'skip' => 'export_html_package'
					],
					'config' => [
						'title' => 'HTML Micro Module',
						'sectionCount' => 3
					]
				],
				[
					'id' => 'export_html_package',
					'type' => 'export',
					'handlerName' => 'contentforgeexportstephandler',
					'outputType' => 'html_package',
					'reviewPolicy' => 'optional',
					'next' => '',
					'config' => [
						'exporter' => 'contentforgehtmlpackageexporter',
						'target' => 'contentforgedownloadexporttarget'
					]
				]
			]
		];
	}
}
