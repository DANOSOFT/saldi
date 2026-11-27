<?php
// ---- systemdata/usersRolesIncludes/peopleAccess.php --- ver 5.1.0 --- 2026.11.10 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261110 CDX/ASR Complete grouped access summaries, role-change preview data and people status filters.

/**
 * Quick filters count the same visible people as the table, including employees without a login.
 *
 * @param array<string, mixed> $vm From ur_view_model() and ur_people_model().
 */
function ur_view_people_filters(array $vm, callable $t): void
{
	$counts = array('' => count($vm['users']) + count($vm['noLogin']), 'active' => 0, 'invited' => 0, 'nologin' => count($vm['noLogin']), 'closed' => 0);
	foreach ($vm['users'] as $user) {
		$counts[$user['closed'] ? 'closed' : ($user['invited'] ? 'invited' : 'active')]++;
	}
	$labels = array('' => '2498|Alle', 'active' => 'people.has_access|Har adgang', 'invited' => 'people.invited|Inviterede', 'nologin' => 'people.no_login|Uden login', 'closed' => 'people.closed|Lukkede');
	?>
    <div class="ur-quick-filters" role="group" aria-label="<?= $t('5557|Status') ?>">
      <?php foreach ($labels as $status => $label) {
      	if ($status === 'nologin' && empty($vm['canOrg'])) {
      		continue;
      	}
      ?>
      <button type="button" class="sk-btn sk-btn-sm" data-people-status="<?= $status ?>" aria-pressed="false"><?= $t($label) ?> <span class="ur-filter-count"><?= $counts[$status] ?></span></button>
      <?php } ?>
    </div>
	<?php
}

/**
 * Show every registered capability individually, including dashboard, settings and sensitive operations.
 * The client updates levels and descriptions when another role is selected.
 *
 * @param array<string, mixed> $vm From ur_view_model().
 */
function ur_view_user_access(array $vm, callable $h, callable $t): void
{
	$user = $vm['editUser'];
	$initialRole = $user['role'];
	$initialLevels = $vm['roleLevels'][$user['role_id']] ?? perm_levels_from_legacy($user['rettigheder']);
	if ($user['isRevisor'] && $vm['enforceMode'] === 'deny') {
		foreach ($vm['roles'] as $role) {
			if ($role['key'] === 'revisor') {
				$initialLevels = $vm['roleLevels'][$role['id']] ?? array();
				break;
			}
		}
	}
	$groups = permission_groups();
	if (isset($groups['dashboard'])) {
		$dashboard = $groups['dashboard'];
		unset($groups['dashboard']);
		$groups['dashboard'] = $dashboard;
	}
	?>
      <section class="ur-role-summary" aria-labelledby="ur-access-label">
        <h3 id="ur-access-label"><?= $t('5933|Adgang fra rollen') ?></h3>
        <p id="ur-role-description" class="sk-muted"><?= $initialRole ? $h(perm_role_description($initialRole, (int) $vm['sprogId'])) : $t('5554|Ingen rolle') ?></p>
        <p id="ur-role-customized" class="sk-note sk-warn" hidden><?= $t('people.customized|Standardrollen er tilpasset. Oversigten viser de aktuelle rettigheder.') ?></p>
        <p id="ur-auditor-note" class="sk-note sk-info" hidden><?= $t('people.auditor_access|Regnskabets særlige revisorlogin følger rollen Revisor, når håndhævelse er slået til.') ?></p>
        <p class="ur-mode-note <?= $vm['enforceMode'] === 'deny' ? 'ur-mode-deny' : 'ur-mode-log' ?>"><?= $vm['enforceMode'] === 'deny' ? $t('people.mode_deny|Håndhævelse er slået til. Adgang kontrolleres efter rettighederne nedenfor.') : $t('people.mode_log|Logtilstand: almindelige rettigheder registreres til kontrol og blokerer ikke alle handlinger endnu. Særligt beskyttede funktioner kræver stadig adgang.') ?></p>
        <details id="ur-access-details">
          <summary><?= $t('people.all_rights|Se alle rettigheder') ?> <span id="ur-access-count" class="sk-muted" aria-live="polite"></span></summary>
          <div id="ur-access">
          <?php foreach ($groups as $group => $label) { ?>
            <section class="ur-access-group">
              <h4><?= $t($label) ?></h4>
              <ul class="sk-kv">
              <?php foreach (permission_registry() as $key => $def) {
              	if ($def['group'] !== $group) {
              		continue;
              	}
              ?>
                <li data-key="<?= $h($key) ?>"><span><?= $t($def['label']) ?><?php if ($def['dangerous'] || $key === 'settings.organisation.sensitive') { ?> <small class="ur-sensitive-label"><?= $t('people.sensitive|Følsom adgang') ?></small><?php } ?></span><strong class="ur-lv-<?= $h($initialLevels[$key] ?? 'none') ?>"><?= $h(perm_level_label($initialLevels[$key] ?? 'none', (int) $vm['sprogId'])) ?></strong></li>
              <?php } ?>
              </ul>
            </section>
          <?php } ?>
          </div>
        </details>
        <div id="ur-user-access-diff" aria-live="polite" hidden></div>
      </section>
	<?php
}

/**
 * Serialize role and user access data for previews without re-querying each role.
 *
 * @param array<string, mixed> $vm From ur_view_model().
 */
function ur_view_access_data(array $vm): void
{
	$sprog = (int) $vm['sprogId'];
	$data = array('levels' => $vm['roleLevels'], 'roles' => array(), 'users' => array(), 'keys' => array(), 'groups' => array(),
		'mode' => $vm['enforceMode'], 'auditorRole' => 0, 'levelLabels' => array(), 'text' => array());
	foreach (permission_groups() as $group => $label) {
		$data['groups'][$group] = findtekst($label, $sprog);
	}
	foreach (permission_registry() as $key => $def) {
		$data['keys'][$key] = array('label' => findtekst($def['label'], $sprog), 'group' => $def['group'], 'sensitive' => $def['dangerous'] || $key === 'settings.organisation.sensitive');
	}
	foreach (array('none', 'read', 'write') as $level) {
		$data['levelLabels'][$level] = perm_level_label($level, $sprog);
	}
	foreach ($vm['roles'] as $role) {
		$data['roles'][$role['id']] = array('name' => perm_role_name($role, $sprog), 'description' => perm_role_description($role, $sprog), 'changed' => !empty($role['changed']));
		if ($role['key'] === 'revisor') {
			$data['auditorRole'] = (int) $role['id'];
		}
	}
	foreach ($vm['users'] as $user) {
		$data['users'][$user['id']] = array('name' => $user['navn'], 'role' => $user['role_id'], 'auditor' => $user['isRevisor'], 'status' => $user['closed'] ? 'closed' : ($user['invited'] ? 'invited' : 'active'),
			'suggestion' => $user['suggestion'] ? (int) $user['suggestion']['id'] : 0,
			'levels' => $user['role_id'] > 0 ? array() : perm_levels_from_legacy($user['rettigheder']));
	}
	$texts = array(
		'confirm' => 'people.confirm_rights|Bekræft ændring af adgang',
		'apply' => 'people.apply_rights|Gem adgang',
		'invite' => '5795|Send invitation',
		'create' => '5952|Opret',
		'added' => 'people.access_added|Udvidet adgang',
		'removed' => 'people.access_removed|Reduceret adgang',
		'unchanged' => 'people.access_unchanged|Ingen ændringer i rettighederne.',
		'accessCount' => 'people.access_count|%s af %s rettigheder giver adgang',
		'affected' => 'people.affected_users|Berørte brugere: %s',
		'affectedStates' => 'roles.editor.affected|Berørte brugere: %s aktive · %s inviterede · %s lukkede',
		'rolesOnly' => 'people.assignment_only|Kun de valgte brugeres rolletildeling ændres. Rollernes rettigheder ændres ikke.',
		'current' => 'people.current_role|Nuværende rolle',
		'newRole' => 'people.new_role|Ny rolle',
		'none' => '5554|Ingen rolle',
		'unknown' => 'people.preview_unavailable|Forhåndsvisningen kunne ikke indlæses. Genindlæs siden før du gemmer.',
	);
	foreach ($texts as $key => $text) {
		$data['text'][$key] = findtekst($text, $sprog);
	}
	if ($vm['charset'] !== 'UTF-8') {
		array_walk_recursive($data, function (&$value) use ($vm): void {
			if (is_string($value)) {
				$value = mb_convert_encoding($value, 'UTF-8', $vm['charset']);
			}
		});
	}
	print '<script type="application/json" id="ur-access-data">' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}
