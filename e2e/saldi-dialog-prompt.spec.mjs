// 20261116 CL/ASR Created: prompt() under Saldi's message box (javascript/saldi-dialog.js). OK on a question does the action again, and a
//                 prompt() asked before the question in that action gets its answer back, only for the same question and in the same order.
//                 The serial number rename (lager/lister/serialnumber.php) asked the new name a second time after OK.
//                 A prompt() in an event the page sends itself while OK does the action again asks the browser, also when it asks the same question.
// 20261117 CL/ASR A text and default value split differently is another question; an event a window listener of the page sends at the
//                 same element before the box's own click, change or submit asks the browser; prompt() shows its arguments as the browser
//                 does without the box, each converted once (a Symbol throws the browser's own error). OK's click does what el.click()
//                 did on a submit button, a checkbox, a radio, a link, a label and an element disabled meanwhile. A window listener the
//                 page added before the box that asks a prompt() of its own in the click, change or submit gets its answer back in the
//                 event OK sends, as the element's own prompt does, also when requestSubmit() refuses the submit button and its click
//                 sends the form. A fieldset disabled while the question is open gets its click, as el.click() gives it.
// 20261119 CL/ASR A form OK sends by its button's click (requestSubmit() missing or refusing the button) gets no saved answers: its prompt() asks the browser again.
//                 A click a listener of the page sends at that button meanwhile starts a submit whose prompt() asks the browser too, and neither submit takes the other's answer.
// Self-contained: the rename and delete handlers are cut from serialnumber.php and run on a stub page with the real
// saldi-dialog.js, and every request is answered by page.route, so it needs neither the docker stack nor the seeded
// login, and it runs with the rest of e2e/ in CI. Every browser box is recorded as type:message, and its answer comes
// from the test's list (a string is typed, null is Annuller in the browser's prompt).
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const repo = fileURLToPath(new URL('..', import.meta.url));

// The two onclick handlers of the serial number list, with the PHP values as jsString() and htmlspecialchars() print them.
const handlers = [...readFileSync(repo + 'lager/lister/serialnumber.php', 'utf8').matchAll(/<svg\s+onclick="([^"]*)"/g)].map(m => m[1]);
const literal = s => JSON.stringify(s).replace(/[\u0080-￿]/g, c => '\\u' + c.charCodeAt(0).toString(16).padStart(4, '0')).replace(/"/g, '&quot;');
const [renameHandler, deleteHandler] = handlers.map(h => h
    .replace('{$renamePrompt}', literal('Hvad skal serienummeret omdøbes til?\nSerienummer: SN-1'))
    .replace('{$renameConfirm}', literal('Omdøb SN-1 til '))
    .replace('{$deleteConfirm}', literal('Slet SN-1?'))
    .replace(/\{\$row\['id'\]\}/g, '8')
    .replace(/\{\$row\['vare_id'\]\}/g, '13'));
const RENAME = 'prompt:Hvad skal serienummeret omdøbes til?\nSerienummer: SN-1';
const posted = name => `serienr_id=8&vare_id=13&rename=${name}&delete=`;

const ICON = 'xmlns="http://www.w3.org/2000/svg" height="22px" width="22px" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>';
const PAGE = `<!doctype html><html><head><meta charset="utf-8">
<script>window.SALDI_DIALOG_CFG = {t:{}, hidden:[], csrf:'', prefUrl:''}; window.LOG = []; function log(s) { window.LOG.push(String(s)); }
// A window listener the page adds before the box: at an element of class early it sends a click, change or submit of its own first.
['click', 'change', 'submit'].forEach(function (type) {
    window.addEventListener(type, function (e) {
        if (!e.target.classList || !e.target.classList.contains('early') || window.inEarly) return;
        window.inEarly = true;
        try { e.target.dispatchEvent(new Event(type, { bubbles: true, cancelable: true })); } finally { window.inEarly = false; }
    }, true);
});
// Another window listener the page adds before the box: at an element of class earlyAsk it asks a prompt() of its own (data-ask)
// before the element's own handler asks.
['click', 'change', 'submit'].forEach(function (type) {
    window.addEventListener(type, function (e) {
        if (e.target.classList && e.target.classList.contains('earlyAsk')) window.earlyAnswer = prompt(e.target.getAttribute('data-ask'));
    }, true);
});
// A click listener the page adds before the box: once window.clickAgain is set, a click at an element of class again first
// sends a click of its own at it, a MouseEvent, so the browser does what it does on it (a submit button sends its form).
window.addEventListener('click', function (e) {
    if (!window.clickAgain || window.inAgain || !e.target.classList || !e.target.classList.contains('again')) return;
    window.inAgain = true;
    try { e.target.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true })); } finally { window.inAgain = false; }
}, true);</script>
<script src="/javascript/saldi-dialog.js"></script>
</head><body>
<div style="display: flex">
<svg id="rename" onclick="${renameHandler}" style="cursor: pointer" ${ICON}
<svg id="delete" onclick="${deleteHandler}" style="cursor: pointer" ${ICON}
</div>
<form method="post" action="/save">
<input name="serienr_id" type="hidden"><input name="vare_id" type="hidden"><input name="rename" type="hidden"><input name="delete" type="hidden">
</form>
<button type="button" id="later" onclick="log('later=' + prompt('Andet spørgsmål?'))">later</button>
<button type="button" id="keyed" data-confirm-key="probe" onclick="var a = prompt('Nøgle?'); if (confirm('Gem ' + a + '?')) log('ok=' + a); else log('no=' + a);">keyed</button>
<button type="button" id="throws" onclick="var a = prompt('Kast?'); if (confirm('Kast ' + a + '?')) log('ok=' + a);">throws</button>
<button type="button" id="more" onclick="var a = prompt('Antal?'); if (!confirm('Brug ' + a + '?')) return; log('a=' + a + ',b=' + prompt('Antal?') + ',c=' + prompt('Note?'));">more</button>
<button type="button" id="timer" onclick="var a = prompt('Navn?'); setTimeout(function () { log('timer=' + prompt('I timer?')); }, 0); if (confirm('Brug ' + a + '?')) log('ok=' + a);">timer</button>
<input id="name" value="">
<button type="button" id="ifEmpty" onclick="var f = document.getElementById('name'); if (!f.value) f.value = prompt('Navn hvis tom?'); if (!confirm('Gem ' + f.value + '?')) return; log('name=' + f.value + ',note=' + prompt('Note?'));">ifEmpty</button>
<button type="button" id="branch" onclick="window.runs = (window.runs || 0) + 1; var a = window.runs === 1 ? prompt('Første gren?') : prompt('Anden gren?'); if (confirm('Gren?')) log('branch=' + a);">branch</button>
<span id="inner"></span>
<button type="button" id="nested" onclick="document.getElementById('inner').dispatchEvent(new Event('ask')); var n = prompt('Ydre?'); if (confirm('Ydre ' + n + '?')) log('outer=' + n + ',inner=' + window.innerAnswer);">nested</button>
<button type="button" id="nestedSame" onclick="document.getElementById('inner').dispatchEvent(new Event('askSame')); var n = prompt('Navn?'); if (confirm('Gem navnene?')) log('outer=' + n + ',inner=' + window.innerAnswer); else log('outerno');">nestedSame</button>
<button type="button" id="selfSame" onclick="if (window.inSelf) { window.innerAnswer = prompt('Navn?'); return; } window.inSelf = true; this.dispatchEvent(new MouseEvent('click', { bubbles: true })); window.inSelf = false; var n = prompt('Navn?'); if (confirm('Gem begge?')) log('outer=' + n + ',inner=' + window.innerAnswer); else log('selfno');">selfSame</button>
<button type="button" id="split" onclick="window.splits = (window.splits || 0) + 1; var a = window.splits === 1 ? prompt('A\\nB', 'C') : prompt('A', 'B\\nC'); if (confirm('Delt?')) log('split=' + a);">split</button>
<button type="button" class="early" id="earlyClick" onclick="if (window.inEarly) { window.innerAnswer = prompt('Navn?'); return; } var n = prompt('Navn?'); if (confirm('Gem tidligt?')) log('outer=' + n + ',inner=' + window.innerAnswer); else log('earlyno');">earlyClick</button>
<input class="early" id="earlyChange" onchange="if (window.inEarly) { window.innerAnswer = prompt('Navn?'); return; } var n = prompt('Navn?'); if (confirm('Skift tidligt?')) log('outer=' + n + ',inner=' + window.innerAnswer); else log('earlyno');">
<form class="early" id="earlySubmit" action="/save" method="post" onsubmit="if (window.inEarly) { window.innerAnswer = prompt('Navn?'); return false; } var n = prompt('Navn?'); if (!confirm('Send tidligt?')) { log('earlyno'); return false; } this.outer.value = n; this.inner.value = window.innerAnswer;">
<input name="outer" type="hidden"><input name="inner" type="hidden"><button type="submit" name="send" value="1" id="earlySend">earlySend</button>
</form>
<button type="button" class="earlyAsk" data-ask="Navn?" id="askClick" onclick="var n = prompt('Navn?'); if (confirm('Gem med svar?')) log('early=' + window.earlyAnswer + ',own=' + n); else log('askno');">askClick</button>
<input class="earlyAsk" data-ask="Navn?" id="askChange" onchange="var n = prompt('Navn?'); if (confirm('Skift med svar?')) log('early=' + window.earlyAnswer + ',own=' + n); else log('askno');">
<form class="earlyAsk" data-ask="Navn?" id="askSubmit" action="/save" method="post" onsubmit="var n = prompt('Navn?'); if (!confirm('Send med svar?')) { log('askno'); return false; } this.early.value = window.earlyAnswer; this.own.value = n;">
<input name="early" type="hidden"><input name="own" type="hidden"><button type="submit" name="send" value="1" id="askSend">askSend</button>
</form>
<form id="againSubmit" action="/save" method="post" onsubmit="if (window.inAgain) { window.innerAnswer = prompt('Navn?'); return false; } var n = prompt('Navn?'); if (!confirm('Send igen?')) { log('againno'); return false; } this.outer.value = n; this.inner.value = window.innerAnswer;">
<input name="outer" type="hidden"><input name="inner" type="hidden"><button type="submit" name="send" value="1" class="again" id="againSend">againSend</button>
</form>
<form action="/save" method="post"><input name="felt" type="hidden" value="1">
<button type="submit" name="gem" value="ja" id="sendForm" onclick="return confirm('Send formularen?')">sendForm</button>
<button type="submit" name="aldrig" value="ja" id="sendNever" onclick="if (confirm('Send aldrig?')) log('never'); event.preventDefault();">sendNever</button>
</form>
<input type="checkbox" id="tick" onclick="return confirm('Sæt flueben?')">
<input type="radio" name="valg" id="valg1" checked><input type="radio" name="valg" id="valg2" onclick="return confirm('Skift valg?')">
<a href="/save?link=1" id="link" onclick="return confirm('Følg linket?')">link</a>
<a href="javascript:log('js-link')" id="jsLink" onclick="return confirm('Kør linket?')">jsLink</a>
<input type="checkbox" id="labelled"><label for="labelled" id="label" onclick="return confirm('Etiketten?')">label</label>
<button type="button" id="goesOff" onclick="if (confirm('Slås fra?')) log('goesOff')">goesOff</button>
<fieldset id="group" style="min-height: 40px" onclick="if (event.target === this && confirm('Feltgruppen?')) log('group')"></fieldset>
<script>document.getElementById('inner').addEventListener('ask', function () { window.innerAnswer = prompt('Indre?'); });
document.getElementById('inner').addEventListener('askSame', function () { window.innerAnswer = prompt('Navn?'); });</script>
</body></html>`;

// Serves the stub page and saldi-dialog.js from this checkout, records the form posts and every browser box, and answers
// the browser's prompts from `answers` in turn (undefined: OK on the default value). box: false serves the page without
// saldi-dialog.js, so every box is the browser's own.
async function openPage(page, answers, { box = true } = {}) {
    const posts = [];
    const asked = [];
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('dialog', dialog => {
        asked.push(dialog.type() + ':' + dialog.message() + (dialog.defaultValue() ? ' [' + dialog.defaultValue() + ']' : ''));
        if (dialog.type() !== 'prompt') return dialog.dismiss();
        const answer = answers.length ? answers.shift() : 'NOT EXPECTED';
        if (answer === null) return dialog.dismiss();
        return answer === undefined ? dialog.accept(dialog.defaultValue()) : dialog.accept(answer);
    });
    await page.route(url => url.hostname !== 'saldi.test', route => route.abort());
    await page.route('http://saldi.test/**', route => {
        const path = new URL(route.request().url()).pathname;
        if (path === '/save') {
            posts.push(route.request().postData() || '');
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body: '<!doctype html><title>saved</title>saved' });
        }
        if (path === '/javascript/saldi-dialog.js') return route.fulfill({ path: repo + 'javascript/saldi-dialog.js' });
        return route.fulfill({ contentType: 'text/html; charset=utf-8', body: box ? PAGE : PAGE.replace('<script src="/javascript/saldi-dialog.js"></script>\n', '') });
    });
    await page.goto('http://saldi.test/lager/lister/serialnumber.php');
    return { posts, asked, errors };
}

const question = page => page.locator('#sdlg-root .sdlg-msg');
const ok = page => page.locator('#sdlg-root .sdlg-primary').click();
const cancel = page => page.locator('#sdlg-root .sdlg-btn:not(.sdlg-primary)').click();
const log = page => page.evaluate(() => window.LOG.join(' | '));

test.describe('prompt() under Saldi\'s message box', () => {
    test('serial number rename, OK: the new name is asked once and posted once', async ({ page }) => {
        expect(handlers, 'the rename and delete icons of lager/lister/serialnumber.php').toHaveLength(2);
        const box = await openPage(page, ['NEW-1', 'NEW-2']);
        await page.locator('#rename').click();
        await expect(question(page)).toHaveText('Omdøb SN-1 til NEW-1?');
        await ok(page);
        await expect(page).toHaveTitle('saved');
        expect(box.posts).toEqual([posted('NEW-1')]);
        expect(box.asked, 'the new name is asked once').toEqual([RENAME]);
    });

    for (const how of ['Annullér', 'Escape']) {
        test(`serial number rename, ${how}: nothing is posted, the name is cleared, and a later prompt asks the browser`, async ({ page }) => {
            const box = await openPage(page, ['NEW-1', 'LATER']);
            await page.locator('#rename').click();
            await expect(question(page)).toHaveText('Omdøb SN-1 til NEW-1?');
            if (how === 'Escape') await page.keyboard.press('Escape');
            else await cancel(page);
            await expect(question(page)).toHaveCount(0);
            await expect(page.locator('[name=rename]')).toHaveValue('');
            await page.locator('#later').click();
            await expect.poll(() => log(page)).toBe('later=LATER');
            expect(box.posts).toEqual([]);
            expect(box.asked).toEqual([RENAME, 'prompt:Andet spørgsmål?']);
        });
    }

    test('serial number delete asks no prompt: OK posts once', async ({ page }) => {
        const box = await openPage(page, []);
        await page.locator('#delete').click();
        await expect(question(page)).toHaveText('Slet SN-1?');
        await ok(page);
        await expect(page).toHaveTitle('saved');
        expect(box.posts).toEqual(['serienr_id=8&vare_id=13&rename=&delete=1']);
        expect(box.asked).toEqual([]);
    });

    test('Vis ikke igen and OK: the action is done again with the answer, and the next prompts ask the browser', async ({ page }) => {
        const box = await openPage(page, ['K1', 'K2', 'LATER']);
        await page.locator('#keyed').click();
        await expect(question(page)).toHaveText('Gem K1?');
        await page.locator('#sdlg-root .sdlg-dont input').check();
        await ok(page);
        await page.locator('#keyed').click();
        await page.locator('#later').click();
        await expect.poll(() => log(page)).toBe('no=K1 | ok=K1 | ok=K2 | later=LATER');
        expect(box.asked).toEqual(['prompt:Nøgle?', 'prompt:Nøgle?', 'prompt:Andet spørgsmål?']);
    });

    test('OK, and doing the action again throws: a later prompt asks the browser', async ({ page }) => {
        const box = await openPage(page, ['X1', 'LATER']);
        await page.evaluate(() => {
            const el = document.getElementById('throws');
            el.click = el.dispatchEvent = () => { throw new Error('the action cannot be done again'); };
        });
        await page.locator('#throws').click();
        await expect(question(page)).toHaveText('Kast X1?');
        await ok(page);
        await expect.poll(() => box.errors).toContain('the action cannot be done again');
        await expect(question(page)).toHaveCount(0);
        await page.locator('#later').click();
        await expect.poll(() => log(page)).toBe('later=LATER');
        expect(box.asked).toEqual(['prompt:Kast?', 'prompt:Andet spørgsmål?']);
    });

    test('the action done again asks more prompts than it got answers for: those ask the browser', async ({ page }) => {
        const box = await openPage(page, ['A1', 'B1', 'C1']);
        await page.locator('#more').click();
        await expect(question(page)).toHaveText('Brug A1?');
        await ok(page);
        await expect.poll(() => log(page)).toBe('a=A1,b=B1,c=C1');
        expect(box.asked).toEqual(['prompt:Antal?', 'prompt:Antal?', 'prompt:Note?']);
    });

    test('prompt() outside any event is the browser\'s own', async ({ page }) => {
        const box = await openPage(page, ['X', undefined, null]);
        expect(await page.evaluate(() => prompt('Uden hændelse?'))).toBe('X');
        expect(await page.evaluate(() => prompt('Med standard?', 'std'))).toBe('std');
        expect(await page.evaluate(() => prompt('Annuller?'))).toBeNull();
        expect(box.asked).toEqual(['prompt:Uden hændelse?', 'prompt:Med standard? [std]', 'prompt:Annuller?']);
        await expect(question(page)).toHaveCount(0);
    });

    test('prompt() in a timer the action starts asks the browser each time, also while the action is done again', async ({ page }) => {
        const box = await openPage(page, ['N1', 'T1', 'T2']);
        await page.locator('#timer').click();
        await expect(question(page)).toHaveText('Brug N1?');
        await expect.poll(() => log(page)).toBe('timer=T1');
        await ok(page);
        await expect.poll(() => log(page)).toBe('timer=T1 | ok=N1 | timer=T2');
        expect(box.asked).toEqual(['prompt:Navn?', 'prompt:I timer?', 'prompt:I timer?']);
    });

    test('the action done again skips the prompt it answered (the field is filled now): the next prompt asks the browser', async ({ page }) => {
        const box = await openPage(page, ['NAME1', 'NOTE1']);
        await page.locator('#ifEmpty').click();
        await expect(question(page)).toHaveText('Gem NAME1?');
        await ok(page);
        await expect.poll(() => log(page)).toBe('name=NAME1,note=NOTE1');
        expect(box.asked).toEqual(['prompt:Navn hvis tom?', 'prompt:Note?']);
    });

    test('the action done again asks another question than the first time: the browser asks it', async ({ page }) => {
        const box = await openPage(page, ['FIRST', 'SECOND']);
        await page.locator('#branch').click();
        await expect(question(page)).toHaveText('Gren?');
        await ok(page);
        await expect.poll(() => log(page)).toBe('branch=SECOND');
        expect(box.asked).toEqual(['prompt:Første gren?', 'prompt:Anden gren?']);
    });

    test('a prompt in an event the page sends itself asks the browser again; the action\'s own prompt keeps its answer', async ({ page }) => {
        const box = await openPage(page, ['IN1', 'OUT1', 'IN2']);
        await page.locator('#nested').click();
        await expect(question(page)).toHaveText('Ydre OUT1?');
        await ok(page);
        await expect.poll(() => log(page)).toBe('outer=OUT1,inner=IN2');
        expect(box.asked).toEqual(['prompt:Indre?', 'prompt:Ydre?', 'prompt:Indre?']);
        await expect(question(page)).toHaveCount(0);
    });

    test('a prompt in an event the page sends itself asks the same question first: it asks the browser again, and the action\'s own prompt keeps its answer', async ({ page }) => {
        const box = await openPage(page, ['IN1', 'OUT1', 'IN2']);
        await page.locator('#nestedSame').click();
        await expect(question(page)).toHaveText('Gem navnene?');
        await ok(page);
        await expect.poll(() => log(page)).toBe('outerno | outer=OUT1,inner=IN2');
        expect(box.asked).toEqual(['prompt:Navn?', 'prompt:Navn?', 'prompt:Navn?']);
        await expect(question(page)).toHaveCount(0);
    });

    test('the page sends a click of its own on the same button before the action\'s prompt: only the click OK sends gets the answer', async ({ page }) => {
        const box = await openPage(page, ['IN1', 'OUT1', 'IN2']);
        await page.locator('#selfSame').click();
        await expect(question(page)).toHaveText('Gem begge?');
        await ok(page);
        await expect.poll(() => log(page)).toBe('selfno | outer=OUT1,inner=IN2');
        expect(box.asked).toEqual(['prompt:Navn?', 'prompt:Navn?', 'prompt:Navn?']);
        await expect(question(page)).toHaveCount(0);
    });

    test('a text and default value split differently is another question: the action done again asks the browser', async ({ page }) => {
        const box = await openPage(page, ['FIRST', 'SECOND']);
        await page.locator('#split').click();
        await expect(question(page)).toHaveText('Delt?');
        await ok(page);
        await expect.poll(() => log(page)).toBe('split=SECOND');
        expect(box.asked).toEqual(['prompt:A\nB [C]', 'prompt:A [B\nC]']);
    });

    // The page's window listener was added before the box's, so its own event reaches the element first.
    for (const how of ['click', 'change', 'submit']) {
        test(`a window listener of the page sends a ${how} of its own at the element first: it asks the browser again, and the ${how} OK sends gets the answer`, async ({ page }) => {
            const box = await openPage(page, ['IN1', 'OUT1', 'IN2']);
            if (how === 'click') await page.locator('#earlyClick').click();
            if (how === 'change') { await page.locator('#earlyChange').fill('ny'); await page.keyboard.press('Tab'); }
            if (how === 'submit') await page.locator('#earlySend').click();
            await expect(question(page)).toHaveText({ click: 'Gem tidligt?', change: 'Skift tidligt?', submit: 'Send tidligt?' }[how]);
            await ok(page);
            if (how === 'submit') {
                await expect(page).toHaveTitle('saved');
                expect(box.posts).toEqual(['outer=OUT1&inner=IN2&send=1']);
            } else {
                await expect.poll(() => log(page)).toBe('earlyno | outer=OUT1,inner=IN2');
            }
            expect(box.asked).toEqual(['prompt:Navn?', 'prompt:Navn?', 'prompt:Navn?']);
        });
    }

    // That listener was added before the box's own listeners, so it sees the event OK sends before anything else of the box does.
    for (const how of ['click', 'change', 'submit']) {
        for (const ask of ['Navn?', 'Tidlig?']) {
            test(`a window listener of the page asks a prompt() of its own (${ask === 'Navn?' ? 'the same question' : 'another question'}) in the ${how} first: in the ${how} OK sends both get their answers back, in order`, async ({ page }) => {
                const box = await openPage(page, ['E1', 'O1']);
                const el = { click: '#askClick', change: '#askChange', submit: '#askSubmit' }[how];
                await page.locator(el).evaluate((e, q) => e.setAttribute('data-ask', q), ask);
                if (how === 'click') await page.locator('#askClick').click();
                if (how === 'change') { await page.locator('#askChange').fill('ny'); await page.keyboard.press('Tab'); }
                if (how === 'submit') await page.locator('#askSend').click();
                await expect(question(page)).toHaveText({ click: 'Gem med svar?', change: 'Skift med svar?', submit: 'Send med svar?' }[how]);
                await ok(page);
                if (how === 'submit') {
                    await expect(page).toHaveTitle('saved');
                    expect(box.posts).toEqual(['early=E1&own=O1&send=1']);
                } else {
                    await expect.poll(() => log(page)).toBe('askno | early=E1,own=O1');
                }
                expect(box.asked, 'each question is asked once, as without the box').toEqual(['prompt:' + ask, 'prompt:Navn?']);
            });
        }
    }

    // requestSubmit() refuses the button (as when it is no longer this form's submit button), or the browser has none: the
    // button's click sends the form. Its submit cannot be told from one a click the page sends at the same button starts
    // (below), so it gets no saved answers: every prompt() in it asks the browser again, as before the box kept answers.
    for (const how of ['refuses the submit button', 'is missing']) {
        for (const early of [false, true]) {
            test(`requestSubmit() ${how}: the button's click sends the form, and ${early ? 'the window listener\'s prompt() and the form\'s own ask' : 'its prompt() asks'} the browser again, and the answers given then are posted`, async ({ page }) => {
                const box = await openPage(page, early ? ['E1', 'O1', 'E2', 'O2'] : ['O1', 'O2']);
                await page.locator('#askSubmit').evaluate((form, [missing, early]) => {
                    if (!early) form.classList.remove('earlyAsk');
                    form.requestSubmit = missing ? undefined : function () { throw new DOMException('refused', 'NotFoundError'); };
                }, [how === 'is missing', early]);
                await page.locator('#askSend').click();
                await expect(question(page)).toHaveText('Send med svar?');
                await ok(page);
                await expect(page).toHaveTitle('saved');
                expect(box.posts).toEqual([early ? 'early=E2&own=O2&send=1' : 'early=undefined&own=O2&send=1']);
                expect(box.asked).toEqual(Array(early ? 4 : 2).fill('prompt:Navn?'));
                expect(box.errors).toEqual([]);
            });
        }
        // While the question is open, the page starts sending a click of its own at the submit button whenever it is
        // clicked (a capture listener on the window). OK's click of the button then makes two trusted submits of the form
        // from that button: the listener's first, then OK's. Each asks the browser; neither gets the other's answer.
        test(`requestSubmit() ${how}, and a click listener of the page sends a click of its own at the same button: each submit asks the browser, and neither takes the other's answer`, async ({ page }) => {
            const box = await openPage(page, ['O1', 'I2', 'O2']);
            await page.locator('#againSend').click();
            await expect(question(page)).toHaveText('Send igen?');
            await page.locator('#againSubmit').evaluate((form, missing) => {
                form.requestSubmit = missing ? undefined : function () { throw new DOMException('refused', 'NotFoundError'); };
                window.clickAgain = true;
            }, how === 'is missing');
            await ok(page);
            await expect(page).toHaveTitle('saved');
            expect(box.posts, 'the listener\'s submit keeps the answer the browser gave it, and the submit OK sends posts its own').toEqual(['outer=O2&inner=I2&send=1']);
            expect(box.asked).toEqual(['prompt:Navn?', 'prompt:Navn?', 'prompt:Navn?']);
            expect(box.errors).toEqual([]);
        });
    }

    test('prompt() shows its text and default value as the browser does without the box, each converted once', async ({ page }) => {
        const calls = [
            () => prompt(),
            () => prompt(undefined),
            () => prompt('x'),
            () => prompt('x', undefined),
            () => prompt('x', null),
            () => prompt(null, undefined),
            () => prompt(5, 6),
            () => { let n = 0; const a = prompt({ toString() { return 'Spørgsmål ' + (++n); } }); return a + ' toString=' + n; },
            () => { let n = 0; const a = prompt('Tekst', { toString() { return 'Standard ' + (++n); } }); return a + ' toString=' + n; },
            () => prompt({ valueOf() { return 'valueOf'; }, toString() { return 'toString'; } }),
            () => { try { return prompt(Symbol('s')); } catch (e) { return e.constructor.name + ': ' + e.message; } },
            () => { try { return prompt('x', Symbol('s')); } catch (e) { return e.constructor.name + ': ' + e.message; } },
            () => { let n = 0; try { return prompt({ toString() { return 'Tekst ' + (++n); } }, Symbol('s')); } catch (e) { return e.constructor.name + ': ' + e.message + ' toString=' + n; } },
        ];
        const shown = {};
        for (const withBox of [false, true]) {
            const box = await openPage(page, calls.map(() => undefined), { box: withBox });
            expect(await page.evaluate(() => typeof window.SaldiDialog)).toBe(withBox ? 'object' : 'undefined');
            const returned = [];
            for (const call of calls) returned.push(await page.evaluate(call));
            shown[withBox ? 'box' : 'browser'] = { asked: box.asked, returned };
            page.removeAllListeners('dialog');
            await page.unrouteAll();
        }
        expect(shown.browser.asked).toEqual(['prompt:', 'prompt:', 'prompt:x', 'prompt:x', 'prompt:x [null]', 'prompt:null', 'prompt:5 [6]',
            'prompt:Spørgsmål 1', 'prompt:Tekst [Standard 1]', 'prompt:toString']);
        expect(shown.browser.returned).toEqual(['', '', '', '', 'null', '', '6', ' toString=1', 'Standard 1 toString=1', '', expect.stringMatching(/^TypeError: /),
            expect.stringMatching(/^TypeError: /), expect.stringMatching(/^TypeError: .* toString=1$/)]);
        expect(shown.box).toEqual(shown.browser);
        await expect(question(page)).toHaveCount(0);
    });
});

// OK does the click again as an event the box makes, so it is the event OK sends (above); what the browser does on the
// element is what el.click() did.
test.describe('OK does the click again with what the browser does on the element', () => {
    const cases = [
        ['a submit button sends its form with the button', '#sendForm', 'Send formularen?', async (page, box) => {
            await expect(page).toHaveTitle('saved');
            expect(box.posts).toEqual(['felt=1&gem=ja']);
        }],
        ['a submit button whose own handler prevents the default sends nothing', '#sendNever', 'Send aldrig?', async (page, box) => {
            await expect.poll(() => log(page)).toBe('never');
            expect(box.posts).toEqual([]);
        }],
        ['a checkbox is ticked', '#tick', 'Sæt flueben?', async page => {
            await expect(page.locator('#tick')).toBeChecked();
        }],
        ['a radio button is chosen', '#valg2', 'Skift valg?', async page => {
            await expect(page.locator('#valg2')).toBeChecked();
            await expect(page.locator('#valg1')).not.toBeChecked();
        }],
        ['a link is followed', '#link', 'Følg linket?', async page => {
            await expect(page).toHaveURL('http://saldi.test/save?link=1');
        }],
        ['a javascript: link runs', '#jsLink', 'Kør linket?', async page => {
            await expect.poll(() => log(page)).toBe('js-link');
        }],
        ['a label clicks its control', '#label', 'Etiketten?', async page => {
            await expect(page.locator('#labelled')).toBeChecked();
        }],
        ['a button disabled while the question is open does nothing', '#goesOff', 'Slås fra?', async page => {
            await page.waitForTimeout(100);
            expect(await log(page)).toBe('');
        }, () => { document.getElementById('goesOff').disabled = true; }],
        ['a fieldset disabled while the question is open is no control: it gets its click', '#group', 'Feltgruppen?', async page => {
            await expect.poll(() => log(page)).toBe('group');
        }, () => { document.getElementById('group').disabled = true; }],
    ];
    for (const [name, selector, asks, after, meanwhile] of cases) {
        test(name, async ({ page }) => {
            const box = await openPage(page, []);
            await page.locator(selector).click();
            await expect(question(page)).toHaveText(asks);
            if (meanwhile) await page.evaluate(meanwhile);
            await ok(page);
            await after(page, box);
            expect(box.asked).toEqual([]);
            expect(box.errors).toEqual([]);
        });
    }
});
