<?php
	$rootId = uniqid('contentforgeStepWidget_', false);
	$showStatus = !empty($this->_['show_status']);
	$showDebug = !empty($this->_['show_debug']);
	$assetVersion = '028';
	$translations = is_array($this->_['translations'] ?? null) ? $this->_['translations'] : [];
	$t = static fn(string $key, string $fallback): string => trim((string)($translations[$key] ?? '')) !== ''
		? (string)$translations[$key]
		: $fallback;
	$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<style>
	#<?php echo $rootId; ?> {
		max-width: 88rem;
		margin: 0 auto;
		font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	}

	#<?php echo $rootId; ?> .cf-shell {
		display: grid;
		grid-template-columns: minmax(0, 2fr) minmax(20rem, 26rem);
		gap: 1rem;
		align-items: start;
	}

	#<?php echo $rootId; ?> .cf-main,
	#<?php echo $rootId; ?> .cf-side {
		border: 1px solid #d9e0e8 !important;
		border-radius: 5px !important;
		background: #f8fafc !important;
		box-shadow: none !important;
	}

	#<?php echo $rootId; ?> .cf-screen {
		display: none;
		padding: 1.25rem;
	}

	#<?php echo $rootId; ?> .cf-screen.is-active {
		display: block;
	}

	#<?php echo $rootId; ?> .cf-side {
		padding: 1rem;
		font-size: .92rem;
	}

	#<?php echo $rootId; ?> label {
		display: block;
		font-weight: 600;
		margin: .85rem 0 .35rem;
	}

	#<?php echo $rootId; ?> input,
	#<?php echo $rootId; ?> textarea,
	#<?php echo $rootId; ?> select {
		width: 100%;
		box-sizing: border-box;
		border: 1px solid #ccc;
		border-radius: 5px;
		padding: .7rem .8rem;
		font: inherit;
	}

	#<?php echo $rootId; ?> textarea {
		min-height: 8rem;
		resize: vertical;
	}

	#<?php echo $rootId; ?> button {
		border: 0;
		border-radius: 5px;
		padding: .7rem 1rem;
		cursor: pointer;
		background: #222;
		color: #fff;
		font: inherit;
	}

	#<?php echo $rootId; ?> button.secondary {
		background: #eee;
		color: #222;
	}

	#<?php echo $rootId; ?> .cf-actions {
		display: flex;
		gap: .5rem;
		flex-wrap: wrap;
		margin-top: 1rem;
	}

	#<?php echo $rootId; ?> .cf-muted {
		color: #666;
	}

	@media (max-width: 760px) {
		#<?php echo $rootId; ?> .cf-shell {
			grid-template-columns: 1fr;
		}
	}
</style>

<div id="<?php echo $rootId; ?>" class="contentforge-step-widget" role="region" aria-label="<?php echo $e($t('aria_widget', 'ContentForge Step Widget')); ?>">
	<input type="hidden" data-cf-state="projectId" value="" />
	<input type="hidden" data-cf-state="workflowInstanceId" value="" />
	<input type="hidden" data-cf-state="proposalId" value="" />
	<input type="hidden" data-cf-state="selectedSectionIndex" value="" />
	<input type="hidden" data-cf-state="selectedSectionTitle" value="" />
	<input type="hidden" data-cf-state="selectedSectionIndexes" value="" />
	<input type="hidden" data-cf-state="selectedSectionTitles" value="" />
	<div class="cf-shell">
		<main class="cf-main" aria-live="polite">
			<div class="cf-error" data-cf-error hidden></div>
			<section class="cf-screen is-active" data-cf-screen="material" aria-label="<?php echo $e($t('aria_enter_material', 'Enter material')); ?>">
				<p class="cf-kicker"><?php echo $e($t('step_1', 'Step 1')); ?></p>
				<h2><?php echo $e($t('create_heading', 'What should be created?')); ?></h2>
				<p class="cf-muted"><?php echo $e($t('create_intro', 'A short input is enough. ContentForge will then show only the next proposal.')); ?></p>

				<label for="<?php echo $rootId; ?>_title"><?php echo $e($t('title', 'Title')); ?></label>
				<input id="<?php echo $rootId; ?>_title" data-cf-field="title" type="text" value="<?php echo htmlspecialchars((string) $this->_['default_project_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />

				<input type="hidden" data-cf-field="generator" value="html_micro_module" />

				<label for="<?php echo $rootId; ?>_template"><?php echo $e($t('template', 'Template')); ?></label>
				<select id="<?php echo $rootId; ?>_template" data-cf-field="template">
					<option value="micro_learning"><?php echo $e($t('template_micro_learning', 'Micro-learning')); ?></option>
					<option value="short_overview"><?php echo $e($t('template_short_overview', 'Short overview')); ?></option>
					<option value="checklist"><?php echo $e($t('template_checklist', 'Checklist')); ?></option>
					<option value="faq"><?php echo $e($t('template_faq', 'FAQ')); ?></option>
				</select>
				<p class="cf-muted"><?php echo $e($t('template_help', 'Choose the format of the proposal. The source material can still be in any language.')); ?></p>

				<label for="<?php echo $rootId; ?>_target_count"><?php echo $e($t('target_size', 'Target size')); ?></label>
				<select id="<?php echo $rootId; ?>_target_count" data-cf-field="targetSectionCount">
					<option value="auto"><?php echo $e($t('target_auto', 'Automatic')); ?></option>
					<option value="3"><?php echo $e($t('target_compact', 'Compact: 3 parts')); ?></option>
					<option value="5"><?php echo $e($t('target_standard', 'Standard: 5 parts')); ?></option>
					<option value="8"><?php echo $e($t('target_detailed', 'Detailed: 8 parts')); ?></option>
				</select>

				<p class="cf-muted"><?php echo $e($t('materials_panel_help', 'Add or update source materials in the panel on the right. The same material control remains available while you review and revise the proposal.')); ?></p>

				<div class="cf-actions">
					<button type="button" data-contentforge-action="start-widget"><?php echo $e($t('create_proposal', 'Create proposal')); ?></button>
				</div>
			</section>

			<section class="cf-screen" data-cf-screen="proposal" aria-label="<?php echo $e($t('aria_review_proposal', 'Review proposal')); ?>">
				<p class="cf-kicker"><?php echo $e($t('step_2', 'Step 2')); ?></p>
				<h2 data-cf-proposal-title><?php echo $e($t('review_proposal', 'Review proposal')); ?></h2>
				<p class="cf-muted" data-cf-proposal-summary></p>

				<div class="cf-review-notice" data-cf-review-notice hidden></div>
				<div class="cf-choice-list" data-cf-proposal-sections></div>
				<p class="cf-selection-summary" data-cf-selection-summary><?php echo $e($t('no_sections_selected', 'No sections selected.')); ?></p>

				<div class="cf-section-tools cf-section-tools-inline" aria-label="<?php echo $e($t('aria_add_section', 'Add section')); ?>">
					<label for="<?php echo $rootId; ?>_new_section_template"><?php echo $e($t('new_section', 'New section')); ?></label>
					<select id="<?php echo $rootId; ?>_new_section_template" data-cf-field="newSectionTemplate">
						<option value="micro_learning"><?php echo $e($t('section_micro_learning', 'Micro-learning card')); ?></option>
						<option value="short_overview"><?php echo $e($t('section_information', 'Information section')); ?></option>
						<option value="checklist"><?php echo $e($t('section_checklist', 'Checklist section')); ?></option>
						<option value="faq"><?php echo $e($t('section_faq', 'FAQ section')); ?></option>
					</select>
					<button type="button" class="secondary" data-contentforge-action="add-section"><?php echo $e($t('add_section', 'Add section')); ?></button>
				</div>

<?php if (!empty($this->_['export_template_locked']) && !empty($this->_['export_template'])) { ?>
				<input type="hidden" data-cf-field="exportTemplate" value="<?php echo htmlspecialchars((string) $this->_['export_template'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
<?php } else { ?>
				<div class="cf-export-tools" aria-label="<?php echo $e($t('aria_export_options', 'Export options')); ?>">
					<label for="<?php echo $rootId; ?>_export_template"><?php echo $e($t('export_as', 'Export as')); ?></label>
					<select id="<?php echo $rootId; ?>_export_template" data-cf-field="exportTemplate">
						<option value="html_package"<?php echo ($this->_['export_template'] ?? '') === 'html_package' ? ' selected' : ''; ?>><?php echo $e($t('export_html_package', 'HTML package')); ?></option>
						<option value="scorm12"<?php echo ($this->_['export_template'] ?? '') === 'scorm12' ? ' selected' : ''; ?>><?php echo $e($t('export_scorm12_package', 'SCORM 1.2 package')); ?></option>
						<option value="pdf_document"<?php echo ($this->_['export_template'] ?? '') === 'pdf_document' ? ' selected' : ''; ?>><?php echo $e($t('export_pdf_document', 'PDF document')); ?></option>
						<option value="docx_document"<?php echo ($this->_['export_template'] ?? '') === 'docx_document' ? ' selected' : ''; ?>><?php echo $e($t('export_docx_document', 'DOCX document')); ?></option>
						<option value="pptx_presentation"<?php echo ($this->_['export_template'] ?? '') === 'pptx_presentation' ? ' selected' : ''; ?>><?php echo $e($t('export_pptx_presentation', 'PPTX presentation')); ?></option>
					</select>
				</div>
<?php } ?>

				<div class="cf-actions cf-review-actions">
					<button type="button" data-contentforge-action="accept-widget"><?php echo $e($t('accept', 'Accept')); ?></button>
					<button type="button" class="secondary" data-contentforge-action="open-feedback"><?php echo $e($t('request_changes', 'Request changes')); ?></button>
					<button type="button" class="secondary" data-contentforge-action="open-edit" data-cf-selection-optional="1"><?php echo $e($t('edit', 'Edit')); ?></button>
					<button type="button" class="secondary" data-contentforge-action="duplicate-section" data-cf-selection-required="1"><?php echo $e($t('duplicate', 'Duplicate')); ?></button>
					<button type="button" class="secondary" data-contentforge-action="delete-section" data-cf-selection-required="1"><?php echo $e($t('delete', 'Delete')); ?></button>
					<button type="button" class="secondary" data-contentforge-action="move-section-up" data-cf-selection-required="1"><?php echo $e($t('move_up', 'Up')); ?></button>
					<button type="button" class="secondary" data-contentforge-action="move-section-down" data-cf-selection-required="1"><?php echo $e($t('move_down', 'Down')); ?></button>
				</div>
			</section>

			<section class="cf-screen" data-cf-screen="feedback" aria-label="<?php echo $e($t('aria_describe_changes', 'Describe changes')); ?>">
				<p class="cf-kicker"><?php echo $e($t('step_2a', 'Step 2a')); ?></p>
				<h2><?php echo $e($t('change_heading', 'What should change?')); ?></h2>
				<p class="cf-muted" data-cf-selected-label><?php echo $e($t('no_sections_selected_whole', 'No sections selected. The change applies to the whole proposal.')); ?></p>
				<div data-cf-feedback-context></div>

				<label for="<?php echo $rootId; ?>_feedback"><?php echo $e($t('change_request', 'Change request')); ?></label>
				<textarea id="<?php echo $rootId; ?>_feedback" data-cf-field="feedback" placeholder="<?php echo $e($t('change_placeholder', 'For example: shorter, different order, more practical examples, make section 2 more concrete...')); ?>"></textarea>

				<div class="cf-actions">
					<button type="button" data-contentforge-action="send-feedback"><?php echo $e($t('create_new_version', 'Create new version')); ?></button>
					<button type="button" class="secondary" data-contentforge-action="back-to-proposal"><?php echo $e($t('back', 'Back')); ?></button>
				</div>
			</section>

			<section class="cf-screen" data-cf-screen="edit" aria-label="<?php echo $e($t('aria_edit_proposal', 'Edit proposal directly')); ?>">
				<p class="cf-kicker"><?php echo $e($t('step_2b', 'Step 2b')); ?></p>
				<h2><?php echo $e($t('edit_proposal', 'Edit proposal directly')); ?></h2>
				<p class="cf-muted" data-cf-edit-intro><?php echo $e($t('edit_intro', 'Apply manual edits and review the updated proposal before accepting it.')); ?></p>

				<div data-cf-edit-document-fields>
					<label for="<?php echo $rootId; ?>_edit_title"><?php echo $e($t('title', 'Title')); ?></label>
					<input id="<?php echo $rootId; ?>_edit_title" data-cf-field="editTitle" type="text" value="" />

					<label for="<?php echo $rootId; ?>_edit_summary"><?php echo $e($t('summary', 'Summary')); ?></label>
					<textarea id="<?php echo $rootId; ?>_edit_summary" data-cf-field="editSummary"></textarea>
				</div>

				<div class="cf-edit-sections" data-cf-edit-sections></div>

				<div class="cf-actions">
					<button type="button" data-contentforge-action="accept-edit"><?php echo $e($t('apply_edits', 'Apply edits')); ?></button>
					<button type="button" class="secondary" data-contentforge-action="back-to-proposal"><?php echo $e($t('back', 'Back')); ?></button>
				</div>
			</section>

			<section class="cf-screen" data-cf-screen="done" aria-label="<?php echo $e($t('aria_result', 'Result')); ?>">
				<p class="cf-kicker"><?php echo $e($t('step_3', 'Step 3')); ?></p>
				<h2><?php echo $e($t('result_created', 'Result created')); ?></h2>
				<p class="cf-muted"><?php echo $e($t('result_intro', 'The proposal was accepted as an artifact revision. The first export step was executed.')); ?></p>
				<div class="cf-result" data-cf-result></div>
				<div class="cf-actions">
					<button type="button" data-contentforge-action="new-widget"><?php echo $e($t('start_new_run', 'Start new run')); ?></button>
				</div>
			</section>
		</main>

		<aside class="cf-side cf-material-panel" aria-label="<?php echo $e($t('aria_source_materials', 'Source materials')); ?>">
			<h3><?php echo $e($t('source_materials', 'Source materials')); ?></h3>
			<p class="cf-muted"><?php echo $e($t('source_materials_help', 'Add text or web links at any time. Web links are downloaded and converted to text before they are used.')); ?></p>
			<div class="cf-material-list" data-cf-material-list>
				<div class="cf-material-item" data-cf-material-item>
					<div class="cf-material-row">
						<select data-cf-material-type aria-label="<?php echo $e($t('aria_material_type', 'Material type')); ?>">
							<option value="text"><?php echo $e($t('material_text', 'Text')); ?></option>
							<option value="web_url"><?php echo $e($t('material_web_link', 'Web link')); ?></option>
						</select>
						<input data-cf-material-name type="text" value="<?php echo $e($t('main_material', 'Main material')); ?>" aria-label="<?php echo $e($t('aria_material_name', 'Material name')); ?>" />
						<button type="button" class="secondary" data-contentforge-action="remove-material"><?php echo $e($t('remove', 'Remove')); ?></button>
					</div>
					<div class="cf-material-text" data-cf-material-text-wrap>
						<textarea data-cf-material-content aria-label="<?php echo $e($t('aria_text_material', 'Text material')); ?>"><?php echo htmlspecialchars((string) $this->_['default_material'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
					</div>
					<div class="cf-material-url" data-cf-material-url-wrap hidden>
						<input data-cf-material-url type="url" placeholder="https://example.org/article" aria-label="<?php echo $e($t('aria_web_link_url', 'Web link URL')); ?>" />
					</div>
					<details class="cf-material-input-preview" data-cf-material-input-preview hidden>
						<summary data-cf-material-input-preview-summary><?php echo $e($t('preview', 'Preview')); ?></summary>
						<pre data-cf-material-input-preview-text></pre>
					</details>
				</div>
			</div>
			<div class="cf-actions cf-material-actions">
				<button type="button" class="secondary" data-contentforge-action="add-material"><?php echo $e($t('add_material', 'Add material')); ?></button>
			</div>
<?php if ($showDebug) { ?>
			<details class="cf-debug">
				<summary><?php echo $e($t('technical_status', 'Technical status')); ?></summary>
				<pre data-cf-status-json>{}</pre>
			</details>
<?php } ?>
		</aside>
	</div>
</div>

<script>
function initContentForgeStepWidget_<?php echo str_replace('-', '_', $rootId); ?>() {
	const root = document.getElementById('<?php echo $rootId; ?>');
	if (!root || root.dataset.initialized === '1') return;
	root.dataset.initialized = '1';

	const config = {
		serviceUrl: <?php echo json_encode((string) $this->_['service_url'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
		generatorType: <?php echo json_encode((string) $this->_['generator_type'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
		exportTemplate: <?php echo json_encode((string) ($this->_['export_template'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
		exportTarget: <?php echo json_encode((string) $this->_['export_target'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
		exportTargetConfig: <?php echo json_encode($this->_['export_target_config'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
		showDebug: <?php echo $showDebug ? 'true' : 'false'; ?>,
		sectionTemplates: <?php echo json_encode($this->_['section_templates'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
	};

	function loadScript(src) {
		return new Promise((resolve, reject) => {
			const existing = document.querySelector('script[src="' + src + '"]');
			if (existing) {
				resolve();
				return;
			}

			const script = document.createElement('script');
			script.src = src;
			script.onload = resolve;
			script.onerror = reject;
			document.head.appendChild(script);
		});
	}

	function loadCss(href) {
		if (document.querySelector('link[href="' + href + '"]')) return;
		const link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = href;
		document.head.appendChild(link);
	}

	loadCss('<?php echo $this->_['resolve']('plugin/ContentForge/assets/contentforge/contentforge.css'); ?>?v=<?php echo $assetVersion; ?>');
	loadScript('<?php echo $this->_['resolve']('plugin/ContentForge/assets/contentforge/contentforge.js'); ?>?v=<?php echo $assetVersion; ?>').then(() => {
		window.ContentForgeStepWidget.init(root, config);
	});
}

if (document.readyState !== 'loading') {
	initContentForgeStepWidget_<?php echo str_replace('-', '_', $rootId); ?>();
} else {
	document.addEventListener('DOMContentLoaded', initContentForgeStepWidget_<?php echo str_replace('-', '_', $rootId); ?>);
}

window.addEventListener('contentforge:init', initContentForgeStepWidget_<?php echo str_replace('-', '_', $rootId); ?>);
</script>
