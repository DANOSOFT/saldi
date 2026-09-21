<?php
// 20260920 CDX/LH Build the read-only destination after a successful cash-journal save.

/**
 * Keep display context without replaying journal mutations on refresh or Back.
 *
 * @param array<string, mixed> $query Current query parameters.
 * @return string Relative URL for the saved journal.
 */
function journalSaveRedirectUrl($journalId, $focus, array $query) {
    $params = ['kladde_id' => (int) $journalId];
    foreach (['returside', 'sort', 'kksort', 'kkdir', 'popup', 'visipop'] as $key) {
        if (isset($query[$key]) && is_scalar($query[$key])) {
            $params[$key] = (string) $query[$key];
        }
    }
    if (is_string($focus) && preg_match('/^[a-z_]+[0-9]+$/i', $focus)) {
        $params['fokus'] = $focus;
    }
    return 'kassekladde.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}
