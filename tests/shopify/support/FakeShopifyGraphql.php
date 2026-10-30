<?php
// tests/shopify/support/FakeShopifyGraphql.php
//
// A Shopify order that answers GraphQL, for the WP10-1 writeback tests (SD-759). It keeps
// the note, the tags and the metafields the way the shop would, so a replay sees what the
// previous call left behind, and it can act out the case the writeback exists for: a
// mutation that reaches Shopify and is applied, but whose response never comes back.
//
// History:
// 20261022 CL/MJ SD-759: created.

require_once dirname(__DIR__, 3) . '/includes/shopify/noteAppend.php';

final class FakeShopifyGraphql implements ShopifyGraphqlClient
{
    public string $note = '';

    /** @var array<int, string> */
    public array $tags = [];

    /** @var array<string, string> "namespace.key" => value */
    public array $metafields = [];

    /** Set false to make the order unreadable, as a deleted or wrong-shop order would be. */
    public bool $orderExists = true;

    /** @var array<int, string> Operation name of every call, in order. */
    public array $calls = [];

    /** @var array<int, array{apply: bool, throw: ?Throwable, userErrors: array<int, string>}> */
    private array $scripted = [];

    /**
     * Scripts the next mutation. $applyAnyway true is the dangerous case: Shopify applied
     * it and the response was lost, so a blind resend would apply it a second time.
     *
     * @param array<int, string> $userErrors
     */
    public function scriptNextMutation(bool $applyAnyway, ?Throwable $throw = null, array $userErrors = []): void
    {
        $this->scripted[] = ['apply' => $applyAnyway, 'throw' => $throw, 'userErrors' => $userErrors];
    }

    public function callsOf(string $operation): int
    {
        return count(array_filter($this->calls, static fn (string $c): bool => $c === $operation));
    }

    public function call(int $shopId, string $document, array $variables): array
    {
        $operation = $this->operationName($document);
        $this->calls[] = $operation;
        if ($operation === 'saldiOrderNote') {
            if (!$this->orderExists) {
                return ['data' => ['order' => null]];
            }
            return ['data' => ['order' => ['id' => $variables['id'], 'note' => $this->note]]];
        }

        $plan = array_shift($this->scripted) ?? ['apply' => true, 'throw' => null, 'userErrors' => []];
        if ($plan['userErrors'] !== []) {
            return ['data' => [$this->field($operation) => [
                'userErrors' => array_map(static fn (string $m): array => ['field' => null, 'message' => $m], $plan['userErrors']),
            ]]];
        }
        if ($plan['apply']) {
            $this->apply($operation, $variables);
        }
        if ($plan['throw'] !== null) {
            throw $plan['throw'];
        }
        return ['data' => [$this->field($operation) => $this->payload($operation, $variables)]];
    }

    /** @param array<string, mixed> $variables */
    private function apply(string $operation, array $variables): void
    {
        if ($operation === 'saldiOrderNoteAppend') {
            $this->note = (string)$variables['input']['note'];
            return;
        }
        if ($operation === 'saldiOrderTagsAdd') {
            foreach ($variables['tags'] as $tag) {
                if (!in_array($tag, $this->tags, true)) {
                    $this->tags[] = (string)$tag;
                }
            }
            return;
        }
        foreach ($variables['metafields'] as $metafield) {
            $this->metafields[$metafield['namespace'] . '.' . $metafield['key']] = (string)$metafield['value'];
        }
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function payload(string $operation, array $variables): array
    {
        if ($operation === 'saldiOrderNoteAppend') {
            return ['order' => ['id' => $variables['input']['id'], 'note' => $this->note], 'userErrors' => []];
        }
        if ($operation === 'saldiOrderTagsAdd') {
            return ['node' => ['id' => $variables['id']], 'userErrors' => []];
        }
        $written = array_map(
            static fn (array $m): array => ['namespace' => $m['namespace'], 'key' => $m['key']],
            $variables['metafields']
        );
        return ['metafields' => $written, 'userErrors' => []];
    }

    private function field(string $operation): string
    {
        return match ($operation) {
            'saldiOrderNoteAppend' => 'orderUpdate',
            'saldiOrderTagsAdd' => 'tagsAdd',
            'saldiOrderMetafields' => 'metafieldsSet',
            default => throw new RuntimeException("unscripted operation $operation"),
        };
    }

    private function operationName(string $document): string
    {
        if (!preg_match('/\b(?:query|mutation)\s+([A-Za-z0-9_]+)/', $document, $m)) {
            throw new RuntimeException("GraphQL document has no operation name: $document");
        }
        return $m[1];
    }
}
