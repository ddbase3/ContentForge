# ContentForge Privacy and Data Processing

> This document describes the technical data handling implemented by the ContentForge component. It is not a legal privacy notice. Provider-specific, deployment-specific, and organizational requirements must be documented by the operator of the final application.

## Scope

ContentForge is a generic BASE3 component for human-in-the-loop artifact production. It collects source material, maintains project and workflow state, can submit generation requests to a configured AI service, stores review and revision data, and creates export files.

This document covers ContentForge itself. Host-specific export targets, host authentication, the selected BASE3 storage backends, external AI providers, reverse proxies, backups, and surrounding infrastructure have their own data-processing behavior.

## Main processing purposes

ContentForge processes data in order to:

- create and manage content-production projects
- persist source materials
- retrieve and extract public web pages when requested
- generate and revise structured proposals
- record review decisions and manual edits
- maintain workflow and artifact revision state
- create downloadable or stored export files
- provide operational diagnostics and error logging

## Data categories processed by ContentForge

Depending on how the component is used, ContentForge can process personal, confidential, or otherwise sensitive information contained in user-provided material.

The current data model can include:

| Area | Examples |
|---|---|
| Projects | project ID, title, description, arbitrary project data, creation time |
| Materials | material ID, project ID, material name, MIME type, full material content, metadata, creation time |
| Web materials | source URL, page title, MIME type, HTTP status, extracted text length, fetch time, extracted page text |
| Workflow state | workflow IDs, node IDs, status, workflow data, timestamps |
| Step runs | handler names, status, generated proposals, reports, warnings and errors |
| Proposals | title, generated section content, review metadata, AI metadata, status, timestamps |
| Decisions | feedback, edited content, attached material IDs, optional `createdBy`, timestamps |
| Artifacts | accepted artifact identifiers and current revision references |
| Artifact revisions | accepted content, source proposal and decision references, timestamps |
| Delivered exports | export ID, filesystem path, download URL, filename, MIME type, source project ID, exporter and target metadata |
| Diagnostics | request IDs, request key names, selected proposal context, exception metadata, storage paths and captured output |

The component does not require source material to contain personal data, but it does not automatically remove such data from free text or generated artifacts.

## Local JSON persistence

The built-in `ContentForgeJsonStorageService` stores collections as JSON files under:

```text
ContentForge/var/data
```

Current collection names used by the component include:

```text
projects
materials
workflow_instances
step_runs
proposals
decisions
artifacts
artifact_revisions
delivered_exports
```

Writes use filesystem locking for the individual file write. The storage service does not implement encryption, per-record access control, retention periods, or a database permission model.

Filesystem permissions, backup protection, encryption at rest, and access to these JSON files are deployment responsibilities.

## Source materials

### Text material

Text materials are stored with their full content. ContentForge does not apply automatic redaction or pseudonymization before persistence.

### Web-link material

When a user supplies a web URL, ContentForge performs a server-side HTTP request and converts the returned page into plain text.

The current implementation:

- accepts HTTP and HTTPS URLs
- rejects localhost and private or reserved hosts during initial URL validation
- follows redirects
- limits the response body to approximately 1 MiB
- records the source URL, source title, MIME type, HTTP status, extracted length, and fetch timestamp
- stores the extracted text as material content

The remote web server receives a request from the network environment of the ContentForge host and can therefore observe ordinary HTTP request metadata such as source network address and the `ContentForge/0.1` user agent.

Operators should only submit URLs that may legitimately be retrieved by the ContentForge server.

## AI processing

ContentForge can use a configured AI service for structured proposal generation and revision. The currently included Mistral chat provider sends requests to the configured connection endpoint at:

```text
/v1/chat/completions
```

The generated request can contain:

- source material text
- requested title
- current proposal content
- selected sections
- user feedback
- output language
- generator template
- section-count guidance
- system instructions and structured-output guidance

If these values contain personal or confidential information, that information is transmitted to the configured AI endpoint.

### Provider configuration

ContentForge reads AI service configuration from the BASE3 Settings Store. The built-in Mistral provider expects a service definition and a referenced HTTP connection. A bearer secret from the connection settings is sent in the HTTP `Authorization` header.

ContentForge does not copy the bearer secret into its own project JSON collections and does not include it in its normal AI log messages.

The operator must document the actual endpoint, provider, model, processing region, provider retention rules, training usage, subcontractors, and possible international transfers for the selected connection.

### AI result persistence

Successful generation stores the structured proposal and AI metadata such as provider, model, usage metadata, warnings, and errors.

The current provider can place a short remote-response preview into AI error metadata when a response is invalid or unsuccessful. Review metadata includes AI result metadata, so an error preview can become part of a persisted proposal. Such previews should be treated as content data for retention and access-control purposes.

The normal ContentForge logging path does not deliberately log the complete system prompt or user prompt.

## Deterministic generation

AI is not mandatory for every ContentForge run. If no usable AI service is available, the current workflow can produce a deterministic proposal structure. If a revision request fails after a current proposal already exists, the component can preserve the existing proposal.

This local path does not send material to an AI endpoint.

## Review data and manual edits

The human-in-the-loop workflow persists review state. This can include:

- full current proposal content
- selected section content and titles
- user feedback
- manually edited proposal content
- decision type
- revision history and references

Feedback is free text and can contain personal or confidential information. The component does not sanitize feedback for privacy before persistence or AI submission.

## Export files

Built-in exporters can create HTML, SCORM 1.2, PDF, DOCX, and PPTX outputs. Exported files contain the accepted artifact content and can therefore contain the same personal or confidential information as the source material and reviewed proposal.

The normal built-in export directory is:

```text
ContentForge/var/exports
```

An export target can use a configured alternative directory.

The current component does not automatically expire or delete export files after download.

## Delivered export records and download endpoint

The default download target stores a `delivered_exports` record containing information such as:

- generated export ID
- export type
- server-side path
- generated download URL
- filename and MIME type
- creation time
- source project ID
- exporter and target names
- file count

The routable `contentforgeexportdownloadservice` looks up this record by ID and serves the file if its resolved path is inside the normal ContentForge export directory.

The service validates the ID format and path but does not perform its own user identity or authorization check. If generated exports require restricted access, the surrounding application must protect this route at the appropriate access-control boundary.

## Request processing

The workbench service accepts request parameters and JSON bodies through BASE3 `IRequest`. Depending on the action, the request can contain:

- project, proposal, workflow, and material identifiers
- project titles and descriptions
- material names and full text
- source URLs
- review feedback
- selected section indexes and titles
- full edited proposal JSON
- export target configuration

ContentForge does not persist every raw inbound request as a request log. Individual request values become persistent when they are written into projects, materials, decisions, proposals, workflow state, artifacts, or export metadata.

## Diagnostics and logging

ContentForge uses the `contentforge` logger scope. AI status logging normally contains technical identifiers such as service, provider, model, and error messages.

The workbench error path can contain more detailed diagnostics. Depending on the failure, logged or returned diagnostic data can include:

- action name and request ID
- exception class, message, file, line, and abbreviated stack trace
- request context and request key names
- proposal data used for a failing lookup
- material name, material type, or source URL
- storage paths and collection metadata
- up to 4000 characters of unexpected PHP output captured before a JSON response

If the configured BASE3 logger is unavailable or fails, ContentForge can append the diagnostic message to:

```text
ContentForge/var/log/contentforge.log
```

The fallback logger does not implement automatic rotation or deletion. The deployment must define access restrictions and retention for this file and for the configured BASE3 logging backend.

## Authentication and authorization

ContentForge does not implement its own authentication or user-permission model. The current workbench and download services rely on the surrounding BASE3 application to decide who may invoke them.

This is especially important because authorized callers can read project snapshots, create or change materials, submit feedback, accept proposals, generate exports, and retrieve delivered files.

Access control should be enforced by the host application rather than inferred from ContentForge project IDs or export IDs.

## User identity

The generic ContentForge workflow does not require a user identity in order to create its standard project records. The `ContentForgeDecision` model supports an optional `createdBy` value, but the current widget flow creates its normal decisions without populating that field.

Host integrations can introduce identity-related data through component APIs, project metadata, target configuration, or other extensions. Such processing belongs to the integrating component as well as the final deployment documentation.

## Retention and deletion

The current component supports deletion of individual material records through its workbench flow. It does not provide a complete project-purge operation or an automatic retention job for all ContentForge data.

No built-in automatic cleanup was identified for:

- projects
- workflow instances
- step runs
- proposals
- decisions
- artifacts and artifact revisions
- delivered-export records
- generated export files
- fallback log files

A production deployment should define coordinated retention and deletion rules for these stores. Deleting only one collection may leave related records or generated files behind.

## Backups and replicas

ContentForge itself does not control filesystem backups, snapshots, log shipping, or replication. If `var/data`, `var/exports`, or `var/log` are included in infrastructure backups, deletion from the live filesystem does not necessarily remove older backup copies immediately.

## Data minimization guidance

For privacy-sensitive deployments, operators should consider at least the following component boundaries:

- only provide source material required for the artifact being produced
- avoid adding unnecessary personal data to free-text material and feedback
- configure external AI processing only when appropriate for the material
- restrict workbench and download routes to intended users
- protect `var/data`, `var/exports`, and `var/log` from direct public access
- define retention for projects, revisions, exports, logs, and provider-side request data
- review error logging because diagnostic context can contain content-related information

## Deployment-specific documentation

The final application should document at least:

- which AI service and connection are active
- whether the AI endpoint is local or external
- the provider's retention and training policy
- access control for the workbench and download endpoints
- storage location and protection of ContentForge JSON data
- storage location and lifetime of generated exports
- logger backend and log-retention period
- backup and deletion behavior
- any host-specific exporters or export targets that receive ContentForge artifacts
