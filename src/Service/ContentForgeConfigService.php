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

namespace ContentForge\Service;

use Base3\Configuration\Api\IConfiguration;
use ContentForge\Api\IContentForgeConfigService;

class ContentForgeConfigService implements IContentForgeConfigService {

	public function __construct(private readonly IConfiguration $configuration) {}

	public static function getName(): string {
		return 'contentforgeconfigservice';
	}

	public function getString(string $key, string $default = ''): string {
		return $this->configuration->getString('contentforge', $key, $default);
	}

	public function getArray(string $key, array $default = []): array {
		return $this->configuration->getArray('contentforge', $key, $default);
	}
}
