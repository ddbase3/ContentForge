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

class ContentForgeJsonArtifactRenderer implements IContentForgeArtifactRenderer {

	public static function getName(): string {
		return 'contentforgejsonartifactrenderer';
	}

	public function supports(string $type): bool {
		return in_array($type, ['json', 'html_micro_module', 'export_report', 'quality_report'], true);
	}

	public function renderProposal(ContentForgeProposal $proposal): string {
		return '<pre class="contentforge-json">' . $this->escape(json_encode($proposal->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
	}

	public function renderRevision(ContentForgeArtifactRevision $revision): string {
		return '<pre class="contentforge-json">' . $this->escape(json_encode($revision->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
	}

	protected function escape(string|false $value): string {
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
