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

namespace ContentForge\Exporter;

use ContentForge\Api\IContentForgeExporter;
use ContentForge\Model\ContentForgeExportRequest;
use ContentForge\Model\ContentForgeExportResult;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeWorkflowContext;

class ContentForgeHtmlPackageExporter implements IContentForgeExporter {

	public static function getName(): string {
		return 'contentforgehtmlpackageexporter';
	}

	public function supports(ContentForgeExportRequest $request): bool {
		return in_array($request->type, ['html_package', 'html_micro_module'], true);
	}

	public function export(ContentForgeExportRequest $request, ContentForgeWorkflowContext $context): ContentForgeExportResult {
		$content = $this->getLatestRevisionContent($context);
		$title = (string) ($content['title'] ?? $context->project->title);
		$language = $this->normalizeLanguageCode((string) ($content['language'] ?? 'en'));
		$template = (string) ($content['generatorTemplate'] ?? 'micro_learning');

		return new ContentForgeExportResult(
			ContentForgeProject::newId('export'),
			'html_package',
			$title,
			[
				'index.html' => $this->buildHtml($title, $content),
				'manifest.json' => json_encode([
					'title' => $title,
					'type' => 'html_package',
					'language' => $language,
					'generatorTemplate' => $template,
					'exportTemplate' => 'html_package',
					'sectionTemplates' => $this->collectSectionTemplates($content),
					'createdAt' => gmdate('c'),
					'sourceProjectId' => $context->project->id
				], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			],
			[
				'projectId' => $context->project->id,
				'revisionCount' => count($context->revisions),
				'exportTemplate' => 'html_package'
			]
		);
	}

	protected function getLatestRevisionContent(ContentForgeWorkflowContext $context): array {
		$revisions = $context->revisions;
		$latest = end($revisions);

		if ($latest && is_array($latest->content ?? null)) {
			return $latest->content;
		}

		return [
			'title' => $context->project->title,
			'summary' => 'No accepted artifact revision exists yet.',
			'sections' => []
		];
	}

	protected function buildHtml(string $title, array $content): string {
		$lang = $this->normalizeLanguageCode((string) ($content['language'] ?? 'en'));
		$html = '<!doctype html><html lang="' . $this->escape($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		$html .= '<title>' . $this->escape($title) . '</title>';
		$html .= '<style>body{font-family:system-ui,sans-serif;line-height:1.5;max-width:72rem;margin:0 auto;padding:2rem;color:#111827;background:#fff;}section{margin:2rem 0;padding:1rem;border:1px solid #d9e0e8;border-radius:5px;background:#f8fafc;}section p{color:#111827;}section.faq h2::before{content:"Q: ";color:#245b84;}section.checklist h2::before{content:"☐ ";color:#245b84;}aside{padding:1rem;border-left:4px solid #245b84;background:#f3f7fb;color:#111827;}ul.checklist{padding-left:1.4rem;}p.extra{padding:.65rem .75rem;border:1px solid #d9e0e8;border-radius:5px;background:#fff;}</style>';
		$html .= '</head><body><main>';
		$html .= '<h1>' . $this->escape($title) . '</h1>';
		$html .= '<p>' . $this->escape((string) ($content['summary'] ?? '')) . '</p>';


		foreach (($content['sections'] ?? []) as $section) {
			if (!is_array($section)) {
				continue;
			}

			$sectionTemplate = $this->normalizeSectionTemplate((string) ($section['template'] ?? $content['generatorTemplate'] ?? 'micro_learning'));
			$html .= '<section class="' . $this->escape($sectionTemplate) . '"><h2>' . $this->escape((string) ($section['title'] ?? 'Section')) . '</h2>';
			$html .= '<p>' . nl2br($this->escape((string) ($section['body'] ?? ''))) . '</p>';
			$html .= $this->renderOptionalSectionFields($section);
			$interaction = is_array($section['interaction'] ?? null) ? $section['interaction'] : [];
			if (($interaction['prompt'] ?? '') !== '') {
				$html .= '<aside>' . $this->escape((string) $interaction['prompt']) . '</aside>';
			}

			$changeRequest = is_array($section['changeRequest'] ?? null) ? $section['changeRequest'] : [];
			if (($changeRequest['feedback'] ?? '') !== '') {
				$html .= '<aside>Change request: ' . $this->escape((string) $changeRequest['feedback']) . '</aside>';
			}
			$html .= '</section>';
		}

		$html .= '</main></body></html>';

		return $html;
	}


	protected function renderOptionalSectionFields(array $section): string {
		$html = '';

		if (is_array($section['items'] ?? null) && $section['items'] !== []) {
			$html .= '<ul class="checklist">';

			foreach ($section['items'] as $item) {
				$html .= '<li>' . $this->escape(is_scalar($item) ? (string) $item : (string) ($item['text'] ?? '')) . '</li>';
			}

			$html .= '</ul>';
		}

		foreach (['footer', 'note'] as $key) {
			if (($section[$key] ?? '') !== '') {
				$html .= '<p class="extra">' . $this->escape((string) $section[$key]) . '</p>';
			}
		}

		return $html;
	}

	protected function collectSectionTemplates(array $content): array {
		$result = [];

		foreach (($content['sections'] ?? []) as $section) {
			if (is_array($section)) {
				$result[] = $this->normalizeSectionTemplate((string) ($section['template'] ?? $content['generatorTemplate'] ?? 'micro_learning'));
			}
		}

		return array_values(array_unique($result));
	}

	protected function normalizeSectionTemplate(string $template): string {
		$template = strtolower(trim($template));

		return in_array($template, ['micro_learning', 'short_overview', 'checklist', 'faq'], true) ? $template : 'micro_learning';
	}

	protected function normalizeLanguageCode(string $value): string {
		$value = strtolower(trim($value));

		if (str_starts_with($value, 'de')) {
			return 'de';
		}

		if (str_starts_with($value, 'en')) {
			return 'en';
		}

		return 'en';
	}

	protected function escape(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
