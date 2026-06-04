# ContentForge Download Endpoint

Version: 0.1.26

The download endpoint is an `IOutput` implementation:

```text
ContentForge\Service\ContentForgeExportDownloadService
```

It is registered by `ContentForgePlugin::init()` under its technical name:

```text
contentforgeexportdownloadservice
```

Export targets must not build URLs manually. The default download target uses BASE3 `ILinkTargetService`:

```php
$linkTargetService->getLink(
	[
		'name' => 'contentforgeexportdownloadservice',
		'out' => 'download'
	],
	[
		'id' => $downloadId
	]
);
```

The endpoint reads the export id through `IRequest`, checks the stored export record, validates that the file is inside `ContentForge/var/exports` and sends download headers when possible.

The endpoint intentionally does not expose arbitrary filesystem paths.
