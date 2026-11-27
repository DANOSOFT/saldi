// javascript/usersRolesEditor.js
// 20261110 CDX/ASR Role drafts, accessible permission groups, impact previews and protection of unsaved edits.
(function () {
	'use strict';
	var form = document.getElementById('ur-role-form');
	var data = document.getElementById('ur-role-editor-data');
	if (!form || !data) { return; }
	var cfg = JSON.parse(data.textContent);
	var text = cfg.text;
	var ranks = { none: 0, read: 1, write: 2 };
	var rows = Array.from(form.querySelectorAll('.ur-permission'));
	var groups = Array.from(form.querySelectorAll('[data-role-group]'));
	var search = document.getElementById('ur-role-search');
	var filter = document.getElementById('ur-role-filter');
	var count = document.getElementById('ur-role-change-count');
	var save = document.getElementById('ur-role-save');
	var undo = document.getElementById('ur-role-undo');
	var dialog = document.getElementById('ur-role-dialog');
	var dialogBody = document.getElementById('ur-role-dialog-body');
	var confirmButton = dialog.querySelector('[data-role-dialog-confirm]');
	var cancelButton = dialog.querySelector('[data-role-dialog-cancel]');
	var template = document.getElementById('ur-role-template');
	var submitting = false;
	var dialogAction = null;
	var dialogFocus = null;
	var filtered = false;
	var groupOpen = new Map();
	var baseline = cfg.isNew ? {} : cfg.levels;
	var initial = snapshot();

	function format(value) {
		var args = Array.prototype.slice.call(arguments, 1), index = 0;
		return value.replace(/%s/g, function () { return String(args[index++]); });
	}
	function node(tag, value, className) {
		var el = document.createElement(tag);
		if (value !== undefined) { el.textContent = value; }
		if (className) { el.className = className; }
		return el;
	}
	function selected(row) {
		var choice = row.querySelector('input[type="radio"]:checked');
		return choice ? choice.value : 'none';
	}
	function levels() {
		var result = {};
		rows.forEach(function (row) { result[row.dataset.key] = selected(row); });
		return result;
	}
	function snapshot() {
		return JSON.stringify({ name: form.elements.navn.value, description: form.elements.beskrivelse.value,
			source: form.elements.role_source.value, levels: levels() });
	}
	function dirty() { return snapshot() !== initial; }
	function differences(before, after) {
		var changes = { added: [], removed: [] };
		Object.keys(cfg.labels).forEach(function (key) {
			var from = before[key] || 'none', to = after[key] || 'none';
			if (from === to) { return; }
			changes[ranks[to] > ranks[from] ? 'added' : 'removed'].push({ key: key, from: from, to: to });
		});
		return changes;
	}
	function affected() {
		return format(text.affected, cfg.counts.active, cfg.counts.invited, cfg.counts.closed);
	}
	function addChanges(container, changes, title) {
		if (!changes.length) { return; }
		container.appendChild(node('h3', title));
		var list = node('ul', undefined, 'ur-role-diff');
		changes.forEach(function (change) {
			var item = node('li');
			item.appendChild(node('b', cfg.labels[change.key]));
			item.appendChild(node('span', text[change.from] + ' → ' + text[change.to]));
			list.appendChild(item);
		});
		container.appendChild(list);
	}
	function renderDiff(container, before, after) {
		var changes = differences(before, after);
		addChanges(container, changes.added, text.added);
		addChanges(container, changes.removed, text.removed);
		if (!changes.added.length && !changes.removed.length) { container.appendChild(node('p', text.noPermissions)); }
		if ((before['system.indstillinger'] || 'none') === 'write' && (after['system.indstillinger'] || 'none') !== 'write' && cfg.counts.total > 0) {
			container.appendChild(node('p', text.revenue, 'sk-note sk-warn'));
		}
	}
	function closeDialog() {
		dialog.close();
		dialogAction = null;
		if (dialogFocus && document.contains(dialogFocus)) { dialogFocus.focus(); }
	}
	function openDialog(title, verb, action, danger) {
		dialogFocus = document.activeElement;
		document.getElementById('ur-role-dialog-title').textContent = title;
		dialogBody.replaceChildren();
		confirmButton.textContent = verb || text.close;
		confirmButton.className = 'sk-btn ' + (danger ? 'sk-btn-danger' : 'sk-btn-primary');
		confirmButton.disabled = false;
		confirmButton.hidden = !action;
		cancelButton.textContent = action ? text.cancel : text.close;
		dialogAction = action;
		if (!dialog.open) { dialog.showModal(); }
		cancelButton.focus();
	}
	cancelButton.addEventListener('click', closeDialog);
	confirmButton.addEventListener('click', function () {
		var action = dialogAction;
		closeDialog();
		if (action) { action(); }
	});
	dialog.addEventListener('cancel', function () { dialogAction = null; });

	function submit(target) {
		submitting = true;
		window.docChange = false;
		if (target.requestSubmit) { target.requestSubmit(); } else { target.submit(); }
	}
	function navigate(href) {
		if (!href) { return; }
		var go = function () { submitting = true; window.docChange = false; window.location.assign(href); };
		if (!dirty()) { go(); return; }
		openDialog(text.discardTitle, text.discard, go, false);
		dialogBody.appendChild(node('p', text.discardBody));
	}
	document.addEventListener('click', function (event) {
		var anchor = event.target.closest('a[href]');
		if (!anchor || !dirty() || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || anchor.target === '_blank' || anchor.hasAttribute('download')) { return; }
		var href = anchor.getAttribute('href');
		if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href)) { return; }
		event.preventDefault();
		event.stopImmediatePropagation();
		navigate(anchor.href);
	}, true);
	window.addEventListener('beforeunload', function (event) {
		if (dirty() && !submitting) { event.preventDefault(); event.returnValue = ''; }
	});
	var picker = document.getElementById('ur-role-picker');
	picker.addEventListener('change', function () {
		var href = picker.value;
		Array.from(picker.options).forEach(function (option) { option.selected = option.defaultSelected; });
		navigate(href);
	});

	function refresh() {
		var current = levels(), viewOn = current['dashboard.view'] !== 'none';
		var changed = differences(cfg.levels, current);
		var n = changed.added.length + changed.removed.length;
		if (form.elements.navn.value !== cfg.name) { n++; }
		if (form.elements.beskrivelse.value !== cfg.description) { n++; }
		count.textContent = n ? format(text.pending, n) : (cfg.isNew ? text.newDraft : text.noChanges);
		if (undo) { undo.disabled = !dirty(); }
		if (save) { save.disabled = !cfg.isNew && !dirty(); }
		window.docChange = dirty();
		form.querySelector('[data-role-title]').textContent = form.elements.navn.type === 'hidden' ? cfg.displayName : (form.elements.navn.value.trim() || text.newRole);
		rows.forEach(function (row) {
			var key = row.dataset.key, level = current[key], blockOff = row.hasAttribute('data-dashboard-block') && !viewOn;
			row.querySelector('[data-level-fallback]').value = level;
			row.classList.toggle('ur-permission-off', blockOff);
			var isChanged = level !== (cfg.levels[key] || 'none');
			row.classList.toggle('ur-permission-edited', isChanged);
			row.querySelector('.ur-permission-changed').hidden = !isChanged;
			row.querySelectorAll('input[type="radio"]').forEach(function (choice) {
				choice.disabled = choice.dataset.locked === '1' || blockOff;
				choice.closest('label').classList.toggle('is-selected', choice.checked);
			});
			var needs = row.querySelector('[data-dashboard-needs]');
			if (needs) { needs.hidden = !blockOff; }
			var hint = row.querySelector('[data-dashboard-hint]');
			if (hint) {
				var derived = row.dataset.derive.split(',').filter(Boolean);
				var moduleOn = derived.indexOf('*') >= 0 || derived.some(function (module) { return (current[module] || 'none') !== 'none'; });
				hint.hidden = blockOff || level === 'none' || moduleOn;
			}
		});
		groups.forEach(function (group) {
			var read = 0, write = 0;
			group.querySelectorAll('.ur-permission').forEach(function (row) {
				if (row.hasAttribute('data-dashboard-block') && !viewOn) { return; }
				if (current[row.dataset.key] === 'read') { read++; }
				if (current[row.dataset.key] === 'write') { write++; }
			});
			group.querySelector('[data-group-summary]').textContent = read || write ? text.write + ': ' + write + ' · ' + text.read + ': ' + read : text.none;
		});
		applyFilter();
	}
	function applyFilter() {
		var query = search.value.trim().toLocaleLowerCase(), mode = filter.value;
		var active = !!query || mode !== 'all', visibleCount = 0;
		if (active && !filtered) { groups.forEach(function (group) { groupOpen.set(group, group.open); }); }
		var current = levels();
		rows.forEach(function (row) {
			var key = row.dataset.key;
			var matches = (!query || row.textContent.toLocaleLowerCase().indexOf(query) >= 0 || key.toLocaleLowerCase().indexOf(query) >= 0);
			if (mode === 'access') { matches = matches && current[key] !== 'none' && (!row.hasAttribute('data-dashboard-block') || current['dashboard.view'] !== 'none'); }
			if (mode === 'changed') { matches = matches && current[key] !== (cfg.levels[key] || 'none'); }
			row.hidden = !matches;
			if (matches) { visibleCount++; }
		});
		groups.forEach(function (group) {
			group.hidden = !group.querySelector('.ur-permission:not([hidden])');
			if (active && !group.hidden) { group.open = true; }
			if (!active && filtered) { group.open = groupOpen.get(group); }
		});
		filtered = active;
		document.getElementById('ur-role-empty').hidden = visibleCount > 0;
		updateExpandLabel();
	}
	function updateExpandLabel() {
		var allOpen = groups.filter(function (group) { return !group.hidden; }).every(function (group) { return group.open; });
		document.getElementById('ur-role-expand').textContent = allOpen ? text.collapse : text.expand;
	}
	search.addEventListener('input', applyFilter);
	filter.addEventListener('change', applyFilter);
	groups.forEach(function (group) { group.addEventListener('toggle', updateExpandLabel); });
	document.getElementById('ur-role-expand').addEventListener('click', function () {
		var visible = groups.filter(function (group) { return !group.hidden; });
		var open = !visible.every(function (group) { return group.open; });
		visible.forEach(function (group) { group.open = open; });
		updateExpandLabel();
	});
	form.addEventListener('input', function (event) {
		if (event.target !== search) { refresh(); }
	});
	form.addEventListener('change', function (event) {
		if (event.target !== filter && event.target !== template) { refresh(); }
	});
	form.querySelectorAll('[data-group-level]').forEach(function (button) {
		button.addEventListener('click', function () {
			var changed = 0;
			button.closest('[data-role-group]').querySelectorAll('.ur-permission').forEach(function (row) {
				if (row.dataset.dangerous === '1') { return; }
				var choice = row.querySelector('input[type="radio"][value="' + button.dataset.groupLevel + '"]');
				if (!choice || choice.dataset.locked === '1' || choice.checked) { return; }
				// Hidden/filter-matched rows still belong to this group; locked or dependent rows never change implicitly.
				if (row.hasAttribute('data-dashboard-block') && levels()['dashboard.view'] === 'none') { return; }
				choice.checked = true;
				changed++;
			});
			refresh();
			document.getElementById('ur-role-feedback').textContent = format(text.groupApplied, changed);
		});
	});
	if (undo) {
		undo.addEventListener('click', function () {
			form.reset();
			form.elements.role_source.value = String(cfg.source);
			refresh();
		});
	}
	if (template) {
		template.addEventListener('change', function () {
			var source = cfg.roles[template.value];
			var next = source ? source.levels : {};
			rows.forEach(function (row) {
				var target = next[row.dataset.key] || 'none';
				var choice = row.querySelector('input[type="radio"][value="' + target + '"]');
				if (choice && choice.dataset.locked !== '1') { choice.checked = true; }
			});
			form.elements.role_source.value = template.value;
			form.elements.beskrivelse.value = source ? source.description : '';
			refresh();
		});
	}

	form.addEventListener('submit', function (event) {
		if (submitting) { return; }
		event.preventDefault();
		if (!form.reportValidity()) { return; }
		openDialog(text.preview + ' · ' + (form.elements.navn.value || text.newRole), text.save, function () { submit(form); }, false);
		dialogBody.appendChild(node('p', cfg.isNew ? text.noUsers : affected()));
		if (!cfg.isNew) { dialogBody.appendChild(node('p', text.nextView)); }
		if (form.elements.navn.value !== cfg.name || form.elements.beskrivelse.value !== cfg.description || cfg.isNew) {
			dialogBody.appendChild(node('h3', text.details));
			if (cfg.isNew || form.elements.navn.value !== cfg.name) {
				dialogBody.appendChild(node('p', text.nameField + ': ' + (cfg.isNew ? '' : cfg.name + ' → ') + form.elements.navn.value));
			}
			if (form.elements.beskrivelse.value !== cfg.description || (cfg.isNew && form.elements.beskrivelse.value)) {
				dialogBody.appendChild(node('p', text.descriptionField + ': ' + (cfg.isNew ? '' : (cfg.description || '—') + ' → ') + (form.elements.beskrivelse.value || '—')));
			}
		}
		renderDiff(dialogBody, baseline, levels());
	});
	var reset = document.getElementById('ur-role-reset');
	if (reset) {
		reset.addEventListener('click', function () {
			openDialog(text.resetPreview, reset.textContent, function () { submit(document.getElementById('ur-role-reset-form')); }, false);
			dialogBody.appendChild(node('p', affected()));
			dialogBody.appendChild(node('p', text.nextView));
			if (dirty()) { dialogBody.appendChild(node('p', text.unsavedIgnored, 'sk-note sk-warn')); }
			renderDiff(dialogBody, cfg.levels, cfg.defaults);
		});
	}
	var standard = document.getElementById('ur-role-standard-diff');
	if (standard) {
		standard.addEventListener('click', function () {
			openDialog(text.standardDiff, text.close, null, false);
			dialogBody.appendChild(node('p', text.fromStandard));
			var changes = differences(cfg.defaults, levels());
			if (!changes.added.length && !changes.removed.length) { dialogBody.appendChild(node('p', text.noStandardDiff)); }
			else { renderDiff(dialogBody, cfg.defaults, levels()); }
		});
	}
	var remove = document.getElementById('ur-role-delete');
	if (remove) {
		remove.addEventListener('click', function () {
			var targetForm = document.getElementById('ur-role-delete-form');
			var hasUsers = cfg.counts.total > 0;
			openDialog((hasUsers ? text.deletePreview : text.deleteEmpty) + ' · ' + cfg.name, hasUsers ? text.moveDelete : text.delete, function () { submit(targetForm); }, true);
			dialogBody.appendChild(node('p', affected()));
			if (dirty()) { dialogBody.appendChild(node('p', text.unsavedIgnored, 'sk-note sk-warn')); }
			if (!hasUsers) { return; }
			dialogBody.appendChild(node('p', text.deleteBody));
			var label = node('label', undefined, 'sk-field');
			label.appendChild(node('span', text.moveTo, 'sk-field-lbl'));
			var select = node('select', undefined, 'sk-input');
			Object.keys(cfg.roles).forEach(function (id) {
				if (Number(id) === cfg.roleId || !cfg.roles[id].allowed) { return; }
				var option = node('option', cfg.roles[id].name);
				option.value = id;
				select.appendChild(option);
			});
			label.appendChild(select);
			dialogBody.appendChild(label);
			var diff = node('div');
			dialogBody.appendChild(diff);
			function updateMove() {
				targetForm.elements.move_to.value = select.value || '0';
				diff.replaceChildren();
				confirmButton.disabled = !select.value;
				if (!select.value) { diff.appendChild(node('p', text.noTargets)); return; }
				renderDiff(diff, cfg.levels, cfg.roles[select.value].levels);
			}
			select.addEventListener('change', updateMove);
			updateMove();
		});
	}
	var savebar = form.querySelector('.ur-role-savebar');
	function sizeHeader() { form.style.setProperty('--ur-savebar-height', savebar.getBoundingClientRect().height + 'px'); }
	if (window.ResizeObserver) { new ResizeObserver(sizeHeader).observe(savebar); }
	window.addEventListener('resize', sizeHeader);
	var workspace = document.getElementById('ur-roles-editor');
	function sizeWorkspace() { workspace.classList.toggle('is-compact', workspace.clientWidth <= 768); }
	if (window.ResizeObserver) { new ResizeObserver(sizeWorkspace).observe(workspace); }
	window.addEventListener('resize', sizeWorkspace);
	sizeWorkspace();
	sizeHeader();
	refresh();
})();
