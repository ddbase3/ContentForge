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

namespace ContentForge\Api;

use Base3\Api\IBase;
use ContentForge\Model\ContentForgeStepResult;
use ContentForge\Model\ContentForgeWorkflowContext;
use ContentForge\Model\ContentForgeWorkflowNode;

interface IContentForgeWorkflowNodeHandler extends IBase {
	public function handle(ContentForgeWorkflowContext $context, ContentForgeWorkflowNode $node): ContentForgeStepResult;
}
