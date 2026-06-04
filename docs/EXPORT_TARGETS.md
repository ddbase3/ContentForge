# ContentForge Export Targets

Version: 0.1.26

ContentForge separates two concerns:

1. Exporters create technical files from an accepted artifact.
2. Export targets deliver or place those files.

Examples:

```text
Exporter: HTML package, SCORM 1.2 package, PDF document
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
