// 20260914 CDX/LH SST-788: Exercise the page's actual async flow without contacting a terminal.
// Run: node tests/integration/reconciliationStatus.mjs
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const page = fs.readFileSync(new URL('../../debitor/payments/lane3000_afstemning.php', import.meta.url), 'utf8');
const script = page.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/<\?php echo json_encode\(findtekst\('([^']+)'[\s\S]*?\?>/g, (_, key) => JSON.stringify(key.split('|')[1]))
    .replace(/<\?php[\s\S]*?\?>/g, 'fixture')
    .replace(/\nstart\(\);/, '');

function setup(fetch, open = () => ({})) {
    const nodes = Object.fromEntries(['status', 'bg', 'continue'].map(id => [id, {innerText: '', style: {}, disabled: true}]));
    const context = vm.createContext({
        document: {getElementById: id => nodes[id]},
        window: {open, addEventListener() {}, location: {replace() {}}},
        fetch, console: {log() {}, warn() {}, error() {}},
        setTimeout() { throw new Error('Finished flow must not keep polling'); },
    });
    vm.runInContext(script, context);
    return {context, nodes};
}

const ok = body => ({ok: true, status: 200, json: async () => body});
const terminal = {result: {reconciliation: {printText: {Text: 'Total 0,00'}}}};
for (const scenario of ['success', 'blocked-popup', 'save-http-failure', 'save-network-failure', 'terminal-http-failure', 'terminal-rejection', 'login-failure']) {
    let opens = 0;
    const calls = [];
    const {context, nodes} = setup(async url => {
        calls.push(url);
        if (url.endsWith('login')) {
            return scenario === 'login-failure' ? {ok: false, status: 401, text: async () => 'Unauthorized'} : ok({token: 'fixture'});
        }
        if (url.endsWith('administration')) {
            if (scenario === 'terminal-http-failure') return {ok: false, status: 500, text: async () => 'Failed'};
            if (scenario === 'terminal-rejection') return ok({failure: {error: 'Rejected'}});
            return ok(terminal);
        }
        if (scenario === 'save-network-failure') throw new Error('Offline');
        return scenario === 'save-http-failure' ? {ok: false, status: 500} : ok({});
    }, () => { opens++; return scenario === 'blocked-popup' ? null : {}; });
    await vm.runInContext('start()', context);
    assert.equal(nodes.continue.disabled, false, scenario);
    assert.equal(vm.runInContext('finished', context), true, scenario);
    if (scenario === 'success') {
        assert.equal(nodes.status.innerText, 'Færdig');
        assert.equal(nodes.status.style.backgroundColor, '#34a853');
        assert.equal(opens, 1);
    } else if (scenario === 'blocked-popup') {
        assert.match(nodes.status.innerText, /Afstemning gennemført.*blokerede/);
        assert.equal(nodes.status.style.backgroundColor, '#fbbc04');
    } else {
        assert.match(nodes.status.innerText, /Fejl:/);
        assert.equal(nodes.status.style.backgroundColor, '#ea3a3a');
        assert.equal(opens, 0);
    }
    console.log(`PASS: ${scenario}`);
}

let resolveSave;
const pending = setup(() => new Promise(resolve => { resolveSave = resolve; }));
const printing = vm.runInContext("print_str('', '', 'receipt')", pending.context);
assert.equal(pending.nodes.status.innerText, 'Printer...');
assert.equal(pending.nodes.continue.disabled, true);
assert.equal(vm.runInContext('finished', pending.context), false);
resolveSave(ok({}));
await printing;
assert.equal(pending.nodes.status.innerText, 'Færdig');
console.log('PASS: no premature success while receipt preparation is pending');
