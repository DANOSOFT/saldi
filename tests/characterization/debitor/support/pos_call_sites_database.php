<?php
// 20261023 CL/LH MB-95 Run lifted POS functions and fragments against an isolated in-memory database.
//
// The POS code lives in page scripts and files include()d into a page, so a test cannot include it.
// Functions and fragments are lifted out of the shipped source and evaluated into this namespace,
// where the db_* calls below answer them from sqlite and opret_ordrelinje() only records its call.

namespace Saldi\Tests\PosCallSites;

use PDO;

function db_select($sql, $source = null) {
    $GLOBALS['posCallSitesSql'][] = $sql;
    return $GLOBALS['posCallSitesDb']->query($sql);
}

function db_fetch_array($statement) {
    if ($statement === false) {
        return false;
    }
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function db_modify($sql, $source = null) {
    $GLOBALS['posCallSitesSql'][] = $sql;
    $GLOBALS['posCallSitesDb']->exec($sql);
    return "0\tquery accepted";
}

/** includes/std_func.php afrund(): round with the float cast it got 20260217. */
function afrund($tal, $decimaler) {
    return round((float)$tal, (int)$decimaler);
}

/** Records the line opret_saet() asks for; the line itself is not what these tests are about. */
function opret_ordrelinje(...$args) {
    $GLOBALS['posCallSitesLines'][] = $args;
    return count($GLOBALS['posCallSitesLines']);
}

function find_kasse($kasse) {
    return $kasse;
}

function alert($message) {
    $GLOBALS['posCallSitesAlerts'][] = (string)$message;
}

/** Source code of one PHP file, read from the repository root. */
function shipped(string $relativePath): string {
    $path = dirname(__DIR__, 4) . '/' . $relativePath;
    $src = file_get_contents($path);
    if ($src === false) {
        throw new \RuntimeException("cannot read $relativePath");
    }
    return $src;
}

/**
 * The code from $needle up to the brace that closes the first block opened after it.
 *
 * Braces are counted on tokens, so a brace inside a string or comment does not count.
 */
function liftBlock(string $src, string $needle): string {
    $from = strpos($src, $needle);
    if ($from === false) {
        throw new \RuntimeException("'$needle' is no longer in the source");
    }
    $tokens = token_get_all('<?php ' . substr($src, $from));
    array_shift($tokens);
    $code = '';
    $depth = 0;
    $opened = false;
    foreach ($tokens as $token) {
        $text = is_array($token) ? $token[1] : $token;
        $code .= $text;
        if ($text === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
            $opened = true;
        } elseif ($text === '}') {
            $depth--;
            if ($opened && $depth === 0) {
                return $code;
            }
        }
    }
    throw new \RuntimeException("no closing brace after '$needle'");
}

/** The code from $start up to and including $end. */
function liftBetween(string $src, string $start, string $end): string {
    $from = strpos($src, $start);
    if ($from === false) {
        throw new \RuntimeException("'$start' is no longer in the source");
    }
    $to = strpos($src, $end, $from);
    if ($to === false) {
        throw new \RuntimeException("'$end' no longer follows '$start'");
    }
    return substr($src, $from, $to + strlen($end) - $from);
}

/**
 * Every executable call of $name in a file, as source text.
 *
 * Tokens, not a regex: a mention in a comment or a log string is never a T_STRING.
 *
 * @return list<string>
 */
function callsOf(string $src, string $name): array {
    $tokens = token_get_all($src);
    $count = count($tokens);
    $calls = [];
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || $tokens[$i][1] !== $name) {
            continue;
        }
        $prev = $i - 1;
        while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
            $prev--;
        }
        if ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_FUNCTION) {
            continue;   // the definition, not a call
        }
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if ($j >= $count || $tokens[$j] !== '(') {
            continue;
        }
        $text = $name;
        $depth = 0;
        for (; $j < $count; $j++) {
            $piece = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            $text .= $piece;
            if ($piece === '(') {
                $depth++;
            } elseif ($piece === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
        }
        $calls[] = $text;
    }
    return $calls;
}

/** Evaluates opret_saet(), gendan_saet() and usdecimal() into this namespace, once per process. */
function liftSetFunctions(): void {
    static $lifted = false;
    if ($lifted) {
        return;
    }
    $ordrefunc = shipped('includes/ordrefunc.php');
    $usdecimal = shipped('includes/stdFunc/usDecimal.php');
    $code = liftBlock($ordrefunc, 'function opret_saet(')
        . "\n" . liftBlock($ordrefunc, 'function gendan_saet(')
        . "\n" . liftBlock($usdecimal, 'function usdecimal(');
    eval('namespace ' . __NAMESPACE__ . '; ' . $code);
    $lifted = true;
}
