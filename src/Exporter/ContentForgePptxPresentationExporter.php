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

class ContentForgePptxPresentationExporter implements IContentForgeExporter {

	public static function getName(): string {
		return 'contentforgepptxpresentationexporter';
	}

	public function supports(ContentForgeExportRequest $request): bool {
		return in_array($request->type, ['pptx', 'pptx_presentation'], true);
	}

	public function export(ContentForgeExportRequest $request, ContentForgeWorkflowContext $context): ContentForgeExportResult {
		$content = $this->getLatestRevisionContent($context);
		$title = (string) ($content['title'] ?? $context->project->title);
		$language = $this->normalizeLanguageCode((string) ($content['language'] ?? 'en'));
		$manifest = [
			'title' => $title,
			'type' => 'pptx_presentation',
			'language' => $language,
			'generatorTemplate' => (string) ($content['generatorTemplate'] ?? 'micro_learning'),
			'exportTemplate' => 'pptx_presentation',
			'sectionTemplates' => $this->collectSectionTemplates($content),
			'createdAt' => gmdate('c'),
			'sourceProjectId' => $context->project->id
		];

		return new ContentForgeExportResult(
			ContentForgeProject::newId('export'),
			'pptx_presentation',
			$title,
			[
				'presentation.pptx' => $this->buildPptx($title, $content, $language)
			],
			[
				'projectId' => $context->project->id,
				'primaryFile' => 'presentation.pptx',
				'revisionCount' => count($context->revisions),
				'exportTemplate' => 'pptx_presentation',
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

	protected function buildPptx(string $title, array $content, string $language): string {
		if (!class_exists(ZipArchive::class)) {
			throw new \RuntimeException('ZipArchive is required to create PPTX exports.');
		}

		$slides = $this->buildSlideModels($title, $content);
		$files = [
			'[Content_Types].xml' => $this->buildContentTypes(count($slides)),
			'_rels/.rels' => $this->buildRootRels(),
			'docProps/core.xml' => $this->buildCoreProperties($title, $language),
			'docProps/app.xml' => $this->buildAppProperties(count($slides)),
			'ppt/presentation.xml' => $this->buildPresentationXml(count($slides), $language),
			'ppt/_rels/presentation.xml.rels' => $this->buildPresentationRels(count($slides)),
			'ppt/presProps.xml' => $this->buildPresentationProperties(),
			'ppt/viewProps.xml' => $this->buildViewProperties(),
			'ppt/tableStyles.xml' => $this->buildTableStyles(),
			'ppt/slideMasters/slideMaster1.xml' => $this->buildSlideMaster(),
			'ppt/slideMasters/_rels/slideMaster1.xml.rels' => $this->buildSlideMasterRels(),
			'ppt/slideLayouts/slideLayout1.xml' => $this->buildSlideLayout(),
			'ppt/slideLayouts/_rels/slideLayout1.xml.rels' => $this->buildSlideLayoutRels(),
			'ppt/theme/theme1.xml' => $this->buildTheme()
		];

		foreach ($slides as $index => $slide) {
			$number = $index + 1;
			$files['ppt/slides/slide' . $number . '.xml'] = $this->buildSlideXml($slide['title'], $slide['body'], $slide['items'], $language);
			$files['ppt/slides/_rels/slide' . $number . '.xml.rels'] = $this->buildSlideRels();
		}

		return $this->zipFiles($files);
	}

	protected function buildSlideModels(string $title, array $content): array {
		$slides = [[
			'title' => $title,
			'body' => (string) ($content['summary'] ?? ''),
			'items' => []
		]];

		foreach (($content['sections'] ?? []) as $section) {
			if (!is_array($section)) {
				continue;
			}

			$items = [];
			if (is_array($section['items'] ?? null)) {
				foreach ($section['items'] as $item) {
					$items[] = is_scalar($item) ? (string) $item : (string) ($item['text'] ?? '');
				}
			}

			$slides[] = [
				'title' => (string) ($section['title'] ?? 'Section'),
				'body' => (string) ($section['body'] ?? ''),
				'items' => array_values(array_filter($items, fn($item) => trim((string) $item) !== ''))
			];
		}

		return $slides;
	}

	protected function buildContentTypes(int $slideCount): string {
		$overrides = '';
		for ($i = 1; $i <= $slideCount; $i++) {
			$overrides .= '<Override PartName="/ppt/slides/slide' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
			. '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
			. '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>'
			. '<Override PartName="/ppt/presProps.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presProps+xml"/>'
			. '<Override PartName="/ppt/viewProps.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.viewProps+xml"/>'
			. '<Override PartName="/ppt/tableStyles.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.tableStyles+xml"/>'
			. '<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>'
			. '<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>'
			. '<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>'
			. $overrides
			. '</Types>';
	}

	protected function buildRootRels(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
			. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
			. '</Relationships>';
	}

	protected function buildCoreProperties(string $title, string $language): string {
		$created = gmdate('Y-m-d\TH:i:s\Z');

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
			. '<dc:title>' . $this->xml($title) . '</dc:title>'
			. '<dc:creator>BASE3 ContentForge</dc:creator>'
			. '<dc:language>' . $this->xml($language) . '</dc:language>'
			. '<cp:lastModifiedBy>BASE3 ContentForge</cp:lastModifiedBy>'
			. '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>'
			. '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>'
			. '</cp:coreProperties>';
	}

	protected function buildAppProperties(int $slideCount): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
			. '<Application>BASE3 ContentForge</Application>'
			. '<PresentationFormat>On-screen Show (16:9)</PresentationFormat>'
			. '<Slides>' . $slideCount . '</Slides>'
			. '<Notes>0</Notes>'
			. '<HiddenSlides>0</HiddenSlides>'
			. '<MMClips>0</MMClips>'
			. '<ScaleCrop>false</ScaleCrop>'
			. '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Slides</vt:lpstr></vt:variant><vt:variant><vt:i4>' . $slideCount . '</vt:i4></vt:variant></vt:vector></HeadingPairs>'
			. '<TitlesOfParts><vt:vector size="0" baseType="lpstr"/></TitlesOfParts>'
			. '<Company>BASE3</Company>'
			. '<LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>16.0000</AppVersion>'
			. '</Properties>';
	}

	protected function buildPresentationXml(int $slideCount, string $language): string {
		$slideIds = '';
		for ($i = 1; $i <= $slideCount; $i++) {
			$slideIds .= '<p:sldId id="' . (255 + $i) . '" r:id="rId' . (5 + $i) . '"/>';
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" saveSubsetFonts="1" autoCompressPictures="0">'
			. '<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>'
			. '<p:sldIdLst>' . $slideIds . '</p:sldIdLst>'
			. '<p:sldSz cx="12192000" cy="6858000" type="wide"/><p:notesSz cx="6858000" cy="9144000"/>'
			. '<p:defaultTextStyle><a:defPPr><a:defRPr lang="' . $this->xml($this->pptLanguage($language)) . '"/></a:defPPr></p:defaultTextStyle>'
			. '</p:presentation>';
	}

	protected function buildPresentationRels(int $slideCount): string {
		$rels = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>';
		$rels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/presProps" Target="presProps.xml"/>';
		$rels .= '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/viewProps" Target="viewProps.xml"/>';
		$rels .= '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>';
		$rels .= '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/tableStyles" Target="tableStyles.xml"/>';

		for ($i = 1; $i <= $slideCount; $i++) {
			$rels .= '<Relationship Id="rId' . (5 + $i) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $i . '.xml"/>';
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
	}

	protected function buildPresentationProperties(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<p:presentationPr xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"/>';
	}

	protected function buildViewProperties(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<p:viewPr xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
			. '<p:normalViewPr><p:restoredLeft sz="15620"/><p:restoredTop sz="94660"/></p:normalViewPr>'
			. '<p:slideViewPr><p:cSldViewPr><p:cViewPr varScale="1"><p:scale><a:sx n="100" d="100"/><a:sy n="100" d="100"/></p:scale><p:origin x="0" y="0"/></p:cViewPr><p:guideLst/></p:cSldViewPr></p:slideViewPr>'
			. '<p:notesTextViewPr><p:cViewPr><p:scale><a:sx n="100" d="100"/><a:sy n="100" d="100"/></p:scale><p:origin x="0" y="0"/></p:cViewPr></p:notesTextViewPr>'
			. '<p:gridSpacing cx="72008" cy="72008"/>'
			. '</p:viewPr>';
	}

	protected function buildTableStyles(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<a:tblStyleLst xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" def="{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}"/>';
	}

	protected function buildSlideMaster(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
			. '<p:cSld><p:bg><p:bgRef idx="1001"><a:schemeClr val="bg1"/></p:bgRef></p:bg><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
			. '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld>'
			. '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>'
			. '<p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>'
			. '<p:txStyles><p:titleStyle/><p:bodyStyle/><p:otherStyle/></p:txStyles>'
			. '</p:sldMaster>';
	}

	protected function buildSlideMasterRels(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>'
			. '</Relationships>';
	}

	protected function buildSlideLayout(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">'
			. '<p:cSld name="Blank"><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
			. '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld>'
			. '<p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>'
			. '</p:sldLayout>';
	}

	protected function buildSlideLayoutRels(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>'
			. '</Relationships>';
	}

	protected function buildTheme(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="ContentForge">'
			. '<a:themeElements>'
			. '<a:clrScheme name="ContentForge"><a:dk1><a:srgbClr val="111827"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="245B84"/></a:dk2><a:lt2><a:srgbClr val="F8FAFC"/></a:lt2><a:accent1><a:srgbClr val="245B84"/></a:accent1><a:accent2><a:srgbClr val="6B7280"/></a:accent2><a:accent3><a:srgbClr val="D9E0E8"/></a:accent3><a:accent4><a:srgbClr val="F3F7FB"/></a:accent4><a:accent5><a:srgbClr val="111827"/></a:accent5><a:accent6><a:srgbClr val="FFFFFF"/></a:accent6><a:hlink><a:srgbClr val="245B84"/></a:hlink><a:folHlink><a:srgbClr val="245B84"/></a:folHlink></a:clrScheme>'
			. '<a:fontScheme name="ContentForge"><a:majorFont><a:latin typeface="Arial"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont><a:minorFont><a:latin typeface="Arial"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont></a:fontScheme>'
			. '<a:fmtScheme name="ContentForge"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="50000"/></a:schemeClr></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"><a:shade val="50000"/></a:schemeClr></a:gs></a:gsLst><a:lin ang="5400000" scaled="0"/></a:gradFill><a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"/></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"><a:shade val="70000"/></a:schemeClr></a:gs></a:gsLst><a:lin ang="5400000" scaled="0"/></a:gradFill></a:fillStyleLst><a:lnStyleLst><a:ln w="9525" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln><a:ln w="25400" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln><a:ln w="38100" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="40000"/></a:schemeClr></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"><a:shade val="40000"/></a:schemeClr></a:gs></a:gsLst><a:lin ang="5400000" scaled="0"/></a:gradFill><a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"/></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"><a:shade val="70000"/></a:schemeClr></a:gs></a:gsLst><a:lin ang="5400000" scaled="0"/></a:gradFill></a:bgFillStyleLst></a:fmtScheme>'
			. '</a:themeElements><a:objectDefaults/><a:extraClrSchemeLst/></a:theme>';
	}

	protected function buildSlideRels(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
			. '</Relationships>';
	}

	protected function buildSlideXml(string $title, string $body, array $items, string $language): string {
		$text = trim($body);
		if ($items !== []) {
			$text .= ($text !== '' ? "\n" : '') . implode("\n", array_map(fn($item) => '• ' . (string) $item, $items));
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
			. '<p:cSld><p:spTree>'
			. '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
			. $this->textBox(2, 'Title', $title, 640000, 380000, 10900000, 900000, 3200, true, $language)
			. $this->textBox(3, 'Body', $text, 640000, 1460000, 10900000, 4750000, 2000, false, $language)
			. '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>'
			. '</p:sld>';
	}

	protected function textBox(int $id, string $name, string $text, int $x, int $y, int $cx, int $cy, int $fontSize, bool $bold, string $language): string {
		$paragraphs = preg_split('/\R+/', trim($text)) ?: [];
		$paragraphXml = '';
		$lang = $this->pptLanguage($language);

		foreach ($paragraphs as $paragraph) {
			$paragraph = trim((string) $paragraph);
			if ($paragraph === '') {
				continue;
			}

			$paragraphXml .= '<a:p><a:r><a:rPr lang="' . $this->xml($lang) . '" sz="' . $fontSize . '"' . ($bold ? ' b="1"' : '') . ' dirty="0"/><a:t>' . $this->xml($paragraph) . '</a:t></a:r><a:endParaRPr lang="' . $this->xml($lang) . '" dirty="0"/></a:p>';
		}

		if ($paragraphXml === '') {
			$paragraphXml = '<a:p><a:endParaRPr lang="' . $this->xml($lang) . '" dirty="0"/></a:p>';
		}

		return '<p:sp><p:nvSpPr><p:cNvPr id="' . $id . '" name="' . $this->xml($name) . '"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
			. '<p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>'
			. '<p:txBody><a:bodyPr wrap="square" rtlCol="0"><a:spAutoFit/></a:bodyPr><a:lstStyle/>' . $paragraphXml . '</p:txBody></p:sp>';
	}

	protected function zipFiles(array $files): string {
		$tmp = tempnam(sys_get_temp_dir(), 'cf_pptx_');
		if ($tmp === false) {
			throw new \RuntimeException('Temporary file cannot be created for PPTX export.');
		}

		$zip = new ZipArchive();
		if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			@unlink($tmp);
			throw new \RuntimeException('PPTX export archive cannot be created.');
		}

		foreach ($files as $name => $content) {
			$zip->addFromString((string) $name, (string) $content);
		}

		$zip->close();
		$content = file_get_contents($tmp);
		@unlink($tmp);

		if (!is_string($content)) {
			throw new \RuntimeException('PPTX export archive cannot be read.');
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

	protected function pptLanguage(string $language): string {
		return $language === 'de' ? 'de-DE' : 'en-US';
	}

	protected function xml(string $value): string {
		return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
	}
}
