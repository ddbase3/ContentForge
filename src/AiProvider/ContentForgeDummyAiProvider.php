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

namespace ContentForge\AiProvider;

use ContentForge\Api\IContentForgeAiProvider;
use ContentForge\Model\ContentForgeAiRequest;
use ContentForge\Model\ContentForgeAiResult;

class ContentForgeDummyAiProvider implements IContentForgeAiProvider {

	public static function getName(): string {
		return 'contentforgedummyaiprovider';
	}

	public function supports(array $serviceSettings, array $connectionSettings): bool {
		return (string) ($serviceSettings['driver'] ?? '') === 'contentforge-dummy';
	}

	public function generateStructured(ContentForgeAiRequest $request, array $serviceSettings, array $connectionSettings): ContentForgeAiResult {
		$sectionCount = max(1, min(12, (int) ($request->context['sectionCount'] ?? 3)));
		$template = (string) ($request->context['generatorTemplate'] ?? 'micro_learning');
		$material = trim((string) ($request->context['material'] ?? ''));
		$feedback = trim((string) ($request->context['feedback'] ?? ''));

		if ($material === '') {
			$material = 'No material was provided yet.';
		}

		$sections = [];
		$sentences = preg_split('/(?<=[.!?])\s+/', $material) ?: [];
		$sentences = array_values(array_filter(array_map('trim', $sentences)));

		if ($sentences === []) {
			$sentences = [$material];
		}

		for ($i = 0; $i < $sectionCount; $i++) {
			$body = $sentences[$i % count($sentences)] ?? $material;

			if ($feedback !== '' && (int) ($request->context['selectedSectionIndex'] ?? -1) === $i) {
				$body .= "\n\nChange request noted: " . $feedback;
			}

			$sections[] = [
				'title' => $template === 'faq' ? 'Question ' . ($i + 1) : ($template === 'checklist' ? 'Item ' . ($i + 1) : 'Section ' . ($i + 1)),
				'body' => $body,
				'interaction' => [
					'type' => $i === $sectionCount - 1 ? 'reflection' : 'reading',
					'prompt' => $i === $sectionCount - 1 ? 'What should the reader remember from this module?' : ''
				]
			];
		}

		return ContentForgeAiResult::success([
			'title' => trim((string) ($request->context['title'] ?? 'HTML Micro Module')),
			'summary' => substr(preg_replace('/\s+/', ' ', $material) ?? '', 0, 220),
			'generatorTemplate' => $template,
			'sections' => $sections
		], '', self::getName(), 'contentforge-dummy');
	}
}
