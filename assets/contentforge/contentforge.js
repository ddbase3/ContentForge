(function(global) {
	function encode(data) {
		const params = new URLSearchParams();

		Object.keys(data).forEach(function(key) {
			params.set(key, data[key] == null ? '' : String(data[key]));
		});

		return params;
	}

	async function request(config, data) {
		const response = await fetch(config.serviceUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: encode(data)
		});

		const text = await response.text();

		try {
			return JSON.parse(text);
		} catch (error) {
			return {
				ok: false,
				error: 'ContentForge service did not return valid JSON.',
				diagnostics: {
					status: response.status,
					responseText: text.substring(0, 2000)
				}
			};
		}
	}

	function field(root, name) {
		return root.querySelector('[data-cf-field="' + name + '"]');
	}

	function setScreen(root, name) {
		root.querySelectorAll('[data-cf-screen]').forEach(function(item) {
			item.classList.toggle('is-active', item.getAttribute('data-cf-screen') === name);
		});

		root.querySelectorAll('[data-cf-step]').forEach(function(item) {
			item.classList.toggle('is-active', item.getAttribute('data-cf-step') === name);
			item.classList.toggle('is-done', stepIndex(item.getAttribute('data-cf-step')) < stepIndex(name));
		});
	}

	function stepIndex(name) {
		return {material: 0, proposal: 1, feedback: 1, edit: 1, done: 2}[name] || 0;
	}

	function setState(root, key, value) {
		root.dataset[key] = value == null ? '' : String(value);

		const input = root.querySelector('[data-cf-state="' + key + '"]');
		if (input) input.value = root.dataset[key] || '';
	}

	function getState(root, key) {
		const value = root.dataset[key] || '';

		if (value !== '') {
			return value;
		}

		const input = root.querySelector('[data-cf-state="' + key + '"]');
		return input ? input.value || '' : '';
	}

	function rememberPayloadState(root, payload) {
		if (!payload) return;

		if (payload.sectionTemplates) {
			rememberTemplateDefinitions(root, payload.sectionTemplates);
		}

		if (payload.project && payload.project.id) {
			setState(root, 'projectId', payload.project.id);
		}

		if (Array.isArray(payload.materials)) {
			root.__contentForgeMaterials = payload.materials;
			syncMaterialRowsWithPayload(root, payload.materials);
		}

		if (payload.workflowInstance && payload.workflowInstance.id) {
			setState(root, 'workflowInstanceId', payload.workflowInstance.id);
		}

		const proposal = firstProposal(payload);

		if (proposal) {
			rememberProposalState(root, proposal);
		}
	}

	function rememberProposalState(root, proposal) {
		if (!proposal) return;

		if (proposal.id) {
			setState(root, 'proposalId', proposal.id);
		}

		if (proposal.projectId && getState(root, 'projectId') === '') {
			setState(root, 'projectId', proposal.projectId);
		}

		if (proposal.workflowInstanceId && getState(root, 'workflowInstanceId') === '') {
			setState(root, 'workflowInstanceId', proposal.workflowInstanceId);
		}
	}

	function setStatus(root, message, payload) {
		const text = root.querySelector('[data-cf-status-text]');
		if (text) text.textContent = message;

		const errorBox = root.querySelector('[data-cf-error]');
		if (errorBox) {
			const isError = payload && payload.ok === false;
			errorBox.hidden = !isError;
			errorBox.textContent = isError ? buildErrorMessage(message, payload) : '';
		}

		const json = root.querySelector('[data-cf-status-json]');
		if (json && payload) json.textContent = JSON.stringify(payload, null, 2);
	}

	function buildErrorMessage(message, payload) {
		const parts = [message || payload.error || 'ContentForge could not complete the request.'];

		if (payload && payload.requestId) {
			parts.push('Request: ' + payload.requestId);
		}

		if (payload && payload.error && payload.error !== message) {
			parts.push(payload.error);
		}

		parts.push('Technical details were written to the contentforge log.');

		return parts.join('\n');
	}

	function setBusy(root, busy) {
		root.classList.toggle('is-busy', busy);
		root.querySelectorAll('button').forEach(function(button) {
			button.disabled = busy;
		});

		if (!busy) {
			updateActionState(root);
		}
	}

	function firstProposal(payload) {
		if (Array.isArray(payload.pendingProposals) && payload.pendingProposals.length > 0) {
			return payload.pendingProposals[0];
		}

		if (payload.proposal) {
			return payload.proposal;
		}

		return null;
	}

	function clone(value) {
		return JSON.parse(JSON.stringify(value || {}));
	}


	function syncMaterialRowsWithPayload(root, materials) {
		const rows = Array.from(root.querySelectorAll('[data-cf-material-item]'));
		materials = Array.isArray(materials) ? materials : [];

		materials.forEach(function(material, index) {
			const row = rows[index];
			if (!row || !material) return;

			row.__contentForgeSyncing = true;

			if (material.id) {
				row.setAttribute('data-cf-material-id', String(material.id));
			}

			const type = material.type === 'web_url' ? 'web_url' : 'text';
			const typeField = row.querySelector('[data-cf-material-type]');
			if (typeField) typeField.value = type;

			const nameField = row.querySelector('[data-cf-material-name]');
			if (nameField && isGenericMaterialName(nameField.value)) {
				nameField.value = material.sourceTitle || material.name || nameField.value || 'Material';
			}

			if (type === 'web_url') {
				const urlField = row.querySelector('[data-cf-material-url]');
				const sourceUrl = material.sourceUrl || (material.meta && material.meta.sourceUrl) || material.url || '';
				if (urlField && sourceUrl !== '') urlField.value = sourceUrl;
				row.__contentForgeParsedMaterial = Object.assign({}, row.__contentForgeParsedMaterial || {}, material, {url: sourceUrl});
				row.__contentForgeParsedUrl = sourceUrl;
			}

			updateMaterialRow(root, row, {});
			row.__contentForgeSyncing = false;
		});
	}

	function setupMaterialRows(root, config) {
		root.querySelectorAll('[data-cf-material-item]').forEach(function(row) {
			updateMaterialRow(root, row, config);
		});

		root.addEventListener('change', function(event) {
			const control = event.target.closest('[data-cf-material-type], [data-cf-material-url], [data-cf-material-name], [data-cf-material-content]');
			if (!control) return;
			const row = control.closest('[data-cf-material-item]');
			if (!row) return;

			if (control.matches('[data-cf-material-type]')) {
				deleteMaterialRowOnServer(root, row, config);
				row.__contentForgeParsedMaterial = null;
				row.__contentForgeParsedUrl = '';
				row.removeAttribute('data-cf-material-id');
				updateMaterialRow(root, row, config);
				return;
			}

			scheduleMaterialPreview(root, row, config);
		});

		root.addEventListener('input', function(event) {
			const control = event.target.closest('[data-cf-material-url], [data-cf-material-name], [data-cf-material-content]');
			if (!control) return;
			const row = control.closest('[data-cf-material-item]');
			if (!row) return;
			scheduleMaterialPreview(root, row, config);
		});
	}

	function updateMaterialRow(root, row, config) {
		const type = row.querySelector('[data-cf-material-type]')?.value || 'text';
		const textWrap = row.querySelector('[data-cf-material-text-wrap]');
		const urlWrap = row.querySelector('[data-cf-material-url-wrap]');

		if (textWrap) textWrap.hidden = type !== 'text';
		if (urlWrap) urlWrap.hidden = type !== 'web_url';

		scheduleMaterialPreview(root, row, config);
	}

	function addMaterialRow(root, type, config) {
		const list = root.querySelector('[data-cf-material-list]');
		if (!list) return;

		const row = document.createElement('div');
		row.className = 'cf-material-item';
		row.setAttribute('data-cf-material-item', '1');
		row.innerHTML = '' +
			'<div class="cf-material-row">' +
				'<select data-cf-material-type aria-label="Material type">' +
					'<option value="text">Text</option>' +
					'<option value="web_url">Web link</option>' +
				'</select>' +
				'<input data-cf-material-name type="text" value="Material" aria-label="Material name" />' +
				'<button type="button" class="secondary" data-contentforge-action="remove-material">Remove</button>' +
			'</div>' +
			'<div class="cf-material-text" data-cf-material-text-wrap><textarea data-cf-material-content aria-label="Text material"></textarea></div>' +
			'<div class="cf-material-url" data-cf-material-url-wrap hidden><input data-cf-material-url type="url" placeholder="https://example.org/article" aria-label="Web link URL" /></div>' +
			'<details class="cf-material-input-preview" data-cf-material-input-preview hidden><summary data-cf-material-input-preview-summary>Preview</summary><pre data-cf-material-input-preview-text></pre></details>';

		list.appendChild(row);
		const typeField = row.querySelector('[data-cf-material-type]');
		if (typeField) typeField.value = type || 'text';
		updateMaterialRow(root, row, config);
	}

	function removeMaterialRow(root, row, config) {
		deleteMaterialRowOnServer(root, row, config);
		const rows = Array.from(root.querySelectorAll('[data-cf-material-item]'));
		if (rows.length <= 1) {
			const name = row.querySelector('[data-cf-material-name]');
			const content = row.querySelector('[data-cf-material-content]');
			const url = row.querySelector('[data-cf-material-url]');
			if (name) name.value = 'Material';
			if (content) content.value = '';
			if (url) url.value = '';
			row.__contentForgeParsedMaterial = null;
			row.__contentForgeParsedUrl = '';
			row.removeAttribute('data-cf-material-id');
			renderMaterialInputPreview(row, null, 'empty');
			return;
		}

		row.remove();
	}

	function scheduleMaterialPreview(root, row, config) {
		clearTimeout(row.__contentForgePreviewTimer);

		const type = row.querySelector('[data-cf-material-type]')?.value || 'text';
		if (type === 'text') {
			row.__contentForgeParsedMaterial = null;
			row.__contentForgeParsedUrl = '';
			renderTextMaterialPreview(row);
			scheduleMaterialSave(root, row, config);
			return;
		}

		const url = (row.querySelector('[data-cf-material-url]')?.value || '').trim();
		if (url === '') {
			row.__contentForgeParsedMaterial = null;
			row.__contentForgeParsedUrl = '';
			renderMaterialInputPreview(row, null, 'empty');
			return;
		}

		if (!/^https?:\/\//i.test(url)) {
			row.__contentForgeParsedMaterial = null;
			row.__contentForgeParsedUrl = '';
			renderMaterialInputPreview(row, {contentPreview: 'Enter a full http or https URL.'}, 'invalid');
			return;
		}

		if (row.__contentForgeParsedMaterial && row.__contentForgeParsedUrl === url) {
			renderMaterialInputPreview(row, row.__contentForgeParsedMaterial, 'ready');
			scheduleMaterialSave(root, row, config);
			return;
		}

		renderMaterialInputPreview(row, {contentPreview: 'Downloading and extracting web content...'}, 'loading');
		row.__contentForgePreviewTimer = setTimeout(function() {
			processMaterialPreview(root, row, config);
		}, 650);
	}

	function renderTextMaterialPreview(row) {
		const content = (row.querySelector('[data-cf-material-content]')?.value || '').trim();
		if (content === '') {
			renderMaterialInputPreview(row, null, 'empty');
			return;
		}

		renderMaterialInputPreview(row, {
			name: row.querySelector('[data-cf-material-name]')?.value || 'Text material',
			type: 'text',
			content: content,
			contentPreview: content.substring(0, 1200),
			contentLength: content.length,
			meta: {materialType: 'text'}
		}, 'ready', false);
	}

	async function processMaterialPreview(root, row, config) {
		const url = (row.querySelector('[data-cf-material-url]')?.value || '').trim();
		const name = row.querySelector('[data-cf-material-name]')?.value || 'Web link';

		if (url === '') return;

		try {
			const payload = await request(config, {
				action: 'preview_material',
				materialType: 'web_url',
				name: name,
				url: url
			});

			if (!payload.ok) {
				row.__contentForgeParsedMaterial = null;
				row.__contentForgeParsedUrl = '';
				renderMaterialInputPreview(row, {contentPreview: payload.error || 'Material preview failed.'}, 'error');
				setStatus(root, payload.error || 'Material preview failed.', payload);
				return;
			}

			row.__contentForgeParsedMaterial = payload.material || null;
			row.__contentForgeParsedUrl = url;
			renderMaterialInputPreview(row, payload.material || null, 'ready', true);
			await saveMaterialRow(root, row, config, payload.material || null);
			setStatus(root, 'Material preview ready.', payload);
		} catch (error) {
			row.__contentForgeParsedMaterial = null;
			row.__contentForgeParsedUrl = '';
			renderMaterialInputPreview(row, {contentPreview: 'Material preview failed: ' + error.message}, 'error');
			setStatus(root, 'Material preview failed: ' + error.message, {ok: false, error: error.message});
		}
	}


	async function ensureMaterialPreviews(root, config) {
		const rows = Array.from(root.querySelectorAll('[data-cf-material-item]'));

		for (const row of rows) {
			const type = row.querySelector('[data-cf-material-type]')?.value || 'text';
			if (type !== 'web_url') continue;

			const url = (row.querySelector('[data-cf-material-url]')?.value || '').trim();
			if (url === '' || !/^https?:\/\//i.test(url)) continue;

			if (row.__contentForgeParsedMaterial && row.__contentForgeParsedUrl === url) continue;

			clearTimeout(row.__contentForgePreviewTimer);
			renderMaterialInputPreview(row, {contentPreview: 'Downloading and extracting web content...'}, 'loading', true);
			await processMaterialPreview(root, row, config);
		}
	}

	function renderMaterialInputPreview(row, material, state, open) {
		const preview = row.querySelector('[data-cf-material-input-preview]');
		const summary = row.querySelector('[data-cf-material-input-preview-summary]');
		const output = row.querySelector('[data-cf-material-input-preview-text]');
		if (!preview || !summary || !output) return;

		preview.classList.remove('is-loading', 'is-error', 'is-ready', 'is-invalid');
		preview.classList.add('is-' + (state || 'ready'));

		if (!material && state === 'empty') {
			preview.hidden = true;
			preview.open = false;
			output.textContent = '';
			summary.textContent = 'Preview';
			return;
		}

		const name = material && material.name ? material.name : (row.querySelector('[data-cf-material-name]')?.value || 'Material');
		const label = state === 'loading' ? 'Loading preview' : state === 'error' ? 'Preview failed' : state === 'invalid' ? 'Preview unavailable' : 'Preview';
		const source = material && material.meta && material.meta.sourceTitle ? material.meta.sourceTitle : name;
		const text = material && (material.contentPreview || material.content) ? String(material.contentPreview || material.content) : '';

		preview.hidden = false;
		summary.textContent = label + (source ? ': ' + source : '');
		output.textContent = text;

		if (open !== undefined) {
			preview.open = !!open;
		}
	}

	function isGenericMaterialName(name) {
		name = String(name || '').trim().toLowerCase();
		return name === '' || ['material', 'main material', 'web link', 'text material'].indexOf(name) >= 0;
	}

	function collectMaterialInputFromRow(row) {
		const type = row.querySelector('[data-cf-material-type]')?.value || 'text';
		const name = row.querySelector('[data-cf-material-name]')?.value || '';
		const id = row.getAttribute('data-cf-material-id') || '';

		if (type === 'web_url') {
			const url = (row.querySelector('[data-cf-material-url]')?.value || '').trim();
			if (url === '') return null;

			if (row.__contentForgeParsedMaterial && row.__contentForgeParsedUrl === url) {
				const parsed = row.__contentForgeParsedMaterial;
				const effectiveName = isGenericMaterialName(name) ? parsed.name || name || 'Web link' : name;
				return {
					id: id,
					type: 'web_url',
					name: effectiveName,
					url: parsed.url || parsed.sourceUrl || url,
					content: parsed.content || '',
					meta: parsed.meta || {}
				};
			}

			return {id: id, type: 'web_url', name: name, url: url};
		}

		const content = (row.querySelector('[data-cf-material-content]')?.value || '').trim();
		if (content === '') return null;

		return {id: id, type: 'text', name: name, content: content, meta: {materialType: 'text'}};
	}

	function collectMaterialInputs(root) {
		const result = [];

		root.querySelectorAll('[data-cf-material-item]').forEach(function(row) {
			const item = collectMaterialInputFromRow(row);
			if (item) result.push(item);
		});

		return result;
	}

	function scheduleMaterialSave(root, row, config) {
		if (row.__contentForgeSyncing) return;
		if (getState(root, 'projectId') === '') return;

		clearTimeout(row.__contentForgeSaveTimer);
		row.__contentForgeSaveTimer = setTimeout(function() {
			saveMaterialRow(root, row, config);
		}, 900);
	}

	async function saveMaterialRow(root, row, config, preparedMaterial) {
		if (row.__contentForgeSyncing) return null;
		const projectId = getState(root, 'projectId');
		if (projectId === '') return null;

		const item = collectMaterialInputFromRow(row);
		if (!item) return null;

		if (preparedMaterial && item.type === 'web_url') {
			item.content = preparedMaterial.content || item.content || '';
			item.meta = preparedMaterial.meta || item.meta || {};
			item.name = isGenericMaterialName(item.name) ? preparedMaterial.name || item.name : item.name;
			item.url = preparedMaterial.url || preparedMaterial.sourceUrl || item.url || '';
		}

		try {
			const payload = await request(config, {
				action: 'save_material',
				projectId: projectId,
				materialId: item.id || '',
				materialType: item.type,
				name: item.name || 'Material',
				url: item.url || '',
				content: item.content || '',
				metaJson: JSON.stringify(item.meta || {})
			});

			if (!payload.ok) {
				setStatus(root, payload.error || 'Material could not be saved.', payload);
				return null;
			}

			if (payload.material && payload.material.id) {
				row.setAttribute('data-cf-material-id', payload.material.id);
				if (item.type === 'web_url') {
					row.__contentForgeParsedMaterial = Object.assign({}, row.__contentForgeParsedMaterial || {}, payload.material, {url: payload.material.sourceUrl || item.url || ''});
					row.__contentForgeParsedUrl = payload.material.sourceUrl || item.url || '';
				}
			}

			if (Array.isArray(payload.materials)) {
				root.__contentForgeMaterials = payload.materials;
				renderMaterialResults(root, payload.materials);
			}

			return payload.material || null;
		} catch (error) {
			setStatus(root, 'Material could not be saved: ' + error.message, {ok: false, error: error.message});
			return null;
		}
	}

	async function deleteMaterialRowOnServer(root, row, config) {
		const projectId = getState(root, 'projectId');
		const materialId = row.getAttribute('data-cf-material-id') || '';
		if (projectId === '' || materialId === '') return;

		try {
			const payload = await request(config, {
				action: 'delete_material',
				projectId: projectId,
				materialId: materialId
			});

			if (payload.ok && Array.isArray(payload.materials)) {
				root.__contentForgeMaterials = payload.materials;
				renderMaterialResults(root, payload.materials);
			} else if (!payload.ok) {
				setStatus(root, payload.error || 'Material could not be deleted.', payload);
			}
		} catch (error) {
			setStatus(root, 'Material could not be deleted: ' + error.message, {ok: false, error: error.message});
		}
	}

	function renderMaterialResults(root, materials) {
		const target = root.querySelector('[data-cf-material-results]');
		if (!target) return;

		materials = Array.isArray(materials) ? materials : [];
		target.innerHTML = '';
		target.hidden = materials.length === 0;

		if (materials.length === 0) return;

		const title = document.createElement('h3');
		title.textContent = 'Integrated materials';
		target.appendChild(title);

		materials.forEach(function(material) {
			const details = document.createElement('details');
			details.className = 'cf-integrated-material';

			const summary = document.createElement('summary');
			const label = material.type === 'web_url' ? 'Web link' : 'Text';
			const title = material.sourceTitle || material.name || 'Material';
			summary.textContent = label + ': ' + title;
			details.appendChild(summary);

			if (material.sourceUrl) {
				const url = document.createElement('p');
				url.className = 'cf-material-source';
				url.textContent = material.sourceUrl;
				details.appendChild(url);
			}

			const preview = document.createElement('pre');
			preview.textContent = material.contentPreview || '';
			details.appendChild(preview);
			target.appendChild(details);
		});
	}


	function rememberTemplateDefinitions(root, definitions) {
		if (!definitions || typeof definitions !== 'object') return;

		root.__contentForgeSectionTemplates = definitions;
	}

	function getTemplateDefinitions(root) {
		return root.__contentForgeSectionTemplates || getFallbackTemplateDefinitions();
	}

	function getTemplateDefinition(root, template) {
		template = normalizeSectionTemplate(template);
		const definitions = getTemplateDefinitions(root);

		return definitions[template] || definitions.micro_learning || null;
	}

	function getFallbackTemplateDefinitions() {
		return {
			micro_learning: {
				key: 'micro_learning',
				label: 'Micro-learning card',
				preview: {label: 'Show section content', preview: {type: 'text', path: 'body', length: 180}, details: [{type: 'paragraph', path: 'body'}, {type: 'interaction', path: 'interaction.prompt'}]},
				schema: {properties: {
					template: {'const': 'micro_learning', title: 'Section template', 'x-contentforge-control': 'template_select'},
					title: {type: 'string', title: 'Headline', 'x-contentforge-control': 'text'},
					body: {type: 'string', title: 'Text', 'x-contentforge-control': 'textarea'},
					interaction: {type: 'object', properties: {type: {type: 'string', default: 'reading', 'x-contentforge-hidden': true}, prompt: {type: 'string', title: 'Learner prompt', 'x-contentforge-control': 'textarea'}}}
				}},
				defaults: {en: {template: 'micro_learning', title: 'New learning card', body: 'New content.', interaction: {type: 'reading', prompt: ''}}}
			},
			short_overview: {
				key: 'short_overview',
				label: 'Information section',
				preview: {label: 'Show overview text', preview: {type: 'text', path: 'body', length: 260}, details: [{type: 'paragraph', path: 'body'}, {type: 'extra', path: 'footer'}]},
				schema: {properties: {
					template: {'const': 'short_overview', title: 'Section template', 'x-contentforge-control': 'template_select'},
					title: {type: 'string', title: 'Headline', 'x-contentforge-control': 'text'},
					body: {type: 'string', title: 'Text', 'x-contentforge-control': 'textarea'},
					footer: {type: 'string', title: 'Footer', 'x-contentforge-control': 'textarea'}
				}},
				defaults: {en: {template: 'short_overview', title: 'New information section', body: 'New content.', footer: ''}}
			},
			checklist: {
				key: 'checklist',
				label: 'Checklist section',
				preview: {label: 'Show checklist details', preview: {type: 'list', path: 'items', fallbackPath: 'body', limit: 3, length: 180}, details: [{type: 'list', path: 'items'}, {type: 'paragraph', path: 'body'}]},
				schema: {properties: {
					template: {'const': 'checklist', title: 'Section template', 'x-contentforge-control': 'template_select'},
					title: {type: 'string', title: 'Headline', 'x-contentforge-control': 'text'},
					body: {type: 'string', title: 'Intro text', 'x-contentforge-control': 'textarea'},
					items: {type: 'array', title: 'Checklist items', 'x-contentforge-control': 'string_list'}
				}},
				defaults: {en: {template: 'checklist', title: 'New checklist', body: 'Check the following points.', items: []}}
			},
			faq: {
				key: 'faq',
				label: 'FAQ section',
				preview: {label: 'Show answer', preview: {type: 'text', path: 'body', length: 220, className: 'cf-choice-answer'}, details: [{type: 'paragraph', path: 'body'}, {type: 'interaction', path: 'interaction.prompt'}]},
				schema: {properties: {
					template: {'const': 'faq', title: 'Section template', 'x-contentforge-control': 'template_select'},
					title: {type: 'string', title: 'Question', 'x-contentforge-control': 'text'},
					body: {type: 'string', title: 'Answer', 'x-contentforge-control': 'textarea'},
					interaction: {type: 'object', properties: {type: {type: 'string', default: 'question', 'x-contentforge-hidden': true}, prompt: {type: 'string', title: 'Follow-up prompt', 'x-contentforge-control': 'textarea'}}}
				}},
				defaults: {en: {template: 'faq', title: 'New question', body: 'New answer.', interaction: {type: 'question', prompt: ''}}}
			}
		};
	}

	function renderProposal(root, proposal) {
		if (!proposal) {
			setStatus(root, 'No proposal found.', null);
			return;
		}

		rememberProposalState(root, proposal);
		clearSectionSelection(root);

		const content = proposal.content || {};
		root.__contentForgeProposalContent = clone(content);

		const title = root.querySelector('[data-cf-proposal-title]');
		const summary = root.querySelector('[data-cf-proposal-summary]');
		const sections = root.querySelector('[data-cf-proposal-sections]');
		const feedbackContext = root.querySelector('[data-cf-feedback-context]');

		if (title) title.textContent = content.title || proposal.title || 'Review proposal';
		if (summary) summary.textContent = content.summary || 'Review the proposed structure.';
		if (feedbackContext) feedbackContext.innerHTML = '';

		renderReviewNotice(root, content);
		updateSelectionDisplay(root);
		renderMaterialResults(root, root.__contentForgeMaterials || []);

		if (!sections) return;

		sections.innerHTML = '';
		const items = Array.isArray(content.sections) ? content.sections : [];

		if (items.length === 0) {
			const empty = document.createElement('p');
			empty.className = 'cf-muted';
			empty.textContent = 'This proposal does not contain any sections yet.';
			sections.appendChild(empty);
			return;
		}

		items.forEach(function(item, index) {
			sections.appendChild(renderSectionCard(root, item, index));
		});
	}

	function renderSectionCard(root, item, index) {
		const content = clone(root.__contentForgeProposalContent || {});
		const template = getSectionTemplate(item, content.generatorTemplate || 'micro_learning');
		const title = item.title || 'Section ' + (index + 1);
		const card = document.createElement('article');
		card.className = 'cf-choice cf-choice--' + normalizeCssToken(template);
		card.setAttribute('data-cf-section-index', String(index));
		card.setAttribute('aria-selected', 'false');

		const head = document.createElement('div');
		head.className = 'cf-choice-head';

		const dragHandle = document.createElement('span');
		dragHandle.className = 'cf-drag-handle';
		dragHandle.setAttribute('data-cf-drag-handle', '1');
		dragHandle.setAttribute('role', 'button');
		dragHandle.setAttribute('aria-label', 'Drag to reorder section');
		dragHandle.title = 'Drag to reorder';
		dragHandle.draggable = true;
		dragHandle.textContent = '↕';
		head.appendChild(dragHandle);

		card.draggable = false;

		const selectButton = document.createElement('button');
		selectButton.type = 'button';
		selectButton.className = 'cf-choice-select';
		selectButton.setAttribute('aria-pressed', 'false');
		selectButton.setAttribute('aria-label', 'Select section: ' + title);

		const heading = document.createElement('strong');
		heading.textContent = title;
		selectButton.appendChild(heading);

		const badge = document.createElement('span');
		badge.className = 'cf-section-template-badge';
		badge.textContent = getSectionTemplateLabel(template);
		selectButton.appendChild(badge);

		const marker = document.createElement('span');
		marker.className = 'cf-selected-marker';
		marker.textContent = 'Selected';
		marker.setAttribute('aria-hidden', 'true');
		selectButton.appendChild(marker);

		head.appendChild(selectButton);
		card.appendChild(head);
		card.appendChild(renderTemplatePreview(root, item, template));
		card.appendChild(renderTemplateDetails(root, item, false, template));

		if (item.changeRequest && item.changeRequest.feedback) {
			const note = document.createElement('span');
			note.className = 'cf-change-note';
			note.textContent = 'Change request: ' + item.changeRequest.feedback;
			card.appendChild(note);
		}

		selectButton.addEventListener('click', function() {
			toggleSectionSelection(root, index);
		});

		return card;
	}

	function renderTemplatePreview(root, section, template) {
		const definition = getTemplateDefinition(root, getSectionTemplate(section, template));
		const preview = definition && definition.preview && definition.preview.preview ? definition.preview.preview : {
			type: 'text',
			path: 'body',
			length: 180
		};

		if (preview.type === 'list') {
			return renderPreviewList(section, preview);
		}

		return renderPreviewText(section, preview);
	}

	function renderPreviewText(section, preview) {
		const excerpt = document.createElement('p');
		excerpt.className = ['cf-choice-excerpt', preview.className || ''].filter(Boolean).join(' ');
		excerpt.textContent = makeExcerpt(getSectionPathValue(section, preview.path || 'body'), Number(preview.length || 180));

		return excerpt;
	}

	function renderPreviewList(section, preview) {
		const list = document.createElement('ul');
		list.className = preview.className || 'cf-checklist-preview';
		const items = getStringListValue(section, preview.path || 'items');
		const limit = Number(preview.limit || 3);

		items.slice(0, limit).forEach(function(item) {
			const li = document.createElement('li');
			li.textContent = item;
			list.appendChild(li);
		});

		if (items.length === 0) {
			const li = document.createElement('li');
			li.textContent = makeExcerpt(getSectionPathValue(section, preview.fallbackPath || 'body'), Number(preview.length || 180));
			list.appendChild(li);
		}

		return list;
	}

	function renderTemplateDetails(root, section, open, template) {
		const definition = getTemplateDefinition(root, getSectionTemplate(section, template));
		const preview = definition && definition.preview ? definition.preview : {};
		const details = document.createElement('details');
		details.className = 'cf-template-details';
		details.open = !!open;

		const summary = document.createElement('summary');
		summary.textContent = preview.label || 'Show section content';
		details.appendChild(summary);

		const body = document.createElement('div');
		body.className = 'cf-template-body';

		(preview.details || [{type: 'paragraph', path: 'body'}]).forEach(function(item) {
			appendPreviewDetail(body, section, item);
		});

		details.appendChild(body);

		return details;
	}

	function appendPreviewDetail(target, section, item) {
		const type = item.type || 'paragraph';
		const value = getSectionPathValue(section, item.path || 'body');

		if (type === 'list') {
			const items = getStringListValue(section, item.path || 'items');
			if (items.length === 0) return;

			const list = document.createElement('ul');
			list.className = item.className || 'cf-template-checklist';
			items.forEach(function(text) {
				const li = document.createElement('li');
				li.textContent = text;
				list.appendChild(li);
			});
			target.appendChild(list);
			return;
		}

		if (type === 'interaction') {
			if (String(value || '').trim() === '') return;

			const interaction = document.createElement('aside');
			interaction.className = item.className || 'cf-template-interaction';
			interaction.textContent = String(value);
			target.appendChild(interaction);
			return;
		}

		if (type === 'extra') {
			if (String(value || '').trim() === '') return;

			const extra = document.createElement('p');
			extra.className = item.className || 'cf-template-extra';
			extra.textContent = String(value);
			target.appendChild(extra);
			return;
		}

		if (type === 'image_hint') {
			if (!value || typeof value !== 'object') return;

			const image = document.createElement('p');
			image.className = item.className || 'cf-template-extra';
			image.textContent = 'Image: ' + String(value.alt || value.title || value.url || 'planned image');
			target.appendChild(image);
			return;
		}

		if (String(value || '').trim() === '') return;

		const paragraph = document.createElement('p');
		paragraph.textContent = String(value);
		target.appendChild(paragraph);
	}


	function getStringListValue(section, path) {
		const value = getSectionPathValue(section, path);

		if (!Array.isArray(value)) {
			return [];
		}

		return value
			.map(function(item) { return typeof item === 'string' ? item : String(item && item.text ? item.text : ''); })
			.map(function(item) { return item.trim(); })
			.filter(Boolean);
	}

	function getSectionPathValue(section, path) {
		const parts = String(path || '').split('.').filter(Boolean);
		let current = section;

		for (let i = 0; i < parts.length; i++) {
			if (!current || typeof current !== 'object') return '';
			current = current[parts[i]];
		}

		return current == null ? '' : current;
	}

	function getChecklistItems(section) {
		if (Array.isArray(section.items)) {
			return section.items
				.map(function(item) { return typeof item === 'string' ? item : String(item && item.text ? item.text : ''); })
				.map(function(item) { return item.trim(); })
				.filter(Boolean);
		}

		return [];
	}

	function getSectionTemplate(section, fallback) {
		return normalizeSectionTemplate(section && section.template ? section.template : fallback);
	}

	function normalizeSectionTemplate(value) {
		value = String(value || '').toLowerCase().trim();
		return ['micro_learning', 'short_overview', 'checklist', 'faq'].indexOf(value) >= 0 ? value : 'micro_learning';
	}

	function getSectionTemplateLabel(template) {
		template = normalizeSectionTemplate(template);

		if (template === 'faq') return 'FAQ';
		if (template === 'checklist') return 'Checklist';
		if (template === 'short_overview') return 'Info';

		return 'Card';
	}

	function getLanguageCode(content) {
		const language = String(content && content.language ? content.language : 'en').toLowerCase();

		return language.indexOf('de') === 0 ? 'de' : 'en';
	}

	function createDefaultSection(root, content, template, index) {
		template = normalizeSectionTemplate(template);
		const language = getLanguageCode(content);
		const definition = getTemplateDefinition(root, template);
		let section = null;

		if (definition && definition.defaults) {
			section = clone(definition.defaults[language] || definition.defaults.en || definition.defaults.de || {});
		}

		if (!section || Object.keys(section).length === 0) {
			section = {
				template: template,
				title: getDefaultSectionTitle(template, index, language),
				body: language === 'de' ? 'Neuer Inhalt.' : 'New content.'
			};
		}

		section.template = template;
		section.id = 'section_' + (index + 1);

		return section;
	}

	function getDefaultSectionTitle(template, index, language) {
		if (template === 'faq') return language === 'de' ? 'Neue Frage' : 'New question';
		if (template === 'checklist') return language === 'de' ? 'Neue Checkliste' : 'New checklist';
		if (template === 'short_overview') return language === 'de' ? 'Neuer Informationsabschnitt' : 'New information section';

		return language === 'de' ? 'Neue Lernkarte' : 'New learning card';
	}

	function normalizeSectionIds(sections) {
		sections.forEach(function(section, index) {
			if (!section || typeof section !== 'object') return;
			if (!section.id) section.id = 'section_' + (index + 1);
		});

		return sections;
	}

	function appendOptionalSectionFields(target, section) {
		['footer', 'note'].forEach(function(key) {
			if (section[key]) {
				const item = document.createElement('p');
				item.className = 'cf-template-extra';
				item.textContent = String(section[key]);
				target.appendChild(item);
			}
		});

		if (section.image && typeof section.image === 'object') {
			const image = document.createElement('p');
			image.className = 'cf-template-extra';
			image.textContent = 'Image: ' + String(section.image.alt || section.image.title || section.image.url || 'planned image');
			target.appendChild(image);
		}
	}

	function normalizeCssToken(value) {
		return String(value || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-');
	}

	function makeExcerpt(value, length) {
		const text = String(value || '').replace(/\s+/g, ' ').trim();

		if (text.length <= length) {
			return text;
		}

		return text.substring(0, length - 1).replace(/[\s.,;:-]+$/, '') + '…';
	}

	function renderReviewNotice(root, content) {
		const notice = root.querySelector('[data-cf-review-notice]');
		if (!notice) return;

		const review = content.review || {};
		const latestFeedback = review.latestFeedback || '';
		const version = review.version || 1;
		if (review.status === 'automatic_generation_failed') {
			notice.hidden = false;
			notice.textContent = review.message || 'Automatic revision failed. The previous proposal was kept.';
			return;
		}

		if (!latestFeedback) {
			notice.hidden = true;
			notice.textContent = '';
			return;
		}

		notice.hidden = false;
		notice.textContent = 'Version ' + version + ': The last change request was processed.';
	}

	function clearSectionSelection(root) {
		setState(root, 'selectedSectionIndex', '');
		setState(root, 'selectedSectionTitle', '');
		setState(root, 'selectedSectionIndexes', '');
		setState(root, 'selectedSectionTitles', '');
	}

	function getSelectedIndexes(root) {
		return getState(root, 'selectedSectionIndexes')
			.split(',')
			.map(function(value) { return String(value).trim(); })
			.filter(function(value) { return value !== ''; })
			.map(function(value) { return Number(value); })
			.filter(function(value) { return Number.isInteger(value) && value >= 0; });
	}

	function setSelectedIndexes(root, indexes) {
		indexes = Array.from(new Set(indexes)).sort(function(a, b) { return a - b; });
		const content = clone(root.__contentForgeProposalContent || {});
		const sections = Array.isArray(content.sections) ? content.sections : [];
		const titles = indexes.map(function(index) {
			return sections[index] && sections[index].title ? String(sections[index].title) : 'Section ' + (index + 1);
		});

		setState(root, 'selectedSectionIndexes', indexes.join(','));
		setState(root, 'selectedSectionTitles', titles.join('\n'));
		setState(root, 'selectedSectionIndex', indexes.length > 0 ? String(indexes[0]) : '');
		setState(root, 'selectedSectionTitle', titles.length > 0 ? titles[0] : '');

		updateSelectionDisplay(root);
	}

	function toggleSectionSelection(root, index) {
		const selected = getSelectedIndexes(root);
		const position = selected.indexOf(index);

		if (position >= 0) {
			selected.splice(position, 1);
		} else {
			selected.push(index);
		}

		setSelectedIndexes(root, selected);
	}

	function updateSelectionDisplay(root) {
		const selected = getSelectedIndexes(root);
		const content = clone(root.__contentForgeProposalContent || {});
		const sections = Array.isArray(content.sections) ? content.sections : [];
		const titles = selected.map(function(index) {
			return sections[index] && sections[index].title ? String(sections[index].title) : 'Section ' + (index + 1);
		});

		root.querySelectorAll('[data-cf-section-index]').forEach(function(item) {
			const index = Number(item.getAttribute('data-cf-section-index'));
			const isSelected = selected.indexOf(index) >= 0;
			item.classList.toggle('is-selected', isSelected);
			item.setAttribute('aria-selected', isSelected ? 'true' : 'false');

			const button = item.querySelector('.cf-choice-select');
			if (button) button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
		});

		let message = 'No sections selected.';
		if (titles.length === 1) {
			message = 'Selected: ' + titles[0];
		} else if (titles.length > 1) {
			message = 'Selected ' + titles.length + ' sections: ' + titles.join(', ');
		}

		root.querySelectorAll('[data-cf-selected-label], [data-cf-selection-summary]').forEach(function(label) {
			label.textContent = message;
		});

		updateActionState(root);
	}

	function updateActionState(root) {
		const hasSelection = getSelectedIndexes(root).length > 0;

		root.querySelectorAll('[data-cf-selection-required]').forEach(function(button) {
			button.disabled = !hasSelection || root.classList.contains('is-busy');
			button.setAttribute('aria-disabled', button.disabled ? 'true' : 'false');
		});
	}

	function renderFeedbackContext(root) {
		const target = root.querySelector('[data-cf-feedback-context]');
		if (!target) return;

		target.innerHTML = '';

		const content = clone(root.__contentForgeProposalContent || {});
		const sections = Array.isArray(content.sections) ? content.sections : [];
		const selected = getSelectedIndexes(root).filter(function(index) { return sections[index]; });

		if (selected.length === 0) {
			const info = document.createElement('p');
			info.className = 'cf-muted';
			info.textContent = 'No sections selected. The change request will be treated as whole-proposal feedback.';
			target.appendChild(info);
			return;
		}

		const intro = document.createElement('p');
		intro.className = 'cf-muted';
		intro.textContent = selected.length === 1
			? 'The following change request will focus on this section.'
			: 'The following change request will focus on these sections.';
		target.appendChild(intro);

		selected.forEach(function(index) {
			const box = document.createElement('div');
			box.className = 'cf-feedback-context-box';

			const heading = document.createElement('h3');
			heading.textContent = sections[index].title || 'Section ' + (index + 1);
			box.appendChild(heading);

			box.appendChild(renderTemplateDetails(root, sections[index], false, getSectionTemplate(sections[index], content.generatorTemplate || 'micro_learning')));
			target.appendChild(box);
		});
	}

	function renderEditForm(root) {
		const content = clone(root.__contentForgeProposalContent || {});
		const target = root.querySelector('[data-cf-edit-sections]');
		const title = field(root, 'editTitle');
		const summary = field(root, 'editSummary');
		const documentFields = root.querySelector('[data-cf-edit-document-fields]');
		const intro = root.querySelector('[data-cf-edit-intro]');
		const sections = Array.isArray(content.sections) ? content.sections : [];
		const selected = getSelectedIndexes(root).filter(function(index) { return sections[index]; });
		const editableIndexes = selected.length > 0 ? selected : sections.map(function(_, index) { return index; });
		const isTargetedEdit = selected.length > 0;

		if (title) title.value = content.title || '';
		if (summary) summary.value = content.summary || '';
		if (documentFields) documentFields.hidden = isTargetedEdit;
		if (intro) {
			intro.textContent = isTargetedEdit
				? 'Only the selected sections are shown. Applying edits returns to the review step.'
				: 'No sections are selected. The full proposal can be edited. Applying edits returns to the review step.';
		}
		if (!target) return;

		target.innerHTML = '';

		editableIndexes.forEach(function(index) {
			const section = sections[index] || {};
			const wrap = document.createElement('div');
			wrap.className = 'cf-edit-section';
			wrap.setAttribute('data-cf-edit-section', String(index));

			const title = document.createElement('h3');
			title.textContent = section.title || 'Section ' + (index + 1);
			wrap.appendChild(title);

			renderSectionEditFields(wrap, section, index, root);
			target.appendChild(wrap);
		});
	}

	function renderSectionEditFields(target, section, index, root) {
		const template = getSectionTemplate(section, 'micro_learning');
		const definition = getTemplateDefinition(root, template);
		const schema = definition && definition.schema ? definition.schema : null;
		const properties = schema && schema.properties ? schema.properties : null;
		const rendered = new Set();

		if (properties) {
			Object.keys(properties).forEach(function(path) {
				renderSchemaField(target, index, path, properties[path], section, root);
				rendered.add(path);
			});
		} else {
			appendTemplateEditField(target, index, template, root);
			appendEditField(target, index, 'title', 'Headline', section.title || '', 'text');
			appendEditField(target, index, 'body', 'Text', section.body || '', 'textarea');
			rendered.add('template');
			rendered.add('title');
			rendered.add('body');
		}

		Object.keys(section || {}).forEach(function(key) {
			if (['id', 'changeRequest'].indexOf(key) >= 0 || rendered.has(key)) return;

			const value = section[key];
			if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
				appendEditField(target, index, key, labelFromKey(key), String(value), String(value).length > 120 ? 'textarea' : 'text');
				return;
			}

			if (Array.isArray(value) || (value && typeof value === 'object')) {
				appendEditField(target, index, key, labelFromKey(key) + ' (JSON)', JSON.stringify(value, null, 2), 'json');
			}
		});
	}

	function renderSchemaField(target, index, path, schema, section, root, prefix) {
		prefix = prefix || '';
		const fullPath = prefix ? prefix + '.' + path : path;

		if (schema && schema['x-contentforge-hidden']) {
			return;
		}

		if (path === 'template' || schema && schema['x-contentforge-control'] === 'template_select') {
			appendTemplateEditField(target, index, getPathValue(section, fullPath.split('.')) || getSectionTemplate(section, 'micro_learning'), root);
			return;
		}

		if (schema && schema.type === 'object' && schema.properties) {
			Object.keys(schema.properties).forEach(function(childPath) {
				renderSchemaField(target, index, childPath, schema.properties[childPath], section, root, fullPath);
			});
			return;
		}

		const label = schema && schema.title ? schema.title : labelFromKey(path);
		const control = schema && schema['x-contentforge-control'] ? schema['x-contentforge-control'] : '';
		const value = getPathValue(section, fullPath.split('.'));
		let mode = 'text';

		if (control === 'textarea' || schema && schema.type === 'string' && String(value || '').length > 120) {
			mode = 'textarea';
		}

		if (control === 'string_list' || schema && schema.type === 'array' && schema.items && schema.items.type === 'string') {
			mode = 'string_array';
		}

		appendEditField(target, index, fullPath, label, formatEditValue(value, mode), mode, schema && schema.description ? schema.description : '');
	}

	function getPathValue(target, path) {
		let current = target;

		for (let i = 0; i < path.length; i++) {
			if (!current || typeof current !== 'object') return '';
			current = current[path[i]];
		}

		return current == null ? '' : current;
	}

	function formatEditValue(value, mode) {
		if (mode === 'string_array') {
			return Array.isArray(value) ? value.join('\n') : '';
		}

		if (Array.isArray(value) || value && typeof value === 'object') {
			return JSON.stringify(value, null, 2);
		}

		return value == null ? '' : String(value);
	}

	function appendTemplateEditField(target, index, value, root) {
		const id = 'cf_edit_' + index + '_template';
		const labelNode = document.createElement('label');
		labelNode.setAttribute('for', id);
		labelNode.textContent = 'Section template';
		target.appendChild(labelNode);

		const select = document.createElement('select');
		select.id = id;
		select.setAttribute('data-cf-edit-path', 'template');
		select.setAttribute('data-cf-edit-mode', 'text');

		Object.keys(getTemplateDefinitions(root)).forEach(function(key) {
			const definition = getTemplateDefinitions(root)[key];
			const option = document.createElement('option');
			option.value = key;
			option.textContent = definition.label || getSectionTemplateLabel(key);
			option.selected = key === normalizeSectionTemplate(value);
			select.appendChild(option);
		});

		target.appendChild(select);
	}

	function appendEditField(target, index, path, label, value, mode, description) {
		const id = 'cf_edit_' + index + '_' + path.replace(/[^a-z0-9]+/gi, '_');
		const labelNode = document.createElement('label');
		labelNode.setAttribute('for', id);
		labelNode.textContent = label;
		target.appendChild(labelNode);

		if (description) {
			const help = document.createElement('p');
			help.className = 'cf-field-help';
			help.textContent = description;
			target.appendChild(help);
		}

		const field = mode === 'text' ? document.createElement('input') : document.createElement('textarea');
		field.id = id;
		field.value = value == null ? '' : String(value);
		field.setAttribute('data-cf-edit-path', path);
		field.setAttribute('data-cf-edit-mode', mode);

		if (mode === 'text') {
			field.type = 'text';
		}

		target.appendChild(field);
	}

	function labelFromKey(key) {
		return String(key || '')
			.replace(/([a-z])([A-Z])/g, '$1 $2')
			.replace(/[_-]+/g, ' ')
			.replace(/^./, function(char) { return char.toUpperCase(); });
	}

	function collectEditedContent(root) {
		const content = clone(root.__contentForgeProposalContent || {});
		const sections = Array.isArray(content.sections) ? content.sections : [];
		const selected = getSelectedIndexes(root).filter(function(index) { return sections[index]; });
		const isTargetedEdit = selected.length > 0;

		if (!isTargetedEdit) {
			const editTitle = field(root, 'editTitle');
			const editSummary = field(root, 'editSummary');
			content.title = editTitle ? editTitle.value : content.title || '';
			content.summary = editSummary ? editSummary.value : content.summary || '';
		}

		content.review = content.review && typeof content.review === 'object' ? content.review : {};
		content.review.status = 'manual_revision_ready_for_review';
		content.review.manualEditApplied = true;
		content.review.manualEditAt = new Date().toISOString();
		content.review.manualEditMode = isTargetedEdit ? 'selected_sections' : 'full_proposal';
		content.review.manualEditSectionIndexes = selected;

		root.querySelectorAll('[data-cf-edit-section]').forEach(function(sectionNode) {
			const index = Number(sectionNode.getAttribute('data-cf-edit-section'));
			if (!Number.isInteger(index) || !sections[index]) return;

			sectionNode.querySelectorAll('[data-cf-edit-path]').forEach(function(input) {
				const path = input.getAttribute('data-cf-edit-path') || '';
				const mode = input.getAttribute('data-cf-edit-mode') || 'text';
				let value = input.value;

				if (mode === 'json') {
					try {
						value = JSON.parse(value);
					} catch (error) {
						value = input.value;
					}
				}

				if (mode === 'string_array') {
					value = input.value.split(/\r?\n/).map(function(item) { return item.trim(); }).filter(Boolean);
				}

				setPathValue(sections[index], path.split('.'), value);
			});
		});

		return content;
	}

	function setPathValue(target, path, value) {
		let current = target;

		path.forEach(function(part, index) {
			if (part === '') return;

			if (index === path.length - 1) {
				current[part] = value;
				return;
			}

			if (!current[part] || typeof current[part] !== 'object' || Array.isArray(current[part])) {
				current[part] = {};
			}

			current = current[part];
		});
	}

	function buildAddSectionContent(root) {
		const content = clone(root.__contentForgeProposalContent || {});
		const sections = Array.isArray(content.sections) ? content.sections : [];
		const selected = getSelectedIndexes(root).filter(function(index) { return sections[index]; });
		const templateField = field(root, 'newSectionTemplate');
		const template = normalizeSectionTemplate(templateField ? templateField.value : content.generatorTemplate || 'micro_learning');
		const insertAt = selected.length > 0 ? Math.max.apply(null, selected) + 1 : sections.length;
		const newSection = createDefaultSection(root, content, template, insertAt);

		sections.splice(insertAt, 0, newSection);
		content.sections = normalizeSectionIds(sections);
		content.review = content.review && typeof content.review === 'object' ? content.review : {};
		content.review.status = 'section_added_ready_for_review';
		content.review.manualEditApplied = true;
		content.review.manualEditAt = new Date().toISOString();
		content.review.manualEditMode = 'add_section';
		content.review.manualEditSectionIndexes = [insertAt];

		return {content: content, selectedIndexes: [insertAt]};
	}

	function buildMoveSectionsContent(root, direction) {
		const content = clone(root.__contentForgeProposalContent || {});
		const sections = Array.isArray(content.sections) ? content.sections : [];
		let selected = getSelectedIndexes(root).filter(function(index) { return sections[index]; });

		if (selected.length === 0) {
			return {error: 'Select at least one section first.'};
		}

		selected = Array.from(new Set(selected)).sort(function(a, b) { return a - b; });
		const selectedSet = new Set(selected);
		const wrapped = sections.map(function(section, index) {
			return {section: section, selected: selectedSet.has(index)};
		});

		if (direction === 'up') {
			if (selected[0] === 0) return {error: 'The selected section is already at the top.'};

			for (let i = 1; i < wrapped.length; i++) {
				if (wrapped[i].selected && !wrapped[i - 1].selected) {
					const tmp = wrapped[i - 1];
					wrapped[i - 1] = wrapped[i];
					wrapped[i] = tmp;
				}
			}
		} else {
			if (selected[selected.length - 1] >= sections.length - 1) return {error: 'The selected section is already at the bottom.'};

			for (let i = wrapped.length - 2; i >= 0; i--) {
				if (wrapped[i].selected && !wrapped[i + 1].selected) {
					const tmp = wrapped[i + 1];
					wrapped[i + 1] = wrapped[i];
					wrapped[i] = tmp;
				}
			}
		}

		const moved = [];
		content.sections = normalizeSectionIds(wrapped.map(function(item, index) {
			if (item.selected) moved.push(index);
			return item.section;
		}));
		content.review = content.review && typeof content.review === 'object' ? content.review : {};
		content.review.status = 'section_order_changed_ready_for_review';
		content.review.manualEditApplied = true;
		content.review.manualEditAt = new Date().toISOString();
		content.review.manualEditMode = 'reorder_sections';
		content.review.manualEditSectionIndexes = moved.sort(function(a, b) { return a - b; });

		return {content: content, selectedIndexes: content.review.manualEditSectionIndexes};
	}

	function buildDuplicateSectionsContent(root) {
		const content = clone(root.__contentForgeProposalContent || {});
		const sections = Array.isArray(content.sections) ? content.sections : [];
		const selected = getSelectedIndexes(root).filter(function(index) { return sections[index]; });

		if (selected.length === 0) {
			return {error: 'Select at least one section first.'};
		}

		let offset = 0;
		const duplicated = [];

		selected.forEach(function(index) {
			const insertAt = index + 1 + offset;
			const copy = clone(sections[index]);
			copy.id = '';
			copy.title = getDuplicatedTitle(copy.title || 'Section ' + (index + 1));
			sections.splice(insertAt, 0, copy);
			duplicated.push(insertAt);
			offset++;
		});

		content.sections = normalizeSectionIds(sections);
		content.review = content.review && typeof content.review === 'object' ? content.review : {};
		content.review.status = 'sections_duplicated_ready_for_review';
		content.review.manualEditApplied = true;
		content.review.manualEditAt = new Date().toISOString();
		content.review.manualEditMode = 'duplicate_sections';
		content.review.manualEditSectionIndexes = duplicated;

		return {content: content, selectedIndexes: duplicated};
	}

	function getDuplicatedTitle(title) {
		const value = String(title || '').trim();

		if (value === '') {
			return 'Copy';
		}

		return value + ' (copy)';
	}

	function buildDeleteSectionsContent(root) {
		const content = clone(root.__contentForgeProposalContent || {});
		const sections = Array.isArray(content.sections) ? content.sections : [];
		const selected = getSelectedIndexes(root).filter(function(index) { return sections[index]; });

		if (selected.length === 0) {
			return {error: 'Select at least one section first.'};
		}

		if (selected.length >= sections.length) {
			return {error: 'At least one section must remain.'};
		}

		const selectedSet = new Set(selected);
		content.sections = normalizeSectionIds(sections.filter(function(_, index) {
			return !selectedSet.has(index);
		}));
		content.review = content.review && typeof content.review === 'object' ? content.review : {};
		content.review.status = 'sections_deleted_ready_for_review';
		content.review.manualEditApplied = true;
		content.review.manualEditAt = new Date().toISOString();
		content.review.manualEditMode = 'delete_sections';
		content.review.manualEditSectionIndexes = [];

		return {content: content, selectedIndexes: []};
	}

	function buildDragReorderContent(root, fromIndex, toIndex) {
		const content = clone(root.__contentForgeProposalContent || {});
		const sections = Array.isArray(content.sections) ? content.sections : [];

		if (!Number.isInteger(fromIndex) || !Number.isInteger(toIndex) || !sections[fromIndex] || !sections[toIndex]) {
			return {error: 'The section could not be moved.'};
		}

		if (fromIndex === toIndex) {
			return {content: content, selectedIndexes: [fromIndex], unchanged: true};
		}

		const moved = sections.splice(fromIndex, 1)[0];
		sections.splice(toIndex, 0, moved);
		content.sections = normalizeSectionIds(sections);
		content.review = content.review && typeof content.review === 'object' ? content.review : {};
		content.review.status = 'section_order_changed_ready_for_review';
		content.review.manualEditApplied = true;
		content.review.manualEditAt = new Date().toISOString();
		content.review.manualEditMode = 'drag_reorder_section';
		content.review.manualEditSectionIndexes = [toIndex];

		return {content: content, selectedIndexes: [toIndex]};
	}

	async function submitEditedProposal(root, config, content, selectedIndexes, successMessage) {
		const payload = await request(config, {
			action: 'apply_widget_edits',
			workflowInstanceId: getState(root, 'workflowInstanceId'),
			proposalId: getState(root, 'proposalId'),
			editedContentJson: JSON.stringify(content)
		});

		rememberPayloadState(root, payload);

		if (payload.ok) {
			renderProposal(root, firstProposal(payload));
			if (Array.isArray(selectedIndexes) && selectedIndexes.length > 0) {
				setSelectedIndexes(root, selectedIndexes);
			}
			setScreen(root, 'proposal');
		}

		setStatus(root, payload.ok ? successMessage : (payload.error || 'Error.'), payload);
		return payload;
	}

	function renderDone(root, payload) {
		const target = root.querySelector('[data-cf-result]');
		if (!target) return;

		target.innerHTML = '';

		const proposal = firstProposal(payload);
		const content = proposal ? proposal.content || {} : {};
		const path = content.path || '';
		const url = content.url || '';

		const list = document.createElement('dl');
		list.className = 'cf-result-list';

		appendResult(list, 'Status', 'Accepted and export step executed.');

		if (url !== '') {
			appendResult(list, 'URL', url);
		}

		if (path !== '') {
			appendResult(list, 'Storage', path);
		}

		target.appendChild(list);
	}

	function appendResult(list, key, value) {
		const dt = document.createElement('dt');
		dt.textContent = key;
		list.appendChild(dt);

		const dd = document.createElement('dd');

		if (key === 'URL' && String(value).trim() !== '') {
			const link = document.createElement('a');
			link.href = value;
			link.textContent = 'Download export';
			link.target = '_blank';
			link.rel = 'noopener';
			dd.appendChild(link);
		} else {
			dd.textContent = value;
		}

		list.appendChild(dd);
	}

	function reset(root) {
		setState(root, 'projectId', '');
		setState(root, 'workflowInstanceId', '');
		setState(root, 'proposalId', '');
		clearSectionSelection(root);
		root.__contentForgeProposalContent = null;
		root.__contentForgeMaterials = [];
		renderMaterialResults(root, []);

		const errorBox = root.querySelector('[data-cf-error]');
		if (errorBox) {
			errorBox.hidden = true;
			errorBox.textContent = '';
		}

		const feedback = field(root, 'feedback');
		if (feedback) feedback.value = '';

		setScreen(root, 'material');
		setStatus(root, 'Ready.', null);
	}

	function setupDragAndDrop(root, config) {
		let dragIndex = null;

		root.addEventListener('dragstart', function(event) {
			const card = event.target.closest('[data-cf-section-index]');
			if (!card || !root.contains(card)) return;
			if (!event.target.closest('[data-cf-drag-handle]')) {
				event.preventDefault();
				return;
			}

			dragIndex = Number(card.getAttribute('data-cf-section-index'));
			card.classList.add('is-dragging');
			if (event.dataTransfer) {
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData('text/plain', String(dragIndex));
			}
		});

		root.addEventListener('dragover', function(event) {
			if (dragIndex === null) return;
			const card = event.target.closest('[data-cf-section-index]');
			if (!card || !root.contains(card)) return;
			event.preventDefault();
			card.classList.add('is-drag-over');
			if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
		});

		root.addEventListener('dragleave', function(event) {
			const card = event.target.closest('[data-cf-section-index]');
			if (card) card.classList.remove('is-drag-over');
		});

		root.addEventListener('drop', async function(event) {
			if (dragIndex === null) return;
			const card = event.target.closest('[data-cf-section-index]');
			if (!card || !root.contains(card)) return;
			event.preventDefault();
			const targetIndex = Number(card.getAttribute('data-cf-section-index'));
			root.querySelectorAll('.is-drag-over').forEach(function(item) { item.classList.remove('is-drag-over'); });

			const operation = buildDragReorderContent(root, dragIndex, targetIndex);
			dragIndex = null;

			if (operation.error) {
				setStatus(root, operation.error, {ok: false, error: operation.error});
				return;
			}

			if (operation.unchanged) {
				setSelectedIndexes(root, operation.selectedIndexes || []);
				return;
			}

			setBusy(root, true);
			try {
				await submitEditedProposal(root, config, operation.content, operation.selectedIndexes, 'Section order updated. Review the updated proposal.');
			} catch (error) {
				setStatus(root, 'Request failed: ' + error.message, {ok: false, error: error.message});
			} finally {
				setBusy(root, false);
			}
		});

		root.addEventListener('dragend', function() {
			dragIndex = null;
			root.querySelectorAll('.is-dragging, .is-drag-over').forEach(function(item) {
				item.classList.remove('is-dragging', 'is-drag-over');
			});
		});
	}

	function init(root, config) {
		rememberTemplateDefinitions(root, config.sectionTemplates || null);
		setupMaterialRows(root, config);
		setupDragAndDrop(root, config);
		updateActionState(root);

		root.addEventListener('click', async function(event) {
			const button = event.target.closest('[data-contentforge-action]');
			if (!button || !root.contains(button)) return;

			const action = button.getAttribute('data-contentforge-action');

			if (action === 'add-material') {
				addMaterialRow(root, 'text', config);
				return;
			}

			if (action === 'remove-material') {
				const row = button.closest('[data-cf-material-item]');
				if (row) removeMaterialRow(root, row, config);
				return;
			}

			if (action === 'open-feedback') {
				renderFeedbackContext(root);
				setScreen(root, 'feedback');
				setStatus(root, 'Describe what should change.', null);
				return;
			}

			if (action === 'open-edit') {
				renderEditForm(root);
				setScreen(root, 'edit');
				setStatus(root, 'Edit the selected content and review the result before accepting.', null);
				return;
			}

			if (action === 'back-to-proposal') {
				setScreen(root, 'proposal');
				return;
			}

			if (action === 'new-widget') {
				reset(root);
				return;
			}

			if (['add-section', 'move-section-up', 'move-section-down', 'duplicate-section', 'delete-section'].indexOf(action) >= 0) {
				setBusy(root, true);

				try {
					let operation = null;
					let message = 'Proposal updated.';

					if (action === 'add-section') {
						operation = buildAddSectionContent(root);
						message = 'Section added. Review the updated proposal.';
					} else if (action === 'duplicate-section') {
						operation = buildDuplicateSectionsContent(root);
						message = 'Section duplicated. Review the updated proposal.';
					} else if (action === 'delete-section') {
						operation = buildDeleteSectionsContent(root);
						message = 'Section deleted. Review the updated proposal.';
					} else {
						operation = buildMoveSectionsContent(root, action === 'move-section-up' ? 'up' : 'down');
						message = 'Section order updated. Review the updated proposal.';
					}

					if (operation.error) {
						setStatus(root, operation.error, {ok: false, error: operation.error});
						return;
					}

					await submitEditedProposal(root, config, operation.content, operation.selectedIndexes, message);
				} catch (error) {
					setStatus(root, 'Request failed: ' + error.message, {ok: false, error: error.message});
				} finally {
					setBusy(root, false);
				}
				return;
			}

			setBusy(root, true);

			try {
				let payload = null;

				if (action === 'start-widget') {
					setStatus(root, 'Processing materials and creating proposal.', null);
					await ensureMaterialPreviews(root, config);
					const materials = collectMaterialInputs(root);
					payload = await request(config, {
						action: 'start_widget',
						title: field(root, 'title')?.value || 'ContentForge Project',
						generatorType: field(root, 'generator')?.value || config.generatorType,
						generatorTemplate: field(root, 'template')?.value || 'micro_learning',
						targetSectionCount: field(root, 'targetSectionCount')?.value || 'auto',
						materialsJson: JSON.stringify(materials),
						material: materials.length > 0 && materials[0].type === 'text' ? materials[0].content || '' : ''
					});

					rememberPayloadState(root, payload);

					if (payload.ok) {
						renderProposal(root, firstProposal(payload));
						setScreen(root, 'proposal');
					}

					setStatus(root, payload.ok ? 'Proposal ready.' : (payload.error || 'Error.'), payload);
				}

				if (action === 'send-feedback') {
					setStatus(root, 'Processing materials and creating a revised proposal.', null);
					await ensureMaterialPreviews(root, config);
					const materials = collectMaterialInputs(root);
					payload = await request(config, {
						action: 'request_widget_changes',
						workflowInstanceId: getState(root, 'workflowInstanceId'),
						proposalId: getState(root, 'proposalId'),
						selectedSectionIndex: getState(root, 'selectedSectionIndex'),
						selectedSectionTitle: getState(root, 'selectedSectionTitle'),
						selectedSectionIndexes: getState(root, 'selectedSectionIndexes'),
						selectedSectionTitles: getState(root, 'selectedSectionTitles'),
						materialsJson: JSON.stringify(materials),
						feedback: field(root, 'feedback')?.value || ''
					});

					rememberPayloadState(root, payload);

					if (payload.ok) {
						renderProposal(root, firstProposal(payload));
						setScreen(root, 'proposal');
					}

					setStatus(root, payload.ok ? 'New proposal ready.' : (payload.error || 'Error.'), payload);
				}

				if (action === 'accept-widget' || action === 'accept-edit') {
					const data = {
						action: action === 'accept-edit' ? 'apply_widget_edits' : 'accept_widget',
						workflowInstanceId: getState(root, 'workflowInstanceId'),
						proposalId: getState(root, 'proposalId'),
						exportTemplate: field(root, 'exportTemplate')?.value || 'html_package',
						exportTarget: config.exportTarget || 'contentforgedownloadexporttarget',
						exportTargetConfigJson: JSON.stringify(config.exportTargetConfig || {})
					};

					if (action === 'accept-edit') {
						data.editedContentJson = JSON.stringify(collectEditedContent(root));
					}

					payload = await request(config, data);

					rememberPayloadState(root, payload);

					if (payload.ok && action === 'accept-edit') {
						renderProposal(root, firstProposal(payload));
						setScreen(root, 'proposal');
						setStatus(root, 'Manual edits applied. Review the updated proposal.', payload);
					} else if (payload.ok) {
						renderDone(root, payload || {});
						setScreen(root, 'done');
						setStatus(root, 'Done.', payload);
					} else {
						setStatus(root, payload.error || 'Error.', payload);
					}
				}
			} catch (error) {
				setStatus(root, 'Request failed: ' + error.message, {ok: false, error: error.message});
			} finally {
				setBusy(root, false);
			}
		});
	}

	global.ContentForgeStepWidget = {init: init};
	global.ContentForgeWorkbench = {init: init};
})(window);
