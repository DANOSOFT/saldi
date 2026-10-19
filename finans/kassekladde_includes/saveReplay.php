<?php
// 20260914 CDX/LH Recognize completed journal saves before staging or creating a journal.

/** @return string|null Key for an explicit save, scoped to the authenticated tenant/user and form. */
function journalSaveRequestKey(array $post, string $database, string $user): ?string {
	foreach (['copy2new', 'import', 'lookup', 'doPost', 'revert', 'offset', 'simulate', 'upload'] as $action) {
		if (!empty($post[$action])) {
			return null;
		}
	}
	if (empty($post['save']) && ($post['submit'] ?? null) !== 'save') {
		return null;
	}
	if (journalSaveFormKey($post, $database, $user) === null) {
		return null;
	}
	return hash('sha256', serialize([$database, $user, $post]));
}

/** @return string|null Stable form identity, also used to retry a failed first save in its existing journal. */
function journalSaveFormKey(array $post, string $database, string $user): ?string {
	$token = $post['kk_save_token'] ?? null;
	if (!is_string($token) || !preg_match('/\A[0-9a-f]{64}\z/D', $token)) {
		return null;
	}
	return hash('sha256', serialize([$database, $user, $token]));
}

/** @return int Journal allocated to this form, or the explicitly requested journal. */
function journalSaveTarget(array $session, ?string $formKey, int $requestedId): int {
	if ($requestedId > 0 || $formKey === null) {
		return $requestedId;
	}
	return (int)($session['kk_created_journals'][$formKey] ?? 0);
}

/** @return void Preserve a newly allocated journal even if its first save fails validation. */
function journalRememberCreation(array &$session, ?string $formKey, int $journalId): void {
	if ($formKey === null || $journalId <= 0) {
		return;
	}
	$created = $session['kk_created_journals'] ?? [];
	$created[$formKey] = $journalId;
	$session['kk_created_journals'] = array_slice($created, -128, null, true);
}

/** @return int|null Previously saved journal for this exact request; failures are never remembered. */
function journalSavedRequest(array $session, ?string $key): ?int {
	if ($key === null) {
		return null;
	}
	$id = $session['kk_completed_saves'][$key] ?? null;
	return is_int($id) && $id > 0 ? $id : null;
}

/** @return void Remember a successful save, with bounded session storage. */
function journalRememberSave(array &$session, ?string $key, int $journalId): void {
	if ($key === null || $journalId <= 0) {
		return;
	}
	$completed = $session['kk_completed_saves'] ?? [];
	$completed[$key] = $journalId;
	$session['kk_completed_saves'] = array_slice($completed, -128, null, true);
}
