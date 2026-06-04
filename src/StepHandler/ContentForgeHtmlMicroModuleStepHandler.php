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

namespace ContentForge\StepHandler;

use Base3\Logger\Api\ILogger;
use ContentForge\Api\IContentForgeAiService;
use ContentForge\Api\IContentForgeWorkflowNodeHandler;
use ContentForge\Model\ContentForgeAiRequest;
use ContentForge\Model\ContentForgeAiResult;
use ContentForge\Model\ContentForgeDecision;
use ContentForge\Model\ContentForgeProposal;
use ContentForge\Model\ContentForgeStepResult;
use ContentForge\Model\ContentForgeWorkflowContext;
use ContentForge\Model\ContentForgeWorkflowNode;

class ContentForgeHtmlMicroModuleStepHandler implements IContentForgeWorkflowNodeHandler {

	public function __construct(
		private readonly ?IContentForgeAiService $aiService = null,
		private readonly ?ILogger $logger = null
	) {}

	public static function getName(): string {
		return 'contentforgehtmlmicromodulestephandler';
	}

	public function handle(ContentForgeWorkflowContext $context, ContentForgeWorkflowNode $node): ContentForgeStepResult {
		$configTitle = trim((string) ($node->config['title'] ?? $context->project->title));
		$requestedTitle = $this->normalizeRequestedTitle($configTitle);
		$options = $this->getGenerationOptions($context, $node);
		$template = $options['template'];
		$sectionCount = $options['sectionCount'];
		$materialText = $context->getMaterialText();
		$feedback = $context->getLatestFeedback();
		$feedbackContext = $this->parseFeedbackContext($feedback);
		$language = $this->detectLanguage($materialText, (string) ($feedbackContext['userFeedback'] ?? ''));
		$changeRound = $this->countChangeRequests($context->decisions);
		$warnings = [];

		if ($materialText === '') {
			$materialText = $language['code'] === 'de'
				? 'Es wurde noch kein Material bereitgestellt. Dieser Vorschlag ist eine Platzhalter-Struktur.'
				: 'No material was provided yet. This proposal is a placeholder module structure.';
		}

		$aiResult = null;
		$useAi = !array_key_exists('useAi', $node->config) || !empty($node->config['useAi']);

		if ($useAi && $this->aiService !== null) {
			$aiResult = $this->generateWithAi($context, $node, $requestedTitle, $materialText, $feedback, $feedbackContext, $sectionCount, $language, $template);
		} elseif (!$useAi) {
			$this->logDebug('ContentForge micro module automatic generation disabled by node config.');
		} else {
			$this->logDebug('ContentForge micro module has no AI service instance.');
		}

		if ($aiResult !== null && $aiResult->ok) {
			$content = $this->normalizeAiContent($aiResult->content, $requestedTitle, $materialText, $sectionCount, $language, $feedbackContext, $template);
			$content['review'] = $this->buildReviewData($changeRound, $feedback, $feedbackContext, 'ai', $aiResult);
		} elseif ($aiResult !== null && !$aiResult->ok && is_array($feedbackContext['currentProposal'] ?? null) && $feedbackContext['currentProposal'] !== []) {
			$content = $feedbackContext['currentProposal'];
			$content['review'] = $this->buildReviewData($changeRound, $feedback, $feedbackContext, 'generation_failed', $aiResult);
			$content['review']['status'] = 'automatic_generation_failed';
			$content['review']['message'] = $language['code'] === 'de'
				? 'Die automatische Überarbeitung ist fehlgeschlagen. Der bisherige Vorschlag wurde beibehalten.'
				: 'Automatic revision failed. The previous proposal was kept.';
			$this->logDebug('ContentForge kept current proposal after provider failure during revision.', [
				'errors' => $aiResult->errors,
				'provider' => $aiResult->provider,
				'model' => $aiResult->model
			]);
		} else {
			$sections = $this->buildSections($materialText, $sectionCount, $language, $template);
			$sections = $this->applyFeedbackMarker($sections, $feedbackContext);

			$content = [
				'generatorType' => 'html_micro_module',
				'generatorTemplate' => $template,
				'language' => $language['code'],
				'title' => $requestedTitle !== '' ? $requestedTitle : $this->deriveTitleFromMaterial($materialText, $language),
				'summary' => $this->summarize($materialText),
				'sections' => $sections,
				'review' => $this->buildReviewData($changeRound, $feedback, $feedbackContext, 'deterministic', $aiResult),
				'exportHints' => [
					'preferredExporter' => 'contentforgehtmlpackageexporter',
					'scormReadyLater' => true
				]
			];

			if ($aiResult !== null && !$aiResult->ok) {
				$this->logDebug('ContentForge micro module used deterministic generation after provider failure.', [
					'errors' => $aiResult->errors,
					'provider' => $aiResult->provider,
					'model' => $aiResult->model
				]);
			}
		}

		$content['generatorType'] = 'html_micro_module';
		$content['generatorTemplate'] = (string) ($content['generatorTemplate'] ?? $template);
		$content['language'] = (string) ($content['language'] ?? $language['code']);
		$content['exportHints'] = is_array($content['exportHints'] ?? null) ? $content['exportHints'] : [
			'preferredExporter' => 'contentforgehtmlpackageexporter',
			'scormReadyLater' => true
		];

		$proposal = ContentForgeProposal::create(
			$context->project->id,
			$context->instance->id,
			(string) ($context->data['stepRunId'] ?? $context->instance->data['lastStepRunId'] ?? ''),
			'html_micro_module',
			(string) ($content['title'] ?? ($requestedTitle !== '' ? $requestedTitle : 'Micro Module')),
			$content,
			[
				'handler' => self::getName(),
				'materialCount' => count($context->materials),
				'language' => (string) ($content['language'] ?? $language['code']),
				'generatorTemplate' => (string) ($content['generatorTemplate'] ?? $template),
				'deterministic' => ($content['review']['generator'] ?? '') !== 'ai',
				'aiUsed' => ($content['review']['generator'] ?? '') === 'ai',
				'aiProvider' => (string) ($content['review']['ai']['provider'] ?? ''),
				'aiModel' => (string) ($content['review']['ai']['model'] ?? ''),
				'aiErrors' => $aiResult ? $aiResult->errors : []
			]
		);

		return new ContentForgeStepResult('success', [$proposal], [], [], [], 'review', [], $warnings);
	}


	protected function getGenerationOptions(ContentForgeWorkflowContext $context, ContentForgeWorkflowNode $node): array {
		$meta = $this->getLatestMaterialMeta($context);
		$template = $this->normalizeTemplate((string) ($meta['generatorTemplate'] ?? $node->config['generatorTemplate'] ?? 'micro_learning'));
		$count = (string) ($meta['targetSectionCount'] ?? $node->config['sectionCount'] ?? 'auto');

		return [
			'template' => $template,
			'sectionCount' => $this->resolveSectionCount($count, $template)
		];
	}

	protected function getLatestMaterialMeta(ContentForgeWorkflowContext $context): array {
		$materials = $context->materials;
		$latest = end($materials);

		return $latest && is_array($latest->meta ?? null) ? $latest->meta : [];
	}

	protected function normalizeTemplate(string $template): string {
		$template = strtolower(trim($template));
		$allowed = ['micro_learning', 'short_overview', 'checklist', 'faq'];

		return in_array($template, $allowed, true) ? $template : 'micro_learning';
	}

	protected function normalizeSectionTemplate(string $template): string {
		return $this->normalizeTemplate($template);
	}

	protected function resolveSectionCount(string $count, string $template): int {
		$count = strtolower(trim($count));

		if ($count !== '' && $count !== 'auto' && ctype_digit($count)) {
			return max(1, min(12, (int) $count));
		}

		return match ($template) {
			'checklist' => 6,
			'faq' => 5,
			'short_overview' => 4,
			default => 3
		};
	}

	protected function getTemplateLabel(string $template): string {
		return match ($template) {
			'short_overview' => 'Short overview',
			'checklist' => 'Checklist',
			'faq' => 'FAQ',
			default => 'Micro-learning'
		};
	}

	protected function getTemplateGuidance(string $template): array {
		return match ($template) {
			'short_overview' => [
				'Create a concise overview with clear topical sections.',
				'Each section should explain one core idea in plain language.',
				'Use interaction prompts only when they help reflection.'
			],
			'checklist' => [
				'Create an actionable checklist.',
				'Each section title should be a short action item.',
				'Each section body should explain what to check or do.',
				'Use an optional items array when a checklist section needs multiple concrete checks.'
			],
			'faq' => [
				'Create a FAQ.',
				'Each section title should be a question.',
				'Each section body should be the answer to that question.'
			],
			default => [
				'Create a small micro-learning module.',
				'Each section should be a compact learning card.',
				'Use at least one meaningful reflection or question prompt.',
			'Section templates may vary when this improves the module.'
			]
		};
	}

	protected function generateWithAi(ContentForgeWorkflowContext $context, ContentForgeWorkflowNode $node, string $requestedTitle, string $materialText, string $feedback, array $feedbackContext, int $sectionCount, array $language, string $template): ?ContentForgeAiResult {
		$serviceName = trim((string) ($node->config['aiService'] ?? 'mistral_default'));

		if ($this->aiService === null || !$this->aiService->isAvailable($serviceName)) {
			$status = $this->aiService ? $this->aiService->getStatus($serviceName) : [];
			$this->logDebug('ContentForge automatic generation service is not available for micro module step.', $status);

			return null;
		}

		$this->logDebug('ContentForge automatic generation service selected for micro module step.', [
			'service' => $serviceName,
			'language' => $language['code'],
			'generatorTemplate' => $template,
			'selectedSectionIndexes' => $feedbackContext['selectedSectionIndexes'],
			'selectedSectionTitles' => $feedbackContext['selectedSectionTitles']
		]);

		$request = new ContentForgeAiRequest(
			$serviceName,
			$this->buildSystemPrompt($language),
			$this->buildUserPrompt($requestedTitle, $materialText, $feedback, $feedbackContext, $sectionCount, $node->config, $language, $template),
			$this->getOutputSchema(),
			[
				'title' => $requestedTitle,
				'material' => $materialText,
				'feedback' => $feedback,
				'language' => $language['code'],
				'generatorTemplate' => $template,
				'selectedSectionIndex' => $feedbackContext['selectedSectionIndex'],
				'selectedSectionTitle' => $feedbackContext['selectedSectionTitle'],
				'selectedSectionIndexes' => $feedbackContext['selectedSectionIndexes'],
				'selectedSectionTitles' => $feedbackContext['selectedSectionTitles'],
				'sectionCount' => $sectionCount
			],
			[
				'temperature' => $node->config['temperature'] ?? null,
				'maxTokens' => $node->config['maxTokens'] ?? null
			]
		);

		return $this->aiService->generateStructured($request);
	}

	protected function buildSystemPrompt(array $language): string {
		return implode("\n", [
			'You are ContentForge, a careful structured content reviser.',
			'Generate concise, reviewable HTML micro module content.',
			'Return only a valid JSON object. Do not wrap it in Markdown.',
			'Do not invent external facts. Use only the provided material, current proposal and user feedback.',
			'Write every user-facing value in this language: ' . $language['name'] . '.',
			'Selection scope is strict by default: when selected sections are provided, the user feedback is about those selected sections unless the feedback explicitly says otherwise.',
			'Use the full current proposal as context, not as permission to freely rewrite unrelated sections.',
			'For targeted revisions, do not apply the requested transformation to unrelated sections. You may make small consistency edits nearby, but the main requested change must stay focused on the selected sections.',
			'If the user asks to split, merge, reorder or restructure selected sections, you may change the section list accordingly.',
			'Return the complete module JSON after your revision.',
			'Do not mention AI, providers, prompts, fallbacks or internal workflow details.'
		]);
	}

	protected function buildUserPrompt(string $requestedTitle, string $materialText, string $feedback, array $feedbackContext, int $sectionCount, array $config, array $language, string $template): string {
		$templateGuidance = $this->getTemplateGuidance($template);
		$parts = [
			'Task: Create a structured HTML content proposal.',
			'Output language: ' . $language['name'] . '.',
			'Requested title: ' . ($requestedTitle !== '' ? $requestedTitle : '(derive a concrete title from the material)'),
			'Template: ' . $this->getTemplateLabel($template) . ' (' . $template . ')',
			'Target part count: ' . $sectionCount,
			'Audience: ' . (string) ($config['audience'] ?? 'general'),
			'Tone: ' . (string) ($config['tone'] ?? 'clear'),
			'',
			'Rules:',
			'- Return exactly one JSON object and no extra text.',
			'- Respect the selected proposal template as the default structure.',
			'- Every section may also define its own section template via section.template.',
			'- Allowed section.template values are: micro_learning, short_overview, checklist, faq.',
			'- Aim for the target part count, but allow a different count when the material or feedback clearly requires it.',
			'- Keep the result concise and directly reviewable.',
			'- Derive the title from the material if no concrete requested title is provided.',
			'- Use the same output language for title, summary, section titles, section bodies and interaction prompts.',
			'- Do not add facts that are not supported by the material, the current proposal or the user feedback.',
			'- If selected sections are present, the feedback targets those selected sections. Treat this as the default editing scope.',
			'- Interpret words like this, this chapter, this section, here and selected part as referring to the selected sections.',
			'- Use the whole proposal as context, but preserve unrelated sections by default.',
			'- Do not convert unrelated sections to another template just because the selected section changes template. If the user says "change this to a checklist", only selected sections should become checklist sections.',
			'- You may adjust neighboring or global content only when this is necessary for consistency or when the user clearly asks for structural/global changes.',
			'- Do not expose implementation details.',
			'',
			'Template guidance:',
		];

		foreach ($templateGuidance as $line) {
			$parts[] = '- ' . $line;
		}

		$parts = array_merge($parts, [
			'',
			'Return this JSON shape exactly:',
			'{',
			'  "title": "string",',
			'  "summary": "string",',
			'  "language": "' . $language['code'] . '",',
			'  "generatorTemplate": "' . $template . '",',
			'  "sections": [',
			'    {',
			'      "id": "section_1",',
			'      "template": "micro_learning|short_overview|checklist|faq",',
			'      "title": "string",',
			'      "body": "string",',
			'      "items": ["optional checklist item"],',
			'      "footer": "optional footer or note",',
			'      "interaction": {"type": "reading|reflection|question", "prompt": "string"}',
			'    }',
			'  ]',
			'}',
			'',
			'Material:',
			$materialText
		]);

		$currentProposal = is_array($feedbackContext['currentProposal'] ?? null) ? $feedbackContext['currentProposal'] : [];
		$selectedSections = is_array($feedbackContext['selectedSections'] ?? null) ? $feedbackContext['selectedSections'] : [];
		$selectedSection = is_array($feedbackContext['selectedSection'] ?? null) ? $feedbackContext['selectedSection'] : [];

		if ($currentProposal !== []) {
			$parts[] = '';
			$parts[] = 'Current proposal JSON:';
			$parts[] = $this->encodePromptJson($currentProposal);
		}

		if ($selectedSections !== []) {
			$parts[] = '';
			$parts[] = 'Selected sections JSON:';
			$parts[] = $this->encodePromptJson($selectedSections);
		} elseif ($selectedSection !== []) {
			$parts[] = '';
			$parts[] = 'Selected section JSON:';
			$parts[] = $this->encodePromptJson($selectedSection);
		}

		if ($feedback !== '') {
			$selectedIndexes = is_array($feedbackContext['selectedSectionIndexes'] ?? null) ? $feedbackContext['selectedSectionIndexes'] : [];
			$selectedIndex = $feedbackContext['selectedSectionIndex'];
			$hasSelectedSection = $selectedIndexes !== [] || is_int($selectedIndex);

			$parts[] = '';
			$parts[] = 'User requested changes:';
			$parts[] = 'Selected section indexes: ' . ($selectedIndexes !== [] ? implode(',', $selectedIndexes) : ($hasSelectedSection ? (string) $selectedIndex : 'none'));
			$parts[] = 'Selected section titles: ' . implode(' | ', is_array($feedbackContext['selectedSectionTitles'] ?? null) ? $feedbackContext['selectedSectionTitles'] : [(string) $feedbackContext['selectedSectionTitle']]);
			$parts[] = 'Feedback: ' . (string) $feedbackContext['userFeedback'];
			$parts[] = 'Apply the feedback in the generated content instead of merely noting it.';

			if ($hasSelectedSection) {
				$parts[] = 'This is a targeted change request. The selected section(s) are the edit target.';
				$parts[] = 'Apply the feedback to the selected section(s) as the primary edit scope and preserve unrelated sections by default.';
				$parts[] = 'Do not repeat the selected-section transformation across all sections unless the user explicitly asks for a global change.';
				$parts[] = 'If the feedback says phrases like "this", "this chapter", "this section" or "change this to a checklist", it refers to the selected section(s), not to the complete proposal.';
				$parts[] = 'If the selected section template changes, keep other section templates unchanged unless consistency clearly requires a small adjustment.';
				$parts[] = 'If the feedback asks to split, merge, reorder or restructure selected sections, do that and return the complete revised proposal.';
			} else {
				$parts[] = 'No section is selected. Apply the feedback to the whole proposal only if necessary.';
			}
		}

		return implode("\n", $parts);
	}

	protected function encodePromptJson(array $data): string {
		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $json !== false ? $json : '{}';
	}

	protected function getOutputSchema(): array {
		return [
			'type' => 'object',
			'required' => ['title', 'summary', 'sections'],
			'properties' => [
				'title' => ['type' => 'string'],
				'summary' => ['type' => 'string'],
				'language' => ['type' => 'string'],
				'generatorTemplate' => ['type' => 'string'],
				'sections' => [
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'required' => ['title', 'body'],
						'properties' => [
							'id' => ['type' => 'string'],
							'template' => ['type' => 'string'],
							'title' => ['type' => 'string'],
							'body' => ['type' => 'string'],
							'items' => [
								'type' => 'array',
								'items' => ['type' => 'string']
							],
							'footer' => ['type' => 'string'],
							'note' => ['type' => 'string'],
							'image' => ['type' => 'object'],
							'interaction' => [
								'type' => 'object',
								'properties' => [
									'type' => ['type' => 'string'],
									'prompt' => ['type' => 'string']
								]
							]
						]
					]
				]
			]
		];
	}

	protected function normalizeAiContent(array $content, string $requestedTitle, string $materialText, int $sectionCount, array $language, array $feedbackContext = [], string $template = 'micro_learning'): array {
		$fallbackSections = $this->buildSections($materialText, $sectionCount, $language, $template);
		$sections = [];

		foreach (($content['sections'] ?? []) as $section) {
			if (!is_array($section)) {
				continue;
			}

			$interaction = is_array($section['interaction'] ?? null) ? $section['interaction'] : [];
			$normalizedSection = [
				'id' => $this->normalizeSectionId((string) ($section['id'] ?? ''), count($sections)),
				'template' => $this->normalizeSectionTemplate((string) ($section['template'] ?? $template)),
				'title' => trim((string) ($section['title'] ?? $this->getDefaultSectionTitle(count($sections), $language, $template))),
				'body' => trim((string) ($section['body'] ?? '')),
				'interaction' => [
					'type' => $this->normalizeInteractionType((string) ($interaction['type'] ?? 'reading')),
					'prompt' => trim((string) ($interaction['prompt'] ?? ''))
				]
			];

			$normalizedSection = array_merge($normalizedSection, $this->normalizeOptionalSectionFields($section));
			$sections[] = $normalizedSection;
		}

		if ($sections === []) {
			$sections = $fallbackSections;
		}

		$title = trim((string) ($content['title'] ?? ''));
		if ($title === '') {
			$title = $requestedTitle !== '' ? $requestedTitle : $this->deriveTitleFromMaterial($materialText, $language);
		}

		$result = [
			'generatorType' => 'html_micro_module',
			'generatorTemplate' => $this->normalizeTemplate((string) ($content['generatorTemplate'] ?? $template)),
			'language' => $this->normalizeLanguageCode((string) ($content['language'] ?? ''), $language),
			'title' => $title,
			'summary' => trim((string) ($content['summary'] ?? '')) !== '' ? trim((string) $content['summary']) : $this->summarize($materialText),
			'sections' => $sections,
			'exportHints' => [
				'preferredExporter' => 'contentforgehtmlpackageexporter',
				'scormReadyLater' => true
			]
		];

		return $result;
	}


	protected function normalizeOptionalSectionFields(array $section): array {
		$result = [];

		foreach (['items', 'footer', 'note', 'image'] as $key) {
			if (!array_key_exists($key, $section)) {
				continue;
			}

			$value = $section[$key];

			if (is_array($value)) {
				$result[$key] = $value;
				continue;
			}

			if (is_scalar($value) || $value === null) {
				$result[$key] = trim((string) $value);
			}
		}

		return $result;
	}

	protected function buildReviewData(int $changeRound, string $feedback, array $feedbackContext, string $generator, ?ContentForgeAiResult $aiResult): array {
		return [
			'status' => 'draft',
			'version' => $changeRound + 1,
			'latestFeedbackApplied' => $feedback !== '',
			'latestFeedback' => $feedback,
			'feedbackContext' => $feedbackContext,
			'generator' => $generator,
			'appliedWithoutAi' => $feedback !== '' && $generator !== 'ai',
			'ai' => $aiResult ? [
				'ok' => $aiResult->ok,
				'provider' => $aiResult->provider,
				'model' => $aiResult->model,
				'usedFallback' => $aiResult->usedFallback,
				'errors' => $aiResult->errors,
				'warnings' => $aiResult->warnings,
				'metadata' => $aiResult->metadata
			] : [
				'ok' => false,
				'provider' => '',
				'model' => '',
				'usedFallback' => true,
				'errors' => [],
				'warnings' => [],
				'metadata' => []
			]
		];
	}

	protected function buildSections(string $text, int $sectionCount, array $language, string $template = 'micro_learning'): array {
		$sentences = preg_split('/(?<=[.!?])\s+/', trim($text)) ?: [];
		$sentences = array_values(array_filter(array_map('trim', $sentences)));

		if ($sentences === []) {
			$sentences = [$text];
		}

		$sections = [];

		for ($i = 0; $i < $sectionCount; $i++) {
			$sentence = $sentences[$i % count($sentences)] ?? $text;
			$sections[] = [
				'id' => 'section_' . ($i + 1),
				'template' => $this->normalizeSectionTemplate($template),
				'title' => $this->getDefaultSectionTitle($i, $language, $template),
				'body' => $this->getDefaultSectionBody($sentence, $i, $language, $template),
				'interaction' => [
					'type' => $this->getDefaultInteractionType($i, $sectionCount, $template),
					'prompt' => $this->getDefaultInteractionPrompt($i, $sectionCount, $language, $template)
				]
			];
		}

		return $sections;
	}

	protected function parseFeedbackContext(string $feedback): array {
		$result = [
			'selectedSectionIndex' => null,
			'selectedSectionIndexes' => [],
			'selectedSectionTitle' => '',
			'selectedSectionTitles' => [],
			'selectedSection' => [],
			'selectedSections' => [],
			'currentProposal' => [],
			'userFeedback' => trim($feedback)
		];

		if ($feedback === '') {
			return $result;
		}

		if (preg_match('/^SelectedSectionIndexes:\s*([0-9,\s]+)/mi', $feedback, $match)) {
			$indexes = [];

			foreach (explode(',', $match[1]) as $value) {
				$value = trim($value);

				if ($value !== '' && ctype_digit($value)) {
					$indexes[] = (int) $value;
				}
			}

			$indexes = array_values(array_unique(array_filter($indexes, fn(int $value) => $value >= 0)));
			sort($indexes);
			$result['selectedSectionIndexes'] = $indexes;
		}

		if ($result['selectedSectionIndexes'] === [] && preg_match('/^SelectedSectionIndex:\s*(\d+)/mi', $feedback, $match)) {
			$result['selectedSectionIndexes'] = [(int) $match[1]];
		}

		if ($result['selectedSectionIndexes'] !== []) {
			$result['selectedSectionIndex'] = $result['selectedSectionIndexes'][0];
		}

		$selectedTitles = $this->extractFeedbackBlock($feedback, 'SelectedSectionTitles', ['SelectedSectionsJson', 'SelectedSectionJson', 'CurrentProposalJson', 'UserFeedback']);
		if ($selectedTitles !== '') {
			$result['selectedSectionTitles'] = array_values(array_filter(array_map('trim', preg_split('/\R+/', $selectedTitles) ?: []), fn(string $title) => $title !== ''));
		}

		if ($result['selectedSectionTitles'] === [] && preg_match('/^SelectedSectionTitle:\s*(.+)$/mi', $feedback, $match)) {
			$result['selectedSectionTitles'] = [trim($match[1])];
		}

		if ($result['selectedSectionTitles'] !== []) {
			$result['selectedSectionTitle'] = $result['selectedSectionTitles'][0];
		}

		$selectedSectionsJson = $this->extractFeedbackBlock($feedback, 'SelectedSectionsJson', ['CurrentProposalJson', 'UserFeedback']);
		if ($selectedSectionsJson !== '') {
			$decoded = json_decode($selectedSectionsJson, true);
			$result['selectedSections'] = is_array($decoded) ? $decoded : [];
		}

		$selectedSectionJson = $this->extractFeedbackBlock($feedback, 'SelectedSectionJson', ['CurrentProposalJson', 'UserFeedback']);
		if ($selectedSectionJson !== '') {
			$decoded = json_decode($selectedSectionJson, true);
			$result['selectedSection'] = is_array($decoded) ? $decoded : [];

			if ($result['selectedSections'] === [] && $result['selectedSection'] !== []) {
				$result['selectedSections'] = [$result['selectedSection']];
			}
		}

		if ($result['selectedSection'] === [] && isset($result['selectedSections'][0]) && is_array($result['selectedSections'][0])) {
			$result['selectedSection'] = $result['selectedSections'][0];
		}

		$currentProposalJson = $this->extractFeedbackBlock($feedback, 'CurrentProposalJson', ['UserFeedback']);
		if ($currentProposalJson !== '') {
			$decoded = json_decode($currentProposalJson, true);
			$result['currentProposal'] = is_array($decoded) ? $decoded : [];
		}

		$userFeedback = $this->extractFeedbackBlock($feedback, 'UserFeedback', []);
		if ($userFeedback !== '') {
			$result['userFeedback'] = trim($userFeedback);
		}

		if ($result['selectedSectionIndex'] === null && preg_match('/Selected proposal part:\s*(.*?)\s+#(\d+)/i', $feedback, $match)) {
			$result['selectedSectionTitle'] = trim($match[1]);
			$result['selectedSectionTitles'] = [$result['selectedSectionTitle']];
			$result['selectedSectionIndex'] = (int) $match[2];
			$result['selectedSectionIndexes'] = [$result['selectedSectionIndex']];
		}

		return $result;
	}

	protected function extractFeedbackBlock(string $text, string $label, array $nextLabels): string {
		$pattern = '/^' . preg_quote($label, '/') . ':\s*\R?/mi';

		if (!preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
			return '';
		}

		$start = $match[0][1] + strlen($match[0][0]);
		$end = strlen($text);

		foreach ($nextLabels as $nextLabel) {
			$nextPattern = '/^' . preg_quote($nextLabel, '/') . ':\s*$/mi';

			if (preg_match($nextPattern, $text, $nextMatch, PREG_OFFSET_CAPTURE, $start)) {
				$end = min($end, $nextMatch[0][1]);
			}
		}

		return trim(substr($text, $start, $end - $start));
	}

	protected function applyFeedbackMarker(array $sections, array $feedbackContext): array {
		$feedback = trim((string) ($feedbackContext['userFeedback'] ?? ''));

		if ($feedback === '') {
			return $sections;
		}

		$indexes = is_array($feedbackContext['selectedSectionIndexes'] ?? null) ? $feedbackContext['selectedSectionIndexes'] : [];
		if ($indexes === [] && is_int($feedbackContext['selectedSectionIndex'] ?? null)) {
			$indexes = [$feedbackContext['selectedSectionIndex']];
		}

		if ($indexes !== []) {
			foreach ($indexes as $index) {
				if (isset($sections[$index])) {
					$sections[$index]['changeRequest'] = [
						'scope' => 'section',
						'feedback' => $feedback,
						'appliedWithoutAi' => true
					];
				}
			}

			return $sections;
		}

		foreach ($sections as $i => $section) {
			$sections[$i]['changeRequest'] = [
				'scope' => 'proposal',
				'feedback' => $feedback,
				'appliedWithoutAi' => true
			];
		}

		return $sections;
	}

	/**
	 * Counts change-request decisions without mutating the readonly context arrays.
	 */
	protected function countChangeRequests(array $decisions): int {
		return count(array_filter($decisions, fn(ContentForgeDecision $decision) => $decision->type === 'request_changes'));
	}

	protected function normalizeRequestedTitle(string $title): string {
		$title = trim($title);
		$generic = ['html micro module', 'micro module', 'contentforge'];

		return in_array(strtolower($title), $generic, true) ? '' : $title;
	}

	protected function detectLanguage(string $materialText, string $feedback): array {
		$text = strtolower($materialText . ' ' . $feedback);
		$germanScore = 0;

		foreach ([' der ', ' die ', ' das ', ' und ', ' ist ', ' ein ', ' eine ', ' mit ', ' fuer ', ' nicht ', ' soll ', ' user ', ' aender'] as $needle) {
			if (str_contains(' ' . $text . ' ', $needle)) {
				$germanScore++;
			}
		}

		if (preg_match('/[\x{00C4}\x{00D6}\x{00DC}\x{00E4}\x{00F6}\x{00FC}\x{00DF}]/u', $materialText . ' ' . $feedback)) {
			$germanScore += 3;
		}

		if ($germanScore >= 2) {
			return [
				'code' => 'de',
				'name' => 'German'
			];
		}

		return [
			'code' => 'en',
			'name' => 'English'
		];
	}

	protected function normalizeLanguageCode(string $code, array $fallback): string {
		$code = strtolower(trim($code));

		if (str_starts_with($code, 'de')) {
			return 'de';
		}

		if (str_starts_with($code, 'en')) {
			return 'en';
		}

		return (string) ($fallback['code'] ?? 'en');
	}

	protected function deriveTitleFromMaterial(string $text, array $language): string {
		$text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

		if ($text === '') {
			return $language['code'] === 'de' ? 'Kurzes Inhaltsmodul' : 'Short Content Module';
		}

		$firstSentence = preg_split('/(?<=[.!?])\s+/', $text)[0] ?? $text;
		$firstSentence = trim((string) $firstSentence);

		if (strlen($firstSentence) > 80) {
			$firstSentence = rtrim(substr($firstSentence, 0, 77), ' .,;:-') . '...';
		}

		return $firstSentence !== '' ? $firstSentence : ($language['code'] === 'de' ? 'Kurzes Inhaltsmodul' : 'Short Content Module');
	}

	protected function getDefaultSectionTitle(int $index, array $language, string $template = 'micro_learning'): string {
		return match ($template) {
			'checklist' => $language['code'] === 'de' ? 'Punkt ' . ($index + 1) : 'Item ' . ($index + 1),
			'faq' => $language['code'] === 'de' ? 'Frage ' . ($index + 1) : 'Question ' . ($index + 1),
			default => $language['code'] === 'de' ? 'Abschnitt ' . ($index + 1) : 'Section ' . ($index + 1)
		};
	}

	protected function getDefaultSectionBody(string $sentence, int $index, array $language, string $template): string {
		$sentence = trim($sentence);

		if ($template === 'checklist') {
			return $language['code'] === 'de'
				? 'Pruefen oder bearbeiten Sie diesen Punkt: ' . $sentence
				: 'Check or handle this point: ' . $sentence;
		}

		if ($template === 'faq') {
			return $sentence;
		}

		return $sentence;
	}

	protected function getDefaultInteractionType(int $index, int $sectionCount, string $template): string {
		return match ($template) {
			'faq' => 'question',
			'checklist' => 'reading',
			default => $index === $sectionCount - 1 ? 'reflection' : 'reading'
		};
	}

	protected function getDefaultInteractionPrompt(int $index, int $sectionCount, array $language, string $template): string {
		return match ($template) {
			'checklist' => $language['code'] === 'de' ? 'Welche Punkte sind bereits erledigt?' : 'Which items are already done?',
			'faq' => $language['code'] === 'de' ? 'Welche Anschlussfrage bleibt offen?' : 'Which follow-up question remains open?',
			default => $index === $sectionCount - 1 ? $this->getDefaultReflectionPrompt($language) : ''
		};
	}

	protected function getDefaultReflectionPrompt(array $language): string {
		return $language['code'] === 'de'
			? 'Was soll aus diesem Modul in Erinnerung bleiben?'
			: 'What should the reader remember from this module?';
	}

	protected function normalizeSectionId(string $id, int $index): string {
		$id = strtolower(trim($id));
		$id = preg_replace('/[^a-z0-9_-]+/', '_', $id) ?? '';
		$id = trim($id, '_-');

		return $id !== '' ? $id : 'section_' . ($index + 1);
	}

	protected function normalizeInteractionType(string $type): string {
		$type = strtolower(trim($type));

		return in_array($type, ['reading', 'reflection', 'question'], true) ? $type : 'reading';
	}

	protected function summarize(string $text): string {
		$text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

		if (strlen($text) <= 220) {
			return $text;
		}

		return substr($text, 0, 217) . '...';
	}

	protected function logDebug(string $message, array $context = []): void {
		if ($this->logger === null) {
			return;
		}

		try {
			$this->logger->log('contentforge', '[debug] ' . $message . $this->formatLogContext($context));
		} catch (\Throwable) {}
	}

	protected function formatLogContext(array $context): string {
		if ($context === []) {
			return '';
		}

		$json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $json !== false ? ' context=' . $json : '';
	}
}
