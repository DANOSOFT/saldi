// tools/guardrails/tests/linear-time.mjs - the markers' cost grows linearly with their input, judged without the
// machine's speed or load.
//
// A wall-clock bound (Date.now() - started < 10000 ms) fails on a loaded machine, so a bound here compares the reader
// with itself: the CPU time it spends on an input FACTOR times as large is at most BOUND times what it spends on the
// smaller one. A linear reading spends about FACTOR times as much, a quadratic one FACTOR squared (16), an exponential
// one far more. CPU time (process.cpuUsage, every thread of this process) counts only the time the process runs, so
// neither the time it waits for a CPU while other work runs nor the time PHP's tokenizer spends in its own process
// counts, and each size is the least of up to three runs, so a pause (a collection, a page fault) in one run does not
// decide. A reading that spends less than FLOOR_MS at the larger size is too small to judge and passes; that floor can
// only pass a reading, never fail one.
//
// Each run reads PHP's texts afresh: doors.mjs keeps PHP's reading of each text it has read, so a run that read a text
// an earlier run read would time what was kept, not the reading, and a reading that grows quadratically would pass.
//
// History:
// 20261106 CL/LH Created.

import assert from "node:assert/strict";
import { usePhpTokenizer } from "../lib/doors.mjs";

/** How much larger the larger input is. */
export const FACTOR = 4;
/** How many times the smaller input's CPU time the larger one may spend: between linear (FACTOR) and quadratic (FACTOR squared). */
export const BOUND = 10;
/** Below this many milliseconds of CPU at the larger size there is no growth to judge. */
export const FLOOR_MS = 250;

/** This process's CPU time so far, user and system, in milliseconds. */
export const cpuMs = () => {
  const { user, system } = process.cpuUsage();
  return (user + system) / 1000;
};

/** [the CPU milliseconds fn() spends, its value], with every PHP text read afresh (doors.mjs keeps PHP's reading per text). */
function spent(fn) {
  usePhpTokenizer(usePhpTokenizer(null));
  const from = cpuMs();
  const value = fn();
  return [cpuMs() - from, value];
}

/** Whether a reading that spends `big` ms at the larger size and `little` at the smaller one grows no faster than BOUND allows. */
export const grows = (big, little) => big < FLOOR_MS || big <= BOUND * little;

/**
 * read(n) at size `n` and at n / FACTOR, and assert that its CPU time grows no faster than BOUND allows: the least of up
 * to three runs at each size, after one run at the smaller size (the JIT and what the reader builds once). Returns
 * read(n)'s value, for the caller's assertions on what the reader decided.
 */
export function linear(label, read, n) {
  const small = Math.max(1, Math.floor(n / FACTOR));
  read(small);
  let big = Infinity;
  let little = Infinity;
  let result;
  for (let attempt = 0; attempt < 3; attempt++) {
    const [b, value] = spent(() => read(n));
    result = value;
    big = Math.min(big, b);
    little = Math.min(little, spent(() => read(small))[0]);
    if (grows(big, little)) return result;
  }
  assert.fail(`${label}: ${big.toFixed(0)} ms of CPU at size ${n} against ${little.toFixed(0)} ms at ${small}, more than ${BOUND} times for ${FACTOR} times the input`);
}
