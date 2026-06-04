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

namespace ContentForge\Parser;

use ContentForge\Api\IContentForgeMaterialParser;
use ContentForge\Model\ContentForgeArtifactRevision;
use ContentForge\Model\ContentForgeMaterial;
use ContentForge\Model\ContentForgeProject;
use ContentForge\Model\ContentForgeWorkflowContext;

class ContentForgePlainTextParser implements IContentForgeMaterialParser {

	public static function getName(): string {
		return 'contentforgeplaintextparser';
	}

	public function supports(ContentForgeMaterial $material): bool {
		return $material->mimeType === 'text/plain';
	}

	public function parse(ContentForgeMaterial $material, ContentForgeWorkflowContext $context): array {
		$artifactId = ContentForgeProject::newId('artifact');

		return [
			new ContentForgeArtifactRevision(
				ContentForgeProject::newId('revision'),
				$artifactId,
				$material->projectId,
				'text',
				[
					'title' => $material->name,
					'text' => $material->content
				],
				'extracted',
				'material_parser',
				'',
				'',
				gmdate('c')
			)
		];
	}
}
