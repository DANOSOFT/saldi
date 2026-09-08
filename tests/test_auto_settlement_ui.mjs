// 20260908 CDX/LH Exercise the actual settlement-page JavaScript with an isolated DOM and transport.
// Run: node tests/test_auto_settlement_ui.mjs
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const page = fs.readFileSync(new URL('../finans/autoudlign.php', import.meta.url), 'utf8');
let script = page.match(/<script>\s*(\(function \(\) \{[\s\S]*?\}\)\(\);)\s*<\/script>/)[1];
const values = {KLADDE_ID:99, ENTRY_ID:1, TOKEN:'fixture-session', SNAPSHOT:'fixture-snapshot', AMOUNT:500,
  BESKRIVELSE:'invoice payment', BRUGT:[], HINT_TOKENS:[], SKIPPED:0, SETTLED:0};
script = script.replace(/(const|let)\s+(\w+)\s*=\s*<\?= .*? \?>;/g, (_,kind,name) => {
  assert.ok(Object.hasOwn(values, name), `Missing PHP fixture for ${name}`);
  return `${kind} ${name} = ${JSON.stringify(values[name])};`;
});

function boot(initialAccount = '') {
  const nodes = new Map();
  const listeners = {};
  const requests = [];
  const alerts = [];
  const timers = new Map();
  let timerId = 0;
  function element(id) {
    const handlers = {};
    const node = {id, value:'', disabled:false, style:{}, dataset:{}, selectedOptions:[{dataset:{}}],
      addEventListener: (event, fn) => { handlers[event] = fn; },
      dispatch: (event) => handlers[event]?.(), focus(){}, scrollIntoView(){},
      classList:{toggle(){}}, textContent:'', rows:[],
      querySelectorAll: () => node.rows};
    Object.defineProperty(node, 'innerHTML', {get:()=>node.html || '', set:html => {
      node.html = html;
      node.rows = [...html.matchAll(/data-index="(\d+)"/g)].map(([,index]) => {
        const row = element(`row-${index}`); row.dataset.index=index; return row;
      });
    }});
    nodes.set(id,node);
    return node;
  }
  ['accountSelect','searchInput','candidateBody','udlignBtn','skipBtn','selectionInfo','matchHint','pageInfo','paginationBar','prevBtn','nextBtn'].forEach(element);
  nodes.get('udlignBtn').disabled = true;
  const account = nodes.get('accountSelect');
  function choose(id, number = '1009', type = 'K') {
    account.value = id;
    account.selectedOptions = [{dataset:{account:number,type}}];
    account.dispatch('change');
  }
  account.value = initialAccount;
  if (initialAccount) account.selectedOptions = [{dataset:{account:'1009',type:'K'}}];
  const location = {pathname:'/finans/autoudlign.php', href:''};
  const context = {URLSearchParams,FormData,console,window:{location},alert:message=>alerts.push(message),
    document:{getElementById:id=>nodes.get(id),addEventListener:(name,fn)=>{listeners[name]=fn;}},
    setTimeout:fn=>{timers.set(++timerId,fn);return timerId;},clearTimeout:id=>timers.delete(id),
    fetch:(url,options)=>new Promise((resolve,reject)=>requests.push({url,options,resolve,reject}))};
  vm.runInNewContext(script, context, {filename:'autoudlign-page.js'});
  const key = (key,target=nodes.get('searchInput')) => listeners.keydown({key,target,preventDefault(){}});
  return {nodes,requests,choose,key,location,alerts,timers};
}
const candidate = (id=101, amountMatch=true) => ({id,konto_id:29,kontonr:'1009',art:'K',faktnr:`REF-${id}`,firmanavn:'Alcar',amount:amountMatch?500:1991.50,amountMatch,transdate:'2026-09-01'});
async function respond(request, results) {
  request.resolve({json:async()=>({results,pagination:{total:results.length,hasMore:false}})});
  await new Promise(resolve=>setImmediate(resolve));
}
async function saveResponse(request, data) {
  request.resolve({json:async()=>data});
  await new Promise(resolve=>setImmediate(resolve));
}

let ui = boot();
assert.equal(ui.requests.length,0,'Unassigned line fetched cross-account suggestions');
ui.key('Enter');
assert.equal(ui.requests.length,0,'Enter submitted without an account');
ui.choose('29');
let query = new URL(ui.requests[0].url,'https://fixture.invalid').searchParams;
assert.equal(query.get('account'),'1009');
assert.equal(query.get('accountType'),'K');
await respond(ui.requests[0],[candidate()]);
assert.equal(ui.nodes.get('udlignBtn').disabled,false,'Unique exact amount should be selectable');
ui.key('Enter');
ui.key('Enter');
assert.equal(ui.requests.length,2,'Repeated Enter submitted twice while saving');
assert.equal(ui.requests[1].options.body.get('openpost_id'),'101');
assert.equal(ui.requests[1].options.body.get('account_id'),'29');
assert.equal(ui.requests[1].options.body.has('amount'),false,'Client controls accounting amount');
await saveResponse(ui.requests[1],{success:false,error:'This journal line has changed.'});
assert.equal(ui.location.href,'','Failed save advanced to the next line');
assert.equal(ui.alerts[0],'This journal line has changed.');
ui.key('Enter');
await saveResponse(ui.requests[2],{success:true});
assert.match(ui.location.href,/settled=1/);

ui=boot('29');
await respond(ui.requests[0],[candidate(101),candidate(102)]);
assert.equal(ui.nodes.get('udlignBtn').disabled,true,'Tied amount matches were automatically selected');
ui.key('Enter');
assert.equal(ui.requests.length,1);
ui.key('ArrowDown',ui.nodes.get('accountSelect'));
assert.equal(ui.nodes.get('udlignBtn').disabled,true,'Account chooser arrow key selected an invoice');
ui.key('ArrowDown');
ui.key('Enter');
assert.equal(ui.requests.length,2,'Explicit keyboard selection did not submit');

ui=boot('29');
await respond(ui.requests[0],[candidate(101,false)]);
assert.equal(ui.nodes.get('udlignBtn').disabled,true,'Partial payment was automatically selected');
ui.key('ArrowDown');
ui.key('Enter');
assert.equal(ui.requests[1].options.body.get('openpost_id'),'101','Partial payment could not be selected explicitly');

ui=boot('29');
await respond(ui.requests[0],[]);
ui.key('Enter');
assert.equal(ui.requests.length,1);
assert.equal(ui.nodes.get('udlignBtn').disabled,true);
assert.match(ui.nodes.get('candidateBody').innerHTML,/No open entries match/);

ui=boot();
ui.choose('29');
ui.choose('31','2000','K');
await respond(ui.requests[1],[]);
await respond(ui.requests[0],[candidate()]);
assert.match(ui.nodes.get('candidateBody').innerHTML,/No open entries match/,'Stale account response overwrote current results');
ui.key('Enter');
assert.equal(ui.requests.length,2);

ui=boot('29');
await respond(ui.requests[0],[candidate()]);
ui.nodes.get('searchInput').value='new query';
ui.nodes.get('searchInput').dispatch('input');
ui.key('Enter');
assert.equal(ui.requests.length,1,'Enter submitted a stale selection during search debounce');
for (const callback of ui.timers.values()) callback();
assert.equal(ui.requests.length,2);
await respond(ui.requests[1],[]);
assert.equal(ui.nodes.get('udlignBtn').disabled,true);

ui=boot('29');
ui.choose('');
await respond(ui.requests[0],[candidate()]);
assert.match(ui.nodes.get('candidateBody').innerHTML,/Choose a customer or supplier/);
assert.equal(ui.nodes.get('udlignBtn').disabled,true);
console.log('PASS: account choice, typed search parameters, keyboard confirmation, ambiguous/partial/no matches, duplicate submit guard, save errors and stale responses.');
