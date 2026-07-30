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
use Base3\Translation\Api\ITranslation;
use ContentForge\Api\IContentForgeSectionTemplate;
use ContentForge\Api\IContentForgeSectionTemplateRegistry;

class ContentForgeSectionTemplateRegistry implements IContentForgeSectionTemplateRegistry {

	/** @param IContentForgeSectionTemplate[] $localTemplates */
	public function __construct(
		private readonly IClassMap $classMap,
		private readonly ITranslation $translation,
		private readonly array $localTemplates = []
	) {}

	public static function getName(): string {
		return 'contentforgesectiontemplateregistry';
	}

	public function getTemplate(string $key): ?IContentForgeSectionTemplate {
		$key = $this->normalizeTemplateKey($key);

		foreach ($this->getTemplates() as $template) {
			if ($template->getKey() === $key) {
				return $template;
			}
		}

		return null;
	}

	public function getTemplates(): array {
		$templates = $this->getClassMapTemplates();
		$templates = array_merge($this->localTemplates, $templates);

		return $this->uniqueTemplates($templates);
	}

	public function getClientDefinitions(): array {
		$result = [];

		foreach ($this->getTemplates() as $template) {
			$result[$template->getKey()] = $this->localizeClientDefinition($template->getKey(), [
				'key' => $template->getKey(),
				'name' => $template::getName(),
				'label' => $template->getLabel(),
				'description' => $template->getDescription(),
				'schema' => $template->getSchema(),
				'preview' => $template->getPreviewDefinition(),
				'defaults' => [
					'en' => $template->getDefaultContent('en'),
					'de' => $template->getDefaultContent('de')
				]
			]);
		}

		return $result;
	}


	private function localizeClientDefinition(string $key, array $definition): array {
		return match ($key) {
			'micro_learning' => $this->localizeMicroLearningDefinition($definition),
			'short_overview' => $this->localizeInformationDefinition($definition),
			'checklist' => $this->localizeChecklistDefinition($definition),
			'faq' => $this->localizeFaqDefinition($definition),
			default => $definition,
		};
	}

	private function localizeMicroLearningDefinition(array $definition): array {
		$definition['label'] = $this->t('section_micro_learning', 'Micro-learning card');
		$definition['description'] = $this->t('section_micro_learning_description', 'A compact learning card with headline, body text and optional learner prompt.');
		$definition['preview']['label'] = $this->t('show_section_content', 'Show section content');
		$definition['schema']['properties']['template']['title'] = $this->t('section_template', 'Section template');
		$definition['schema']['properties']['title']['title'] = $this->t('headline', 'Headline');
		$definition['schema']['properties']['body']['title'] = $this->t('text', 'Text');
		$definition['schema']['properties']['interaction']['title'] = $this->t('learner_prompt', 'Learner prompt');
		$definition['schema']['properties']['interaction']['properties']['prompt']['title'] = $this->t('learner_prompt', 'Learner prompt');
		$definition['schema']['properties']['interaction']['properties']['prompt']['description'] = $this->t('learner_prompt_description', 'Optional prompt shown below this card.');

		return $definition;
	}

	private function localizeInformationDefinition(array $definition): array {
		$definition['label'] = $this->t('section_information', 'Information section');
		$definition['description'] = $this->t('section_information_description', 'A plain information section with headline, explanatory text and optional footer.');
		$definition['preview']['label'] = $this->t('show_overview_text', 'Show overview text');
		$definition['schema']['properties']['template']['title'] = $this->t('section_template', 'Section template');
		$definition['schema']['properties']['title']['title'] = $this->t('headline', 'Headline');
		$definition['schema']['properties']['body']['title'] = $this->t('text', 'Text');
		$definition['schema']['properties']['footer']['title'] = $this->t('footer', 'Footer');
		$definition['schema']['properties']['footer']['description'] = $this->t('footer_description', 'Optional small note below the text.');

		return $definition;
	}

	private function localizeChecklistDefinition(array $definition): array {
		$definition['label'] = $this->t('section_checklist', 'Checklist section');
		$definition['description'] = $this->t('section_checklist_description', 'A section with explanatory text and checklist items.');
		$definition['preview']['label'] = $this->t('show_checklist_details', 'Show checklist details');
		$definition['schema']['properties']['template']['title'] = $this->t('section_template', 'Section template');
		$definition['schema']['properties']['title']['title'] = $this->t('headline', 'Headline');
		$definition['schema']['properties']['body']['title'] = $this->t('intro_text', 'Intro text');
		$definition['schema']['properties']['items']['title'] = $this->t('checklist_items', 'Checklist items');
		$definition['schema']['properties']['items']['description'] = $this->t('one_item_per_line', 'One item per line.');

		return $definition;
	}

	private function localizeFaqDefinition(array $definition): array {
		$definition['label'] = $this->t('section_faq', 'FAQ section');
		$definition['description'] = $this->t('section_faq_description', 'A question and answer section with optional follow-up prompt.');
		$definition['preview']['label'] = $this->t('show_answer', 'Show answer');
		$definition['schema']['properties']['template']['title'] = $this->t('section_template', 'Section template');
		$definition['schema']['properties']['title']['title'] = $this->t('question', 'Question');
		$definition['schema']['properties']['body']['title'] = $this->t('answer', 'Answer');
		$definition['schema']['properties']['interaction']['title'] = $this->t('follow_up_prompt', 'Follow-up prompt');
		$definition['schema']['properties']['interaction']['properties']['prompt']['title'] = $this->t('follow_up_prompt', 'Follow-up prompt');
		$definition['schema']['properties']['interaction']['properties']['prompt']['description'] = $this->t('follow_up_prompt_description', 'Optional prompt shown below this answer.');

		return $definition;
	}

	private function t(string $key, string $fallback): string {
		return $this->translation->translate('Display', 'contentforge_step_widget_display', $key, $fallback);
	}

	public function getDefaultContent(string $key, string $language = 'en'): array {
		$template = $this->getTemplate($key) ?? $this->getTemplate('micro_learning');

		return $template ? $template->getDefaultContent($language) : [
			'template' => 'micro_learning',
			'title' => $language === 'de' ? 'Neue Lernkarte' : 'New learning card',
			'body' => $language === 'de' ? 'Neuer Inhalt.' : 'New content.'
		];
	}

	public function normalizeTemplateKey(string $key): string {
		$key = strtolower(trim($key));
		$key = preg_replace('/[^a-z0-9_\-]+/', '_', $key) ?? '';

		foreach ($this->getTemplates() as $template) {
			if ($template->getKey() === $key) {
				return $key;
			}
		}

		return 'micro_learning';
	}

	public function validateSection(array $section): array {
		$template = $this->getTemplate((string) ($section['template'] ?? 'micro_learning'));

		if ($template === null) {
			return ['Unknown section template.'];
		}

		return $this->validateAgainstSchema($section, $template->getSchema(), 'section');
	}

	public function validateContent(array $content): array {
		$errors = [];
		$sections = $content['sections'] ?? [];

		if (!is_array($sections)) {
			return ['sections must be an array.'];
		}

		if ($sections === []) {
			return ['At least one section is required.'];
		}

		if (array_is_list($sections) === false) {
			$errors[] = 'sections must be a list.';
		}

		if (isset($content['title']) && (!is_string($content['title']) || trim($content['title']) === '')) {
			$errors[] = 'title must be a non-empty string.';
		}

		if (isset($content['summary']) && !is_string($content['summary'])) {
			$errors[] = 'summary must be a string.';
		}

		foreach ($sections as $index => $section) {
			if (!is_array($section) || array_is_list($section)) {
				$errors[] = 'sections[' . $index . '] must be an object.';
				continue;
			}

			foreach ($this->validateSection($section) as $error) {
				$errors[] = 'sections[' . $index . '].' . $error;
			}
		}

		return $errors;
	}

	protected function getClassMapTemplates(): array {
		try {
			$templates =& $this->classMap->getInstancesByInterface(IContentForgeSectionTemplate::class);

			return is_array($templates) ? $templates : [];
		} catch (\Throwable) {
			return [];
		}
	}

	protected function uniqueTemplates(array $templates): array {
		$result = [];

		foreach ($templates as $template) {
			if (!$template instanceof IContentForgeSectionTemplate) {
				continue;
			}

			$key = $template->getKey();

			if (isset($result[$key])) {
				continue;
			}

			$result[$key] = $template;
		}

		return array_values($result);
	}

	protected function validateAgainstSchema(mixed $value, array $schema, string $path): array {
		$errors = [];
		$type = $schema['type'] ?? null;

		if (!$this->matchesType($value, $type)) {
			return [$path . ' must be ' . $this->describeType($type) . '.'];
		}

		if (isset($schema['const']) && $value !== $schema['const']) {
			$errors[] = $path . ' must be ' . $schema['const'] . '.';
		}

		if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
			$errors[] = $path . ' must be one of: ' . implode(', ', array_map('strval', $schema['enum'])) . '.';
		}

		if (is_string($value)) {
			$errors = array_merge($errors, $this->validateString($value, $schema, $path));
		}

		if (is_array($value) && !array_is_list($value)) {
			$errors = array_merge($errors, $this->validateObject($value, $schema, $path));
		}

		if (is_array($value) && array_is_list($value)) {
			$errors = array_merge($errors, $this->validateArray($value, $schema, $path));
		}

		return $errors;
	}

	protected function validateObject(array $value, array $schema, string $path): array {
		$errors = [];
		$required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
		$properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
		$additionalAllowed = ($schema['additionalProperties'] ?? true) !== false;

		foreach ($required as $field) {
			if (!array_key_exists($field, $value)) {
				$errors[] = $path . '.' . $field . ' is required.';
				continue;
			}

			if (is_string($value[$field]) && trim($value[$field]) === '') {
				$errors[] = $path . '.' . $field . ' must not be empty.';
			}
		}

		if (!$additionalAllowed) {
			foreach (array_keys($value) as $field) {
				if (!array_key_exists($field, $properties)) {
					$errors[] = $path . '.' . $field . ' is not allowed.';
				}
			}
		}

		foreach ($properties as $field => $fieldSchema) {
			if (!array_key_exists($field, $value)) {
				continue;
			}

			$errors = array_merge($errors, $this->validateAgainstSchema($value[$field], $fieldSchema, $path . '.' . $field));
		}

		return $errors;
	}

	protected function validateArray(array $value, array $schema, string $path): array {
		$errors = [];

		if (isset($schema['minItems']) && count($value) < (int) $schema['minItems']) {
			$errors[] = $path . ' must contain at least ' . (int) $schema['minItems'] . ' item(s).';
		}

		if (isset($schema['maxItems']) && count($value) > (int) $schema['maxItems']) {
			$errors[] = $path . ' must contain at most ' . (int) $schema['maxItems'] . ' item(s).';
		}

		$itemSchema = is_array($schema['items'] ?? null) ? $schema['items'] : [];

		foreach ($value as $index => $item) {
			$errors = array_merge($errors, $this->validateAgainstSchema($item, $itemSchema, $path . '[' . $index . ']'));
		}

		return $errors;
	}

	protected function validateString(string $value, array $schema, string $path): array {
		$errors = [];
		$length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

		if (isset($schema['minLength']) && $length < (int) $schema['minLength']) {
			$errors[] = $path . ' must contain at least ' . (int) $schema['minLength'] . ' character(s).';
		}

		if (isset($schema['maxLength']) && $length > (int) $schema['maxLength']) {
			$errors[] = $path . ' must contain at most ' . (int) $schema['maxLength'] . ' character(s).';
		}

		if (isset($schema['pattern']) && is_string($schema['pattern']) && @preg_match('/' . str_replace('/', '\\/', $schema['pattern']) . '/u', $value) !== 1) {
			$errors[] = $path . ' has an invalid format.';
		}

		return $errors;
	}

	protected function matchesType(mixed $value, mixed $type): bool {
		if ($type === null) {
			return true;
		}

		if (is_array($type)) {
			foreach ($type as $singleType) {
				if ($this->matchesType($value, $singleType)) {
					return true;
				}
			}

			return false;
		}

		return match ($type) {
			'string' => is_string($value),
			'array' => is_array($value) && array_is_list($value),
			'object' => is_array($value) && !array_is_list($value),
			'boolean' => is_bool($value),
			'integer' => is_int($value),
			'number' => is_int($value) || is_float($value),
			'null' => $value === null,
			default => true
		};
	}

	protected function describeType(mixed $type): string {
		if (is_array($type)) {
			return implode('|', array_map('strval', $type));
		}

		return is_string($type) ? $type : 'valid';
	}
}
