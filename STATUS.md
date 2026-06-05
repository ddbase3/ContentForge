# ContentForge Status

Version: 0.1.32

## Current state

ContentForge is a working BASE3 MVP for human-in-the-loop content generation. It supports multi-material input, live web-link extraction, AI-assisted review loops, manual section editing, section operations, section templates and multiple export formats.

## Changes in patch 0.1.32

- Rebuilt the PPTX package structure for stricter Microsoft PowerPoint compatibility.
- Added missing OpenXML package parts for PPTX exports: core properties, app properties, presentation properties, view properties, table styles and slide layout relationships.
- Linked slide layout, slide master and theme parts through explicit relationships.
- Completed the theme formatting scheme instead of using an empty `<a:fmtScheme>` block.
- Kept the presentation model simple and predictable: one title slide plus one slide per generated ContentForge section.

## Known limitations

- Download delivery is the default target, but host-specific placement targets must be implemented in the host/plugin that owns the placement logic.
- PDF export is still an MVP output and not a full layout engine.
- DOCX/PPTX exporters are MVP OpenXML outputs for interoperability tests, not full design/layout engines.
- SCORM export is an MVP SCORM 1.2 package intended for import tests.

## 0.1.32

- PPTX exports now include stricter PowerPoint-compatible OpenXML package structure.
- The exporter continues to create a title slide and one slide per section.

## 0.1.31

- `ContentForgeExportResult::$files` is no longer readonly, because external export targets often use `reset($result->files)` or similar PHP array-pointer helpers.
- Error logging now has a file fallback at `ContentForge/var/log/contentforge.log` if the BASE3 logger is unavailable or fails.
- DOCX/PPTX direct-file exports remain single-file results; metadata stays in `meta`, not in `files`.
