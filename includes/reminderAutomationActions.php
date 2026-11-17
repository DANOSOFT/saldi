<?php
// includes/reminderAutomationActions.php — ver 5.0.0 — 2026.11.17
// Copyright (c) 2026 Danosoft ApS. GNU GPL version 2 or later.
// 20261117 CDX/ASR Validate the reminder queue's web actions before any state change.

require_once __DIR__ . '/reminderAutomation.php';

/**
 * Apply a posted operator action; the page redirects before rendering.
 *
 * @param array<string, mixed> $input Posted fields.
 * @return string Confirmation for the next page load.
 */
function reminder_automation_action(ReminderAutomation $service, array $input, string $csrf, string $ledger,
    string $actor, bool $canWrite, bool $canConfigure, callable $sender): string
{
    if (!$canWrite) throw new RuntimeException('Du har ikke adgang til at ændre rykkere.');
    if ($csrf === '' || !is_string($input['csrf_token'] ?? null) || !hash_equals($csrf, $input['csrf_token'])) {
        throw new RuntimeException('Siden er udløbet. Genindlæs den før du prøver igen.');
    }
    if (!is_string($input['ledger'] ?? null) || !hash_equals($ledger, $input['ledger'])) {
        throw new RuntimeException('Regnskabet er skiftet. Genindlæs rykkeroversigten.');
    }
    if (!$service->installed()) throw new RuntimeException('Regnskabet skal opdateres før automatisk rykkerflow kan bruges.');
    $action = is_string($input['action'] ?? null) ? $input['action'] : '';
    if ($action === 'configure') {
        if (!$canConfigure) throw new RuntimeException('Du har ikke adgang til at ændre salgsindstillinger.');
        $service->configure(is_string($input['mode'] ?? null) ? $input['mode'] : '', $actor);
        return 'Rykkerflowet er gemt.';
    }
    if ($action === 'run') {
        $result = $service->run($sender, $actor);
        return sprintf('Oprettet: %d. Sendt: %d. Kræver kontrol: %d. ', $result['created'], $result['sent'], $result['held'])
            . implode(' ', $result['errors']);
    }
    $id = filter_var($input['order_id'] ?? '', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
    if (!$id) throw new RuntimeException('Vælg en rykker.');
    if ($action === 'approve') {
        $service->approve($id, $actor, ($input['checked_uncertain'] ?? '') === 'yes');
        return 'Rykkeren er godkendt til næste kørsel.';
    }
    if ($action === 'hold') {
        $service->hold($id, $actor);
        return 'Rykkeren er sat på pause. Kladden er bevaret.';
    }
    throw new RuntimeException('Ukendt handling.');
}
