// 20261001 CDX/LH Check real HTTP responses, published fixtures and real-login app navigation together.
import { readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { z } from 'zod';
import { AccessibilityInfo } from 'react-native';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { Providers } from '../../mobile/__tests__/ui/setup';
import { responseSchemas, successEnvelope, moneyMeSchema } from '../../mobile/src/money/schemas';
import { canUseMoney, customerSource, productSource } from '../../mobile/src/money/access';
import { pengeGate, pengeTabVisible } from '../../mobile/src/features/penge/model';
import { PengeHomeView } from '../../mobile/src/features/penge/PengeHomeView';

const output = process.env.SALDI_T2_CONTRACT_OUTPUT;
if (!output) { throw new Error('Export MobileT2RightsMatrixTest with SALDI_T2_CONTRACT_OUTPUT first.'); }
const exported = z.object({
  cases: z.array(z.object({ schema: z.string(), response: z.unknown() })).min(1),
  personas: z.record(z.string(), successEnvelope(moneyMeSchema)),
}).parse(JSON.parse(readFileSync(output, 'utf8')));
function schemaKey(name: string): keyof typeof responseSchemas {
  if (!(name in responseSchemas)) { throw new Error(`Unknown mobile response contract: ${name}`); }
  return name as keyof typeof responseSchemas;
}

test.each(exported.cases.map((value, index) => ({ ...value, index })))('real PHP $schema response $index matches app', value => {
  expect(() => successEnvelope(responseSchemas[schemaKey(value.schema)]).parse(value.response)).not.toThrow();
});

const fixtureDirectory = resolve(__dirname, 'fixtures/t2');
const fixtureNames = readdirSync(fixtureDirectory).filter(name => name.endsWith('.json') && name !== 'open-posts-accounts.json');
test.each(fixtureNames)('published %s matches its schema and has a real HTTP counterpart', name => {
  const key = schemaKey(name === 'open-posts-list.json' ? 'open-posts' : name.replace('.json', ''));
  successEnvelope(responseSchemas[key]).parse(JSON.parse(readFileSync(resolve(fixtureDirectory, name), 'utf8')));
  expect(exported.cases.some(value => value.schema === key)).toBe(true);
});

beforeEach(() => { jest.spyOn(AccessibilityInfo, 'isReduceMotionEnabled').mockResolvedValue(false); });
const personas = [
  { module: 'debitorordre', customers: false, products: true, sale: true, posts: false, tab: true },
  { module: 'debitorkonti', customers: true, products: false, sale: false, posts: false, tab: true },
  { module: 'varer', customers: false, products: false, sale: false, posts: false, tab: false },
  { module: 'debitorrapport', customers: false, products: false, sale: false, posts: true, tab: true },
];
test.each(personas)('$module real login renders only permitted Penge sections', async persona => {
  const response = exported.personas[persona.module];
  if (!response) { throw new Error(`Missing real persona: ${persona.module}`); }
  const me = response.data;
  expect(pengeTabVisible(me)).toBe(persona.tab);
  const onCustomers = jest.fn(), onProducts = jest.fn(), onSale = jest.fn();
  const posts = exported.cases.find(value => value.schema === 'open-posts');
  if (!posts) { throw new Error('Missing real open-post page.'); }
  const page = successEnvelope(responseSchemas['open-posts']).parse(posts.response).data;
  await render(<PengeHomeView gate={pengeGate(me, false)} posts={{ kind: 'ready', value: page }} morePosts={[]} more={null}
    bucket={null} offline={false} updatedAt={null} refreshing={false} onSelectBucket={jest.fn()} onRefresh={jest.fn()}
    onRetry={jest.fn()} onOpenPost={jest.fn()} rowActions={() => ({})} onOpenCustomers={onCustomers}
    onOpenProducts={onProducts} onOpenNewSale={onSale} />, { wrapper: Providers });
  for (const [id, visible, callback] of [
    ['penge-lookup-customers', persona.customers, onCustomers],
    ['penge-lookup-products', persona.products, onProducts],
    ['penge-lookup-sale', persona.sale, onSale],
  ] as const) {
    expect(screen.queryByTestId(id) !== null).toBe(visible);
    if (visible) { await fireEvent.press(screen.getByTestId(id)); expect(callback).toHaveBeenCalledTimes(1); }
  }
  expect(screen.queryByTestId('penge-hero') !== null).toBe(persona.posts);
  if (persona.module === 'debitorordre') {
    expect(customerSource(me)).toBe('salesCustomers');
    expect(productSource(me)).toBe('salesProducts');
    expect(canUseMoney(me, 'openPosts')).toBe(false);
  }
  if (persona.module === 'varer') {
    // T2 §5.1 excludes varer-only from the Penge tab; its catalogue endpoints remain usable.
    expect(canUseMoney(me, 'products')).toBe(true);
    expect(productSource(me)).toBe('products');
  }
});
