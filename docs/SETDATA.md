# ContentForge Display setData Reference

Version: 0.1.26

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
