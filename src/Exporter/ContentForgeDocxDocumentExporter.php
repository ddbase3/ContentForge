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
use ZipArchive;

class ContentForgeDocxDocumentExporter implements IContentForgeExporter {

	public static function getName(): string {
		return 'contentforgedocxdocumentexporter';
	}

	public function supports(ContentForgeExportRequest $request): bool {
		return in_array($request->type, ['docx', 'docx_document'], true);
	}

	public function export(ContentForgeExportRequest $request, ContentForgeWorkflowContext $context): ContentForgeExportResult {
		$content = $this->getLatestRevisionContent($context);
		$title = (string) ($content['title'] ?? $context->project->title);
		$language = $this->normalizeLanguageCode((string) ($content['language'] ?? 'en'));
		$manifest = [
			'title' => $title,
			'type' => 'docx_document',
			'language' => $language,
			'generatorTemplate' => (string) ($content['generatorTemplate'] ?? 'micro_learning'),
			'exportTemplate' => 'docx_document',
			'sectionTemplates' => $this->collectSectionTemplates($content),
			'createdAt' => gmdate('c'),
			'sourceProjectId' => $context->project->id
		];

		return new ContentForgeExportResult(
			ContentForgeProject::newId('export'),
			'docx_document',
			$title,
			[
				'document.docx' => $this->buildDocx($title, $content)
			],
			[
				'projectId' => $context->project->id,
				'primaryFile' => 'document.docx',
				'revisionCount' => count($context->revisions),
				'exportTemplate' => 'docx_document',
				'manifest' => $manifest,
				'manifestJson' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
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

	protected function buildDocx(string $title, array $content): string {
		if (!class_exists(ZipArchive::class)) {
			throw new \RuntimeException('ZipArchive is required to create DOCX exports.');
		}

		$files = [
			'[Content_Types].xml' => $this->buildContentTypes(),
			'_rels/.rels' => $this->buildRootRels(),
			'word/_rels/document.xml.rels' => $this->buildDocumentRels(),
			'word/styles.xml' => $this->buildStyles(),
			'word/document.xml' => $this->buildDocumentXml($title, $content)
		];

		return $this->zipFiles($files);
	}

	protected function buildDocumentXml(string $title, array $content): string {
		$body = '';
		$body .= $this->paragraph($title, 'Title');
		$summary = trim((string) ($content['summary'] ?? ''));

		if ($summary !== '') {
			$body .= $this->paragraph($summary, 'Subtitle');
		}

		foreach (($content['sections'] ?? []) as $section) {
			if (!is_array($section)) {
				continue;
			}

			$body .= $this->paragraph((string) ($section['title'] ?? 'Section'), 'Heading1');
			$body .= $this->paragraph((string) ($section['body'] ?? ''), 'Normal');

			if (is_array($section['items'] ?? null)) {
				foreach ($section['items'] as $item) {
					$body .= $this->paragraph('• ' . (is_scalar($item) ? (string) $item : (string) ($item['text'] ?? '')), 'Normal');
				}
			}

			foreach (['note', 'footer'] as $field) {
				if (trim((string) ($section[$field] ?? '')) !== '') {
					$body .= $this->paragraph((string) $section[$field], 'Quote');
				}
			}

			$interaction = is_array($section['interaction'] ?? null) ? $section['interaction'] : [];
			if (trim((string) ($interaction['prompt'] ?? '')) !== '') {
				$body .= $this->paragraph((string) $interaction['prompt'], 'Quote');
			}
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
			. '<w:body>'
			. $body
			. '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
			. '</w:body></w:document>';
	}

	protected function paragraph(string $text, string $style = 'Normal'): string {
		$text = trim($text);
		if ($text === '') {
			return '';
		}

		$lines = preg_split('/\R+/', $text) ?: [$text];
		$result = '';

		foreach ($lines as $line) {
			$line = trim((string) $line);
			if ($line === '') {
				continue;
			}
			$result .= '<w:p><w:pPr><w:pStyle w:val="' . $this->xml($style) . '"/></w:pPr><w:r><w:t xml:space="preserve">' . $this->xml($line) . '</w:t></w:r></w:p>';
		}

		return $result;
	}

	protected function buildContentTypes(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
			. '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
			. '</Types>';
	}

	protected function buildRootRels(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
			. '</Relationships>';
	}

	protected function buildDocumentRels(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>';
	}

	protected function buildStyles(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
			. '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
			. '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:qFormat/><w:rPr><w:b/><w:sz w:val="36"/></w:rPr></w:style>'
			. '<w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:qFormat/><w:rPr><w:i/><w:sz w:val="24"/></w:rPr></w:style>'
			. '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:before="360" w:after="120"/></w:pPr><w:rPr><w:b/><w:sz w:val="28"/></w:rPr></w:style>'
			. '<w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/><w:basedOn w:val="Normal"/><w:qFormat/><w:pPr><w:ind w:left="360"/></w:pPr><w:rPr><w:i/></w:rPr></w:style>'
			. '</w:styles>';
	}

	protected function zipFiles(array $files): string {
		$tmp = tempnam(sys_get_temp_dir(), 'cf_docx_');
		if ($tmp === false) {
			throw new \RuntimeException('Temporary file cannot be created for DOCX export.');
		}

		$zip = new ZipArchive();
		if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			@unlink($tmp);
			throw new \RuntimeException('DOCX export archive cannot be created.');
		}

		foreach ($files as $name => $content) {
			$zip->addFromString((string) $name, (string) $content);
		}

		$zip->close();
		$content = file_get_contents($tmp);
		@unlink($tmp);

		if (!is_string($content)) {
			throw new \RuntimeException('DOCX export archive cannot be read.');
		}

		return $content;
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

	protected function xml(string $value): string {
		return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
	}
}
