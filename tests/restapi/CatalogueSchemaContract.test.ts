// 20261001 CDX/LH Parse exported real PHP catalogue responses through the mobile's unchanged schemas.
import { readFileSync } from 'node:fs';
import { z } from 'zod';
import { responseSchemas, successEnvelope } from '../../mobile/src/money/schemas';

const fixtureSchema = z.array(z.object({
  schema: z.enum(['products-search', 'products-barcode', 'products-detail', 'products-changes',
    'sales-products', 'sales-products-barcode', 'sales-products-changes']),
  response: z.unknown(),
  expected_modtime: z.string().nullable(),
})).length(90);
const output = process.env.SALDI_CATALOGUE_CONTRACT_OUTPUT;
if (!output) {
  throw new Error('Run CatalogueEndpointTest with SALDI_CATALOGUE_CONTRACT_OUTPUT, then pass the exported response file here.');
}
const fixtures = fixtureSchema.parse(JSON.parse(readFileSync(output, 'utf8')));

test.each(fixtures.map((fixture, index) => ({ ...fixture, index })))('$schema response $index matches the mobile contract', fixture => {
  const parsed = successEnvelope(responseSchemas[fixture.schema]).parse(fixture.response);
  const products = 'items' in parsed.data ? parsed.data.items : [parsed.data];
  expect(products).toHaveLength(1);
  for (const product of products) {
    expect(product.modtime).toBe(fixture.expected_modtime);
  }
});
