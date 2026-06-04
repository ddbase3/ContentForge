# ContentForge Documentation

Version: 0.1.26

## Main display

The main UI is `ContentForgeStepWidgetDisplay`. It renders a single-step-at-a-time widget suitable for embedding in BASE3 pages.

`ContentForgeWorkbenchDisplay` remains available as a compatibility alias and delegates to the step widget.

## Workflow

1. Add one or more material entries and choose an initial proposal template.
2. Review generated sections.
3. Select one or more sections when needed.
4. Request changes, edit manually, add sections, duplicate sections, delete sections or reorder sections.
5. Accept the reviewed proposal.
6. Export runs after explicit acceptance.

## Material intake

The first widget step now supports multiple material entries. Each entry has a material type and its own input fields.

Current material types:

- `text`: pasted or typed text.
- `web_url`: public HTTP/HTTPS web link. The service downloads the page, strips HTML and stores the extracted text as normal project material.

The review screen shows integrated materials in expandable preview blocks. These previews are meant for quick inspection, not as a full source viewer.

Security notes for web links:

- only `http` and `https` URLs are accepted
- localhost/private/reserved hosts are rejected
- the downloaded response is size-limited
- the MVP extractor is plain HTML-to-text cleanup, not a full readability engine

## Section operations

The review screen supports these proposal-level operations before acceptance:

- Add a new section from a selected section template.
- Duplicate selected sections.
- Delete selected sections, while keeping at least one section.
- Move selected sections up or down.
- Drag one section to a new position.

All operations create a new proposal version and return to the review screen. They do not immediately export.

## Section template registry

The registry service exposes section template definitions:

```text
ContentForge\Api\IContentForgeSectionTemplateRegistry
ContentForge\Service\ContentForgeSectionTemplateRegistry
```

The registry collects local templates and `IClassMap` templates implementing:

```text
ContentForge\Api\IContentForgeSectionTemplate
```

Each template provides:

- technical key, for example `faq`
- label and description
- default content
- JSON Schema via `ISchemaProvider`
- preview metadata for review-card previews and details

## Section templates

Each section may contain a `template` field:

```json
{
	"template": "faq",
	"title": "What is uncertainty?",
	"body": "Uncertainty means that relevant information is incomplete or unreliable.",
	"interaction": {
		"type": "question",
		"prompt": "Think of an example from your work."
	}
}
```

Current values:

- `micro_learning`
- `short_overview`
- `checklist`
- `faq`

The proposal-level generator template is only the initial default. Mixed modules can contain different section templates.

## Manual edit forms

Manual section edit forms are now generated from section-template schema definitions. Internal fields can be hidden with:

```json
{
	"x-contentforge-hidden": true
}
```

Custom controls can be requested with:

```json
{
	"x-contentforge-control": "textarea"
}
```

Currently supported controls:

- `text`
- `textarea`
- `string_list`
- `template_select`

## Logging

ContentForge uses the legacy logger scope:

```php
$logger->log('contentforge', '[info] Message');
```

Routine storage debug logs are intentionally not emitted on every collection access anymore.

## Section preview metadata

Each section template can define preview behavior with `getPreviewDefinition()`. The widget uses this metadata for compact review previews and expandable details instead of hardcoded template-specific branches.

Supported preview detail item types in the current widget are:

- `paragraph`
- `list`
- `interaction`
- `extra`
- `image_hint`

## Validation subset

The current registry validates a practical subset of JSON Schema: required fields, recursive object properties, array items, `const`, `enum`, `minLength`, `maxLength`, `minItems` and `maxItems`. This is intentionally not a full JSON Schema implementation.


## Persistent material panel

Starting with 0.1.25, the material intake UI is no longer limited to the first screen. The widget uses a two-column layout: the left column contains the current workflow step, while the right column contains the source material panel. This panel remains available while reviewing and revising proposals.

After a project has been created, the material panel is the canonical project material pool. Text materials and extracted web-link materials are saved through AJAX as they are edited. Removing a saved material removes it from the project immediately. Before the first project exists, material rows are local pending input and are persisted together when the first proposal is created.

When the user requests changes in the review step, the widget still sends the current material list as a safety reconciliation before rerunning generation. This is a consistency guard, not a separate material pool.


## Export template selection

The review step stores the selected export template in the accepted artifact content under `exportTemplate` and `export.template`. The export step reads this value and routes to the matching exporter.

Current export templates:

- `html_package` via `ContentForgeHtmlPackageExporter`
- `scorm12` via `ContentForgeScorm12Exporter`
- `pdf_document` via `ContentForgePdfDocumentExporter`

The SCORM and PDF exporters are MVP implementations. SCORM creates a minimal SCORM 1.2 package with `imsmanifest.xml`, `index.html` and a small runtime driver. PDF creates a simple text-based PDF document for first end-to-end validation.


## Export delivery

Exporters and export targets are separate. Exporters create files; targets decide where these files go. The default target creates a download link via BASE3 `ILinkTargetService`. For integration details see `docs/EXPORT_TARGETS.md`, `docs/DOWNLOAD_ENDPOINT.md` and `docs/SETDATA.md`.
