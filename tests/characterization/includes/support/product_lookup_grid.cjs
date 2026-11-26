// 20261125 CDX/NTR Run the lookup page's actual JavaScript against a small GET-form boundary.
const fs = require('node:fs');
const vm = require('node:vm');
const { script } = JSON.parse(fs.readFileSync(0, 'utf8'));
const fields = {
    'search[productLookup11][varenr]': '',
    'search[productLookup11][beskrivelse]': '',
};
let submitted;
const input = (name) => ({
    length: Object.hasOwn(fields, name) ? 1 : 0,
    val(value) {
        if (value !== undefined) fields[name] = value;
        return fields[name];
    },
    closest() { return form; },
});
const form = {
    length: 1,
    find(selector) { return input(selector.match(/^input\[name="(.*)"\]$/)[1]); },
    append(html) {
        const [, name, value] = html.match(/name="([^"]+)" value="([^"]*)"/);
        fields[name] = value;
    },
    submit() { submitted = { ...fields }; },
};
const document = {};
const $ = (selector) => {
    if (selector === document) return { ready(fn) { fn(); }, on() {} };
    if (selector === '#datatable-wrapper-productLookup11 form') return form;
    if (selector.startsWith('input[name=')) return form.find(selector);
    return { keyup() {} };
};
vm.runInNewContext(script, { $, document, window: { location: {} } }, { timeout: 1000 });
if (!submitted) throw new Error('Lookup did not submit the search form');
process.stdout.write(JSON.stringify(submitted));
