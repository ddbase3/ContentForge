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

class ContentForgeMicroLearningSectionTemplate implements IContentForgeSectionTemplate {

	public static function getName(): string {
		return 'contentforgemicrolearningsectiontemplate';
	}

	public function getKey(): string {
		return 'micro_learning';
	}

	public function getLabel(): string {
		return 'Micro-learning card';
	}

	public function getDescription(): string {
		return 'A compact learning card with headline, body text and optional learner prompt.';
	}

	public function getPreviewDefinition(): array {
		return [
			'label' => 'Show section content',
			'preview' => [
				'type' => 'text',
				'path' => 'body',
				'length' => 180
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
			'title' => $language === 'de' ? 'Neue Lernkarte' : 'New learning card',
			'body' => $language === 'de' ? 'Neuer Inhalt.' : 'New content.',
			'interaction' => [
				'type' => 'reading',
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
					'title' => 'Headline',
					'x-contentforge-control' => 'text'
				],
				'body' => [
					'type' => 'string',
					'minLength' => 1,
					'title' => 'Text',
					'x-contentforge-control' => 'textarea'
				],
				'interaction' => [
					'type' => 'object',
					'title' => 'Learner prompt',
					'properties' => [
						'type' => [
							'type' => 'string',
							'default' => 'reading',
							'x-contentforge-hidden' => true
						],
						'prompt' => [
							'type' => 'string',
							'title' => 'Learner prompt',
							'description' => 'Optional prompt shown below this card.',
							'x-contentforge-control' => 'textarea'
						]
					]
				]
			]
		];
	}
}
