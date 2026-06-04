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

class ContentForgeScorm12Exporter implements IContentForgeExporter {

	public static function getName(): string {
		return 'contentforgescorm12exporter';
	}

	public function supports(ContentForgeExportRequest $request): bool {
		return in_array($request->type, ['scorm12', 'scorm12_package'], true);
	}

	public function export(ContentForgeExportRequest $request, ContentForgeWorkflowContext $context): ContentForgeExportResult {
		$content = $this->getLatestRevisionContent($context);
		$title = (string) ($content['title'] ?? $context->project->title);
		$language = $this->normalizeLanguageCode((string) ($content['language'] ?? 'en'));

		return new ContentForgeExportResult(
			ContentForgeProject::newId('export'),
			'scorm12_package',
			$title,
			[
				'imsmanifest.xml' => $this->buildManifest($title, $language),
				'index.html' => $this->buildHtml($title, $content),
				'scormdriver.js' => $this->buildScormDriver(),
				'contentforge-manifest.json' => json_encode([
					'title' => $title,
					'type' => 'scorm12_package',
					'language' => $language,
					'generatorTemplate' => (string) ($content['generatorTemplate'] ?? 'micro_learning'),
					'exportTemplate' => 'scorm12',
					'sectionTemplates' => $this->collectSectionTemplates($content),
					'createdAt' => gmdate('c'),
					'sourceProjectId' => $context->project->id
				], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			],
			[
				'projectId' => $context->project->id,
				'revisionCount' => count($context->revisions),
				'exportTemplate' => 'scorm12'
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

	protected function buildManifest(string $title, string $language): string {
		$identifier = 'contentforge_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower($title));

		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<manifest identifier="' . $this->escapeXml($identifier) . '" version="1.0"'
			. ' xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"'
			. ' xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"'
			. ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . "\n"
			. "\t<metadata>\n"
			. "\t\t<schema>ADL SCORM</schema>\n"
			. "\t\t<schemaversion>1.2</schemaversion>\n"
			. "\t</metadata>\n"
			. "\t<organizations default=\"contentforge_org\">\n"
			. "\t\t<organization identifier=\"contentforge_org\">\n"
			. "\t\t\t<title>" . $this->escapeXml($title) . "</title>\n"
			. "\t\t\t<item identifier=\"contentforge_item\" identifierref=\"contentforge_resource\">\n"
			. "\t\t\t\t<title>" . $this->escapeXml($title) . "</title>\n"
			. "\t\t\t</item>\n"
			. "\t\t</organization>\n"
			. "\t</organizations>\n"
			. "\t<resources>\n"
			. "\t\t<resource identifier=\"contentforge_resource\" type=\"webcontent\" adlcp:scormtype=\"sco\" href=\"index.html\">\n"
			. "\t\t\t<file href=\"index.html\" />\n"
			. "\t\t\t<file href=\"scormdriver.js\" />\n"
			. "\t\t</resource>\n"
			. "\t</resources>\n"
			. '</manifest>';
	}

	protected function buildHtml(string $title, array $content): string {
		$lang = $this->normalizeLanguageCode((string) ($content['language'] ?? 'en'));
		$html = '<!doctype html><html lang="' . $this->escape($lang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		$html .= '<title>' . $this->escape($title) . '</title><script src="scormdriver.js"></script>';
		$html .= '<style>body{font-family:system-ui,sans-serif;line-height:1.5;max-width:72rem;margin:0 auto;padding:2rem;color:#111827;background:#fff;}section{margin:2rem 0;padding:1rem;border:1px solid #d9e0e8;border-radius:5px;background:#f8fafc;}aside{padding:1rem;border-left:4px solid #245b84;background:#f3f7fb;color:#111827;}ul.checklist{padding-left:1.4rem;}p.extra{padding:.65rem .75rem;border:1px solid #d9e0e8;border-radius:5px;background:#fff;}</style>';
		$html .= '</head><body onload="ContentForgeScorm.init()" onunload="ContentForgeScorm.finish()"><main>';
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
			$html .= '</section>';
		}

		$html .= '</main></body></html>';

		return $html;
	}

	protected function buildScormDriver(): string {
		return <<<JS
var ContentForgeScorm = (function() {
	var api = null;
	function findApi(win) {
		var depth = 0;
		while (win && !win.API && win.parent && win.parent !== win && depth < 10) {
			win = win.parent;
			depth++;
		}
		return win && win.API ? win.API : null;
	}
	function init() {
		api = findApi(window) || (window.opener ? findApi(window.opener) : null);
		if (api && api.LMSInitialize) {
			api.LMSInitialize('');
			api.LMSSetValue('cmi.core.lesson_status', 'completed');
			api.LMSCommit('');
		}
	}
	function finish() {
		if (api && api.LMSFinish) {
			api.LMSFinish('');
		}
	}
	return {init: init, finish: finish};
})();
JS;
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

	protected function escapeXml(string $value): string {
		return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
	}
}
