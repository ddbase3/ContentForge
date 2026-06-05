# ContentForge Export Targets

Version: 0.1.32

ContentForge separates two concerns:

1. Exporters create technical files from an accepted artifact.
2. Export targets deliver or place those files.

Examples:

```text
Exporter: HTML package, SCORM 1.2 package, PDF document, DOCX document, PPTX presentation
Target: download link, file storage, host LMS object creation, API delivery
```

## Current targets

### contentforgedownloadexporttarget

Default target. It writes the export package into `ContentForge/var/exports`, registers the delivered export in JSON storage and returns a download URL generated through BASE3 `ILinkTargetService`.

The download URL points to:

```text
contentforgeexportdownloadservice
```

### contentforgefilestorageexporttarget

Compatibility/storage target. It writes the export package to `ContentForge/var/exports` and returns the filesystem path without creating a download URL.

## Why targets are separate

A SCORM exporter should only create a valid SCORM package. It should not know whether the package is downloaded, stored, imported into an LMS tree, uploaded to an API or attached to another object. That is the export target's job.

## Future host targets

A host-specific target can implement `IContentForgeExportTarget` and use `ContentForgeExportRequest::$config['targetConfig']` for integration data.

Possible targets:

- create SCORM object in a host tree
- create file object in a host tree
- store generated PDF in a document area
- push export package to an external API
- attach export package to an existing record

## External export target discovery

Export targets are extension points. ContentForge registers its built-in targets locally, but a target name may also resolve to a class provided by another BASE3 plugin.

Lookup order:

1. locally wired ContentForge targets
2. `IClassMap::getInstanceByInterfaceName(IContentForgeExportTarget::class, $name)`
3. full `IClassMap::getInstancesByInterface(IContentForgeExportTarget::class)` listing for capability introspection

The technical target name follows the BASE3 convention: `getName()` returns the lowercase class name.

Example:

```php
namespace Base3IliasLab\ContentForge;

use ContentForge\Api\IContentForgeExportTarget;

class ContentForgeIliasFileExportTarget implements IContentForgeExportTarget {
	public static function getName(): string {
		return 'contentforgeiliasfileexporttarget';
	}
}
```

A widget integration can then use:

```php
$display->setData([
	'export_template' => 'pdf_document',
	'export_template_locked' => true,
	'export_target' => 'contentforgeiliasfileexporttarget',
	'export_target_config' => [
		'parent_ref_id' => 123
	]
]);
```

## Single-file document delivery

`ContentForgeDownloadExportTarget` and `ContentForgeFileStorageExportTarget` deliver `.pdf`, `.docx` and `.pptx` outputs directly. HTML and SCORM package exports remain ZIP/package-oriented because they consist of multiple runtime files.

