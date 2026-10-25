// 20261022 CDX/LH Run the actual invoice-page script with deterministic DOM and Stripe.js doubles.
// 20261022 CDX/LH Verify Elements restricts payment methods to cards independently of account defaults.
// 20261022 CDX/LH Regress whole-row consent clicks, keyboard activation and placement above the resizing iframe.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { randomBytes } = require('node:crypto');
const { runInNewContext } = require('node:vm');
const source = readFileSync(__dirname + '/../../api/stripe/faktura.php', 'utf8');
const script = source.match(/<script>\s*(\(function\(\)\{[\s\S]*?)<\/script>/)[1]
  .replace(/var cfg = <\?= [\s\S]*?\?>;/, 'var cfg = fixtureConfig;');
const flush = async () => { for (let i = 0; i < 20; i++) await Promise.resolve(); };

function fixture(options = {}) {
  const state = options.state || 'failed';
  const nodes = {};
  function node(id, parentNode = null) {
    return nodes[id] = { disabled: id === 'pay' || id === 'pay-once', textContent: '', attributes: {}, events: {}, parentNode,
      addEventListener(name, fn) { this.events[name] = fn; },
      setAttribute(name, value) { this.attributes[name] = value; },
      dispatchEvent(event) {
        event.target ||= this;
        event.currentTarget = this;
        if (this.events[event.type]) this.events[event.type](event);
        if (event.bubbles && this.parentNode) this.parentNode.dispatchEvent(event);
      } };
  }
  node('pay-error');
  if (state === 'enrolled') node('paynow');
  else if (state === 'requires_action') node('resume');
  else {
    node('pay-form'); node('pay');
    if (options.twoButtons) node('pay-once');
    if (options.checkbox !== false && !options.twoButtons) {
      const row = node('chk');
      for (const child of ['consent-box', 'consent-title', 'consent-text']) node(child, row);
    }
  }
  const calls = { submits: 0, creates: [], confirmations: [], actions: [], updates: [], redirects: [], mounts: [], elementOptions: [], paymentElementOptions: [] };
  const peEvents = {};
  const updateEvents = new Set();
  const elements = {
    create(type, value) { calls.paymentElementOptions.push({ type, value }); return { on(name, fn) { peEvents[name] = fn; }, mount(id) { calls.mounts.push(id); } }; },
    on(name, fn) { assert.equal(name, 'update-end'); updateEvents.add(fn); },
    update(value) { calls.updates.push(value); },
    async submit() { calls.submits++; return options.submitError ? { error: { message: 'Ret kortoplysningerne' } } : {}; }
  };
  const stripe = {
    elements(value) { calls.elementOptions.push(value); return elements; },
    async confirmPayment(value) {
      calls.confirmations.push(value);
      return { error: { message: 'Afvist - prøv igen' } };
    },
    async handleNextAction(value) { calls.actions.push(value); return {}; }
  };
  const secret = randomBytes(16).toString('hex');
  const fixtureConfig = { pk: 'public-test-placeholder', state, amount: 34000, currency: 'dkk',
    saveDefault: options.saveDefault !== false, btn: 'Betal', btnSave: 'Betal og gem',
    startError: 'Kunne ikke starte', netError: 'Netværksfejl', payError: 'Betaling fejlede', actionError: 'Godkendelse fejlede',
    payUrl: '/pay', stateUrl: '/state', returnUrl: '/invoice?o=1&s=signature' };
  const context = { fixtureConfig, document: { getElementById(id) { return nodes[id] || null; } },
    window: options.noStripe ? {} : { Stripe() {} },
    Stripe() { return stripe; },
    location: { replace(value) { calls.redirects.push(value); } },
    async fetch(url, request) {
      calls.creates.push({ url, request });
      if (options.networkError) throw Error('offline');
      return { async json() { return options.response || { ok: true, pi: 'pi_page', client_secret: secret,
        setup_future_usage: options.effectiveUsage === undefined ? 'off_session' : options.effectiveUsage }; } };
    }, setTimeout() { throw Error('Unexpected timer'); } };
  runInNewContext(script, context);
  return { calls, nodes, secret,
    ready() { peEvents.ready(); },
    loaderror() { peEvents.loaderror(); },
    updated() { for (const fn of [...updateEvents]) fn(); },
    submit() { nodes['pay-form'].events.submit({ preventDefault() {} }); },
    toggle() { nodes.chk.events.click(); },
    click(id) { nodes[id].dispatchEvent({ type: 'click', bubbles: true }); },
    key(key) {
      const event = { type: 'keydown', key, prevented: false, preventDefault() { this.prevented = true; } };
      nodes.chk.dispatchEvent(event);
      return event;
    } };
}

test('replace-card consent is a focusable whole row above the Payment Element; recurring invoices show the consent text above two buttons', () => {
  const failed = source.slice(source.indexOf("case 'failed':"), source.indexOf("case 'enrolled':"));
  assert.match(failed, /<div class="check" role="checkbox"[^>]*tabindex="0" id="chk"/);
  assert.ok(failed.indexOf('$amountBox') < failed.indexOf('id="chk"'));
  assert.ok(failed.indexOf('id="chk"') < failed.indexOf('$elementBlock'));
  const recurring = source.slice(source.indexOf("case 'open_recurring':"), source.indexOf("case 'failed':"));
  assert.doesNotMatch(recurring, /role="checkbox"/);
  assert.ok(recurring.indexOf('$autoInfo') < recurring.indexOf('$elementBlock'));
  assert.ok(recurring.indexOf('$elementBlock') < recurring.indexOf('$payButtons'));
  assert.match(source, /id="pay-once" type="submit" disabled/);
  assert.match(source, /\.check:focus-visible\{outline:3px solid var\(--purple\);outline-offset:3px\}/);
  assert.match(source, /\.check\[aria-checked="true"\] \.box/);
});

test('clicking the consent box, title, text or row toggles once and sends the selected usage', async () => {
  for (const state of ['failed']) {
    const initial = state === 'failed';
    for (const target of ['consent-box', 'consent-title', 'consent-text', 'chk']) {
      const usage = initial ? null : 'off_session';
      const f = fixture({ state, saveDefault: initial, effectiveUsage: usage });
      f.click(target);
      assert.equal(f.nodes.chk.attributes['aria-checked'], String(!initial));
      assert.equal(f.nodes.pay.textContent, initial ? 'Betal' : 'Betal og gem');
      await flush();
      assert.equal(f.calls.updates.length, 1);
      assert.equal(f.calls.updates[0].setupFutureUsage, usage);
      f.updated(); f.ready(); f.submit(); await flush();
      assert.equal(JSON.parse(f.calls.creates[0].request.body).save_card, !initial);
      assert.equal(f.calls.confirmations.length, 1);
    }
  }
});

test('Space and Enter toggle consent and prevent default; other keys leave it unchanged', async () => {
  const f = fixture({ state: 'failed', saveDefault: false });
  assert.equal(f.key('Tab').prevented, false);
  assert.equal(f.nodes.chk.attributes['aria-checked'], 'false');
  for (const [key, checked] of [[' ', 'true'], ['Enter', 'false']]) {
    assert.equal(f.key(key).prevented, true);
    assert.equal(f.nodes.chk.attributes['aria-checked'], checked);
    await flush(); f.updated(); await flush();
  }
  assert.equal(f.calls.updates.length, 2);
});

test('M4: Elements accepts only card methods regardless of account defaults', () => {
  const f = fixture();
  assert.deepEqual(Array.from(f.calls.elementOptions[0].paymentMethodTypes), ['card']);
});

test('Stripe Link is hidden: the Payment Element shows the card form only', () => {
  for (const state of ['open', 'open_recurring']) {
    const f = fixture({ state });
    assert.equal(f.calls.paymentElementOptions.length, 1);
    assert.equal(f.calls.paymentElementOptions[0].type, 'payment');
    assert.equal(f.calls.paymentElementOptions[0].value.wallets.link, 'never');
  }
});

test('two buttons: the main button turns on automatic payment, "pay-once" pays this invoice only', async () => {
  for (const state of ['open_recurring', 'overdue', 'failed']) {
    const auto = fixture({ state, twoButtons: true, saveDefault: false });
    assert.equal(auto.calls.elementOptions[0].setupFutureUsage, 'off_session');
    assert.equal(auto.nodes.pay.disabled, true);
    assert.equal(auto.nodes['pay-once'].disabled, true);
    auto.ready();
    assert.equal(auto.nodes['pay-once'].disabled, false);
    auto.click('pay'); auto.submit(); await flush();
    assert.equal(auto.calls.updates.length, 0);
    assert.equal(JSON.parse(auto.calls.creates[0].request.body).save_card, true);
    assert.equal(auto.calls.confirmations.length, 1);

    const once = fixture({ state, twoButtons: true, effectiveUsage: null });
    once.ready(); once.click('pay-once'); once.submit(); await flush();
    assert.equal(once.calls.submits, 0);
    assert.equal(once.calls.updates[0].setupFutureUsage, null);
    once.updated(); await flush();
    assert.equal(JSON.parse(once.calls.creates[0].request.body).save_card, false);
    assert.equal(once.calls.confirmations.length, 1);
    assert.equal(once.nodes.pay.disabled, false);
    assert.equal(once.nodes['pay-once'].disabled, false);
  }
});

test('N5: no submit before ready; first ready click submits once and permits retry after decline', async () => {
  const f = fixture();
  assert.equal(f.nodes.pay.disabled, true);
  f.submit(); await flush();
  assert.equal(f.calls.submits, 0);
  f.ready();
  assert.equal(f.nodes.pay.disabled, false);
  f.submit(); f.submit(); f.toggle(); await flush();
  assert.equal(f.calls.submits, 1);
  assert.equal(f.calls.creates.length, 1);
  assert.equal(f.calls.confirmations.length, 1);
  assert.equal(JSON.parse(f.calls.creates[0].request.body).save_card, true);
  assert.equal(f.nodes['pay-error'].textContent, 'Afvist - prøv igen');
  assert.equal(f.nodes.pay.disabled, false);
  f.submit(); await flush();
  assert.equal(f.calls.confirmations.length, 2);
});

test('R12: recovered effective consent is applied before Stripe confirmation', async () => {
  const f = fixture({ effectiveUsage: null });
  f.ready(); f.submit(); await flush();
  assert.equal(f.calls.confirmations.length, 0);
  assert.equal(f.calls.updates[0].setupFutureUsage, null);
  f.updated(); await flush();
  assert.equal(f.calls.confirmations.length, 1);
  assert.equal(f.calls.confirmations[0].clientSecret, f.secret);
});

test('R12: checkbox usage update completes before submit and request carries unchecked consent', async () => {
  const f = fixture({ effectiveUsage: null });
  f.ready(); f.toggle(); f.submit(); await flush();
  assert.equal(f.calls.submits, 0);
  assert.equal(f.nodes.chk.attributes['aria-checked'], 'false');
  f.updated(); await flush();
  assert.equal(f.calls.submits, 1);
  assert.equal(JSON.parse(f.calls.creates[0].request.body).save_card, false);
  assert.equal(f.calls.confirmations.length, 1);
});

test('N5: failed SDK/element loading keeps payment disabled and explains failure', () => {
  const missing = fixture({ noStripe: true });
  assert.equal(missing.nodes.pay.disabled, true);
  assert.equal(missing.nodes['pay-error'].textContent, 'Kunne ikke starte');
  const broken = fixture();
  broken.loaderror();
  assert.equal(broken.nodes.pay.disabled, true);
  assert.equal(broken.nodes['pay-error'].textContent, 'Kunne ikke starte');
});

test('element validation and network failures show feedback and release submit', async () => {
  for (const options of [{ submitError: true }, { networkError: true }]) {
    const f = fixture(options);
    f.ready(); f.submit(); await flush();
    assert.notEqual(f.nodes['pay-error'].textContent, '');
    assert.equal(f.nodes.pay.disabled, false);
    assert.equal(f.calls.confirmations.length, 0);
  }
});

test('R40: Betal nu authenticates the returned original intent without creating another', async () => {
  const clientSecret = randomBytes(16).toString('hex');
  const f = fixture({ state: 'enrolled', response: { ok: true, pi: 'pi_original', status: 'requires_action', client_secret: clientSecret } });
  f.click('paynow'); await flush();
  assert.equal(f.calls.creates.length, 1);
  assert.equal(JSON.parse(f.calls.creates[0].request.body).pay_now, 1);
  assert.equal(f.calls.actions[0].clientSecret, clientSecret);
  assert.equal(f.calls.confirmations.length, 0);
  assert.match(f.calls.redirects[0], /payment_intent=pi_original$/);
});

test('authentication page resumes original intent and saved-card decline reloads failed state', async () => {
  const f = fixture({ state: 'requires_action', response: { ok: true, pi: 'pi_resumed', status: 'requires_action', client_secret: randomBytes(16).toString('hex') } });
  f.click('resume'); await flush();
  assert.equal(JSON.parse(f.calls.creates[0].request.body).resume, 1);
  assert.equal(f.calls.actions.length, 1);
  const declined = fixture({ state: 'enrolled', response: { ok: false, error: 'declined' } });
  declined.click('paynow'); await flush();
  assert.equal(declined.calls.redirects.length, 1);
  assert.equal(declined.calls.actions.length, 0);
});
