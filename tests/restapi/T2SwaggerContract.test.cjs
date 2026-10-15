// 20261001 CDX/LH Validate owned OpenAPI contracts and real HTTP responses without changing T1 schemas.
// Run with NODE_PATH pointing to a temporary install of @apidevtools/swagger-parser, ajv and js-yaml.
// Export MobileT2RightsMatrixTest with SALDI_T2_CONTRACT_OUTPUT first.
// 20261005 CDX/LH Validate the complete OpenAPI document before filtering T2 response contracts.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const path = require('node:path');
const yaml = require('js-yaml');
const Parser = require('@apidevtools/swagger-parser');
const Ajv = require('ajv');

test('the complete OpenAPI document has no structural errors', async () => {
  const document = yaml.load(readFileSync(path.resolve(__dirname, '../../restapi/swagger.yaml'), 'utf8'));
  const validated = await Parser.validate(document);
  assert.ok(validated.components.schemas.ErrorResponse);
  assert.ok(validated.components.schemas.MobileAccountLoginRequest);
  assert.ok(validated.components.schemas.MobileAttachmentUploadRequest);
});

test('T2 OpenAPI paths and captured PHP responses agree', async () => {
  const document = yaml.load(readFileSync(path.resolve(__dirname, '../../restapi/swagger.yaml'), 'utf8'));
  const aliases = ['/debitor/open-posts/', '/debitor/dunning/', '/debitor/sales/'];
  document.paths = Object.fromEntries(Object.entries(document.paths).filter(([name, item]) => aliases.includes(name)
    || Object.values(item).some(operation => operation && typeof operation === 'object'
      && (operation['x-saldi-right'] || operation.tags?.includes('Saldi Mobile T2')))));
  document.components.schemas = Object.fromEntries(Object.entries(document.components.schemas).filter(([name]) => name.startsWith('T2')));
  assert.equal(Object.keys(document.paths).length, 30);
  const validated = await Parser.validate(document);
  const output = process.env.SALDI_T2_CONTRACT_OUTPUT;
  assert.ok(output, 'Export real HTTP responses before running the Swagger contract gate.');
  const captured = JSON.parse(readFileSync(output, 'utf8'));
  const names = {
    'open-posts': 'OpenPostList', 'open-posts-detail': 'OpenPostDetail',
    'dunning-preview': 'DunningPreview', 'dunning-history': 'DunningHistory',
    'dunning-created': 'DunningCreated', 'dunning-send': 'DunningSend',
    'customers-search': 'CustomerSearch', 'customers-detail': 'CustomerDetail',
    'customers-created': 'CustomerCreated', 'customers-update': 'CustomerUpdated', cvr: 'Cvr',
    'sales-customers': 'SalesCustomers', 'sales-customer': 'SalesCustomer',
    'products-search': 'ProductSearch', 'products-barcode': 'ProductBarcode',
    'products-detail': 'ProductDetail', 'products-changes': 'ProductChanges',
    'sales-products': 'ProductSearch', 'sales-products-barcode': 'ProductBarcode', 'sales-products-changes': 'ProductChanges',
    'sales-quote': 'Quote', 'sales-order': 'Order', 'sales-orders': 'OrderList', 'sales-created': 'Order',
    'sales-lines': 'Order', 'sales-transition': 'Order', 'sales-deliver': 'Delivered',
    'payments-accounts': 'PaymentAccounts', 'payments-register': 'Registration', 'payments-options': 'PaymentOptions',
    'payments-link': 'Link', 'payments-link-status': 'LinkStatus',
  };
  const ajv = new Ajv({ strict: false, validateFormats: false, allErrors: true });
  const validators = new Map();
  for (const capturedCase of captured.cases) {
    if (capturedCase.schema === 'me') { continue; } // Unchanged T1 envelope plus T2 permissions: the app/PHP gate covers it.
    const name = names[capturedCase.schema];
    assert.ok(name, `Missing documented schema for ${capturedCase.schema}`);
    if (!validators.has(name)) { validators.set(name, ajv.compile(validated.components.schemas[`T2${name}`])); }
    const validate = validators.get(name);
    assert.ok(validate(capturedCase.response.data), `${capturedCase.schema}: ${JSON.stringify(validate.errors)}`);
  }
  assert.equal(validators.size, new Set(Object.values(names)).size, 'Every distinct REST JSON response shape must be covered.');
});
