// javascript/usersAccess.js
// 20261110 CDX/ASR Grouped full-permission preview and explicit role-assignment changes before saving.
(function () {
	'use strict';
	var source = document.getElementById('ur-access-data');
	if (!source) { return; }
	var data = JSON.parse(source.textContent), rank = { none: 0, read: 1, write: 2 };
	var form = document.getElementById('ur-user-form'), pick = document.getElementById('ur-role');
	var auditor = form && form.querySelector('input[name="revisor"]');
	function node(tag, text, className) {
		var el = document.createElement(tag);
		if (text !== undefined) { el.textContent = text; }
		if (className) { el.className = className; }
		return el;
	}
	function levels(user, role, isAuditor) {
		if (data.mode === 'deny' && isAuditor) { return data.levels[data.auditorRole] || {}; }
		return data.levels[role] || (user && user.levels) || {};
	}
	function changes(before, after) {
		return Object.keys(data.keys).filter(function (key) { return (before[key] || 'none') !== (after[key] || 'none'); }).map(function (key) {
			return { key: key, before: before[key] || 'none', after: after[key] || 'none' };
		});
	}
	function appendDiff(target, diff) {
		if (!diff.length) { target.appendChild(node('p', data.text.unchanged, 'sk-muted')); return; }
		['added', 'removed'].forEach(function (kind) {
			var items = diff.filter(function (item) { return (rank[item.after] > rank[item.before]) === (kind === 'added'); });
			if (!items.length) { return; }
			target.appendChild(node('h4', data.text[kind] + ' (' + items.length + ')'));
			var list = node('ul', undefined, 'ur-access-diff-list');
			items.forEach(function (item) {
				var key = data.keys[item.key];
				list.appendChild(node('li', data.groups[key.group] + ' · ' + key.label + ': ' + data.levelLabels[item.before] + ' → ' + data.levelLabels[item.after]));
			});
			target.appendChild(list);
		});
	}
	function render() {
		if (!form || !pick) { return; }
		var user = data.users[form.elements.id.value], role = data.roles[pick.value];
		var before = levels(user, form.dataset.originalRole, user && user.auditor);
		var after = levels(user, pick.value, auditor && auditor.checked);
		document.getElementById('ur-role-description').textContent = role ? role.description : data.text.none;
		document.getElementById('ur-role-customized').hidden = !role || !role.changed;
		document.getElementById('ur-auditor-note').hidden = !(auditor && auditor.checked);
		var count = 0;
		document.querySelectorAll('#ur-access [data-key]').forEach(function (row) {
			var lv = after[row.dataset.key] || 'none', cell = row.lastElementChild;
			cell.textContent = data.levelLabels[lv];
			cell.className = 'ur-lv-' + lv;
			if (lv !== 'none') { count++; }
		});
		document.getElementById('ur-access-count').textContent = data.text.accessCount.replace('%s', count).replace('%s', Object.keys(data.keys).length);
		var diffBox = document.getElementById('ur-user-access-diff');
		diffBox.replaceChildren();
		diffBox.hidden = form.dataset.new === '1' || (String(form.dataset.originalRole) === pick.value && !!(user && user.auditor) === !!(auditor && auditor.checked));
		if (!diffBox.hidden) { appendDiff(diffBox, changes(before, after)); }
	}
	if (pick) { pick.addEventListener('change', render); }
	if (auditor) { auditor.addEventListener('change', render); }
	if (form) {
		render();
		form.addEventListener('submit', function (event) {
			if (event.defaultPrevented || form.dataset.accessConfirmed === '1') { return; }
			var user = data.users[form.elements.id.value];
			var isNew = form.dataset.new === '1';
			if (!isNew && String(form.dataset.originalRole) === pick.value && !!(user && user.auditor) === !!(auditor && auditor.checked)) { return; }
			event.preventDefault();
			if (!data.roles[pick.value]) { pick.focus(); return; }
			var submitter = event.submitter || null, ok = node('button', isNew ? (form.elements.mode.value === 'invite' ? data.text.invite : data.text.create) : data.text.apply, 'sk-btn sk-btn-primary');
			ok.type = 'button';
			ok.addEventListener('click', function () {
				window.urPeopleDialog.close();
				form.dataset.accessConfirmed = '1';
				if (form.requestSubmit) { form.requestSubmit(submitter); } else { form.submit(); }
				delete form.dataset.accessConfirmed;
			});
			window.urPeopleDialog.open(data.text.confirm, '', [ok]);
			var body = document.getElementById('ur-dlg-body');
			body.appendChild(node('p', data.text.newRole + ': ' + data.roles[pick.value].name));
			appendDiff(body, changes(levels(user, form.dataset.originalRole, user && user.auditor), levels(user, pick.value, auditor && auditor.checked)));
		});
	}
	window.UR_ACCESS = {
		previewSuggestion: function (id) {
			var user = data.users[id];
			if (user && data.roles[user.suggestion]) {
				appendDiff(document.getElementById('ur-dlg-body'), changes(levels(user, user.role, user.auditor), levels(user, user.suggestion, user.auditor)));
			}
		},
		previewBulk: function (action, checked) {
			var body = document.getElementById('ur-dlg-body');
			var counts = { active: 0, invited: 0, closed: 0 };
			checked.forEach(function (cb) { var user = data.users[cb.value]; if (user && Object.prototype.hasOwnProperty.call(counts, user.status)) { counts[user.status]++; } });
			body.appendChild(node('p', data.text.affected.replace('%s', checked.length)));
			body.appendChild(node('p', data.text.affectedStates.replace('%s', counts.active).replace('%s', counts.invited).replace('%s', counts.closed)));
			if (action === 'role' || action === 'suggest') { body.appendChild(node('p', data.text.rolesOnly, 'sk-muted')); }
			checked.forEach(function (cb) {
				var user = data.users[cb.value];
				if (!user) { return; }
				var detail = node('details', undefined, 'ur-bulk-preview');
				detail.appendChild(node('summary', user.name));
				if (action === 'role' || action === 'suggest') {
					var next = action === 'suggest' ? user.suggestion : document.querySelector('#ur-bulk select[name="role_id"]').value;
					if (!data.roles[next]) { detail.appendChild(node('p', data.text.unknown)); }
					else {
						detail.appendChild(node('p', data.text.current + ': ' + (data.roles[user.role] ? data.roles[user.role].name : data.text.none) + ' → ' + data.text.newRole + ': ' + data.roles[next].name));
						appendDiff(detail, changes(levels(user, user.role, user.auditor), levels(user, next, user.auditor)));
					}
				} else { detail.open = true; }
				body.appendChild(detail);
			});
		}
	};
})();
