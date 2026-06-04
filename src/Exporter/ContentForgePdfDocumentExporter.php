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

class ContentForgePdfDocumentExporter implements IContentForgeExporter {

	public static function getName(): string {
		return 'contentforgepdfdocumentexporter';
	}

	public function supports(ContentForgeExportRequest $request): bool {
		return in_array($request->type, ['pdf', 'pdf_document'], true);
	}

	public function export(ContentForgeExportRequest $request, ContentForgeWorkflowContext $context): ContentForgeExportResult {
		$content = $this->getLatestRevisionContent($context);
		$title = (string) ($content['title'] ?? $context->project->title);

		return new ContentForgeExportResult(
			ContentForgeProject::newId('export'),
			'pdf_document',
			$title,
			[
				'document.pdf' => $this->buildPdf($title, $content),
				'manifest.json' => json_encode([
					'title' => $title,
					'type' => 'pdf_document',
					'language' => $this->normalizeLanguageCode((string) ($content['language'] ?? 'en')),
					'generatorTemplate' => (string) ($content['generatorTemplate'] ?? 'micro_learning'),
					'exportTemplate' => 'pdf_document',
					'sectionTemplates' => $this->collectSectionTemplates($content),
					'createdAt' => gmdate('c'),
					'sourceProjectId' => $context->project->id
				], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			],
			[
				'projectId' => $context->project->id,
				'revisionCount' => count($context->revisions),
				'exportTemplate' => 'pdf_document'
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

	protected function buildPdf(string $title, array $content): string {
		$lines = [];
		$this->appendWrapped($lines, $title, 58, true);
		$this->appendWrapped($lines, (string) ($content['summary'] ?? ''), 80);
		$lines[] = '';

		foreach (($content['sections'] ?? []) as $section) {
			if (!is_array($section)) {
				continue;
			}

			$this->appendWrapped($lines, (string) ($section['title'] ?? 'Section'), 64, true);
			$this->appendWrapped($lines, (string) ($section['body'] ?? ''), 88);

			if (is_array($section['items'] ?? null)) {
				foreach ($section['items'] as $item) {
					$this->appendWrapped($lines, '- ' . (is_scalar($item) ? (string) $item : (string) ($item['text'] ?? '')), 84);
				}
			}

			$interaction = is_array($section['interaction'] ?? null) ? $section['interaction'] : [];
			if (($interaction['prompt'] ?? '') !== '') {
				$this->appendWrapped($lines, 'Prompt: ' . (string) $interaction['prompt'], 84);
			}

			$lines[] = '';
		}

		return $this->renderPdfLines($lines);
	}

	protected function appendWrapped(array &$lines, string $text, int $width, bool $heading = false): void {
		$text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

		if ($text === '') {
			return;
		}

		foreach (explode("\n", wordwrap($text, $width, "\n", true)) as $line) {
			$lines[] = [$line, $heading];
		}
	}

	protected function renderPdfLines(array $lines): string {
		$pageObjects = [];
		$lineHeight = 16;
		$maxLines = 44;
		$chunks = array_chunk($lines, $maxLines);

		if ($chunks === []) {
			$chunks = [[['No content.', false]]];
		}

		foreach ($chunks as $chunk) {
			$stream = "BT\n/F1 11 Tf\n72 760 Td\n";
			foreach ($chunk as $entry) {
				[$line, $heading] = is_array($entry) ? $entry : [$entry, false];
				$stream .= ($heading ? "/F1 15 Tf\n" : "/F1 11 Tf\n");
				$stream .= '(' . $this->escapePdfText((string) $line) . ") Tj\n0 -" . $lineHeight . " Td\n";
			}
			$stream .= "ET";
			$pageObjects[] = $stream;
		}

		$objects = [];
		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[] = '';
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$pageObjectNumbers = [];

		foreach ($pageObjects as $stream) {
			$pageNumber = count($objects) + 1;
			$contentNumber = $pageNumber + 1;
			$pageObjectNumbers[] = $pageNumber . ' 0 R';
			$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentNumber . ' 0 R >>';
			$objects[] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
		}

		$objects[1] = '<< /Type /Pages /Kids [' . implode(' ', $pageObjectNumbers) . '] /Count ' . count($pageObjectNumbers) . ' >>';

		$pdf = "%PDF-1.4\n";
		$offsets = [0];

		foreach ($objects as $index => $object) {
			$offsets[] = strlen($pdf);
			$pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
		}

		$xref = strlen($pdf);
		$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
		$pdf .= "0000000000 65535 f \n";

		for ($i = 1; $i <= count($objects); $i++) {
			$pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
		}

		$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

		return $pdf;
	}

	protected function escapePdfText(string $text): string {
		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
			if (is_string($converted)) {
				$text = $converted;
			}
		}

		$text = str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", ' ', ' '], $text);

		return $text;
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
}
