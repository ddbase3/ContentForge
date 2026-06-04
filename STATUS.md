# ContentForge Status

Version: 0.1.26

## Current state

ContentForge has a working step-widget flow for multi-material input, proposal review, AI-assisted revision, manual editing, section operations and selectable HTML/SCORM/PDF export. Mixed section templates are backed by a registry service and provide JSON Schema definitions plus preview metadata for editing, validation and rendering.

## Changes in patch 0.1.26

- Fixed `ContentForgeMaterialIntakeService::deleteMaterial()` returning `null` while declaring `bool`.
- User-facing error messages are now more defensive and shorter.
- Detailed technical errors remain available in the `contentforge` log and in debug JSON when enabled.
- Added a download endpoint implemented as BASE3 `IOutput`.
- Default export delivery now returns a download URL generated through `ILinkTargetService`.
- Added `contentforgefilestorageexporttarget` for storage-only export delivery.
- Added `setData()` export configuration for fixed exporter/template and export target integration.
- Added integration documentation in `docs/`.

## Working baseline

- BASE3 DI integration.
- `IRequest`-based request handling.
- SettingsStore-backed Mistral chat provider.
- ContentForge-specific logging scope.
- File-based MVP storage.
- Persistent multi-material intake panel.
- Text and web-link material previews.
- Proposal review loop.
- Template-aware section rendering.
- Schema-backed section edit forms.
- Schema-backed section validation.
- Section add, duplicate, delete, move and drag reorder.
- HTML, SCORM 1.2 and PDF export.
- Download delivery through BASE3 link generation.

## Known limitations

- Web link extraction is a first MVP extractor: it downloads public HTTP/HTTPS pages through the server and strips HTML to text. It is not a full article extraction engine yet.
- Drag and drop is a convenience; button-based reordering remains the reliable fallback.
- Validation intentionally supports a practical JSON Schema subset, not the complete JSON Schema standard.
- No database repository implementation yet.
- SCORM export is a first MVP package and still needs validation against LMS importers.
- PDF export is a first MVP renderer and still needs better typography and pagination.
