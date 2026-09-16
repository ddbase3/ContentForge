# ContentForge FAQ

## What is ContentForge?

ContentForge is a BASE3 plugin for human-in-the-loop artifact production. It collects source material, creates structured content proposals, supports review and revision, and exports accepted content into selectable output formats.

The current workflow is centered on projects, materials, workflow instances, proposals, decisions, artifact revisions, exporters, and export targets.

## Is ContentForge a generic component?

Yes. ContentForge itself is host-neutral. Host-specific placement or delivery can be added through export targets discovered by the BASE3 class map.

## What is the current workflow?

The main widget supports this flow:

1. Add one or more source materials.
2. Select a proposal template and target size.
3. Generate a proposal.
4. Review the generated sections.
5. Request targeted changes, edit content manually, or reorganize sections.
6. Select an export format and export target.
7. Explicitly accept the result.
8. Deliver the generated export.

Export happens after explicit acceptance. Editing and review actions create revised proposal state before final delivery.

## Which material types are currently supported?

The current material intake supports:

- `text` for pasted or typed text
- `web_url` for public HTTP or HTTPS pages

File upload material types are not implemented in the current component.

## What happens when a web URL is added as material?

ContentForge downloads the page on the server, removes common non-content HTML elements, converts the remaining page to plain text, and stores that extracted text as project material.

The current implementation:

- accepts only HTTP and HTTPS URLs
- rejects localhost and private or reserved hosts during initial URL validation
- follows up to three redirects
- limits the downloaded body to approximately 1 MiB
- uses a 20 second request timeout
- records source metadata such as URL, title, MIME type, HTTP status, extracted length, and fetch time

The web server being requested can see the network address of the ContentForge server and the `ContentForge/0.1` user agent.

## Where does ContentForge store its working data?

The current built-in storage service is file-based. It stores JSON collections under:

```text
ContentForge/var/data
```

The current runtime uses collections for data such as:

- projects
- materials
- workflow instances
- step runs
- proposals
- decisions
- artifacts
- artifact revisions
- delivered export records

Generated export files are normally written under:

```text
ContentForge/var/exports
```

An export target can also be configured to use another path.

## Does ContentForge require an AI service?

No. The current content-generation step can fall back to deterministic generation when an AI service is unavailable. During a revision, if AI generation fails and a current proposal exists, the existing proposal can be retained instead of being replaced by a failed result.

When AI generation is enabled and configured, ContentForge can use a supported provider to produce structured proposal content.

## Which AI provider is included?

The component currently includes a Mistral chat provider and a dummy provider. The Mistral path is selected through BASE3 settings records, normally using a `service-llm` service definition and a referenced `connection` definition.

The concrete endpoint, model, credentials, and provider operation depend on the runtime configuration.

## What data is sent to the configured AI service?

For the built-in structured generation workflow, the prompt can include:

- the requested title
- source material text
- the current proposal during revisions
- selected section content and titles
- user feedback
- language and template information
- requested section count
- generation instructions and output schema guidance

The current Mistral provider sends the system prompt and user prompt to the configured `/v1/chat/completions` endpoint with the configured model and generation options.

Operators should therefore assume that source materials, review content, and user feedback can leave the local system when an external AI endpoint is configured.

## Are AI credentials stored by ContentForge?

ContentForge reads connection settings through the BASE3 Settings Store. For the built-in Mistral provider, a bearer secret is read from the selected connection and sent in the HTTP `Authorization` header.

The ContentForge JSON storage service does not write that bearer secret into its own project collections. Storage and protection of the underlying settings record are responsibilities of the configured BASE3 settings backend.

## Are AI prompts or raw provider responses persisted?

The normal success path persists the generated structured proposal, provider name, model name, usage metadata, review metadata, and related workflow state. It does not persist the full outgoing prompt as a separate prompt log.

Provider error metadata can contain a short response preview or raw-text preview. Because AI result metadata is included in review metadata, such diagnostic previews can become part of a stored proposal when generation fails.

## Which section templates are included?

The current component includes these section templates:

- `micro_learning`
- `short_overview`
- `checklist`
- `faq`

A proposal can contain mixed section templates.

## Which export formats are included?

The current exporter registry includes:

- HTML package
- SCORM 1.2 package
- PDF document
- DOCX document
- PPTX presentation

Additional exporters can be provided by other BASE3 plugins through the ContentForge exporter contract and class-map discovery.

## What is the difference between an exporter and an export target?

An exporter creates the file or package content. An export target decides where that result is delivered.

The built-in targets are:

- `contentforgedownloadexporttarget`, which stores the export and returns a download link
- `contentforgefilestorageexporttarget`, which stores the export and returns its filesystem location

Other integrations can provide additional targets without changing ContentForge itself.

## How do ContentForge download links work?

The default download target stores a delivered-export record with a generated ID and creates a link to `contentforgeexportdownloadservice` through the BASE3 link target service.

The download service validates the export ID and restricts file delivery to the normal ContentForge export directory. It sets download headers and returns the generated file content.

The download service does not implement its own user authentication or authorization check. Access to the routable service must therefore be controlled by the host runtime when generated exports are not intended to be public.

## Does ContentForge manage users or permissions?

No. ContentForge does not implement its own identity, authentication, or authorization subsystem. It expects the surrounding application to decide who may open the widget, call the workbench service, retrieve project data, or download exports.

## What does ContentForge log?

ContentForge uses the `contentforge` logger scope for operational messages. Normal AI logs identify the selected service, provider, model, status, and errors rather than logging full prompts.

Workbench errors can include diagnostic information such as request keys, proposal context, material names or source URLs, exception details, storage paths, and short captured PHP output. If the BASE3 logger is unavailable or fails, the workbench service can append an error record to:

```text
ContentForge/var/log/contentforge.log
```

Log access and retention should reflect the sensitivity of the processed content.

## Does ContentForge automatically delete old projects or exports?

The current component does not provide a general project-retention job or automatic cleanup of all JSON records and generated export files.

Individual materials can be removed through the workbench flow. Broader retention and deletion rules for projects, proposals, artifacts, logs, delivered-export records, and files must be defined by the operating application.

## Can ContentForge be extended?

Yes. The component exposes interfaces and class-map discovery points for workflow node handlers, material parsers, artifact renderers, exporters, export targets, and section templates.

Known ContentForge services are registered in the BASE3 container, while discoverable extension implementations can be supplied by other plugins.

## Where is privacy information documented?

See [../PRIVACY.md](../PRIVACY.md).
