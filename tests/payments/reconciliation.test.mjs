// Run: node --test tests/payments/reconciliation.test.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { test } from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../debitor/payments/lane3000_afstemning.php', import.meta.url), 'utf8');
const catalog = new Map(readFileSync(new URL('../../importfiler/tekster.csv', import.meta.url), 'utf8')
    .trimEnd().split('\n').map(line => { const [id, ...texts] = line.split('\t'); return [id, texts]; }));
const response = (body, status = 200) => ({ ok: status < 400, status, json: async () => body, text: async () => JSON.stringify(body) });
const receipt = 'Indsamlet 0\nTotal 0,00\n';
const reconciled = () => response({ result: { reconciliation: { printText: { Text: receipt } } } });

async function run(options = {}) {
    const messages = Object.fromEntries([...source.matchAll(/'([^']+)' => findtekst\('(\d+)\|[^']*', \$sprog_id\)/g)]
        .map(([, key, id]) => [key, catalog.get(id)[options.language ?? 0]]));
    let script = source.split('<script>')[1].split('</script>')[0];
    script = script.replace(/<\?= json_encode\(\$messages,[\s\S]*?\?>/, JSON.stringify(messages))
        .replace(/<\?= json_encode\(get_settings_value\("username"[\s\S]*?\?>/, JSON.stringify(options.noCredentials ? '' : 'test-user'))
        .replace(/<\?= json_encode\(get_settings_value\("password"[\s\S]*?\?>/, JSON.stringify(randomUUID()))
        .replace(/<\?= json_encode\(\$terminal_id,[\s\S]*?\?>/, JSON.stringify(options.noTerminal ? '' : 'test-terminal'))
        .replace(/<\?= json_encode\("http:\/\/\$printserver[\s\S]*?\?>/, JSON.stringify('http://printer.invalid/saldiprint.php'))
        .replaceAll('<?php print $ordre_id; ?>', '123');
    assert.ok(!script.includes('<?'), 'all PHP inputs are supplied by the fixture');
    const nodes = Object.fromEntries(['status', 'bg', 'continue'].map(id => [id, { innerText: '', style: {}, disabled: true }]));
    const calls = [], popups = [], errors = [];
    const responses = [options.login ?? response({ token: randomUUID() }), options.terminal ?? reconciled(), options.save ?? response({ saved: true })];
    let redirect;
    const context = vm.createContext({
        document: { getElementById: id => nodes[id] },
        console: { error: (...args) => errors.push(args) },
        window: {
            open: (...args) => { popups.push(args); if (options.popupThrows) throw new Error('window.open failed'); return options.blocked ? null : {}; },
            location: { replace: url => { redirect = url; } },
            addEventListener: () => {},
        },
        fetch: async (url, init) => {
            calls.push({ url, body: JSON.parse(init.body) });
            const next = responses.shift();
            if (next instanceof Error) throw next;
            if (typeof next === 'function') return next(nodes);
            return next;
        },
    });
    await vm.runInContext(script, context);
    vm.runInContext('failed()', context);
    assert.equal(redirect, '../pos_ordre.php?id=123&godkendt=afvist');
    return { nodes, calls, popups, messages, errors };
}

for (const language of [0, 1, 2]) {
    test(`successful zero-total reconciliation completes in language ${language}`, async () => {
        const { nodes, calls, popups, messages, errors } = await run({ language });
        assert.equal(nodes.status.innerText, messages.completed);
        assert.equal(nodes.status.style.backgroundColor, '#34a853');
        assert.equal(nodes.continue.disabled, false);
        assert.equal(popups.length, 1);
        assert.equal(calls.length, 3);
        assert.deepEqual(calls[1].body, { action: 'reconciliation' });
        assert.deepEqual(calls[2].body, { data: receipt, id: '123', type: 'move3500', confirm_saved: true });
        assert.equal(errors.length, 0);
    });
}

test('success waits for the receipt acknowledgement', async () => {
    await run({ save: nodes => {
        assert.equal(nodes.continue.disabled, true);
        assert.equal(nodes.status.innerText, 'Printer...');
        assert.notEqual(nodes.status.style.backgroundColor, '#34a853');
        return response({ saved: true });
    } });
});

test('blocked popup shows a warning and enables Back', async () => {
    const { nodes, messages } = await run({ blocked: true });
    assert.equal(nodes.status.innerText, messages.popupBlocked);
    assert.equal(nodes.status.style.backgroundColor, '#fbbc04');
    assert.equal(nodes.continue.disabled, false);
});

const failures = [
    ['missing credentials', { noCredentials: true }, 0],
    ['login HTTP failure', { login: response({}, 401) }, 1],
    ['login network failure', { login: new Error('offline') }, 1],
    ['missing token', { login: response({}) }, 1],
    ['missing terminal', { noTerminal: true }, 1],
    ['terminal HTTP failure', { terminal: response({}, 503) }, 2],
    ['terminal network failure', { terminal: new Error('offline') }, 2],
    ['terminal failure payload', { terminal: response({ failure: { error: 'declined' } }) }, 2],
    ['malformed terminal payload', { terminal: response({}) }, 2],
    ['missing receipt', { terminal: response({ result: { reconciliation: {} } }) }, 2],
    ['empty receipt', { terminal: response({ result: { reconciliation: { printText: { Text: '  ' } } } }) }, 2],
    ['receipt HTTP failure', { save: response({}, 500) }, 3],
    ['receipt network failure', { save: new Error('offline') }, 3],
    ['negative receipt acknowledgement', { save: response({ saved: false }) }, 3],
    ['missing receipt acknowledgement', { save: response({}) }, 3],
    ['expired session HTML', { save: { ok: true, json: async () => { throw new SyntaxError('Unexpected HTML'); } } }, 3],
    ['print window exception', { popupThrows: true }, 3],
];
for (const [name, options, count] of failures) {
    test(`${name} stays red and enables Back`, async () => {
        const { nodes, calls, popups, messages } = await run(options);
        assert.equal(nodes.status.style.backgroundColor, '#ea3a3a');
        assert.ok(nodes.status.innerText.startsWith(messages.error + ':'));
        assert.equal(nodes.continue.disabled, false);
        assert.equal(calls.length, count);
        assert.equal(popups.length, options.popupThrows ? 1 : 0);
    });
}
