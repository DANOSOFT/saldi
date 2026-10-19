<?php
// ---------------------------------------------------------------------------
// doc/examples/intelephense_var_provenance.php
//
// Reference bench for the @var provenance convention:
//   doc/ai/convention_global_variable_provenance.md
//
// This file is NOT wired into the app and is never included — it exists only to
// be opened in the IDE so the Intelephense "Undefined variable" squiggles can be
// checked against the EXPECT notes on each scenario. If a scenario ever stops
// matching its EXPECT note, Intelephense's behaviour changed and the convention
// should be re-checked.
//
// Each scenario uses its OWN variable name, because file scope is shared across
// the whole file: a top-of-file @var block would otherwise clear a variable for
// every scenario below it and hide the effect being demonstrated.
//
// This file should show exactly 5 "Undefined variable" errors: F2, G3, G4, L1, MP.
// (Everything else is clean — position within a scope does not matter; only a
// well-formed docblock and scope boundaries do.)
// ---------------------------------------------------------------------------

namespace Doc\Examples\VarProvenance;

/**
 * Top-of-file provenance block — placed after the header comments but BEFORE the
 * first executable statement. This is the default/preferred placement.
 *
 * Phrase the source line for the direction that applies:
 *   A (this file runs the include itself):
 *     Injected by ../includes/connect.php, included below:
 *   B (a parent entry page already ran the include):
 *     Injected by ../includes/connect.php via the entry page that includes this file:
 *
 * @var string $fileTop
 */

// === FILE SCOPE ============================================================

// F1  Covered by the top-of-file block above, used far below.  EXPECT: clean
echo $fileTop;

// F2  No annotation anywhere.                                  EXPECT: squiggle
echo $fileControl;

// F3  Block placed MID-CODE (after statements have begun), use further down.
//     Position within a scope turns out NOT to matter: a well-formed @var
//     declares the variable for the whole scope.               EXPECT: clean
$noise = 1;
/** @var string $fileMid */
$noise = 2;
$noise = 3;
echo $fileMid;

// F4  Block IMMEDIATELY before the use.                        EXPECT: clean
/** @var string $fileBeforeUse */
echo $fileBeforeUse;

// === PLACEMENT IS FREE (within a scope) ====================================
// Resolved 2026-07-22: F3 and ALL of MA-MD are CLEAN. A well-formed @var block
// declares its variable for the whole enclosing scope regardless of where it
// sits or what statement follows it — single- or multi-line, before or after
// other code, even glued to an assignment of a different variable. Position is
// therefore a STYLE choice, not a tooling requirement. The only real constraints
// are a well-formed docblock (see MP) and scope boundaries (see G4).

// MA  single-line block, followed by a NON-assignment statement.  EXPECT: clean
function ma_single_nonassign()
{
    echo "x";
    /** @var string $ma */
    echo "y";
    echo $ma;
}

// MB  multi-line block, followed by a NON-assignment statement.   EXPECT: clean
function mb_multi_nonassign()
{
    echo "x";
    /**
     * @var string $mb
     */
    echo "y";
    echo $mb;
}

// MC  single-line block, followed by an assignment to ANOTHER var. EXPECT: clean
function mc_single_assign_other()
{
    $o = 1;
    /** @var string $mc */
    $o = 2;
    echo $mc;
}

// MD  multi-line block, followed by an assignment to ANOTHER var.  EXPECT: clean
function md_multi_assign_other()
{
    $o = 1;
    /**
     * @var string $md
     */
    $o = 2;
    echo $md;
}

// MP  MALFORMED one-liner: descriptive prose + a path containing ':' crammed in
//     front of the @var tag — exactly the docblock the ORIGINAL failing
//     pbs_import test used. Intelephense does not parse the tag, so $mp stays
//     undefined. THIS (not placement) was the real cause of the original
//     "after-include" failure.                                  EXPECT: squiggle
function mp_malformed_oneliner()
{
    /** Injected by ../includes/connect.php: @var string $mp */
    echo $mp;
}

// === FUNCTION SCOPE ========================================================
// A @var block is honoured scope-wide only within the scope it sits in.

// G1  @var at the TOP OF THE FUNCTION BODY, used far below.    EXPECT: clean
function g1_top_of_body()
{
    /** @var string $fnTop */
    $a = 1;
    $b = 2;
    $c = $a + $b;
    echo $c;
    echo $fnTop;
}

// G2  @var IMMEDIATELY before the use inside a function.       EXPECT: clean
function g2_before_use()
{
    $a = 1;
    $b = 2;
    echo $a + $b;
    /** @var string $fnBeforeUse */
    echo $fnBeforeUse;
}

// G3  No annotation inside the function.                       EXPECT: squiggle
function g3_control()
{
    echo $fnControl;
}

// G4  SCOPE ISOLATION: $fileTop is cleared at file scope by the top-of-file
//     block (see F1), but that block does NOT reach into a function body.
//                                                              EXPECT: squiggle
function g4_isolation()
{
    echo $fileTop;
}

// === LOCAL COUNTER — NOT injected, so do NOT annotate ======================
// The real-world trap (pbs_import.php's $komma): a variable that only looks
// undefined because it is a local accumulator used before initialisation. It is
// NOT injected by any include, so a provenance @var would be false AND would
// mask a genuine "used before initialised" local. The fix is to initialise it.

// L1  Local accumulator used before initialisation.           EXPECT: squiggle
function l1_counter_wrong()
{
    $komma++;
    return $komma;
}

// L2  The honest fix — initialise the counter first.          EXPECT: clean
function l2_counter_fixed()
{
    $komma = 0;
    $komma++;
    return $komma;
}

// === Note: file-included-inside-a-function ================================
// A file whose top-level code runs inside a function (because it is include()d
// there, e.g. kreditor/orderIncludes/moveOrderLines.php) cannot be shown in this
// single file — it needs two files. Intelephense analyses such a file at FILE
// scope regardless, so its provenance block still goes at the top of that file,
// exactly like F1. See the Placement section of the convention.
