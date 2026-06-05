# ContentForge Integration Guide

Version: 0.1.28

ContentForge is embedded through a BASE3 `IDisplay`. The preferred display is:

```text
ContentForge\Content\ContentForgeStepWidgetDisplay
```

The display is configured through `setData()`. This keeps the widget reusable in normal pages and in host-specific creation flows such as a SCORM creation form or a file-object creation form.

## Minimal embedding

```php
$display->setData([
	'default_project_title' => 'New generated object'
]);
```

## Fixed SCORM creation flow

When the widget is embedded inside a host flow that already knows the desired target, the exporter should be locked. The user should not see an export selector in that case.

```php
$display->setData([
	'export_template' => 'scorm12',
	'export_template_locked' => true,
	'export_target' => 'contentforgedownloadexporttarget'
]);
```

A later host-specific export target can use the same structure, for example:

```php
$display->setData([
	'export_template' => 'scorm12',
	'export_template_locked' => true,
	'export_target' => 'contentforgehostscormexporttarget',
	'export_target_config' => [
		'parentRefId' => 123,
		'ownerUserId' => 456
	]
]);
```

ContentForge core should not need to know what `parentRefId` means. That belongs to the target implementation.

## Fixed file/PDF creation flow

```php
$display->setData([
	'export_template' => 'pdf_document',
	'export_template_locked' => true,
	'export_target' => 'contentforgedownloadexporttarget'
]);
```

A host-specific target can later replace the download target and create a file object directly in the host system.


See also `docs/EXPORTERS.md` for cross-plugin exporter discovery.
