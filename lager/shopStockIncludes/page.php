<?php
// --- lager/shopStockIncludes/page.php --- 2026-10-05 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261005 CDX/PHR Show progress, pause/resume and per-shop retries for total-stock updates.
/**
 * Supplied by ../webshopStock.php:
 * @var array $endpoints
 * @var array|null $progress
 * @var string $csrf
 */
?>
<!doctype html>
<html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Opdater webshopbeholdning</title>
<link rel="stylesheet" href="../css/standard.css">
<style>
body{font-family:Arial,sans-serif;background:#f5f6f8;color:#172b45;margin:0}.shop-stock{max-width:900px;margin:28px auto;padding:24px;background:white;border:1px solid #d7dde5;border-radius:8px}.shop-stock h1{font-size:24px}.shop-stock p{line-height:1.5}.shop-stock label{display:block;margin:12px 0}.shop-stock button{padding:10px 16px;margin:4px;border:1px solid #bcc6d2;border-radius:4px;cursor:pointer}.shop-stock button.primary{background:#114691;color:white}.shop-stock button:disabled{opacity:.5;cursor:default}.shop-stock progress{width:100%;height:24px;margin-top:18px}.shop-stock table{width:100%;border-collapse:collapse}.shop-stock th,.shop-stock td{text-align:left;border-bottom:1px solid #ddd;padding:8px}.shop-stock .error{color:#a32020;white-space:pre-wrap}.shop-stock fieldset{border:1px solid #d7dde5;padding:12px}#errors{overflow:auto}
</style></head><body><main class="shop-stock">
<a href="lister/vareliste.php">← Tilbage til varelisten</a>
<h1>Opdater webshopbeholdning</h1>
<p>Send den samlede beholdning fra alle lagre til webshopperne. Varianter med webshopbinding medtages. For sæt beregnes antallet ud fra delvarerne. Priser ændres ikke.</p>
<p>Opdateringen omfatter alle åbne, lagerførte varer og sæt, uanset søgningen på varelisten. Beholdningerne læses løbende under kørslen.</p>
<fieldset id="shops"><legend>Webshops</legend>
<?php foreach ($endpoints as $slot => $endpoint) { ?>
<label><input type="checkbox" name="shop" value="<?= (int) $slot ?>" checked> Webshop <?= (int) $slot ?> · <?= htmlspecialchars((string) parse_url(shopApiUrl($endpoint), PHP_URL_HOST), ENT_QUOTES, 'UTF-8') ?></label>
<?php } ?>
<?php if (!$endpoints) { ?><p>Der er ikke konfigureret nogen webshopforbindelse under Indstillinger.</p><?php } ?>
</fieldset>
<p>Hold siden åben, mens opdateringen kører. Du kan sætte den på pause og fortsætte senere fra samme menupunkt.</p>
<div><button id="start" class="primary">Start ny opdatering</button><button id="resume">Fortsæt</button><button id="pause">Pause</button><button id="retry">Prøv fejl igen</button><button id="cancel">Afslut kørsel</button></div>
<progress id="progress" value="0" max="1"></progress>
<p id="summary" role="status" aria-live="polite"></p><p id="last"></p><p id="message" class="error" role="alert"></p>
<section id="errors" hidden><h2>Fejl</h2><p>De første 100 fejl vises. <a href="webshopStock.php?errors=1">Hent alle fejl som CSV</a></p><table><thead><tr><th>Vare</th><th>Webshop</th><th>Fejl</th></tr></thead><tbody id="errorRows"></tbody></table></section>
</main>
<script>
'use strict';
const csrf = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let job = <?= json_encode($progress, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let running = false, busy = false;
const el = id => document.getElementById(id);
function render() {
    const unfinished = job && job.status === 'paused';
    el('start').disabled = busy || running || unfinished || !document.querySelector('input[name=shop]');
    el('shops').disabled = busy || running || unfinished;
    el('resume').disabled = busy || running || !unfinished;
    el('pause').disabled = !running;
    el('retry').disabled = busy || running || !job || job.status !== 'done' || !job.failed;
    el('cancel').disabled = busy || running || !unfinished;
    el('progress').max = job ? Math.max(1, job.total) : 1;
    el('progress').value = job ? job.processed : 0;
    el('summary').textContent = !job ? 'Klar til opdatering.' :
        `${job.processed} af ${job.total} varer behandlet · ${job.ok} svar uden fejl · ${job.failed} fejl` +
        (running ? ' · Kører' : job.status === 'done' ? ' · Færdig' : job.status === 'cancelled' ? ' · Afsluttet' : ' · På pause') +
        (job.retrying ? ` · ${job.retrying} genforsøg tilbage` : '');
    el('last').textContent = job && job.last ? `Senest behandlet: ${job.last}` : '';
    el('errors').hidden = !job || !job.failed;
    el('errorRows').replaceChildren();
    for (const error of job ? job.errors : []) {
        const row = document.createElement('tr');
        for (const text of [error.item, error.shop, error.error]) {
            const cell = document.createElement('td'); cell.textContent = text; row.appendChild(cell);
        }
        el('errorRows').appendChild(row);
    }
}
async function request(action) {
    const body = new URLSearchParams({action, csrf, job: job ? job.id : '', api: '1'});
    if (action === 'start') for (const input of document.querySelectorAll('input[name=shop]:checked')) body.append('shops[]', input.value);
    const response = await fetch(window.location.pathname, {method:'POST', credentials:'same-origin', body, headers:{Accept:'application/json'}});
    let result;
    try { result = await response.json(); } catch (_) { throw new Error('Uventet svar. Genindlæs siden for at se den gemte status.'); }
    if (!response.ok || result.error) throw new Error(result.error || 'Opdateringen mislykkedes.');
    job = result.job;
}
async function run() {
    running = true; el('message').textContent = ''; render();
    while (running && job && job.status === 'paused') {
        busy = true; render();
        try { await request('batch'); }
        catch (error) { el('message').textContent = error.message; running = false; }
        finally { busy = false; render(); }
        if (running) await new Promise(resolve => setTimeout(resolve, 150));
    }
    running = false; render();
}
async function action(name, continueRun) {
    if (busy || running) return;
    busy = true; el('message').textContent = ''; render();
    try { await request(name); busy = false; if (continueRun) await run(); }
    catch (error) { el('message').textContent = error.message; }
    finally { busy = false; render(); }
}
el('start').addEventListener('click', () => action('start', true));
el('resume').addEventListener('click', run);
el('pause').addEventListener('click', () => { running = false; render(); });
el('retry').addEventListener('click', () => action('retry', true));
el('cancel').addEventListener('click', () => action('cancel', false));
render();
</script></body></html>
