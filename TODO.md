# ContentForge TODO

Version: 0.1.32

## Near term

- [ ] Add host-specific export targets for direct SCORM/file object creation.
- [ ] Add file upload material type using the persistent material panel.
- [ ] Add parser-backed previews for PDF/DOCX/PPTX.
- [ ] Add material-level enable/disable toggles without deleting material records.
- [ ] Improve web article extraction quality beyond plain HTML-to-text cleanup.
- [ ] Add material previews during change-request review in a compact form if needed.

## Runtime and persistence

- [ ] Replace file-based MVP repositories with database-backed repositories.
- [ ] Add explicit project/session loading for continued editing after reload.
- [ ] Add audit-log views for decisions, material changes and export events.

## Export

- [ ] Add full SCORM package validation before download/publish.
- [ ] Improve PDF typography and pagination beyond the MVP plain-text renderer.
- [ ] Improve DOCX/PPTX layout beyond the MVP OpenXML renderers.
- [ ] Add exporter option metadata to `IContentForgeExporter` when the extension API stabilizes.
- [ ] Add a host-specific SCORM creation target.
- [ ] Add a host-specific file-object creation target.
