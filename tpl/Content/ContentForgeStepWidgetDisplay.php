<?php
	$rootId = uniqid('contentforgeStepWidget_', false);
	$showStatus = !empty($this->_['show_status']);
	$showDebug = !empty($this->_['show_debug']);
	$assetVersion = '028';
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

<div id="<?php echo $rootId; ?>" class="contentforge-step-widget" role="region" aria-label="ContentForge Step Widget">
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
			<section class="cf-screen is-active" data-cf-screen="material" aria-label="Enter material">
				<p class="cf-kicker">Step 1</p>
				<h2>What should be created?</h2>
				<p class="cf-muted">A short input is enough. ContentForge will then show only the next proposal.</p>

				<label for="<?php echo $rootId; ?>_title">Title</label>
				<input id="<?php echo $rootId; ?>_title" data-cf-field="title" type="text" value="<?php echo htmlspecialchars((string) $this->_['default_project_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />

				<input type="hidden" data-cf-field="generator" value="html_micro_module" />

				<label for="<?php echo $rootId; ?>_template">Template</label>
				<select id="<?php echo $rootId; ?>_template" data-cf-field="template">
					<option value="micro_learning">Micro-learning</option>
					<option value="short_overview">Short overview</option>
					<option value="checklist">Checklist</option>
					<option value="faq">FAQ</option>
				</select>
				<p class="cf-muted">Choose the format of the proposal. The source material can still be in any language.</p>

				<label for="<?php echo $rootId; ?>_target_count">Target size</label>
				<select id="<?php echo $rootId; ?>_target_count" data-cf-field="targetSectionCount">
					<option value="auto">Automatic</option>
					<option value="3">Compact: 3 parts</option>
					<option value="5">Standard: 5 parts</option>
					<option value="8">Detailed: 8 parts</option>
				</select>

				<p class="cf-muted">Add or update source materials in the panel on the right. The same material control remains available while you review and revise the proposal.</p>

				<div class="cf-actions">
					<button type="button" data-contentforge-action="start-widget">Create proposal</button>
				</div>
			</section>

			<section class="cf-screen" data-cf-screen="proposal" aria-label="Review proposal">
				<p class="cf-kicker">Step 2</p>
				<h2 data-cf-proposal-title>Review proposal</h2>
				<p class="cf-muted" data-cf-proposal-summary></p>

				<div class="cf-review-notice" data-cf-review-notice hidden></div>
				<div class="cf-choice-list" data-cf-proposal-sections></div>
				<p class="cf-selection-summary" data-cf-selection-summary>No sections selected.</p>

				<div class="cf-section-tools cf-section-tools-inline" aria-label="Add section">
					<label for="<?php echo $rootId; ?>_new_section_template">New section</label>
					<select id="<?php echo $rootId; ?>_new_section_template" data-cf-field="newSectionTemplate">
						<option value="micro_learning">Micro-learning card</option>
						<option value="short_overview">Information section</option>
						<option value="checklist">Checklist section</option>
						<option value="faq">FAQ section</option>
					</select>
					<button type="button" class="secondary" data-contentforge-action="add-section">Add section</button>
				</div>

<?php if (!empty($this->_['export_template_locked']) && !empty($this->_['export_template'])) { ?>
				<input type="hidden" data-cf-field="exportTemplate" value="<?php echo htmlspecialchars((string) $this->_['export_template'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
<?php } else { ?>
				<div class="cf-export-tools" aria-label="Export options">
					<label for="<?php echo $rootId; ?>_export_template">Export as</label>
					<select id="<?php echo $rootId; ?>_export_template" data-cf-field="exportTemplate">
						<option value="html_package"<?php echo ($this->_['export_template'] ?? '') === 'html_package' ? ' selected' : ''; ?>>HTML package</option>
						<option value="scorm12"<?php echo ($this->_['export_template'] ?? '') === 'scorm12' ? ' selected' : ''; ?>>SCORM 1.2 package</option>
						<option value="pdf_document"<?php echo ($this->_['export_template'] ?? '') === 'pdf_document' ? ' selected' : ''; ?>>PDF document</option>
						<option value="docx_document"<?php echo ($this->_['export_template'] ?? '') === 'docx_document' ? ' selected' : ''; ?>>DOCX document</option>
						<option value="pptx_presentation"<?php echo ($this->_['export_template'] ?? '') === 'pptx_presentation' ? ' selected' : ''; ?>>PPTX presentation</option>
					</select>
				</div>
<?php } ?>

				<div class="cf-actions cf-review-actions">
					<button type="button" data-contentforge-action="accept-widget">Accept</button>
					<button type="button" class="secondary" data-contentforge-action="open-feedback">Request changes</button>
					<button type="button" class="secondary" data-contentforge-action="open-edit" data-cf-selection-optional="1">Edit</button>
					<button type="button" class="secondary" data-contentforge-action="duplicate-section" data-cf-selection-required="1">Duplicate</button>
					<button type="button" class="secondary" data-contentforge-action="delete-section" data-cf-selection-required="1">Delete</button>
					<button type="button" class="secondary" data-contentforge-action="move-section-up" data-cf-selection-required="1">Up</button>
					<button type="button" class="secondary" data-contentforge-action="move-section-down" data-cf-selection-required="1">Down</button>
				</div>
			</section>

			<section class="cf-screen" data-cf-screen="feedback" aria-label="Describe changes">
				<p class="cf-kicker">Step 2a</p>
				<h2>What should change?</h2>
				<p class="cf-muted" data-cf-selected-label>No sections selected. The change applies to the whole proposal.</p>
				<div data-cf-feedback-context></div>

				<label for="<?php echo $rootId; ?>_feedback">Change request</label>
				<textarea id="<?php echo $rootId; ?>_feedback" data-cf-field="feedback" placeholder="For example: shorter, different order, more practical examples, make section 2 more concrete..."></textarea>

				<div class="cf-actions">
					<button type="button" data-contentforge-action="send-feedback">Create new version</button>
					<button type="button" class="secondary" data-contentforge-action="back-to-proposal">Back</button>
				</div>
			</section>

			<section class="cf-screen" data-cf-screen="edit" aria-label="Edit proposal directly">
				<p class="cf-kicker">Step 2b</p>
				<h2>Edit proposal directly</h2>
				<p class="cf-muted" data-cf-edit-intro>Apply manual edits and review the updated proposal before accepting it.</p>

				<div data-cf-edit-document-fields>
					<label for="<?php echo $rootId; ?>_edit_title">Title</label>
					<input id="<?php echo $rootId; ?>_edit_title" data-cf-field="editTitle" type="text" value="" />

					<label for="<?php echo $rootId; ?>_edit_summary">Summary</label>
					<textarea id="<?php echo $rootId; ?>_edit_summary" data-cf-field="editSummary"></textarea>
				</div>

				<div class="cf-edit-sections" data-cf-edit-sections></div>

				<div class="cf-actions">
					<button type="button" data-contentforge-action="accept-edit">Apply edits</button>
					<button type="button" class="secondary" data-contentforge-action="back-to-proposal">Back</button>
				</div>
			</section>

			<section class="cf-screen" data-cf-screen="done" aria-label="Result">
				<p class="cf-kicker">Step 3</p>
				<h2>Result created</h2>
				<p class="cf-muted">The proposal was accepted as an artifact revision. The first export step was executed.</p>
				<div class="cf-result" data-cf-result></div>
				<div class="cf-actions">
					<button type="button" data-contentforge-action="new-widget">Start new run</button>
				</div>
			</section>
		</main>

		<aside class="cf-side cf-material-panel" aria-label="Source materials">
			<h3>Source materials</h3>
			<p class="cf-muted">Add text or web links at any time. Web links are downloaded and converted to text before they are used.</p>
			<div class="cf-material-list" data-cf-material-list>
				<div class="cf-material-item" data-cf-material-item>
					<div class="cf-material-row">
						<select data-cf-material-type aria-label="Material type">
							<option value="text">Text</option>
							<option value="web_url">Web link</option>
						</select>
						<input data-cf-material-name type="text" value="Main material" aria-label="Material name" />
						<button type="button" class="secondary" data-contentforge-action="remove-material">Remove</button>
					</div>
					<div class="cf-material-text" data-cf-material-text-wrap>
						<textarea data-cf-material-content aria-label="Text material"><?php echo htmlspecialchars((string) $this->_['default_material'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
					</div>
					<div class="cf-material-url" data-cf-material-url-wrap hidden>
						<input data-cf-material-url type="url" placeholder="https://example.org/article" aria-label="Web link URL" />
					</div>
					<details class="cf-material-input-preview" data-cf-material-input-preview hidden>
						<summary data-cf-material-input-preview-summary>Preview</summary>
						<pre data-cf-material-input-preview-text></pre>
					</details>
				</div>
			</div>
			<div class="cf-actions cf-material-actions">
				<button type="button" class="secondary" data-contentforge-action="add-material">Add material</button>
			</div>
<?php if ($showDebug) { ?>
			<details class="cf-debug">
				<summary>Technical status</summary>
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
