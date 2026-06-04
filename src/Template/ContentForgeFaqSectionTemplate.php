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

class ContentForgeFaqSectionTemplate implements IContentForgeSectionTemplate {

	public static function getName(): string {
		return 'contentforgefaqsectiontemplate';
	}

	public function getKey(): string {
		return 'faq';
	}

	public function getLabel(): string {
		return 'FAQ section';
	}

	public function getDescription(): string {
		return 'A question and answer section with optional follow-up prompt.';
	}

	public function getPreviewDefinition(): array {
		return [
			'label' => 'Show answer',
			'preview' => [
				'type' => 'text',
				'path' => 'body',
				'length' => 220,
				'className' => 'cf-choice-answer'
			],
			'details' => [
				['type' => 'paragraph', 'path' => 'body'],
				['type' => 'interaction', 'path' => 'interaction.prompt'],
				['type' => 'extra', 'path' => 'footer'],
				['type' => 'extra', 'path' => 'note'],
				['type' => 'image_hint', 'path' => 'image']
			]
		];
	}

	public function getDefaultContent(string $language = 'en'): array {
		return [
			'template' => $this->getKey(),
			'title' => $language === 'de' ? 'Neue Frage' : 'New question',
			'body' => $language === 'de' ? 'Neue Antwort.' : 'New answer.',
			'interaction' => [
				'type' => 'question',
				'prompt' => ''
			]
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
					'title' => 'Question',
					'x-contentforge-control' => 'text'
				],
				'body' => [
					'type' => 'string',
					'minLength' => 1,
					'title' => 'Answer',
					'x-contentforge-control' => 'textarea'
				],
				'interaction' => [
					'type' => 'object',
					'title' => 'Follow-up prompt',
					'properties' => [
						'type' => [
							'type' => 'string',
							'default' => 'question',
							'x-contentforge-hidden' => true
						],
						'prompt' => [
							'type' => 'string',
							'title' => 'Follow-up prompt',
							'description' => 'Optional prompt shown below this answer.',
							'x-contentforge-control' => 'textarea'
						]
					]
				]
			]
		];
	}
}
