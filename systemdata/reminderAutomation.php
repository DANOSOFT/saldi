<?php
// systemdata/reminderAutomation.php — ver 5.1.1 — 2026.11.26
// Copyright (c) 2026 Danosoft ApS. GNU GPL version 2 or later.
// 20261117 CDX/ASR Opt-in reminder modes, approval queue and read-only due preview.
// 20261126 CL/ASR Stripe port (#1147): the page is drawn in this line's standard settings layout (includes/settings/layout.php, settings_layout_open() at sales.reminders), with the hub menu (Rykkere & inkasso marked), Tilbage, the breadcrumb and the stylesheet, instead of the redesign's flat tab list (settings_section_tabs()).
//                 The forms, actions and texts are the source's.
// 20261126 ASR Review I14: "Du kan også starte en kørsel her." is shown only with the run button (the flow is on here and the user may write).
// 20261126 ASR Review GPT-R-5: the check of the mail server's log is asked for every job with an uncertain attempt (jobs()' uncertain), also a paused one.
// 20261126 ASR Review GPT-R-6: a job with an uncertain attempt can also be registered as sent on the day the mail server's log shows (Registrér som sendt), without sending it again.
// 20261126 ASR Review AR2-1: a job whose reminder order is gone shows "Rykker slettet" (8233) without a link to the reminder page and without actions.

/**
 * Injected by connect.php and online.php below:
 * @var string $db
 * @var string $brugernavn
 * @var string $regnskab
 * @var int $sprog_id
 */
session_start();
$s_id = session_id();
ob_start();
$title = 'Automatiske rykkere';
$css = '../css/unified-components.css?v=20261108';
$modulnr = 0;
$permission_key = 'debitor.konti';
require __DIR__ . '/../includes/connect.php';
require __DIR__ . '/../includes/online.php';
require_once __DIR__ . '/../includes/std_func.php';
require_once __DIR__ . '/../includes/settings/components.php';
require_once __DIR__ . '/settingsRegistry.php';
require_once __DIR__ . '/../includes/settings/layout.php';
require_once __DIR__ . '/../includes/reminderAutomationActions.php';

$service = reminder_automation_service();
$installed = $service && $service->installed();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = (string) $_SESSION['csrf_token'];
$canWrite = perm_can('debitor.konti', 'write');
$canConfigure = $canWrite && perm_can('settings.sales', 'write');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!$service) throw new RuntimeException('Automatiske rykkere kræver PostgreSQL.');
        $message = reminder_automation_action($service, $_POST, $csrf, $db, $brugernavn, $canWrite, $canConfigure,
            'reminder_automation_render_send');
        $_SESSION['reminder_auto_flash'] = array('ok', $message);
    } catch (Throwable $error) {
        $_SESSION['reminder_auto_flash'] = array('err', $error->getMessage());
    }
    ob_end_clean();
    header('Location: reminderAutomation.php', true, 303);
    exit;
}
$config = $installed ? $service->config() : array('mode' => 'off', 'armed' => false, 'last_run' => null, 'last_error' => '');
$jobs = $installed ? $service->jobs() : array();
$preview = $installed ? $service->preview() : array();
$flash = $_SESSION['reminder_auto_flash'] ?? null;
unset($_SESSION['reminder_auto_flash']);
$labels = array('approval' => 'Afventer godkendelse', 'queued' => 'Godkendt til afsendelse', 'sending' => 'Afsendelse uafklaret',
    'sent' => 'Sendt', 'held' => 'På pause', 'failed' => 'Kræver kontrol');
ob_end_flush();
$notes = $flash ? '<div class="st-flash st-flash-' . st_h($flash[0]) . '" role="status">' . st_h($flash[1]) . '</div>' : '';
settings_layout_open(array('at' => 'sales.reminders', 'title' => 'Automatiske rykkere', 'lead' => $regnskab, 'notes' => $notes, 'legacy' => false));
?>
  <?php if (!$installed) { ?><p role="alert"><?= 'Automatisk rykkerflow er ikke installeret i dette regnskab endnu.' ?></p><?php } ?>
    <main class="st-form">
      <section class="st-card" style="padding:24px">
        <h2><?= 'Vælg rykkerflow' ?></h2>
        <p><?= 'Frister, gebyrer, renter og mailformat følger dine ' ?><a href="settingsSection.php?s=sales.reminders"><?= 'rykkerindstillinger' ?></a>.</p>
        <?php if ($config['mode'] !== 'off' && !$config['armed']) { ?><p role="alert"><?= 'Flowet er inaktivt her. Gem indstillingen for at aktivere det på denne installation.' ?></p><?php } ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= st_h($csrf) ?>"><input type="hidden" name="ledger" value="<?= st_h($db) ?>"><input type="hidden" name="action" value="configure">
          <fieldset<?= !$canConfigure || !$installed ? ' disabled' : '' ?> style="border:0;padding:0">
            <legend><?= 'Automatik' ?></legend>
            <?php foreach (array('off'=>'Slået fra','approval'=>'Opret kladder, som jeg godkender','automatic'=>'Opret og send automatisk') as $mode=>$label) { ?>
            <label style="display:block;margin:12px 0"><input type="radio" name="mode" value="<?= st_h($mode) ?>"<?= $config['mode'] === $mode ? ' checked' : '' ?>> <?= st_h($label) ?></label>
            <?php } ?>
            <button class="st-btn st-btn-primary" type="submit"><?= 'Gem rykkerflow' ?></button>
          </fieldset>
        </form>
        <p><?= 'Automatisk afsendelse gælder nye kladder. Ældre kladder afventer fortsat din godkendelse. Slår du flowet fra, bevares kladderne, og ventende afsendelser sættes på pause.' ?></p>
        <p><?= 'Seneste kørsel: ' ?><?= st_h($config['last_run'] ?: 'Ingen endnu') ?></p>
        <?php if ($config['last_error']) { ?><p role="alert" style="white-space:pre-line"><?= st_h($config['last_error']) ?></p><?php } ?>
        <?php $canRun = $canWrite && $config['armed'] && $config['mode'] !== 'off'; // 20261126 ASR Review I14: the sentence only with the button ?>
        <p><?= 'Planlagt kørsel skal være tilknyttet regnskabet af administratoren.' ?><?= $canRun ? ' ' . 'Du kan også starte en kørsel her.' : '' ?></p>
        <?php if ($canRun) { ?>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= st_h($csrf) ?>"><input type="hidden" name="ledger" value="<?= st_h($db) ?>"><input type="hidden" name="action" value="run"><button class="st-btn" type="submit"><?= 'Kør det valgte rykkerflow nu' ?></button></form>
        <?php } ?>
      </section>
      <section class="st-card" style="padding:24px;overflow-x:auto">
        <h2><?= 'Kladder og afsendelser' ?></h2>
        <?php if (!$jobs) { ?><p><?= 'Der er endnu ingen automatiske rykkere.' ?></p><?php } else { ?>
        <table style="width:100%;text-align:left"><thead><tr><th><?= 'Rykker' ?></th><th><?= 'Kunde' ?></th><th><?= 'Status' ?></th><th><?= 'Handling' ?></th></tr></thead><tbody>
        <?php foreach ($jobs as $job) { ?>
        <?php $deleted = ($job['deleted'] ?? 'f') === 't'; // 20261126 ASR Review AR2-1: the reminder order is gone, so no link to it and nothing to do ?>
        <tr><td><?php if ($deleted) { ?><?= st_h(reminder_txt(8233, 'Rykker slettet')) ?><?php } else { ?><a href="../debitor/rykker.php?rykker_id=<?= (int)$job['order_id'] ?>"><?= st_h($job['fakturanr'] ?: $job['order_id']) ?></a><?php } ?></td><td><?= st_h($job['firmanavn']) ?></td>
          <td><?= st_h($labels[$job['state']] ?? $job['state']) ?><?php if ($job['sent_date']) { ?><br><?= st_h($job['sent_date']) ?><?php } ?><?php if ($job['error']) { ?><br><?= st_h($job['error']) ?><?php } ?></td>
          <td><?php if ($canWrite && $job['state'] !== 'sent' && !$deleted) { ?>
            <form method="post" style="padding:12px 0"><input type="hidden" name="csrf_token" value="<?= st_h($csrf) ?>"><input type="hidden" name="ledger" value="<?= st_h($db) ?>"><input type="hidden" name="order_id" value="<?= (int)$job['order_id'] ?>">
              <?php if (in_array($job['state'], array('sending','failed'), true) || $job['uncertain'] === 't') { ?><label style="display:block"><input type="checkbox" name="checked_uncertain" value="yes"> <?= 'Jeg har kontrolleret mailserverens log og bekræftet, at rykkeren ikke blev sendt.' ?></label><?php } ?>
              <?php if ($job['state'] !== 'queued') { ?><button class="st-btn" name="action" value="approve"<?= !$config['armed'] || $config['mode'] === 'off' ? ' disabled' : '' ?>><?= 'Godkend' ?></button><?php } ?>
              <?php if ($job['state'] !== 'sending') { ?><button class="st-btn" name="action" value="hold"><?= 'Sæt på pause' ?></button><?php } ?>
            </form>
            <?php if ($job['uncertain'] === 't') { // 20261126 ASR Review GPT-R-6: or it was sent: the day it went out, nothing is sent again ?>
            <form method="post" style="padding:0 0 12px"><input type="hidden" name="csrf_token" value="<?= st_h($csrf) ?>"><input type="hidden" name="ledger" value="<?= st_h($db) ?>"><input type="hidden" name="order_id" value="<?= (int)$job['order_id'] ?>"><input type="hidden" name="action" value="confirm_sent">
              <label style="display:block"><input type="checkbox" name="checked_delivered" value="yes" required> <?= st_h(reminder_txt(8219, 'Jeg har kontrolleret mailserverens log og bekræftet, at rykkeren blev sendt.')) ?></label>
              <label style="display:block;margin:8px 0"><?= st_h(reminder_txt(8220, 'Sendt den')) ?> <input type="date" name="sent_date" required value="<?= st_h(reminder_dispatch_date((int)$job['order_id'])) ?>" min="<?= st_h($job['attempted_day']) ?>" max="<?= st_h(reminder_today()) ?>"></label>
              <button class="st-btn" type="submit"><?= st_h(reminder_txt(8218, 'Registrér som sendt')) ?></button>
            </form><?php } ?><?php } ?></td></tr>
        <?php } ?></tbody></table><?php } ?>
      </section>
      <section class="st-card" style="padding:24px">
        <h2><?= 'Ved næste kørsel' ?></h2><p><?= 'Her vises kunder med forfaldne fakturaer og årsagen, når noget kræver manuel kontrol. Visningen opretter eller sender ingen rykkere.' ?></p>
        <?php if (!$preview) { ?><p><?= 'Ingen fakturaer er klar til en ny rykker.' ?></p><?php } ?>
        <ul><?php foreach ($preview as $candidate) { ?><li><?= st_h($candidate['name']) ?> — <?= (int)$candidate['level'] ?>. rykker: <?= st_h($candidate['reason'] ?: 'Klar') ?></li><?php } ?></ul>
        <p><?= 'I denne version behandles fakturaer i DKK med gyldig mailadresse. Åbne betalinger og kreditnotaer skal udlignes først. Eksisterende manuelle rykkerforløb fortsætter manuelt.' ?></p>
      </section>
    </main>
<?php settings_layout_close(); ?>
