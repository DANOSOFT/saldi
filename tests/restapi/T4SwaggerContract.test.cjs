// 20261001 CDX/LH Validate T4 fixtures and real PHP responses against the complete OpenAPI document.
// npm install --prefix /tmp/t4-x1-openapi @apidevtools/swagger-parser ajv ajv-formats
// NODE_PATH=/tmp/t4-x1-openapi/node_modules SALDI_T4_CONTRACT_OUTPUT=/tmp/t4-x1-contracts.json node --test tests/restapi/T4SwaggerContract.test.cjs
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync, readdirSync } = require('node:fs');
const path = require('node:path');
const Parser = require('@apidevtools/swagger-parser');
const Ajv = require('ajv');
const addFormats = require('ajv-formats');
const names = {"warehouses": "Warehouses", "count-snapshot": "CountSnapshot", "count-lookup": "CountLookup", "count-submit": "CountSubmit", "count-stale": "CountStale", "count-history": "CountHistory", "receipt-orders": "ReceiptOrders", "receipt-order": "ReceiptOrder", "receipt-submit": "ReceiptSubmit", "receipt-attach": "ReceiptAttach", "cases": "Cases", "case-tasks": "CaseTasks", "hour-types": "HourTypes", "time-entry": "TimeEntry", "time-entries": "TimeEntries", "time-submit": "TimeSubmit", "booking": "Booking", "bookings-day": "BookingsDay", "expense": "Expense", "expenses": "Expenses", "mileage-rates": "MileageRates", "mileage-trip": "MileageTrip", "mileage-trips": "MileageTrips", "mileage-statement": "MileageStatement", "marken-summary": "MarkenSummary", "count-submit.request": "CountSubmitRequest", "receipt-submit.request": "ReceiptSubmitRequest", "receipt-attach.request": "ReceiptAttachRequest", "time-create.request": "TimeCreateRequest", "time-submit.request": "TimeSubmitRequest", "booking-create.request": "BookingCreateRequest", "booking-move.request": "BookingMoveRequest", "booking-status.request": "BookingStatusRequest", "expense-register.request": "ExpenseRegisterRequest", "mileage-trip.request": "MileageTripRequest", "mileage-statement.request": "MileageStatementRequest"};

async function document() {
  return Parser.validate(path.resolve(__dirname, '../../restapi/swagger.yaml'));
}
function validators(doc) {
  const ajv = new Ajv({ strict: false, allErrors: true });
  addFormats(ajv);
  const cache = new Map();
  return (name, value, label) => {
    const schema = doc.components.schemas[`T4${name}`];
    assert.ok(schema, `Missing T4${name}`);
    if (!cache.has(name)) cache.set(name, ajv.compile(schema));
    const validate = cache.get(name);
    assert.ok(validate(value), `${label}: ${JSON.stringify(validate.errors)}`);
  };
}
test('the full OpenAPI document and all 32 T4 methods validate', async () => {
  const doc = await document();
  const operations = Object.entries(doc.paths).flatMap(([route, item]) => Object.entries(item)
    .filter(([, operation]) => operation?.tags?.includes('Saldi Mobile T4'))
    .map(([method, operation]) => ({ route, method, operation })));
  assert.equal(operations.length, 32);
  assert.equal(new Set(operations.map(item => item.route)).size, 20);
  for (const { route, method, operation } of operations) {
    assert.ok(operation['x-saldi-t4-right'], route);
    assert.deepEqual(operation.security, [{ BearerAuth: [] }]);
    assert.ok(operation.responses['401']);
    assert.ok(operation.responses['403']);
    if (method !== 'get') {
      assert.ok(operation.requestBody.content['application/json'].schema.required.includes('client_id'), route);
      const success = operation.responses['201'] ?? operation.responses['200'];
      assert.ok(success.headers['Idempotent-Replayed'], route);
    }
  }
  assert.ok(doc.components.schemas.MobileAttachmentUploadRequest.properties.claim);
  assert.equal(doc.paths['/attachment'].post.requestBody.content['multipart/form-data'].schema.properties.claim.type, 'string');
  assert.ok(doc.components.schemas.MobileMeResponse.properties.data.properties.employee_link);
  assert.ok(doc.components.schemas.T3ReviewItem.properties.claim);
});
test('every T4 request and response fixture validates', async () => {
  const doc = await document();
  const validate = validators(doc);
  const directory = path.join(__dirname, 'fixtures/t4');
  const files = readdirSync(directory).filter(file => file.endsWith('.json'));
  assert.equal(files.length, Object.keys(names).length, 'New fixtures require an explicit schema mapping');
  for (const file of files) {
    const fixture = JSON.parse(readFileSync(path.join(directory, file), 'utf8'));
    const name = file.slice(0, -5);
    validate(names[name], name.endsWith('.request') ? fixture : fixture.data, file);
  }
});
test('every captured PHP response validates against the same contract', async () => {
  assert.ok(process.env.SALDI_T4_CONTRACT_OUTPUT, 'Run MobileT4RightsMatrixTest with SALDI_T4_CONTRACT_OUTPUT and copy the JSON from the container');
  const captured = JSON.parse(readFileSync(process.env.SALDI_T4_CONTRACT_OUTPUT, 'utf8'));
  const doc = await document();
  const validate = validators(doc);
  const seen = new Set();
  for (const { schema, response } of captured.cases) {
    assert.equal(response.success, true);
    assert.equal(typeof response.message, 'string');
    validate(schema, response.data, `PHP ${schema}`);
    seen.add(schema);
  }
  const required = ['Warehouses','CountSnapshot','CountLookup','CountHistory','CountDetail','CountSubmit','ReceiptOrders',
    'ReceiptOrder','Receipts','ReceiptSubmit','ReceiptAttach','Cases','CaseTasks','HourTypes','TimeEntries',
    'TimeEntry','TimeSubmit','Deleted','Booking','BookingsDay','BookingEmployees','Expenses','Expense',
    'MileageRates','MileageTrips','MileageTrip','MileageStatements','MileageStatement','MarkenSummary'];
  assert.deepEqual([...seen].sort(), required.sort());
  const statuses = new Set();
  for (const { status, response } of captured.errors) {
    assert.equal(response.success, false);
    validate('Error', response, `PHP error ${status}`);
    statuses.add(status);
  }
  assert.deepEqual([...statuses].sort(), [401, 403, 404, 409, 412, 422]);
});
test('quantity, money and privacy contracts reject wrong JSON types and payroll fields', async () => {
  const doc = await document();
  const ajv = new Ajv({ strict: false });
  addFormats(ajv);
  for (const [fixture, schema, field, invalid] of [
    ['count-submit','CountSubmit','value_estimate',12], ['mileage-trip','MileageTrip','km',42],
    ['time-entry','TimeEntry','hours',7.5], ['time-entry','TimeEntry','wage','100.00'],
  ]) {
    const data = JSON.parse(readFileSync(path.join(__dirname, 'fixtures/t4', `${fixture}.json`), 'utf8')).data;
    data[field] = invalid;
    assert.equal(ajv.compile(doc.components.schemas[`T4${schema}`])(data), false, `${schema}.${field}`);
  }
});
