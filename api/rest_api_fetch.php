<?php
// --- api/rest_api_fetch.php --- lap 5.1.0 --- 2026-09-22 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20260917 CDX/PHR Recognize only fixed stock export and WooCommerce item lookup requests.
// 20260922 CDX/PHR Compile legacy read queries over the ssl3 table allowlist.

/**
 * Parse the legacy request into values, never executable SQL fragments.
 * @return array{kind: string, sku?: string, shop_id?: string, shop_variant?: string}|null
 */
function restApiFetchRequest($select, $from, $where)
{
    if (!is_string($select) || !is_string($from) || !is_string($where)) {
        return null;
    }
    $select = str_replace(' ', '', $select);
    if ($from === 'varer' && $select === 'varenr,beholdning' && $where === '') {
        return ['kind' => 'stock'];
    }
    if ($select !== 'id') {
        return null;
    }
    // Apostrophes must be SQL-escaped by the caller; decode before db_escape_string.
    // Backslashes and control characters are rejected rather than interpreted.
    if ($from === 'varer' && preg_match("/\\Avarenr\\s*=\\s*'((?:[^'\\\\\\\\\\x00-\\x1f]|'')*)'\\z/u", $where, $match)) {
        return ['kind' => 'item', 'sku' => str_replace("''", "'", $match[1])];
    }
    if ($from === 'shop_varer' && preg_match("/\\Ashop_id\\s*=\\s*'([0-9]+)'\\s+and\\s+shop_variant\\s*=\\s*'([0-9]+)'\\z/i", $where, $match)) {
        return ['kind' => 'variant', 'shop_id' => $match[1], 'shop_variant' => $match[2]];
    }
    return null;
}

/** @return array<string> Legacy read tables, including the original trusted-host exceptions. */
function restApiFetchTables($remoteAddress = '')
{
    $tables = ['adresser', 'batch_kob', 'batch_salg', 'kassekladde', 'lagerstatus', 'mylabel',
        'openpost', 'ordrer', 'ordrelinjer', 'transaktioner', 'varer', 'shop_ordrer', 'shop_varer', 'rental'];
    if ($remoteAddress === '91.235.100.32') {
        $tables[] = 'mysale';
        $tables[] = 'regnskab';
    }
    return $tables;
}

/**
 * Compile the supported legacy SELECT grammar rather than interpolating SQL fragments.
 * Subqueries, arbitrary function calls, comments and statement separators are not accepted.
 */
class RestApiReadQuery
{
    private $tokens = [];
    private $position = 0;
    private $tables = [];

    private function tokenize($text)
    {
        if (!is_string($text) || strlen($text) > 20000) {
            throw new InvalidArgumentException('Invalid query input.');
        }
        $this->tokens = [];
        $this->position = 0;
        $offset = 0;
        while ($offset < strlen($text)) {
            if (preg_match('/\G\s+/A', $text, $match, 0, $offset)) {
                $offset += strlen($match[0]);
                continue;
            }
            if (!preg_match("/\G(?:'((?:[^'\\\\\x00-\x1f]|'')*)'|([a-zA-Z_][a-zA-Z0-9_]*)|([+-]?[0-9]+(?:\.[0-9]+)?)|(<=|>=|<>|!=|=|<|>|[().,*]))/A", $text, $match, 0, $offset)) {
                throw new InvalidArgumentException('Unsupported query syntax.');
            }
            $raw = $match[0];
            if ($raw[0] === "'") {
                $this->tokens[] = ['string', str_replace("''", "'", $match[1])];
            } elseif (preg_match('/^[a-zA-Z_]/', $raw)) {
                $this->tokens[] = ['word', strtolower($raw)];
            } elseif (preg_match('/^[+\-0-9]/', $raw)) {
                $this->tokens[] = ['number', $raw];
            } else {
                $this->tokens[] = ['symbol', $raw];
            }
            $offset += strlen($raw);
            if (count($this->tokens) > 2000) {
                throw new InvalidArgumentException('Query is too complex.');
            }
        }
    }

    private function peek()
    {
        return $this->tokens[$this->position][1] ?? null;
    }

    private function take($value)
    {
        if ($this->peek() !== $value || ($this->tokens[$this->position][0] ?? '') === 'string') {
            return false;
        }
        $this->position++;
        return true;
    }

    private function expect($value)
    {
        if (!$this->take($value)) {
            throw new InvalidArgumentException('Expected ' . $value);
        }
    }

    private function end()
    {
        if ($this->peek() !== null) {
            throw new InvalidArgumentException('Unexpected query token.');
        }
    }

    private function identifier($wildcard = false)
    {
        if ($wildcard && $this->take('*')) {
            return '*';
        }
        $token = $this->tokens[$this->position++] ?? null;
        if (!$token || $token[0] !== 'word') {
            throw new InvalidArgumentException('Expected field name.');
        }
        $name = $token[1];
        if ($this->take('.')) {
            if (!in_array($name, $this->tables, true)) {
                throw new InvalidArgumentException('Unknown table qualifier.');
            }
            return '"' . $name . '".' . $this->identifierPart($wildcard);
        }
        return '"' . $name . '"';
    }

    private function identifierPart($wildcard)
    {
        if ($wildcard && $this->take('*')) {
            return '*';
        }
        $token = $this->tokens[$this->position++] ?? null;
        if (!$token || $token[0] !== 'word') {
            throw new InvalidArgumentException('Expected field name.');
        }
        return '"' . $token[1] . '"';
    }

    private function value()
    {
        $token = $this->tokens[$this->position] ?? null;
        if (!$token) {
            throw new InvalidArgumentException('Expected value.');
        }
        if ($token[0] === 'string') {
            $this->position++;
            return "'" . db_escape_string($token[1]) . "'";
        }
        if ($token[0] === 'number' || in_array($token[1], ['null','true','false'], true)) {
            $this->position++;
            return $token[1];
        }
        return $this->identifier();
    }

    private function condition($depth = 0)
    {
        if ($depth > 30) {
            throw new InvalidArgumentException('Condition nesting is too deep.');
        }
        if ($this->take('not')) {
            return 'NOT (' . $this->condition($depth + 1) . ')';
        }
        if ($this->take('(')) {
            $sql = '(' . $this->expression($depth + 1) . ')';
            $this->expect(')');
            return $sql;
        }
        $left = $this->value();
        if ($this->take('is')) {
            $not = $this->take('not') ? ' NOT' : '';
            $this->expect('null');
            return $left . ' IS' . $not . ' NULL';
        }
        $not = $this->take('not') ? ' NOT' : '';
        if ($this->take('in')) {
            $this->expect('(');
            $values = [$this->value()];
            while ($this->take(',')) {
                $values[] = $this->value();
            }
            $this->expect(')');
            return $left . $not . ' IN (' . implode(', ', $values) . ')';
        }
        if ($this->take('between')) {
            $low = $this->value();
            $this->expect('and');
            return $left . $not . ' BETWEEN ' . $low . ' AND ' . $this->value();
        }
        $operator = $this->peek();
        if (!in_array($operator, ['=','<>','!=','<','>','<=','>=','like','ilike'], true)
            || ($not !== '' && !in_array($operator, ['like','ilike'], true))) {
            throw new InvalidArgumentException('Unsupported comparison.');
        }
        $this->position++;
        return $left . $not . ' ' . strtoupper($operator) . ' ' . $this->value();
    }

    private function expression($depth = 0)
    {
        $sql = $this->condition($depth);
        while (in_array($this->peek(), ['and','or'], true)) {
            $operator = strtoupper($this->peek());
            $this->position++;
            $sql .= ' ' . $operator . ' ' . $this->condition($depth);
        }
        return $sql;
    }

    /** @return string Validated read-only SELECT over explicitly allowed tables. */
    public function compile($select, $from, $where, $orderBy, $limit, $remoteAddress)
    {
        if (!is_string($from) || strlen($from) > 2000) {
            throw new InvalidArgumentException('Invalid table list.');
        }
        $this->tables = array_map('trim', explode(',', strtolower($from)));
        foreach ($this->tables as $table) {
            if (!in_array($table, restApiFetchTables($remoteAddress), true)) {
                throw new InvalidArgumentException('Unsupported table.');
            }
        }
        $this->tokenize($select);
        $distinct = $this->take('distinct') ? 'DISTINCT ' : '';
        $fields = [];
        do {
            if (in_array($this->peek(), ['count','sum','min','max','avg'], true)
                && ($this->tokens[$this->position + 1][1] ?? '') === '(') {
                $function = strtoupper($this->peek());
                $this->position++;
                $this->expect('(');
                $argumentDistinct = $this->take('distinct') ? 'DISTINCT ' : '';
                $field = $function . '(' . $argumentDistinct . $this->identifier($function === 'COUNT') . ')';
                $this->expect(')');
            } else {
                $field = $this->identifier(true);
            }
            if ($this->take('as')) {
                $field .= ' AS ' . $this->identifierPart(false);
            }
            $fields[] = $field;
        } while ($this->take(','));
        $this->end();
        $sql = 'SELECT ' . $distinct . implode(', ', $fields) . ' FROM '
            . implode(', ', array_map(function ($table) { return '"' . $table . '"'; }, $this->tables));
        $this->tokenize($where);
        if ($this->peek() !== null) {
            $sql .= ' WHERE ' . $this->expression();
        }
        $this->end();
        $this->tokenize($orderBy);
        if ($this->peek() !== null) {
            $fields = [];
            do {
                $field = $this->identifier();
                if (in_array($this->peek(), ['asc','desc'], true)) {
                    $field .= ' ' . strtoupper($this->peek());
                    $this->position++;
                }
                if ($this->take('nulls')) {
                    if (!in_array($this->peek(), ['first','last'], true)) {
                        throw new InvalidArgumentException('Invalid null ordering.');
                    }
                    $field .= ' NULLS ' . strtoupper($this->peek());
                    $this->position++;
                }
                $fields[] = $field;
            } while ($this->take(','));
            $sql .= ' ORDER BY ' . implode(', ', $fields);
        }
        $this->end();
        if (!is_string($limit) || ($limit !== '' && !preg_match('/\A\s*[0-9]+(?:\s+offset\s+[0-9]+)?\s*\z/i', $limit))) {
            throw new InvalidArgumentException('Invalid row limit.');
        }
        if (trim($limit) !== '') {
            $sql .= ' LIMIT ' . trim($limit);
        }
        return $sql;
    }
}

/** @return string|null Compiled SQL, or null for unsupported/untrusted query syntax. */
function restApiFetchSql($select, $from, $where = '', $orderBy = '', $limit = '', $remoteAddress = '')
{
    try {
        return (new RestApiReadQuery())->compile($select, $from, $where, $orderBy, $limit, $remoteAddress);
    } catch (InvalidArgumentException $error) {
        return null;
    }
}
