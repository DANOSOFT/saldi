<?php

// tests/characterization/debitor/support/pos_lift.php
//
// Lifts named functions out of a shipped file so a child-process runner can use the real
// ones (SD-772).
//
// The POS characterization runners need a handful of functions that live in files which
// cannot simply be require()d: includes/std_func.php and includes/ordrefunc.php run their
// own includes and expect a configured page. Standing them in instead would be worse,
// because the arithmetic in afrund()/pos_afrund() and the exact emptiness rules in ifset()
// are part of what is being pinned - a stand-in would pin the stand-in.
//
// The tokenizer is used rather than a regex so that a brace inside a string or a comment
// cannot end a function early.
//
// History:
// 20261026 CL/MJ SD-772: created (M-PREP for S12).

/**
 * The source of the named functions, exactly as they ship.
 *
 * @param string $path File to read.
 * @param list<string> $names Functions to take from it; anything else is left behind.
 * @return string PHP source, ready to eval.
 */
function pos_lift(string $path, array $names): string
{
    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "cannot read $path\n");
        exit(2);
    }
    $tokens = token_get_all($source);
    $count = count($tokens);
    $lifted = '';
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $j = $i + 1;
        while (
            $j < $count && is_array($tokens[$j])
            && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ) {
            $j++;
        }
        if (
            $j >= $count || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING
            || !in_array($tokens[$j][1], $names, true)
        ) {
            continue;
        }
        $depth = 0;
        $open = false;
        $code = '';
        for ($k = $i; $k < $count; $k++) {
            $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
            $code .= $text;
            if ($text === '{') {
                $depth++;
                $open = true;
            } elseif ($text === '}') {
                $depth--;
                if ($open && $depth === 0) {
                    break;
                }
            }
        }
        $lifted .= $code . "\n";
    }
    return $lifted;
}

/**
 * Lifts and defines every function in the map, then checks each one arrived.
 *
 * A function that has moved or been renamed fails the run here, with its name, instead of
 * somewhere far away inside the code under test.
 *
 * @param array<string, list<string>> $files Path => functions to take from it.
 */
function pos_lift_into(array $files): void
{
    $source = '';
    foreach ($files as $path => $names) {
        $source .= pos_lift($path, $names);
    }
    eval($source);
    foreach ($files as $path => $names) {
        foreach ($names as $name) {
            if (!function_exists($name)) {
                fwrite(STDERR, "$name() is no longer in " . basename($path) . "\n");
                exit(2);
            }
        }
    }
}
