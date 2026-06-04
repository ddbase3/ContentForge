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

class ContentForgeInformationSectionTemplate implements IContentForgeSectionTemplate {

	public static function getName(): string {
		return 'contentforgeinformationsectiontemplate';
	}

	public function getKey(): string {
		return 'short_overview';
	}

	public function getLabel(): string {
		return 'Information section';
	}

	public function getDescription(): string {
		return 'A plain information section with headline, explanatory text and optional footer.';
	}

	public function getPreviewDefinition(): array {
		return [
			'label' => 'Show overview text',
			'preview' => [
				'type' => 'text',
				'path' => 'body',
				'length' => 260
			],
			'details' => [
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
			'title' => $language === 'de' ? 'Neuer Informationsabschnitt' : 'New information section',
			'body' => $language === 'de' ? 'Neuer Inhalt.' : 'New content.',
			'footer' => ''
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
				'footer' => [
					'type' => 'string',
					'title' => 'Footer',
					'description' => 'Optional small note below the text.',
					'x-contentforge-control' => 'textarea'
				]
			]
		];
	}
}
