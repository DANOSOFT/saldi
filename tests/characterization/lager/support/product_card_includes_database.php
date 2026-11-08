<?php
// 20261105 CL/LH Created. A fake database and text lookup for ProductCardIncludesWarningsTest, which includes lager/productCardIncludes/ files the way lager/varekort.php does.
//                It is loaded only in that test's separate processes, so the global db_select() and findtekst() below never meet the real ones.

/** One query's rows, handed back by db_fetch_array() one at a time. */
final class ProductCardIncludesResult
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /** @return array<string, mixed>|false */
    public function next()
    {
        if ($this->rows === array()) {
            return false;
        }
        return array_shift($this->rows);
    }
}

/**
 * Routes each select to fixture rows by pattern. A select no pattern matches is an error rather than an
 * empty result, so a query the include gains or changes shows up as a failure.
 */
final class ProductCardIncludesDb
{
    /** @var array<int, array{0: string, 1: array<int, array<string, mixed>>}> */
    public static array $plan = array();
    /** @var array<int, string> */
    public static array $writes = array();

    public static function select(string $sql): ProductCardIncludesResult
    {
        foreach (self::$plan as $entry) {
            if (preg_match($entry[0], $sql)) {
                return new ProductCardIncludesResult($entry[1]);
            }
        }
        throw new RuntimeException('no fixture matches query: ' . $sql);
    }
}

function db_select($qtext, $spor = '')
{
    return ProductCardIncludesDb::select((string) $qtext);
}

function db_fetch_array($query)
{
    return $query->next();
}

function db_modify($qtext, $spor = '')
{
    ProductCardIncludesDb::$writes[] = (string) $qtext;
    return true;
}

function db_escape_string($text)
{
    return str_replace("'", "''", (string) $text);
}

function saldi_schema_filter()
{
    return '';
}

/** The text id itself, so the page renders without the tekster tables. */
function findtekst($textId, $languageId)
{
    return 'T' . $textId;
}
