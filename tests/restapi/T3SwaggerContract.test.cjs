// 20261001 CDX/LH Validate complete OpenAPI, every T3 fixture and captured real PHP responses.
// 20261001 CDX/LH Guard defined security schemes and nullable review responses captured from PHP.
// NODE_PATH=/tmp/t2-x1-openapi/node_modules SALDI_T3_CONTRACT_OUTPUT=/tmp/t3-x1-contracts.json node --test tests/restapi/T3SwaggerContract.test.cjs
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync, readdirSync } = require('node:fs');
const path = require('node:path');
const yaml = require('js-yaml');
const Parser = require('@apidevtools/swagger-parser');
const Ajv = require('ajv');

const names = {
  'review-list': 'ReviewList', 'review-detail': 'ReviewDetail', approve: 'Approve',
  'approve-bank-row': 'Approve', 'approve-attach-only': 'Approve', 'approve-eur': 'Approve',
  'approve-file-move-pending': 'Approve', reject: 'Reject', metadata: 'Metadata', audit: 'Audit',
  'journal-drafts': 'JournalDrafts', suggestion: 'Suggestion', feedback: 'Feedback',
  'classification-stats': 'ClassificationStats', 'bank-lines': 'BankLines',
  'bank-line-remind': 'BankLineRemind', 'bank-line-state': 'BankLineState', preferences: 'Preferences',
  'tenant-summary': 'TenantSummary', 'registry-summary': 'RegistrySummary', 'error-journal-locked': 'Error',
  'approve-request': 'ApproveRequest', 'approve-request-eur': 'ApproveRequest',
  'approve-request-vat-exempt': 'ApproveRequest', 'approve-request-vat-override': 'ApproveRequest',
  'reject-request': 'RejectRequest', 'metadata-request': 'MetadataRequest', 'feedback-request': 'FeedbackRequest',
  'bank-line-remind-request': 'BankLineRemindRequest', 'bank-line-state-request': 'BankLineStateRequest',
  'preferences-request': 'PreferencesRequest',
};

async function contracts() {
  const document = yaml.load(readFileSync(path.resolve(__dirname, '../../restapi/swagger.yaml'), 'utf8'));
  return Parser.validate(document);
}

function validators(document) {
  const ajv = new Ajv({ strict: false, validateFormats: false, allErrors: true });
  const cache = new Map();
  return (name, data, label) => {
    const schemaName = names[name];
    assert.ok(schemaName, `Missing schema mapping: ${name}`);
    if (!cache.has(schemaName)) {
      cache.set(schemaName, ajv.compile(document.components.schemas[`T3${schemaName}`]));
    }
    const validate = cache.get(schemaName);
    assert.ok(validate(data), `${label}: ${JSON.stringify(validate.errors)}`);
  };
}

test('the complete OpenAPI document and all 16 T3 operations are valid', async () => {
  const document = await contracts();
  const schemes = document.components.securitySchemes;
  const methods = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];
  const requirements = [['global', document.security ?? []]];
  for (const [route, item] of Object.entries(document.paths)) {
    for (const method of methods) {
      if (item[method]) requirements.push([`${method.toUpperCase()} ${route}`, item[method].security ?? []]);
    }
  }
  for (const [label, security] of requirements) {
    for (const requirement of security) {
      for (const scheme of Object.keys(requirement)) {
        assert.ok(Object.hasOwn(schemes, scheme), `${label}: undefined security scheme ${scheme}`);
      }
    }
  }
  const paths = Object.values(document.paths).flatMap(item => Object.values(item))
    .filter(operation => operation?.tags?.includes('Saldi Mobile T3'));
  assert.equal(paths.length, 16);
  for (const operation of paths) {
    assert.ok(operation['x-saldi-t3-right']);
    assert.deepEqual(operation.security, [{ BearerAuth: [] }]);
    assert.ok(operation.responses['401']);
    assert.ok(operation.responses['403']);
  }
});

test('every top-level T3 request/response fixture validates against Swagger', async () => {
  const document = await contracts();
  const validate = validators(document);
  const directory = path.resolve(__dirname, 'fixtures/t3');
  const files = readdirSync(directory).filter(file => file.endsWith('.json'));
  assert.equal(files.length, Object.keys(names).length, 'New fixtures require an explicit contract mapping.');
  for (const file of files) {
    const name = file.slice(0, -5);
    const fixture = JSON.parse(readFileSync(path.join(directory, file), 'utf8'));
    validate(name, name.includes('request') ? fixture : name.startsWith('error-') ? fixture : fixture.data, file);
  }
});

test('all captured PHP success shapes validate against the same contracts', async t => {
  const output = process.env.SALDI_T3_CONTRACT_OUTPUT;
  assert.ok(output, 'Run MobileT3RightsMatrixTest with SALDI_T3_CONTRACT_OUTPUT and copy its JSON from the container.');
  const captured = JSON.parse(readFileSync(output, 'utf8'));
  const document = await contracts();
  const validate = validators(document);
  const seen = new Set();
  const scenarios = new Set();
  for (const { schema, response, scenario } of captured.cases) {
    assert.equal(response.success, true);
    assert.equal(typeof response.message, 'string');
    if (scenario) {
      await t.test(`PHP ${schema} (${scenario})`, () => validate(schema, response.data, `${schema} (${scenario})`));
    } else {
      validate(schema, response.data, `PHP ${schema}`);
    }
    seen.add(schema);
    if (scenario) scenarios.add(`${schema}:${scenario}`);
  }
  const expected = ['review-list', 'review-detail', 'approve', 'reject', 'metadata', 'audit', 'journal-drafts',
    'suggestion', 'feedback', 'classification-stats', 'bank-lines', 'bank-line-remind', 'bank-line-state',
    'preferences', 'tenant-summary', 'registry-summary'];
  assert.deepEqual([...seen].sort(), expected.sort(), 'Every distinct PHP response shape must be exercised.');
  const reviewScenarios = ['missing-metadata', 'unparsed-metadata', 'common-accounts', 'ambiguous-creditor'];
  const expectedScenarios = reviewScenarios.flatMap(scenario => ['review-list', 'review-detail']
    .map(schema => `${schema}:${scenario}`));
  assert.deepEqual([...scenarios].sort(), expectedScenarios.sort(), 'Nullable review cases must be captured from PHP.');
});
