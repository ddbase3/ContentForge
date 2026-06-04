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

namespace ContentForge\Renderer;

use ContentForge\Api\IContentForgeArtifactRenderer;
use ContentForge\Model\ContentForgeArtifactRevision;
use ContentForge\Model\ContentForgeProposal;

class ContentForgeHtmlMicroModuleRenderer implements IContentForgeArtifactRenderer {

	public static function getName(): string {
		return 'contentforgehtmlmicromodulerenderer';
	}

	public function supports(string $type): bool {
		return $type === 'html_micro_module';
	}

	public function renderProposal(ContentForgeProposal $proposal): string {
		return $this->renderContent($proposal->content);
	}

	public function renderRevision(ContentForgeArtifactRevision $revision): string {
		return $this->renderContent($revision->content);
	}

	protected function renderContent(array $content): string {
		$html = '<article class="contentforge-micro-module">';
		$html .= '<h2>' . $this->escape((string) ($content['title'] ?? 'Micro Module')) . '</h2>';
		$html .= '<p class="contentforge-summary">' . $this->escape((string) ($content['summary'] ?? '')) . '</p>';


		foreach (($content['sections'] ?? []) as $section) {
			if (!is_array($section)) {
				continue;
			}

			$sectionTemplate = $this->normalizeSectionTemplate((string) ($section['template'] ?? $content['generatorTemplate'] ?? 'micro_learning'));
			$html .= '<section class="contentforge-section contentforge-section--' . $this->escape($sectionTemplate) . '">';
			$html .= '<h3>' . $this->escape((string) ($section['title'] ?? 'Section')) . '</h3>';
			$html .= '<p>' . nl2br($this->escape((string) ($section['body'] ?? ''))) . '</p>';
			$html .= $this->renderOptionalSectionFields($section);

			$interaction = is_array($section['interaction'] ?? null) ? $section['interaction'] : [];
			if (($interaction['prompt'] ?? '') !== '') {
				$html .= '<aside class="contentforge-interaction">' . $this->escape((string) $interaction['prompt']) . '</aside>';
			}

			$changeRequest = is_array($section['changeRequest'] ?? null) ? $section['changeRequest'] : [];
			if (($changeRequest['feedback'] ?? '') !== '') {
				$html .= '<aside class="contentforge-change-request">Change request: ' . $this->escape((string) $changeRequest['feedback']) . '</aside>';
			}

			$html .= '</section>';
		}

		$html .= '</article>';

		return $html;
	}


	protected function renderOptionalSectionFields(array $section): string {
		$html = '';

		if (is_array($section['items'] ?? null) && $section['items'] !== []) {
			$html .= '<ul class="contentforge-checklist">';

			foreach ($section['items'] as $item) {
				$html .= '<li>' . $this->escape(is_scalar($item) ? (string) $item : (string) ($item['text'] ?? '')) . '</li>';
			}

			$html .= '</ul>';
		}

		foreach (['footer', 'note'] as $key) {
			if (($section[$key] ?? '') !== '') {
				$html .= '<p class="contentforge-section-extra">' . $this->escape((string) $section[$key]) . '</p>';
			}
		}

		return $html;
	}

	protected function normalizeSectionTemplate(string $template): string {
		$template = strtolower(trim($template));

		return in_array($template, ['micro_learning', 'short_overview', 'checklist', 'faq'], true) ? $template : 'micro_learning';
	}

	protected function escape(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
