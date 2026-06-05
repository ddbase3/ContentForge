# ContentForge Features

Version: 0.1.32

## Current MVP features

- Step-widget UI for artifact generation.
- Two-column layout: workflow on the left, source materials on the right.
- Persistent canonical material panel available during creation and review.
- AJAX save/delete for project materials after project creation.
- Multiple text materials.
- Public web-link material extraction with AJAX preview.
- AI-assisted proposal generation through SettingsStore-backed LLM services.
- Targeted revision requests for selected sections.
- Manual editing with schema-backed section forms.
- Mixed section templates inside one proposal.
- Template registry based on `IContentForgeSectionTemplate` and `ISchemaProvider`.
- Template-aware preview metadata.
- Section add, duplicate, delete, move and drag reorder.
- HTML package export.
- SCORM 1.2 package export.
- PDF document export.
- DOCX document export.
- PPTX presentation export.
- Cross-plugin exporter discovery via BASE3 `IClassMap`.
- Export-template selection in the review step.
- Fixed export template configuration through `setData()`.
- Interchangeable export targets.
- Default download-link delivery through BASE3 `ILinkTargetService`.
- Storage-only export delivery target.
- ContentForge-scoped logging.

## Section templates

- Micro-learning card.
- Information section.
- Checklist section.
- FAQ section.

## Planned next feature areas

- Host-specific export targets for direct object creation.
- File upload material type.
- PDF/DOCX/PPTX extraction previews.
- Material enable/disable state.
- Database repositories.
- Full SCORM validation.
- Better PDF typography and pagination.
