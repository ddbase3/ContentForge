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

namespace ContentForge\Api;

interface IContentForgeSectionTemplateRegistry {

	public function getTemplate(string $key): ?IContentForgeSectionTemplate;

	/**
	 * @return IContentForgeSectionTemplate[]
	 */
	public function getTemplates(): array;

	public function getClientDefinitions(): array;

	public function getDefaultContent(string $key, string $language = 'en'): array;

	public function normalizeTemplateKey(string $key): string;

	public function validateSection(array $section): array;

	public function validateContent(array $content): array;
}
