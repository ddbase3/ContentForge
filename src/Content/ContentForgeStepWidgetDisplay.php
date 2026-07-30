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

namespace ContentForge\Content;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\ISchemaProvider;
use Base3\LinkTarget\Api\ILinkTargetService;
use ContentForge\Api\IContentForgeSectionTemplateRegistry;

class ContentForgeStepWidgetDisplay implements IDisplay, ISchemaProvider {

	private array $data = [];
	private array $translations = [];

	public function __construct(
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IContentForgeSectionTemplateRegistry $sectionTemplateRegistry
	) {}

	public static function getName(): string {
		return 'contentforgestepwidgetdisplay';
	}

	public function getHelp(): string {
		$this->loadTranslations();

		return $this->t('help', 'Interactive ContentForge workflow for creating, reviewing and exporting structured content.');
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$this->view->setPath(DIR_PLUGIN . 'ContentForge');
		$this->loadTranslations();
		$this->view->setTemplate('Content/ContentForgeStepWidgetDisplay.php');

		$config = $this->getClientConfig();
		$config['service_url'] = $this->buildServiceUrl($config);
		$config['section_templates'] = $this->sectionTemplateRegistry->getClientDefinitions();

		foreach ($config as $tag => $content) {
			$this->view->assign($tag, $content);
		}

		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve($src));
		$this->view->assign('translations', $this->translations);

		return $this->view->loadTemplate();
	}

	public function setData($data) {
		$this->data = (array) $data;
	}

	public function getSchema(): array {
		$this->loadTranslations();

		return [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [
				'service' => [
					'type' => 'string',
					'description' => $this->t('schema_service', 'Technical ContentForge widget service name'),
					'default' => 'contentforgeworkbenchservice'
				],
				'default_project_title' => [
					'type' => 'string',
					'description' => $this->t('schema_default_project_title', 'Default title used by the first widget screen'),
					'default' => $this->t('default_project_title', 'ContentForge Test Project')
				],
				'default_material' => [
					'type' => 'string',
					'description' => $this->t('schema_default_material', 'Optional starter material for demos or embedded examples'),
					'default' => ''
				],
				'generator_type' => [
					'type' => 'string',
					'description' => $this->t('schema_generator_type', 'Initial generator type handled by the widget'),
					'default' => 'html_micro_module'
				],
				'export_template' => [
					'type' => 'string',
					'description' => $this->t('schema_export_template', 'Optional fixed export template. Empty means the user may choose in the review step.'),
					'default' => ''
				],
				'export_template_locked' => [
					'type' => 'boolean',
					'description' => $this->t('schema_export_template_locked', 'Hide the export template selector and force export_template.'),
					'default' => false
				],
				'export_target' => [
					'type' => 'string',
					'description' => $this->t('schema_export_target', 'Export delivery target name.'),
					'default' => 'contentforgedownloadexporttarget'
				],
				'export_target_config' => [
					'type' => 'object',
					'description' => $this->t('schema_export_target_config', 'Host integration metadata passed to the export target.'),
					'default' => []
				],
				'show_status' => [
					'type' => 'boolean',
					'description' => $this->t('schema_show_status', 'Legacy option. The side panel is now used for source materials.'),
					'default' => false
				],
				'show_debug' => [
					'type' => 'boolean',
					'description' => $this->t('schema_show_debug', 'Show collapsible technical debug data in the side panel'),
					'default' => false
				]
			],
			'required' => ['service']
		];
	}

	protected function getClientConfig(): array {
		$defaults = [
			'service' => 'contentforgeworkbenchservice',
			'default_project_title' => $this->t('default_project_title', 'ContentForge Test Project'),
			'default_material' => $this->t('default_material', 'Short sample text: ContentForge should create a small, reviewable proposal from material. The user reviews only the current step and requests focused changes when needed.'),
			'generator_type' => 'html_micro_module',
			'export_template' => '',
			'export_template_locked' => false,
			'export_target' => 'contentforgedownloadexporttarget',
			'export_target_config' => [],
			'show_status' => false,
			'show_debug' => false
		];

		$config = array_merge($defaults, $this->data);

		return [
			'service' => $this->normalizeTechnicalKey((string) $config['service']),
			'default_project_title' => trim((string) $config['default_project_title']),
			'default_material' => trim((string) $config['default_material']),
			'generator_type' => $this->normalizeTechnicalKey((string) $config['generator_type']),
			'export_template' => $this->normalizeExportTemplate((string) $config['export_template']),
			'export_template_locked' => $this->toBool($config['export_template_locked']),
			'export_target' => $this->normalizeTechnicalKey((string) $config['export_target']),
			'export_target_config' => is_array($config['export_target_config']) ? $config['export_target_config'] : [],
			'show_status' => $this->toBool($config['show_status']),
			'show_debug' => $this->toBool($config['show_debug'])
		];
	}

	protected function buildServiceUrl(array $config): string {
		$service = trim((string) ($config['service'] ?? ''));

		if ($service === '') {
			return '';
		}

		return $this->linkTargetService->getLink([
			'name' => $service,
			'out' => 'json'
		]);
	}



	private function loadTranslations(): void {
		$this->view->setPath(DIR_PLUGIN . 'ContentForge');
		$this->view->loadBricks('Display');

		$translations = $this->view->getBricks('contentforge_step_widget_display');
		$this->translations = is_array($translations) ? $translations : [];
	}

	private function t(string $key, string $fallback, mixed ...$values): string {
		$text = trim((string)($this->translations[$key] ?? ''));
		if ($text === '') {
			$text = $fallback;
		}

		return $values === [] ? $text : vsprintf($text, $values);
	}

	protected function normalizeExportTemplate(string $value): string {
		$value = strtolower(trim($value));

		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	protected function normalizeTechnicalKey(string $value): string {
		$value = strtolower(trim($value));

		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	protected function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		$value = strtolower(trim((string) $value));

		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}
}
