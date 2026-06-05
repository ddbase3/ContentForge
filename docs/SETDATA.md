# ContentForge Display setData Reference

Version: 0.1.28

`ContentForgeStepWidgetDisplay::setData()` configures the embedded widget.

## Common keys

```text
service                  Technical service name. Default: contentforgeworkbenchservice

default_project_title    Initial title shown in Step 1.
default_material         Optional starter text material for demos.
generator_type           Current generator type. Default: html_micro_module
show_debug               Show technical JSON debug data.
```

## Export keys

```text
export_template          Optional fixed export template.
export_template_locked   Hide export selector and force export_template.
export_target            Export delivery target name.
export_target_config     Integration metadata passed to the export target.
```

Supported export templates in the MVP:

```text
html_package
scorm12
pdf_document
docx_document
pptx_presentation
```

Default target:

```text
contentforgedownloadexporttarget
```

Storage-only target:

```text
contentforgefilestorageexporttarget
```

Host integrations should provide their own export target implementation and pass host-specific metadata through `export_target_config`.

## Export selector behavior

`export_template_locked` controls only the export template selector in the review step.

```text
export_template_locked=false   selector remains visible
export_template_locked=true    selector is hidden and export_template is forced
```

`export_target` is not shown to the end user in the MVP. It is an integration setting for delivery or placement of the generated package.

The target and custom exporter names must match the BASE3 technical-name convention: lowercase class name as returned by `getName()`.
