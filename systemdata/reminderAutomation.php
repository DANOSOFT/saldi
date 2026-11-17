<?php
// systemdata/reminderAutomation.php — ver 5.0.0 — 2026.11.17
// Copyright (c) 2026 Danosoft ApS. GNU GPL version 2 or later.
// 20261117 CDX/ASR Opt-in reminder modes, approval queue and read-only due preview.

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
$css = '../css/unified-components.css';
$modulnr = 0;
$permission_key = 'debitor.konti';
require __DIR__ . '/../includes/connect.php';
require __DIR__ . '/../includes/online.php';
require_once __DIR__ . '/../includes/std_func.php';
require_once __DIR__ . '/../includes/settings/components.php';
require_once __DIR__ . '/settingsRegistry.php';
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
$tabs = settings_section_tabs('sales', (int)$sprog_id);
$labels = array('approval' => 'Afventer godkendelse', 'queued' => 'Godkendt til afsendelse', 'sending' => 'Afsendelse uafklaret',
    'sent' => 'Sendt', 'held' => 'På pause', 'failed' => 'Kræver kontrol');
ob_end_flush();
?>
<link rel="stylesheet" href="../css/unified-components.css">
<div class="st-page">
  <section class="st-phead"><div><h1><?= 'Automatiske rykkere' ?></h1><p class="st-lead"><?= st_h($regnskab) ?></p></div></section>
  <?php if ($flash) { ?><div class="st-flash st-flash-<?= st_h($flash[0]) ?>" role="status"><?= st_h($flash[1]) ?></div><?php } ?>
  <?php if (!$installed) { ?><p role="alert"><?= 'Automatisk rykkerflow er ikke installeret i dette regnskab endnu.' ?></p><?php } ?>
  <div class="st-layout st-layout-list">
    <nav class="st-tabs" aria-label="Salg"><ul><?php foreach ($tabs as $tab) { ?><li><a href="<?= st_h($tab['url']) ?>"<?= $tab['id'] === 'sales.reminders' ? ' class="on"' : '' ?>><?= st_h($tab['label']) ?></a></li><?php } ?></ul></nav>
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
        <p><?= 'Planlagt kørsel skal være tilknyttet regnskabet af administratoren. Du kan også starte en kørsel her.' ?></p>
        <?php if ($canWrite && $config['armed'] && $config['mode'] !== 'off') { ?>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= st_h($csrf) ?>"><input type="hidden" name="ledger" value="<?= st_h($db) ?>"><input type="hidden" name="action" value="run"><button class="st-btn" type="submit"><?= 'Kør det valgte rykkerflow nu' ?></button></form>
        <?php } ?>
      </section>
      <section class="st-card" style="padding:24px;overflow-x:auto">
        <h2><?= 'Kladder og afsendelser' ?></h2>
        <?php if (!$jobs) { ?><p><?= 'Der er endnu ingen automatiske rykkere.' ?></p><?php } else { ?>
        <table style="width:100%;text-align:left"><thead><tr><th><?= 'Rykker' ?></th><th><?= 'Kunde' ?></th><th><?= 'Status' ?></th><th><?= 'Handling' ?></th></tr></thead><tbody>
        <?php foreach ($jobs as $job) { ?>
        <tr><td><a href="../debitor/rykker.php?rykker_id=<?= (int)$job['order_id'] ?>"><?= st_h($job['fakturanr'] ?: $job['order_id']) ?></a></td><td><?= st_h($job['firmanavn']) ?></td>
          <td><?= st_h($labels[$job['state']] ?? $job['state']) ?><?php if ($job['sent_date']) { ?><br><?= st_h($job['sent_date']) ?><?php } ?><?php if ($job['error']) { ?><br><?= st_h($job['error']) ?><?php } ?></td>
          <td><?php if ($canWrite && $job['state'] !== 'sent') { ?>
            <form method="post" style="padding:12px 0"><input type="hidden" name="csrf_token" value="<?= st_h($csrf) ?>"><input type="hidden" name="ledger" value="<?= st_h($db) ?>"><input type="hidden" name="order_id" value="<?= (int)$job['order_id'] ?>">
              <?php if (in_array($job['state'], array('sending','failed'), true)) { ?><label style="display:block"><input type="checkbox" name="checked_uncertain" value="yes"> <?= 'Jeg har kontrolleret mailserverens log og bekræftet, at rykkeren ikke blev sendt.' ?></label><?php } ?>
              <?php if ($job['state'] !== 'queued') { ?><button class="st-btn" name="action" value="approve"<?= !$config['armed'] || $config['mode'] === 'off' ? ' disabled' : '' ?>><?= 'Godkend' ?></button><?php } ?>
              <?php if ($job['state'] !== 'sending') { ?><button class="st-btn" name="action" value="hold"><?= 'Sæt på pause' ?></button><?php } ?>
            </form><?php } ?></td></tr>
        <?php } ?></tbody></table><?php } ?>
      </section>
      <section class="st-card" style="padding:24px">
        <h2><?= 'Ved næste kørsel' ?></h2><p><?= 'Her vises kunder med forfaldne fakturaer og årsagen, når noget kræver manuel kontrol. Visningen opretter eller sender ingen rykkere.' ?></p>
        <?php if (!$preview) { ?><p><?= 'Ingen fakturaer er klar til en ny rykker.' ?></p><?php } ?>
        <ul><?php foreach ($preview as $candidate) { ?><li><?= st_h($candidate['name']) ?> — <?= (int)$candidate['level'] ?>. rykker: <?= st_h($candidate['reason'] ?: 'Klar') ?></li><?php } ?></ul>
        <p><?= 'I denne version behandles fakturaer i DKK med gyldig mailadresse. Åbne betalinger og kreditnotaer skal udlignes først. Eksisterende manuelle rykkerforløb fortsætter manuelt.' ?></p>
      </section>
    </main>
  </div>
</div>
<?= settings_breadcrumb_script(settings_breadcrumb('sales', 'Automatiske rykkere', (int)$sprog_id), $charset) ?>
