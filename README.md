# ContentForge

Version: 0.1.26

ContentForge is a BASE3 plugin for human-in-the-loop artifact production. It provides a step-widget workflow for collecting source materials, generating a structured proposal, reviewing sections, requesting focused changes, editing manually and exporting the accepted result.

## Current focus

Patch 0.1.26 introduces a cleaner export-delivery layer. Exporters create packages such as HTML, SCORM 1.2 or PDF. Export targets decide where those packages go. The default target now creates a BASE3-generated download link through `ILinkTargetService`.

## Main concepts

- Project
- Material
- Workflow instance
- Proposal
- Decision
- Artifact revision
- Section template
- Exporter
- Export target
- Delivered export

## Current widget flow

1. Add source materials in the right-side material panel.
2. Choose a document template and target size.
3. Create a proposal.
4. Review generated sections.
5. Select sections and request changes, or edit manually.
6. Add, duplicate, delete or reorder sections.
7. Choose an export template unless it is fixed by integration.
8. Accept the proposal and receive a delivered export.

## Material types

- Text
- Web link

The same material panel is intended to support future upload material types such as PDF, DOCX and PPTX.

## Export templates

- HTML package
- SCORM 1.2 package
- PDF document

## Export targets

- `contentforgedownloadexporttarget`: default target; creates a download link through BASE3 `ILinkTargetService`.
- `contentforgefilestorageexporttarget`: writes the export package to `var/exports` without creating a public URL.

More target implementations can be added for host integrations.

## Integration docs

See the `docs/` directory:

- `docs/INTEGRATION.md`
- `docs/SETDATA.md`
- `docs/EXPORT_TARGETS.md`
- `docs/DOWNLOAD_ENDPOINT.md`
