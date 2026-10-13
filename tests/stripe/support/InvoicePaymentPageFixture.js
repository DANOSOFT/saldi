// 20261012 CL/LH Exercise the actual hosted-page controller with fake browser/Stripe I/O.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');

const source = fs.readFileSync(path.resolve(__dirname, '../../../api/stripe/faktura.php'), 'utf8');
const script = source.match(/\$script = <<<'JS'\n([\s\S]*?)\nJS;/)[1];
const scenario = process.argv[2];
const handlers = {};
const button = { disabled: false, textContent: '' };
const error = { textContent: '', className: '' };
const amount = { textContent: '500,00 kr.' };
const save = { checked: false, addEventListener() {} };
const form = {
  dataset: { pk: '', amount: '50000', id: '10', sig: '', ret: '/faktura.php?id=10', label: '500,00' },
  addEventListener(name, handler) { handlers[name] = handler; }
};
const window = { location: { href: '' } };
let confirmations = 0;
let updateHandler;
const response = { status: 'ready', clientSecret: '', paymentIntent: 'pi_result', amount: 50000, currency: 'dkk' };
// This is an opaque local fixture marker, never a Stripe credential.
response.clientSecret = 'fixture_' + process.pid;
if (scenario === 'changed') response.amount = 40000;
if (scenario === 'processing') { response.status = 'processing'; response.clientSecret = ''; response.message = 'Betalingen behandles allerede.'; }
if (scenario === 'succeeded') { response.status = 'succeeded'; response.clientSecret = ''; response.paymentIntent = 'pi_paid'; }
let elementsAmount;
const elements = {
  submit: async () => ({}),
  create: () => ({ mount() {}, on(name, ready) { if (name === 'ready') ready(); } }),
  on(name, handler) { if (name === 'update-end') updateHandler = handler; },
  update(opts) { elementsAmount = opts.amount; queueMicrotask(() => updateHandler()); }
};
vm.runInNewContext(script, {
  document: {
    getElementById: id => ({ pay: form, btn: button, save, err: error }[id]),
    querySelector: () => amount
  }, window,
  Stripe: function () {
    return {
      elements(opts) { elementsAmount = opts.amount; return elements; },
      async confirmPayment(opts) {
        confirmations++;
        assert.equal(elementsAmount, response.amount);
        assert.equal(Number(form.dataset.amount), response.amount);
        assert.equal(opts.clientSecret, response.clientSecret);
        return { paymentIntent: { id: 'pi_result' } };
      }
    };
  },
  fetch: async () => ({ ok: true, json: async () => response })
});
async function submit() {
  handlers.submit({ preventDefault() {} });
  await new Promise(resolve => setImmediate(resolve));
}
(async () => {
  await submit();
  if (scenario === 'changed') {
    assert.equal(confirmations, 0);
    assert.equal(amount.textContent, '400,00 kr.');
    assert.equal(button.textContent, 'Betal 400,00 kr.');
    assert.equal(button.disabled, false);
    assert.match(error.textContent, /Kontrollér beløbet/);
    await submit();
    assert.equal(confirmations, 1);
    assert.equal(window.location.href, '/faktura.php?id=10&payment_intent=pi_result');
  } else if (scenario === 'processing') {
    assert.equal(confirmations, 0);
    assert.equal(button.disabled, true);
    assert.equal(error.textContent, response.message);
  } else if (scenario === 'succeeded') {
    assert.equal(confirmations, 0);
    assert.equal(window.location.href, '/faktura.php?id=10&payment_intent=pi_paid');
  } else if (scenario === 'unchanged') {
    assert.equal(confirmations, 1);
    assert.equal(window.location.href, '/faktura.php?id=10&payment_intent=pi_result');
  } else throw new Error('Unknown fixture scenario');
})().catch(error => { console.error(error); process.exitCode = 1; });
