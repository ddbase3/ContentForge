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

namespace ContentForge\Template;

use ContentForge\Api\IContentForgeSectionTemplate;

class ContentForgeChecklistSectionTemplate implements IContentForgeSectionTemplate {

	public static function getName(): string {
		return 'contentforgechecklistsectiontemplate';
	}

	public function getKey(): string {
		return 'checklist';
	}

	public function getLabel(): string {
		return 'Checklist section';
	}

	public function getDescription(): string {
		return 'A section with explanatory text and checklist items.';
	}

	public function getPreviewDefinition(): array {
		return [
			'label' => 'Show checklist details',
			'preview' => [
				'type' => 'list',
				'path' => 'items',
				'fallbackPath' => 'body',
				'limit' => 3,
				'length' => 180
			],
			'details' => [
				['type' => 'list', 'path' => 'items'],
				['type' => 'paragraph', 'path' => 'body'],
				['type' => 'extra', 'path' => 'footer'],
				['type' => 'extra', 'path' => 'note'],
				['type' => 'image_hint', 'path' => 'image']
			]
		];
	}

	public function getDefaultContent(string $language = 'en'): array {
		return [
			'template' => $this->getKey(),
			'title' => $language === 'de' ? 'Neue Checkliste' : 'New checklist',
			'body' => $language === 'de' ? 'Pruefen Sie die folgenden Punkte.' : 'Check the following points.',
			'items' => []
		];
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'required' => ['template', 'title', 'body'],
			'properties' => [
				'template' => [
					'const' => $this->getKey(),
					'title' => 'Section template',
					'x-contentforge-control' => 'template_select'
				],
				'title' => [
					'type' => 'string',
					'minLength' => 1,
					'title' => 'Headline',
					'x-contentforge-control' => 'text'
				],
				'body' => [
					'type' => 'string',
					'minLength' => 1,
					'title' => 'Intro text',
					'x-contentforge-control' => 'textarea'
				],
				'items' => [
					'type' => 'array',
					'title' => 'Checklist items',
					'description' => 'One item per line.',
					'items' => ['type' => 'string'],
					'x-contentforge-control' => 'string_list'
				]
			]
		];
	}
}
