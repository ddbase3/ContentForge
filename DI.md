# ContentForge DI

Version: 0.1.26

## Host services expected from BASE3

The host must provide these base services. They are not registered by ContentForge:

```text
Base3\Api\IContainer
Base3\Api\IClassMap
Base3\Api\IRequest
Base3\Configuration\Api\IConfiguration
Base3\Settings\Api\ISettingsStore
Base3\LinkTarget\Api\ILinkTargetService
Base3\Logger\Api\ILogger
Base3\Api\IMvcView
Base3\Api\IAssetResolver
```

## ContentForge services registered by ContentForgePlugin

`ContentForgePlugin::init()` registers the ContentForge-specific services:

```text
ContentForge\Api\IContentForgeConfigService
ContentForge\Api\IContentForgeJsonStorageService
ContentForge\Api\IContentForgeAiService
ContentForge\Api\IContentForgeProjectService
ContentForge\Api\IContentForgeMaterialIntakeService
ContentForge\Api\IContentForgeWorkflowDefinitionService
ContentForge\Api\IContentForgeWorkflowNodeHandlerRegistry
ContentForge\Api\IContentForgeMaterialParserRegistry
ContentForge\Api\IContentForgeArtifactRendererRegistry
ContentForge\Api\IContentForgeExporterRegistry
ContentForge\Api\IContentForgeExportTargetRegistry
ContentForge\Api\IContentForgeProposalService
ContentForge\Api\IContentForgeArtifactService
ContentForge\Api\IContentForgeDecisionService
ContentForge\Api\IContentForgeWorkflowRunnerService
ContentForge\Api\IContentForgeExportService
ContentForge\Api\IContentForgeCapabilityService
ContentForge\Api\IContentForgeSectionTemplateRegistry
ContentForge\Service\ContentForgeWorkbenchService
contentforgeworkbenchservice
```

## Local runtime implementations

The MVP runtime implementations are wired locally into the ContentForge registries:

```text
ContentForge\StepHandler\ContentForgeHtmlMicroModuleStepHandler
ContentForge\StepHandler\ContentForgeExportStepHandler
ContentForge\Parser\ContentForgePlainTextParser
ContentForge\Renderer\ContentForgeHtmlMicroModuleRenderer
ContentForge\Renderer\ContentForgeJsonArtifactRenderer
ContentForge\Exporter\ContentForgeHtmlPackageExporter
ContentForge\Exporter\ContentForgeScorm12Exporter
ContentForge\Exporter\ContentForgePdfDocumentExporter
ContentForge\ExportTarget\ContentForgeDownloadExportTarget
```

The first AI providers are wired directly into `ContentForgeAiService`:

```text
ContentForge\AiProvider\ContentForgeMistralChatAiProvider
ContentForge\AiProvider\ContentForgeDummyAiProvider
```

The first section templates are wired directly into `ContentForgeSectionTemplateRegistry`:

```text
ContentForge\Template\ContentForgeMicroLearningSectionTemplate
ContentForge\Template\ContentForgeInformationSectionTemplate
ContentForge\Template\ContentForgeChecklistSectionTemplate
ContentForge\Template\ContentForgeFaqSectionTemplate
```

`IClassMap` discovery is still used as an extension mechanism for workflow handlers, parsers, renderers, exporters, export targets and section templates.

## SettingsStore records for Mistral

The real AI path expects:

```text
connection/mistral
service-llm/mistral_default
```

The configured `service-llm/mistral_default` entry should reference `connection=mistral` and use `driver=mistral-chat`.

## Writable paths

The file-based MVP requires:

```text
ContentForge/var/data
ContentForge/var/exports
```

Both paths must be writable by PHP.

## Registry priority note

Locally wired implementations passed into the ContentForge registries take precedence over same-name implementations returned by `IClassMap`. This keeps the MVP runtime predictable because local instances are constructed with the explicit ContentForge dependencies from `ContentForgePlugin::init()`.

## Material intake notes

Web link intake uses PHP HTTP capabilities in `ContentForgeMaterialIntakeService`. It prefers cURL when available and falls back to stream wrappers. No extra BASE3 host service is required for the MVP web link type.

## Routable ContentForge services

```text
contentforgeworkbenchservice
contentforgeexportdownloadservice
```

`contentforgeexportdownloadservice` is used by the default download export target and should be linkable through BASE3 `ILinkTargetService`.
