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

namespace ContentForge\Content;

/**
 * Compatibility alias for older integrations.
 *
 * New integrations should prefer ContentForgeStepWidgetDisplay. Keeping this
 * technical name avoids breaking existing BASE3 placements that already
 * reference contentforgeworkbenchdisplay.
 */
class ContentForgeWorkbenchDisplay extends ContentForgeStepWidgetDisplay {

	public static function getName(): string {
		return 'contentforgeworkbenchdisplay';
	}
}
