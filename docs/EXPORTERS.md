# ContentForge Exporters

Version: 0.1.32

Exporters create technical files from an accepted ContentForge artifact. Export targets decide where those files are delivered.

## Built-in exporters

```text
contentforgehtmlpackageexporter        HTML package
contentforgescorm12exporter           SCORM 1.2 package
contentforgepdfdocumentexporter        PDF document
contentforgedocxdocumentexporter       DOCX document
contentforgepptxpresentationexporter   PPTX presentation
```

## Discovery

`ContentForgeExporterRegistry` checks local ContentForge exporters first. If a requested exporter name is not local, it asks BASE3 `IClassMap`:

```php
$classMap->getInstanceByInterfaceName(
	IContentForgeExporter::class,
	$contentForgeExporterName
);
```

The registry also uses `getInstancesByInterface(IContentForgeExporter::class)` for capability listing. This allows customer-specific plugins to provide their own exporters without changing ContentForge.

## Naming convention

`getName()` should return the lowercase class name.

Example:

```php
class ContentForgeCustomerPdfExporter implements IContentForgeExporter {
	public static function getName(): string {
		return 'contentforgecustomerpdfexporter';
	}
}
```

If such an exporter appears in a BASE3 plugin scanned by `IClassMap`, ContentForge can list and select it. For unknown custom exporters, the technical exporter name is also used as the export template/type key unless a later metadata interface defines a richer mapping.

## Current metadata limitation

`IContentForgeExporter` is intentionally still small. Built-in exporters provide friendly labels through the registry. Custom exporters are currently listed with a generated label derived from their technical name. A future metadata interface can add explicit labels, icons, supported artifact types and configuration schemas.

## Direct document exports

PDF, DOCX and PPTX exporters now return exactly one user-facing file in `ContentForgeExportResult::files`: `document.pdf`, `document.docx` or `presentation.pptx`. Supporting metadata is stored in `ContentForgeExportResult::meta`, not as an additional `manifest.json` file. This is important because generic or external export targets often treat `count($result->files) > 1` as a package and correctly wrap multiple files in a ZIP archive.


## Single-file document export contract

PDF, DOCX and PPTX exporters must return exactly one file in `ContentForgeExportResult::files`. Any manifest or diagnostic metadata belongs in `ContentForgeExportResult::meta`, otherwise generic export targets will correctly treat the result as a package and may wrap it as ZIP.

`ContentForgeExportResult::$files` is intentionally not readonly. This keeps extension targets compatible with normal PHP array helpers such as `array_key_first()` and `reset()` when they need to extract the single final file.
