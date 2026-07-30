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

namespace ContentForge;

use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Api\IRequest;
use Base3\Configuration\Api\IConfiguration;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use Base3\Translation\Api\ITranslation;
use ContentForge\Api\IContentForgeAiService;
use ContentForge\Api\IContentForgeArtifactRendererRegistry;
use ContentForge\Api\IContentForgeArtifactService;
use ContentForge\Api\IContentForgeCapabilityService;
use ContentForge\Api\IContentForgeConfigService;
use ContentForge\Api\IContentForgeDecisionService;
use ContentForge\Api\IContentForgeExportService;
use ContentForge\Api\IContentForgeExportTargetRegistry;
use ContentForge\Api\IContentForgeExporterRegistry;
use ContentForge\Api\IContentForgeJsonStorageService;
use ContentForge\Api\IContentForgeMaterialIntakeService;
use ContentForge\Api\IContentForgeMaterialParserRegistry;
use ContentForge\Api\IContentForgeProjectService;
use ContentForge\Api\IContentForgeProposalService;
use ContentForge\Api\IContentForgeSectionTemplateRegistry;
use ContentForge\Api\IContentForgeWorkflowDefinitionService;
use ContentForge\Api\IContentForgeWorkflowNodeHandlerRegistry;
use ContentForge\Api\IContentForgeWorkflowRunnerService;
use ContentForge\AiProvider\ContentForgeDummyAiProvider;
use ContentForge\AiProvider\ContentForgeMistralChatAiProvider;
use ContentForge\Exporter\ContentForgeDocxDocumentExporter;
use ContentForge\Exporter\ContentForgeHtmlPackageExporter;
use ContentForge\Exporter\ContentForgePdfDocumentExporter;
use ContentForge\Exporter\ContentForgePptxPresentationExporter;
use ContentForge\Exporter\ContentForgeScorm12Exporter;
use ContentForge\ExportTarget\ContentForgeDownloadExportTarget;
use ContentForge\ExportTarget\ContentForgeFileStorageExportTarget;
use ContentForge\Parser\ContentForgePlainTextParser;
use ContentForge\Renderer\ContentForgeHtmlMicroModuleRenderer;
use ContentForge\Renderer\ContentForgeJsonArtifactRenderer;
use ContentForge\Service\ContentForgeAiService;
use ContentForge\Service\ContentForgeArtifactRendererRegistry;
use ContentForge\Service\ContentForgeArtifactService;
use ContentForge\Service\ContentForgeCapabilityService;
use ContentForge\Service\ContentForgeConfigService;
use ContentForge\Service\ContentForgeDecisionService;
use ContentForge\Service\ContentForgeExportDownloadService;
use ContentForge\Service\ContentForgeExportService;
use ContentForge\Service\ContentForgeExporterRegistry;
use ContentForge\Service\ContentForgeExportTargetRegistry;
use ContentForge\Service\ContentForgeJsonStorageService;
use ContentForge\Service\ContentForgeMaterialIntakeService;
use ContentForge\Service\ContentForgeMaterialParserRegistry;
use ContentForge\Service\ContentForgeProjectService;
use ContentForge\Service\ContentForgeProposalService;
use ContentForge\Service\ContentForgeSectionTemplateRegistry;
use ContentForge\Service\ContentForgeWorkbenchService;
use ContentForge\Service\ContentForgeWorkflowDefinitionService;
use ContentForge\Service\ContentForgeWorkflowNodeHandlerRegistry;
use ContentForge\Service\ContentForgeWorkflowRunnerService;
use ContentForge\StepHandler\ContentForgeExportStepHandler;
use ContentForge\Template\ContentForgeChecklistSectionTemplate;
use ContentForge\Template\ContentForgeFaqSectionTemplate;
use ContentForge\Template\ContentForgeInformationSectionTemplate;
use ContentForge\Template\ContentForgeMicroLearningSectionTemplate;
use ContentForge\StepHandler\ContentForgeHtmlMicroModuleStepHandler;

class ContentForgePlugin implements IPlugin {

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'contentforgeplugin';
	}

	// Implementation of IPlugin

	public function init() {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED)

			// ContentForge core services. BASE3 host services stay registered by the host system.
			->set(IContentForgeConfigService::class, fn($c) => new ContentForgeConfigService(
				$c->get(IConfiguration::class)
			), IContainer::SHARED)

			->set(IContentForgeJsonStorageService::class, fn($c) => new ContentForgeJsonStorageService(
				$c->get(ILogger::class)
			), IContainer::SHARED)

			->set(IContentForgeAiService::class, fn($c) => new ContentForgeAiService(
				$c->get(ISettingsStore::class),
				[
					new ContentForgeMistralChatAiProvider(),
					new ContentForgeDummyAiProvider()
				],
				$c->get(ILogger::class)
			), IContainer::SHARED)

			->set(IContentForgeProjectService::class, fn($c) => new ContentForgeProjectService(
				$c->get(IContentForgeJsonStorageService::class)
			), IContainer::SHARED)

			->set(IContentForgeWorkflowDefinitionService::class, fn($c) => new ContentForgeWorkflowDefinitionService(), IContainer::SHARED)

			->set(IContentForgeWorkflowNodeHandlerRegistry::class, fn($c) => new ContentForgeWorkflowNodeHandlerRegistry(
				$c->get(IClassMap::class),
				[
					new ContentForgeHtmlMicroModuleStepHandler(
						$c->get(IContentForgeAiService::class),
						$c->get(ILogger::class)
					),
					new ContentForgeExportStepHandler(
						$c->get(IContentForgeExportService::class)
					)
				]
			), IContainer::SHARED)

			->set(IContentForgeMaterialParserRegistry::class, fn($c) => new ContentForgeMaterialParserRegistry(
				$c->get(IClassMap::class),
				[
					new ContentForgePlainTextParser()
				]
			), IContainer::SHARED)

			->set(IContentForgeSectionTemplateRegistry::class, fn($c) => new ContentForgeSectionTemplateRegistry(
				$c->get(IClassMap::class),
				$c->get(ITranslation::class),
				[
					new ContentForgeMicroLearningSectionTemplate(),
					new ContentForgeInformationSectionTemplate(),
					new ContentForgeChecklistSectionTemplate(),
					new ContentForgeFaqSectionTemplate()
				]
			), IContainer::SHARED)

			->set(IContentForgeArtifactRendererRegistry::class, fn($c) => new ContentForgeArtifactRendererRegistry(
				$c->get(IClassMap::class),
				[
					new ContentForgeHtmlMicroModuleRenderer(),
					new ContentForgeJsonArtifactRenderer()
				]
			), IContainer::SHARED)

			->set(IContentForgeExporterRegistry::class, fn($c) => new ContentForgeExporterRegistry(
				$c->get(IClassMap::class),
				[
					new ContentForgeHtmlPackageExporter(),
					new ContentForgeScorm12Exporter(),
					new ContentForgePdfDocumentExporter(),
					new ContentForgeDocxDocumentExporter(),
					new ContentForgePptxPresentationExporter()
				]
			), IContainer::SHARED)

			->set(IContentForgeExportTargetRegistry::class, fn($c) => new ContentForgeExportTargetRegistry(
				$c->get(IClassMap::class),
				[
					new ContentForgeDownloadExportTarget(
						$c->get(ILinkTargetService::class),
						$c->get(IContentForgeJsonStorageService::class)
					),
					new ContentForgeFileStorageExportTarget()
				]
			), IContainer::SHARED)

			->set(IContentForgeMaterialIntakeService::class, fn($c) => new ContentForgeMaterialIntakeService(
				$c->get(IContentForgeJsonStorageService::class)
			), IContainer::SHARED)

			->set(IContentForgeProposalService::class, fn($c) => new ContentForgeProposalService(
				$c->get(IContentForgeJsonStorageService::class)
			), IContainer::SHARED)

			->set(IContentForgeArtifactService::class, fn($c) => new ContentForgeArtifactService(
				$c->get(IContentForgeJsonStorageService::class)
			), IContainer::SHARED)

			->set(IContentForgeDecisionService::class, fn($c) => new ContentForgeDecisionService(
				$c->get(IContentForgeJsonStorageService::class),
				$c->get(IContentForgeProposalService::class),
				$c->get(IContentForgeArtifactService::class)
			), IContainer::SHARED)

			->set(IContentForgeWorkflowRunnerService::class, fn($c) => new ContentForgeWorkflowRunnerService(
				$c->get(IContentForgeJsonStorageService::class),
				$c->get(IContentForgeProjectService::class),
				$c->get(IContentForgeWorkflowDefinitionService::class),
				$c->get(IContentForgeWorkflowNodeHandlerRegistry::class),
				$c->get(IContentForgeMaterialIntakeService::class),
				$c->get(IContentForgeProposalService::class),
				$c->get(IContentForgeDecisionService::class),
				$c->get(IContentForgeArtifactService::class)
			), IContainer::SHARED)

			->set(IContentForgeExportService::class, fn($c) => new ContentForgeExportService(
				$c->get(IContentForgeExporterRegistry::class),
				$c->get(IContentForgeExportTargetRegistry::class)
			), IContainer::SHARED)

			->set(IContentForgeCapabilityService::class, fn($c) => new ContentForgeCapabilityService(
				$c->get(IContentForgeWorkflowDefinitionService::class),
				$c->get(IContentForgeWorkflowNodeHandlerRegistry::class),
				$c->get(IContentForgeMaterialParserRegistry::class),
				$c->get(IContentForgeArtifactRendererRegistry::class),
				$c->get(IContentForgeExporterRegistry::class),
				$c->get(IContentForgeExportTargetRegistry::class),
				$c->get(IContentForgeSectionTemplateRegistry::class)
			), IContainer::SHARED)


			// Routable download endpoint generated through ILinkTargetService.
			->set(ContentForgeExportDownloadService::getName(), fn($c) => new ContentForgeExportDownloadService(
				$c->get(IContentForgeJsonStorageService::class),
				$c->get(IRequest::class),
				$c->get(ITranslation::class),
				$c->get(ILogger::class)
			), IContainer::SHARED)

			->set(ContentForgeExportDownloadService::class, fn($c) => new ContentForgeExportDownloadService(
				$c->get(IContentForgeJsonStorageService::class),
				$c->get(IRequest::class),
				$c->get(ITranslation::class),
				$c->get(ILogger::class)
			), IContainer::SHARED)

			// Routable service name used by the widget via ILinkTargetService.
			->set(ContentForgeWorkbenchService::getName(), fn($c) => new ContentForgeWorkbenchService(
				$c->get(IContentForgeProjectService::class),
				$c->get(IContentForgeWorkflowDefinitionService::class),
				$c->get(IContentForgeWorkflowRunnerService::class),
				$c->get(IContentForgeMaterialIntakeService::class),
				$c->get(IContentForgeProposalService::class),
				$c->get(IContentForgeCapabilityService::class),
				$c->get(IContentForgeSectionTemplateRegistry::class),
				$c->get(IContentForgeJsonStorageService::class),
				$c->get(IRequest::class),
				$c->get(ITranslation::class),
				$c->get(ILogger::class)
			), IContainer::SHARED)

			->set(ContentForgeWorkbenchService::class, fn($c) => new ContentForgeWorkbenchService(
				$c->get(IContentForgeProjectService::class),
				$c->get(IContentForgeWorkflowDefinitionService::class),
				$c->get(IContentForgeWorkflowRunnerService::class),
				$c->get(IContentForgeMaterialIntakeService::class),
				$c->get(IContentForgeProposalService::class),
				$c->get(IContentForgeCapabilityService::class),
				$c->get(IContentForgeSectionTemplateRegistry::class),
				$c->get(IContentForgeJsonStorageService::class),
				$c->get(IRequest::class),
				$c->get(ITranslation::class),
				$c->get(ILogger::class)
			), IContainer::SHARED);
	}
}
