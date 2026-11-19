// tools/guardrails/tests/doors-js-syntax.test.mjs - the password literal marker (lib/doors.mjs) on JavaScript's template
// interpolations, decorators, a "/" after function and class expressions, automatic semicolon insertion and names
// written with escapes.
//
// A template's interpolations are read as JavaScript reads them (regular expressions, comments, strings and templates),
// and so is a "/" that only the grammar places (after a condition's ")", a block's "}", a property named by a keyword, a
// postfix "++", a function's or a class's body whether the keyword stood as an expression or a declaration). Line
// breaks are read as automatic semicolon insertion reads them (block comments that hold one too), property names as
// operands, async only before function on its line, break, continue and labels, and TypeScript's x!; a changed line
// reads on from the last two tokens above it. Where the tokens cannot show JavaScript's reading, or JavaScript may read
// either way, the line counts, and so does every later line whose state that leaves unknown; function and class
// keywords waiting for their body count toward LEAD_FRAMES, so the reading stays linear. A comment, a regular expression
// or a template inside a template's interpolation is outside the plain subset (doors.mjs "The plain subset"), so a line
// that names a password there counts with a reference too (assertCounts), while every plain interpolation (strings,
// groups, divisions, names) still decides (assertDecides). Names are read with their escapes decoded (JavaScript, PHP,
// Python, YAML), Python's identifiers NFKC-normalized, and a string continued by a backslash over lines; a value an
// escape spells is never a placeholder. Every JavaScript and TypeScript case parses in TypeScript with no diagnostic,
// but the failing-closed cases that JavaScript reads no way. This file's own templates hold no such interpolation, so
// it stays in the plain subset itself.
//
// History:
// 20261105 CL/LH Created.
import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { randomBytes } from "node:crypto";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
// PHP files are read by PHP's own tokens (lib/php.mjs gives doors.mjs its tokenizer).
import "../lib/php.mjs";
import { firstMatch, markerHits, stationReport } from "../lib/station.mjs";
import { addedWithContext } from "../lib/changeset.mjs";
import { ONE_WAY_ROWS } from "../lib/doors.mjs";
import { FACTOR, grows, linear } from "./linear-time.mjs";

const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));
// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const REF = "process.env.X";
const literal = () => `'v${randomBytes(6).toString("hex")}'`;
const NONE = Object.freeze({ hits: [], humanOnly: false });
const B = "`";
const q = "'";

/** The password marker's lines and humanOnly, as check.mjs routes `entries` of `path` (markerHits and stationReport). */
function routed(path, entries) {
  const hits = markerHits(path, entries).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
  return { hits, humanOnly: report.humanOnly };
}
/** The whole of `text` added, as a new file (its first line the file's first). */
const wholeHit = (path, text) => routed(path, text.split("\n").map((line, i) => ({ line: i + 1, text: line })));
/** Only line `n` of `text` changes; the lines above it are unchanged context (changeset.mjs addedWithContext). */
function onlyHit(path, text, n) {
  const file = text.split("\n");
  return routed(path, addedWithContext(new Map([[n, file[n - 1]]]), `${text}\n`));
}
/** The line of `text` that holds `value`, 1-based. */
const lineOf = (text, value) => 1 + text.split("\n").findIndex((line) => line.includes(value));
const hitAt = (n) => ({ hits: [n], humanOnly: true });
/** The password spellings that read `path`, each on one line alone (pattern.test: a reader with one line only). */
const readAlone = (path, text) =>
  ONE_WAY_ROWS.find((row) => row.id === "secrets")
    .markers.filter((m) => m.marker === "password literal" && (!m.in || firstMatch(m.in, path)) && !(m.notIn && firstMatch(m.notIn, path)))
    .some((m) => m.pattern.test(text));

/**
 * A git repository in a scratch directory with `files` ([path, base lines or null, head lines]) committed as base and
 * written as head: what `check.mjs --station --json --base HEAD` routes as the password marker's hits and humanOnly.
 */
function stationOnChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-js-syntax-"));
  const git = (...args) => spawnSync("git", ["-c", "user.name=guardrails-test", "-c", "user.email=guardrails-test@example.invalid", "-c", "commit.gpgsign=false", ...args], { cwd: dir, encoding: "utf8" });
  const write = (path, lines) => {
    mkdirSync(dirname(join(dir, path)), { recursive: true });
    writeFileSync(join(dir, path), `${lines.join("\n")}\n`);
  };
  try {
    assert.equal(git("init", "-q").status, 0);
    for (const [path, base] of files) if (base) write(path, base);
    assert.equal(git("add", "-A").status, 0);
    assert.equal(git("commit", "-q", "--allow-empty", "--no-verify", "-m", "base").status, 0);
    for (const [path, , head] of files) write(path, head);
    const result = spawnSync(process.execPath, [CHECK, "--station", "--json", "--base", "HEAD"], { cwd: dir, encoding: "utf8", timeout: 60000 });
    assert.equal(result.status, 0, result.stderr);
    const report = JSON.parse(result.stdout);
    return { hits: report.markersHit.filter((hit) => hit.row === "secrets").map((hit) => `${hit.path}:${hit.line}`).sort(), humanOnly: report.humanOnly };
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

/**
 * Interpolation code that a reader of brackets and quotes alone misreads: regular expressions,
 * comments, strings and templates that hold a quote, a backtick or a brace, and code whose "/" only JavaScript's grammar
 * places (a condition's ")", a block's "}", a property named by a keyword, a postfix "++", a division). [code, plain]:
 * a regular expression, a comment or a template there is outside the plain subset, the rest inside it.
 */
const INTERPOLATIONS = [
  [`s.replace(/'/g, '')`, false],
  [`s.replace(/${B}/g, '')`, false],
  [`s.replace(/}/g, '')`, false],
  [`s.replace(/[${B}'"}{]/g, "")`, false],
  [`s.replace(/"/g, "")`, false],
  [`a /* ' */`, false],
  [`a /* ${B} */`, false],
  [`a /* } */`, false],
  [`a /* \${ */`, false],
  [`a // ' ${B} }\n`, false],
  [`a /* x\n ' */`, false],
  [`${B}\${a}${B}`, false],
  [`${B}x\${${B}y\${z}${B}}${B}`, false],
  [`'}'`, true],
  [`"${B}"`, true],
  [`({ a: '}' }).a`, true],
  [`(() => { if (a) /'/.test(s); return 1; })()`, false],
  [`(() => { {} /'/.test(s); return 1; })()`, false],
  [`(() => { while (a) /'/.test(s); return 1; })()`, false],
  [`f(a, /'/)`, false],
  [`a ? /'/ : /"/`, false],
  [`typeof /'/`, false],
  [`x.return / 2`, true],
  [`y++ / 2`, true],
  [`a / b / c`, true],
];
/** Where the interpolation stands: after the template's line break on the member's line (the probe), on one line, on the line above, over lines of its own, in a tagged template. */
const PLACEMENTS = [
  ["after the template's line break", (i) => `@Dec(${B}x\n\${${i}}${B})`],
  ["on one line", (i) => `@Dec(${B}\${${i}}${B})`],
  ["on the line above", (i) => `@Dec(${B}\${${i}}\n${B})`],
  ["over lines of its own", (i) => `@Dec(${B}x\n\${\n${i}\n}${B})`],
  ["in a tagged template", (i) => `@tag${B}x\n\${${i}}${B}`],
];
/** The member forms of station.test.mjs membersOverLines. */
function members(name) {
  return [
    ["a field", (d, v) => `class C {\n  ${d} ${name}: string = ${v};\n}`],
    ["a single-quoted field", (d, v) => `class C {\n  ${d} ${q}${name}${q}: string = ${v};\n}`],
    ["a double-quoted field", (d, v) => `class C {\n  ${d} "${name}": string = ${v};\n}`],
    ["a computed field", (d, v) => `class C {\n  ${d} [${q}${name}${q}]: string = ${v};\n}`],
    ["an untyped field", (d, v) => `class C {\n  ${d} ${name} = ${v};\n}`],
    ["an untyped quoted field", (d, v) => `class C {\n  ${d} ${q}${name}${q} = ${v};\n}`],
    ["a method", (d, v) => `class C {\n  ${d} ${name}(): string { return ${v}; }\n}`],
    ["a quoted method", (d, v) => `class C {\n  ${d} ${q}${name}${q}() { return ${v}; }\n}`],
    ["a computed method", (d, v) => `class C {\n  ${d} [${q}${name}${q}]() { return ${v}; }\n}`],
    ["a parameter", (d, v) => `class C {\n  constructor(${d} ${name}: string = ${v}) {}\n}`],
  ];
}
/** A template, then a declaration on its line or the next: a let, a key, a subscript's assignment. */
function statements(name) {
  return [
    ["a let after it", (t, v) => `const t = ${t}; let ${name}: string = ${v};`],
    ["a let on the next line", (t, v) => `const t = ${t};\nlet ${name}: string = ${v};`],
    ["a key after it", (t, v) => `f(${t}, { ${name}: ${v} });`],
    ["a subscript after it", (t, v) => `x = ${t}; cfg[${q}${name}${q}] = ${v};`],
  ];
}
/** A "/" outside any template that only JavaScript's grammar places, V for the value. */
function slashes(name) {
  return [
    `if (x) /'/.test(s) && (${name} = V);`,
    `if (x) /'/.test(s); ${name} = V;`,
    `function f() {} /'/.test(s); const ${name} = V;`,
    `if (a &&\n    b) /'/.test(s) && (${name} = V);`,
    `while (a &&\n    b) /'/.test(s) && (${name} = V);`,
    `for (const k of ks) /'/.test(k) && (${name} = V);`,
    `if (a) {\n  x();\n}\n/'/.test(s) && (${name} = V);`,
    `x = (a &&\n    b) / 2; const ${name} = V;`,
    `const o = {\n  a: 1 } / 2; const ${name} = V;`,
    `const avg = sum\n  / total; const ${name} = V; // ok`,
    `const r = x.return / 2 / y; const ${name} = V;`,
    `i++ / 2 / j; const ${name} = V;`,
    `function f() {\n  return\n  /'/.test(s) || (${name} = V);\n}`,
  ];
}

/** mulberry32: a seeded generator whose arithmetic stays exact, so the mix below is the same on every run. */
function seeded(seed) {
  let s = seed | 0;
  return (n) => {
    s = (s + 0x6d2b79f5) | 0;
    let t = Math.imul(s ^ (s >>> 15), 1 | s);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) % n;
  };
}
/** A seeded mix: one or two interpolations with line breaks where JavaScript allows them, in a decorator before a member or a template before a statement. */
function mixed(name, count, seed) {
  const rnd = seeded(seed);
  const pick = (xs) => xs[rnd(xs.length)];
  const br = () => (rnd(3) === 0 ? "\n" : " ");
  let plain = true;
  const piece = () => {
    const [inner, innerPlain] = pick(INTERPOLATIONS);
    plain &&= innerPlain;
    const k = rnd(3);
    return k === 0 ? `\${${inner}}` : k === 1 ? `\${${br()}${inner}${br()}}` : `x${rnd(2) ? "\n" : ""}\${${inner}}y`;
  };
  const cases = [];
  for (let n = 0; n < count; n++) {
    plain = true;
    const t = `${B}${piece()}${rnd(2) ? piece() : ""}${rnd(2) ? "\n" : ""}${B}`;
    if (rnd(2)) {
      const [label, form] = pick(members(name));
      cases.push([`${label} after @Dec(${JSON.stringify(t)})`, (v) => form(`@Dec(${t})`, v), plain]);
    } else {
      const [label, form] = pick(statements(name));
      cases.push([`${label}, ${JSON.stringify(t)}`, (v) => form(t, v), plain]);
    }
  }
  return cases;
}

/** Assert that `form` counts on its value's line with a literal, whole and where only that line changes, and not with a reference. */
function assertDecides(path, label, form) {
  const lit = literal();
  const hit = form(lit);
  const ref = form(REF);
  const n = lineOf(hit, lit);
  assert.deepEqual(wholeHit(path, hit), hitAt(n), `${label}: a literal`);
  assert.deepEqual(wholeHit(path, ref), NONE, `${label}: a reference`);
  assert.deepEqual(onlyHit(path, hit, n), hitAt(n), `${label}: a literal, only its line changes`);
  assert.deepEqual(onlyHit(path, ref, n), NONE, `${label}: a reference, only its line changes`);
}

/**
 * Assert that `form`, outside the plain subset, counts on its value's line with a literal and with a
 * reference alike, whole and where only that line changes: no reader clears a line that names a password there.
 */
function assertCounts(path, label, form) {
  const lit = literal();
  const hit = form(lit);
  const n = lineOf(hit, lit);
  for (const [v, kind] of [
    [lit, "a literal"],
    [REF, "a reference, outside the plain subset"],
  ]) {
    assert.deepEqual(wholeHit(path, form(v)), hitAt(n), `${label}: ${kind}`);
    assert.deepEqual(onlyHit(path, form(v), n), hitAt(n), `${label}: ${kind}, only its line changes`);
  }
}

/** assertDecides inside the plain subset, assertCounts outside it. */
const assertReads = (path, label, form, plain) => (plain ? assertDecides : assertCounts)(path, label, form);

test("secrets, through markerHits and stationReport: the probe, a decorator template whose interpolation holds /'/ on the member's own line, counts with a literal, and with a reference too: a regular expression in an interpolation is outside the plain subset", () => {
  const lit = literal();
  const probe = (v) => `class C { @Dec(${B}x\n\${s.replace(/'/g, '')}${B}) ${NAME}: string = ${v}; }`;
  for (const v of [lit, REF]) {
    assert.deepEqual(wholeHit("api/client.ts", probe(v)), hitAt(2), v);
    assert.deepEqual(onlyHit("api/client.ts", probe(v), 2), hitAt(2), v);
    // The probe on one line, read alone (a reader with one line only).
    assert.equal(readAlone("api/client.ts", `class C { @Dec(${B}\${s.replace(/'/g, '')}${B}) ${NAME}: string = ${v}; }`), true, v);
  }
});

test("secrets, decorator interpolations, through markerHits and stationReport: a string, a group or a division in a decorator template's interpolation is read as JavaScript reads it, wherever the interpolation stands, so a word, quoted and computed field, a method and a parameter count with a literal, also where only the member's line changes, and not with a reference; with a regular expression, a comment or a template there (outside the plain subset) they count with a reference too", () => {
  // 25 interpolations x 5 placements x 10 members.
  for (const [inner, plain] of INTERPOLATIONS) {
    for (const [where, place] of PLACEMENTS) {
      for (const [label, member] of members(NAME)) assertReads("api/client.ts", `${JSON.stringify(inner)} ${where}, ${label}`, (v) => member(place(inner), v), plain);
    }
  }
});

test("secrets, templates before a statement, through markerHits and stationReport: the same interpolations in a template before a statement, and a \"/\" outside any template that only JavaScript's grammar places (after a condition's \")\" or a block's \"}\", over lines, after return), count with a literal and not with a reference, in .ts and .js files; outside the plain subset with a reference too", () => {
  for (const path of ["api/client.ts", "api/client.js"]) {
    for (const [inner, plain] of INTERPOLATIONS) {
      for (const [label, st] of statements(NAME)) assertReads(path, `${label}, ${JSON.stringify(inner)}`, (v) => st(`${B}\${${inner}}${B}`, v), plain);
    }
    for (const text of slashes(NAME)) assertDecides(path, JSON.stringify(text), (v) => text.replace("V", v));
  }
});

test("secrets: a seeded mix of 600 templates, one or two interpolations each with line breaks where JavaScript allows them, before a member or a statement, decides as JavaScript reads it inside the plain subset, and counts outside it", () => {
  for (const [label, form, plain] of mixed(NAME, 600, 9)) assertReads("api/client.ts", label, form, plain);
});

test("secrets: where the tokens cannot show how JavaScript reads the code (a quote a line break ends in an interpolation, a close that opens nothing, a \"}\" after a case's \":\" or a type's \">\", a function's or a class's body that may be an expression's or a declaration's, a \"/\" at the top of lines whose lines above are not known), the line counts with a reference too, and so does each later line of the block that names a password; elsewhere the same lines decide", () => {
  const ts = "api/client.ts";
  const lit = literal();
  for (const file of [
    // JavaScript reads none of these templates: what follows them is unknown.
    `const t = ${B}\${'a\n}${B};\nclass C { ${NAME}: string = V; }`,
    `const t = ${B}\${a)}${B};\nclass C { ${NAME}: string = V; }`,
    `const t = ${B}\${a)}${B};\nconst u = 1;\nclass C { ${NAME}: string = V; }`,
    // A "{" after a case's ":" opens a block or an object: the "/" after its "}" is a regular expression or a division.
    `switch (k) {\n  case 1: {} /'/.test(s) && (${NAME} = V);\n}`,
    // A "{" after a type's ">" that no function or class keyword waits for (a function's body after its return type decides: below).
    `let x: Array<number>\n{}\n/'/.test(s) && (${NAME} = V);`,
    // A class keyword after a name or a close (a decorator may stand there, @dec class {}): an expression's (a "/" after
    // its body divides) or a declaration's (it opens a regular expression). A function's after a name is a
    // declaration's (the line-break tests below).
    `const D = @dec class {} / factor; const ${NAME} = V; // c`,
    `@dec class D {}\n/'/.test(s) && (${NAME} = V);`,
    `x = y\nclass C {}\n/'/.test(s) && (${NAME} = V);`,
  ]) {
    for (const v of [lit, REF]) {
      const text = file.replace("V", v);
      assert.deepEqual(wholeHit(ts, text), hitAt(lineOf(text, NAME)), `${JSON.stringify(file)}: ${v}`);
    }
  }
  // Lines handed below the file's first line, whose first line starts with "/": what stands above it is not known.
  const top = `/'/.test(s) && (${NAME} = V);`;
  for (const v of [lit, REF]) assert.deepEqual(routed(ts, [{ line: 50, text: top.replace("V", v) }]), hitAt(50), `line 50: ${v}`);
  assert.deepEqual(routed(ts, [{ line: 1, text: top.replace("V", lit) }]), hitAt(1));
  assert.deepEqual(routed(ts, [{ line: 1, text: top.replace("V", REF) }]), NONE);
  // A line read alone after a "}" or a ")" it does not open; one whose ")" it opens decides.
  for (const v of [lit, REF]) {
    assert.equal(readAlone(ts, `} /'/.test(s) && (${NAME} = ${v});`), true, `"}": ${v}`);
    assert.equal(readAlone(ts, `  b) /'/.test(s) && (${NAME} = ${v});`), true, `")": ${v}`);
  }
  assert.equal(readAlone(ts, `if (x) /'/.test(s) && (${NAME} = ${lit});`), true);
  assert.equal(readAlone(ts, `if (x) /'/.test(s) && (${NAME} = ${REF});`), false);
});

/**
 * Expressions a "/" after divides, as TypeScript reads them, [text, TypeScript only]: function and class expressions,
 * on one line and over lines, with return types and generics, and a type literal after as or satisfies. Read as a
 * block's, such a function's or class's "}" would make the "/" after it open a regular expression.
 */
const EXPRESSIONS = [
  ["function(){}", false],
  ["function f(){}", false],
  ["async function(){}", false],
  ["function*(){}", false],
  ["function (a, b) { return a; }", false],
  ["function (a,\nb) { return a; }", false],
  ["function f(\n) {\n}", false],
  ["function () {\n  return 1;\n}", false],
  ["function (a = function () {}) { return a; }", false],
  ["function(): string { return ''; }", true],
  ["function(): {a: 1} { return {a: 1}; }", true],
  ["function<T extends {a: 1}>(x: T): T | {b: 2} { return x; }", true],
  ["function(): Promise<void> { return p; }", true],
  ["function (a: string,\n  b: number): {\n  c: 1 } { return { c: 1 }; }", true],
  ["class {}", false],
  ["class A {}", false],
  ["class extends B {}", false],
  ["class {\nm() { return 1; }\n}", false],
  ["class extends mixin(B) {}", false],
  ["class extends function () {} {}", false],
  ["class extends class {} {}", false],
  ["class A extends B<C> {}", true],
  ["class<T> { x?: T; }", true],
  ["class A implements I {}", true],
  ["x as {a: 1}", true],
  ["x satisfies {a: 1}", true],
];
/** Where an expression stands, its "/" after it and a password after that on the line, which a "//" comment ends (a regular expression misread there closes on it). */
function slashPlaces(name) {
  return [
    ["after const", (e, v) => `const r = ${e} / factor; const ${name} = ${v}; // c`],
    ["its \"/\" on the next line", (e, v) => `const r = ${e}\n/ factor; const ${name} = ${v}; // c`],
    ["in a call", (e, v) => `f(${e} / factor, { ${name}: ${v} }); // c`],
    ["in a list", (e, v) => `x = [${e} / factor]; cfg[${q}${name}${q}] = ${v}; // c`],
    ["in a ternary", (e, v) => `const r = a ? ${e} / factor : 0; let ${name} = ${v}; // c`],
    ["after return", (e, v) => `function g() { return ${e} / factor; } const ${name} = ${v}; // c`],
    ["after new, typeof or !", (e, v) => `const r = [!${e} / factor, typeof ${e} / 2]; const ${name} = ${v}; // c`],
    ["after instanceof and &&", (e, v) => `const r = a && b instanceof ${e} / factor; const ${name} = ${v}; // c`],
  ];
}
/**
 * The same expressions in a decorator's template (the member forms above) and in a template before a statement, with a
 * quote or a backtick in a comment after the "/": a comment in an interpolation, outside the plain subset.
 */
const SLASH_DECORATORS = [
  ["after the template's line break", (e) => `@Dec(${B}x\n\${${e} / factor /* ' */}${B})`],
  ["on one line", (e) => `@Dec(${B}\${${e} / factor /* ${B} */}${B})`],
  ["the \"/\" on its own line", (e) => `@Dec(${B}\${${e}\n/ factor /* ' */}${B})`],
];
/** Declarations and keys a "/" after opens a regular expression, or that are no keyword at all: V for the value. */
function declarations(name) {
  return [
    [`function f() {} /'/.test(s) && (${name} = V);`, false],
    [`function f() {}\n/'/.test(s) && (${name} = V);`, false],
    [`class A {}\n/'/.test(s) && (${name} = V);`, false],
    [`class A {} /'/.test(s) && (${name} = V);`, false],
    [`export default function () {}\n/'/.test(s) && (${name} = V);`, false],
    [`export default async function () {}\n/'/.test(s) && (${name} = V);`, false],
    [`export default class {}\n/'/.test(s) && (${name} = V);`, false],
    [`{ function f() {} /'/.test(s) && (${name} = V); }`, false],
    [`if (a) { x(); } else { class B {} /'/.test(s) && (${name} = V); }`, false],
    [`function f(a,\nb) {\nreturn a;\n}\n/'/.test(s) && (${name} = V);`, false],
    [`const o = { function: { a: 1 } }; o.class = 1; const ${name} = V;`, false],
    [`const o = { class: 1 } / 2; const ${name} = V; // c`, false],
    [`const n = obj.function / factor; const ${name} = V; // c`, false],
    [`function f(): Promise<void> {}\n/'/.test(s) && (${name} = V);`, true],
    [`export function f(): Promise<void> {}\n/'/.test(s) && (${name} = V);`, true],
    [`function f(): { a: 1 } { return { a: 1 }; }\n/'/.test(s) && (${name} = V);`, true],
    [`class A<T> extends B<T> {}\n/'/.test(s) && (${name} = V);`, true],
    [`class A extends class {} {}\n/'/.test(s) && (${name} = V);`, false],
    [`class A extends function () {} {} /'/.test(s) && (${name} = V);`, false],
  ];
}

test("secrets, through markerHits and stationReport: a function or class expression followed by a division divides, in plain code, so a password after it on the line counts with a literal and not with a reference, also where only its line changes; a declaration's body is followed by a regular expression", () => {
  // Reading the expression's body as a block's would leave each literal after it no hit.
  for (const [e, tsOnly] of EXPRESSIONS) {
    for (const path of tsOnly ? ["api/client.ts"] : ["api/client.ts", "api/client.js"]) {
      for (const [label, place] of slashPlaces(NAME)) assertDecides(path, `${JSON.stringify(e)} ${label}`, (v) => place(e, v));
    }
  }
  for (const [text, tsOnly] of declarations(NAME)) {
    for (const path of tsOnly ? ["api/client.ts"] : ["api/client.ts", "api/client.js"]) assertDecides(path, JSON.stringify(text), (v) => text.replace("V", v));
  }
});

test("secrets, through markerHits and stationReport: a function or class expression followed by a division in a decorator template's interpolation, a comment after it, leaves a word, quoted and computed field, a method and a parameter's default after it counting with a literal, and with a reference too, also where only the member's line changes; so does a template before a statement; without the comment the interpolation is plain and decides", () => {
  for (const [e] of EXPRESSIONS) {
    for (const [where, place] of SLASH_DECORATORS) {
      for (const [label, member] of members(NAME)) assertCounts("api/client.ts", `${JSON.stringify(e)} ${where}, ${label}`, (v) => member(place(e), v));
    }
    for (const [label, st] of statements(NAME)) {
      assertCounts("api/client.ts", `${JSON.stringify(e)}, ${label}`, (v) => st(`${B}\${${e} / factor /* ' */}${B}`, v));
      assertDecides("api/client.ts", `${JSON.stringify(e)}, ${label}, no comment`, (v) => st(`${B}\${${e} / factor}${B}`, v));
    }
  }
});

/** The keywords a "/" after opens a regular expression, unless a property names them (doors.mjs REGEX_AFTER). */
const KEYWORDS = ["return", "typeof", "case", "do", "else", "in", "of", "new", "delete", "void", "throw", "yield", "await"];
/** A property a keyword names, its "." or "?." on a line above the "/", in plain code and in a template's interpolation: [label, form]. */
function keywordProperties(name) {
  const forms = [];
  for (const kw of KEYWORDS) {
    forms.push([`obj.${kw}, the "/" on the next line`, (v) => `const n = obj.${kw}\n / factor; const ${name} = ${v}; // c`]);
    forms.push([`obj?.${kw}, the "/" on the next line`, (v) => `const n = obj?.${kw}\n / factor; const ${name} = ${v}; // c`]);
    forms.push([`obj. then ${kw} on the next line`, (v) => `const n = obj.\n${kw}\n/ factor; const ${name} = ${v}; // c`]);
    forms.push([`obj then .${kw} on the next line`, (v) => `const n = obj\n  .${kw}\n  / factor; const ${name} = ${v}; // c`]);
    forms.push([`obj. /* c */ ${kw} /* c */`, (v) => `const n = obj. /* c */ ${kw} /* c */\n / factor; const ${name} = ${v}; // c`]);
    forms.push([`obj.${kw} in a decorator template, a comment in it`, (v) => `class C {\n  @Dec(${B}\${obj.${kw}\n/ factor /* ' */}${B}) ${name}: string = ${v};\n}`]);
    forms.push([`obj.${kw} in a decorator template`, (v) => `class C {\n  @Dec(${B}\${obj.${kw}\n/ factor}${B}) ${name}: string = ${v};\n}`]);
  }
  for (const w of ["if", "while", "for", "with"]) forms.push([`obj.${w}(...), the "(" on the next line`, (v) => `const n = obj.${w}\n(a) / factor; const ${name} = ${v}; // c`]);
  forms.push(["for await, the \"(\" on the next line", (v) => `async function f() {\n  for await\n(const k of ks) /'/.test(k) && (${name} = ${v});\n}`]);
  return forms;
}

test("secrets, through markerHits and stationReport: a keyword a property names on a line above (obj.return, obj?.of, obj.\\nin) is an operand, so the \"/\" after it divides and a password after that counts with a literal and not with a reference, also where only the \"/\"'s line changes; and the \"(\" after for await on the line above holds a condition; a comment in a template's interpolation counts with a reference too", () => {
  for (const [label, form] of keywordProperties(NAME)) {
    const ts = label.includes("decorator") || label.includes("for await");
    for (const path of ts ? ["api/client.ts"] : ["api/client.ts", "api/client.js"]) assertReads(path, label, form, !label.includes("a comment in it"));
  }
});

/** A seeded mix of the function and class expressions' and the keyword properties' shapes, with line breaks where JavaScript allows them, in plain code, a decorator's template and a template before a statement. */
function mixedSlashes(name, count, seed) {
  const rnd = seeded(seed);
  const pick = (xs) => xs[rnd(xs.length)];
  const br = () => (rnd(3) === 0 ? "\n" : " ");
  const expression = () => {
    const k = rnd(4);
    if (k === 0) return `obj${pick([".", "?.", ".\n", "\n."])}${pick(KEYWORDS)}`;
    return pick(EXPRESSIONS)[0].replaceAll("\n", br());
  };
  const cases = [];
  for (let n = 0; n < count; n++) {
    const e = expression();
    const k = rnd(3);
    // A decorator's or a template's interpolation here holds a comment: outside the plain subset.
    if (k === 0) {
      const [label, place] = pick(slashPlaces(name));
      cases.push([`${JSON.stringify(e)} ${label}`, (v) => place(e, v), true]);
    } else if (k === 1) {
      const [, place] = pick(SLASH_DECORATORS);
      const [label, member] = pick(members(name));
      cases.push([`${JSON.stringify(e)} in a decorator, ${label}`, (v) => member(place(e), v), false]);
    } else {
      const [label, st] = pick(statements(name));
      const t = `${B}\${${e}${br()}/ factor /* ' */}${B}`;
      cases.push([`${JSON.stringify(t)}, ${label}`, (v) => st(t, v), false]);
    }
  }
  return cases;
}

test("secrets: a seeded mix of 400 function and class expressions and keyword properties before a \"/\", with line breaks where JavaScript allows them, in plain code, decorators and templates, decides as JavaScript reads it in plain code, and counts where a comment stands in an interpolation", () => {
  for (const [label, form, plain] of mixedSlashes(NAME, 400, 21)) assertReads("api/client.ts", label, form, plain);
});

/** Names written with escapes, as identifiers (in source, escaped once more). */
const ESCAPED_IDENTIFIERS = ["p\\u0061ssword", "\\u0070assword", "\\u{70}assword", "\\u{0000000070}assword", "pas\\u{73}word", "\\u0050ASSWORD", "db_p\\u0061ssword", "p\\u0061sswd"];
/** Names written with escapes, as quoted keys, two continued by a backslash over lines. */
const ESCAPED_KEYS = [`${q}p\\x61ssword${q}`, `"\\u0070assword"`, `${q}\\u{70}assword${q}`, `${q}pass\\\nword${q}`, `"pa\\\nss\\\nword"`, `${q}\\x50ASSWORD${q}`, `"db_p\\x61ssword"`, `${q}p\\u{0000061}ssword${q}`];
/** The declaration forms an escaped name stands in: [label, form, how many line breaks the name holds]. */
function escapedForms() {
  const forms = [];
  for (const id of ESCAPED_IDENTIFIERS) {
    forms.push([`a field ${id}`, (v) => `class C {\n  ${id}: string = ${v};\n}`]);
    forms.push([`an untyped field ${id}`, (v) => `class C {\n  ${id} = ${v};\n}`]);
    forms.push([`a decorated field ${id}`, (v) => `class C {\n  @Dec() ${id}: string = ${v};\n}`]);
    forms.push([`a #private field ${id}`, (v) => `class C {\n  #${id} = ${v};\n}`]);
    forms.push([`a method ${id}`, (v) => `class C {\n  ${id}(): string { return ${v}; }\n}`]);
    forms.push([`a getter ${id}`, (v) => `class C {\n  get ${id}() { return ${v}; }\n}`]);
    forms.push([`a parameter ${id}`, (v) => `function f(${id}: string = ${v}) {}`]);
    forms.push([`a let ${id}`, (v) => `let ${id}: string = ${v};`]);
    forms.push([`a const ${id}`, (v) => `const ${id} = ${v};`]);
    forms.push([`a key ${id}`, (v) => `const o = { ${id}: ${v} };`]);
    forms.push([`a member's assignment ${id}`, (v) => `cfg.${id} = ${v};`]);
  }
  for (const key of ESCAPED_KEYS) {
    forms.push([`a quoted field ${key}`, (v) => `class C {\n  ${key}: string = ${v};\n}`]);
    forms.push([`a computed field ${key}`, (v) => `class C {\n  [${key}]: string = ${v};\n}`]);
    forms.push([`a computed method ${key}`, (v) => `class C {\n  [${key}]() { return ${v}; }\n}`]);
    forms.push([`a quoted key ${key}`, (v) => `const o = { ${key}: ${v} };`]);
    forms.push([`a subscript ${key}`, (v) => `cfg[${key}] = ${v};`]);
    forms.push([`a set call ${key}`, (v) => `localStorage.setItem(${key}, ${v});`]);
    forms.push([`a joined computed key ${key}`, (v) => `const o = { [${q}x_${q} + ${key}]: ${v} };`]);
    forms.push([`a template-computed field ${key}`, (v) => `class C {\n  [${B}${key.slice(1, -1)}${B}]: string = ${v};\n}`]);
  }
  return forms;
}

test("secrets, escaped names, through markerHits and stationReport: an identifier written with escapes and a key written with string escapes (\\x, \\u, \\u{...}, a backslash before a line break) are read as JavaScript reads them, in 152 declaration forms, so a literal counts and a reference does not, also where only the value's line changes", () => {
  for (const path of ["api/client.ts", "api/client.js"]) {
    for (const [label, form] of escapedForms()) {
      const lit = literal();
      const hit = form(lit);
      const n = lineOf(hit, lit);
      // A name a string spells over lines counts on its first line read whole, and on the value's line where only that changes.
      const breaks = (label.match(/\n/g) ?? []).length;
      assert.deepEqual(wholeHit(path, hit), hitAt(n - breaks), `${path}, ${label}: a literal`);
      assert.deepEqual(wholeHit(path, form(REF)), NONE, `${path}, ${label}: a reference`);
      assert.deepEqual(onlyHit(path, hit, n), hitAt(n), `${path}, ${label}: a literal, only its line changes`);
      assert.deepEqual(onlyHit(path, form(REF), n), NONE, `${path}, ${label}: a reference, only its line changes`);
    }
  }
});

test("secrets, escaped names in Python, PHP, YAML and a string's text: a string's \\x, \\u, \\U, \\N{LATIN ... LETTER ...} and octal escapes, PHP's \\u{...}, and Python's identifiers as NFKC reads them (ｐａｓｓｗｏｒｄ), so a literal counts and a reference does not; a value an escape spells is never a placeholder or a blank, while an escaped name keeps an unescaped placeholder exempt", () => {
  // [path, text with V for the value, the reference].
  const py = `os.environ["X"]`;
  const php = "getenv('X')";
  const fullwidth = [...NAME].map((c) => String.fromCodePoint(c.codePointAt(0) + 0xfee0)).join("");
  for (const [path, file, ref] of [
    ["api/client.js", `cfg['p\\141ssword'] = V;`, REF],
    ["api/client.js", `const o = { 'p\\141ssword': V };`, REF],
    ["tools/ci/seed.py", `cfg = {'p\\x61ssword': V}`, py],
    ["tools/ci/seed.py", `cfg = {'p\\N{LATIN SMALL LETTER A}ssword': V}`, py],
    ["tools/ci/seed.py", `cfg = {"\\u0070assword": V}`, py],
    ["tools/ci/seed.py", `cfg = {'\\U00000070assword': V}`, py],
    ["tools/ci/seed.py", `cfg = {'p\\141ssword': V}`, py],
    ["tools/ci/seed.py", `${fullwidth} = V`, py],
    ["tools/ci/seed.py", `db_${fullwidth.slice(0, 1)}${NAME.slice(1)} = V`, py],
    ["tools/ci/seed.py", `connect(p${fullwidth.slice(1, 2)}${NAME.slice(2)}=V)`, py],
    ["tools/ci/seed.py", `cfg['pass\\\nword'] = V`, py],
    ["includes/newFeature.php", `<?php\n$cfg["p\\x61ssword"] = V;`, php],
    ["includes/newFeature.php", `<?php\n$cfg = ["p\\141ssword" => V];`, php],
    ["includes/newFeature.php", `<?php\n$cfg = ["p\\u{61}ssword" => V];`, php],
    ["includes/newFeature.php", `<?php\ndefine("DB_P\\x41SSWORD", V);`, php],
    ["includes/newFeature.php", `<?php\n$cfg = ['p\\x61ssword' => V];`, php],
    ["config/app.yml", `"p\\x61ssword": V`, "${X}"],
    ["config/app.yml", `db:\n  "p\\u0061ssword": V`, "${X}"],
    ["api/client.js", `const dsn = ${B}host=db p\\x61ssword=\${V}${B};`, REF],
  ]) {
    const lit = literal();
    const hit = file.replace("V", lit);
    const n = lineOf(hit, lit);
    // A name a string continues over two lines counts on its first line read whole.
    assert.deepEqual(wholeHit(path, hit), hitAt(file.includes("\\\n") ? n - 1 : n), `${path}: ${JSON.stringify(hit)}`);
    assert.deepEqual(onlyHit(path, hit, n), hitAt(n), `${path}: ${JSON.stringify(hit)}, only its line`);
    assert.deepEqual(wholeHit(path, file.replace("V", ref)), NONE, `${path}: ${JSON.stringify(file)}, a reference`);
  }
  // A name in a string's own text.
  assert.deepEqual(wholeHit("api/client.js", `const dsn = 'host=db p\\x61ssword=hunter2';`), hitAt(1));
  assert.deepEqual(wholeHit("tools/ci/seed.py", `dsn = 'host=db p\\x61ssword=%s' % os.environ['X']`), NONE);
  // What an escape spells is literal: a placeholder, a mask or a blank spelled so counts; the same spelled plainly does not.
  for (const [path, spelled, plain] of [
    ["api/client.js", `const ${NAME} = '\\x25s';`, `const ${NAME} = '%s';`],
    ["api/client.js", `const ${NAME} = '\\x20';`, `const ${NAME} = ' ';`],
    ["api/client.js", `const dsn = '${NAME}=\\x25s';`, `const dsn = '${NAME}=%s';`],
    ["includes/newFeature.php", `<?php\n$${NAME} = '\\x2a\\x2a\\x2a';`, `<?php\n$${NAME} = '***';`],
    ["config/app.yml", `${NAME}: "\\x25s"`, `${NAME}: "%s"`],
  ]) {
    assert.equal(wholeHit(path, spelled).humanOnly, true, `${path}: ${JSON.stringify(spelled)}`);
    assert.deepEqual(wholeHit(path, plain), NONE, `${path}: ${JSON.stringify(plain)}`);
  }
});

test("production wiring, check.mjs --station: the probe, a decorator template's interpolation over lines, a \"/\" after a condition's \")\" over lines, escaped identifiers and keys in TypeScript, JavaScript, Python and PHP count, also where only the member's line changes; the same with references do not, but for the three whose interpolation holds a regular expression or a comment, outside the plain subset", () => {
  const fullwidth = [...NAME].map((c) => String.fromCodePoint(c.codePointAt(0) + 0xfee0)).join("");
  const edited = (v) => ["export class Creds {", `  @Dec(${B}x`, `  \${s.replace(/'/g, '')}${B}) ${NAME}: string = ${v};`, "}"];
  const change = (v, py, php) => [
    ["api/probe.ts", null, [`class C { @Dec(${B}x`, `\${s.replace(/'/g, '')}${B}) ${NAME}: string = ${v}; }`]],
    ["api/edited.ts", edited(REF), edited(v)],
    ["api/over.ts", null, ["export class Creds {", `  @Dec(${B}\${a /* x`, `   ${B} */}${B}) [${q}${NAME}${q}]() { return ${v}; }`, "}"]],
    ["api/slash.js", null, ["if (a &&", `    b) /'/.test(s) && (${NAME} = ${v});`]],
    ["api/escaped.ts", null, ["export class Creds {", `  p\\u0061ssword = ${v};`, "}"]],
    ["api/keys.js", null, ["export const cfg = {", `  'p\\x61ssword': ${v},`, "};"]],
    ["tools/ci/seed.py", null, [`${fullwidth} = ${py}`]],
    ["includes/newFeature.php", null, ["<?php", `$cfg["p\\x61ssword"] = ${php};`]],
  ];
  const lit = literal();
  const all = ["api/edited.ts:3", "api/escaped.ts:2", "api/keys.js:2", "api/over.ts:3", "api/probe.ts:2", "api/slash.js:2", "includes/newFeature.php:2", "tools/ci/seed.py:1"];
  assert.deepEqual(stationOnChange(change(lit, lit, lit)), { hits: all, humanOnly: true });
  // With the reference, api/edited.ts is its base: no line of it changes.
  assert.deepEqual(stationOnChange(change(REF, `os.environ["X"]`, "getenv('X')")), { hits: ["api/over.ts:3", "api/probe.ts:2"], humanOnly: true });
});

test("markers stay linear: 10,000 decorator templates over lines whose interpolations hold regular expressions and comments, 20,000 interpolations on one line, templates nested 20,000 deep on one line and over lines, 20,000 escaped names and 20,000 lines a backslash continues grow no faster than linearly (linear-time.mjs), and count as the reader decides: from a regular expression, a comment or a template in an interpolation on, every line that names a password counts", () => {
  const lit = literal();
  // Joined with "+", so that no template of this file's own holds a template in an interpolation.
  const decorators = (n) => `  @Dec(${B}x\n\${s.replace(/'/g, '')} \${a /* ${B} */}${B}) ${NAME}: string = x;\n`.repeat(n);
  const interpolations = (n) => `\${s.replace(/'/g, '')}`.repeat(n);
  const opens = (n) => `${B}\${`.repeat(n);
  const opensOverLines = (n) => `${B}\${\n`.repeat(n);
  const closes = (n) => `}${B}`.repeat(n);
  const escaped = (n) => "const db_p\\u0061ssword = x;\n".repeat(n);
  const continued = (n) => "\\\nb".repeat(n);
  const cases = [
    // From the first interpolation on, every line that names a password counts: each member's, n + 1 in all.
    ["10,000 decorator templates over lines", (n) => "class C {\n" + decorators(n) + `  @Dec(${B}x\n\${s.replace(/'/g, '')}${B}) ${NAME}: string = ${lit};\n}`, 10000, (n) => n + 1],
    ["20,000 interpolations on one line", (n) => `const t = ${B}` + interpolations(n) + `${B}; const ${NAME} = x;\nconst ${NAME}2 = 1;\nclass C { ${NAME}: string = ${lit}; }`, 20000, () => 3],
    ["templates nested 20,000 deep on one line", (n) => "const t = " + opens(n) + "x" + closes(n) + `;\nclass C { ${NAME}: string = x; }`, 20000, () => 1],
    // Past LEAD_FRAMES open at a line's start the lexer's state there is not kept, and every later line counts.
    ["templates nested 20,000 deep over lines", (n) => "const t = " + opensOverLines(n) + "x" + closes(n) + `;\nclass C { ${NAME}: string = x; }`, 20000, () => 1],
    ["20,000 escaped names", (n) => escaped(n) + `const db_p\\u0061ssword = ${lit};`, 20000, () => 1],
    ["20,000 lines a backslash continues", (n) => "const s = 'a" + continued(n) + `';\ncfg['pass\\\nword'] = ${lit};`, 20000, () => 1],
  ];
  for (const [label, text, n, hits] of cases) {
    const read = (k) => markerHits("api/client.ts", text(k).split("\n").map((line, i) => ({ line: i + 1, text: line }))).filter((hit) => hit.row === "secrets").length;
    assert.equal(linear(label, read, n), hits(n), label);
  }
});

test("production wiring, check.mjs --station: a function and a class expression followed by a division, the same in a decorator's template with its parameters over lines, and a keyword a property names on the line above the \"/\" where only the \"/\"'s line changes, count with a literal; the same with references do not, but for the two decorators whose interpolation holds a comment", () => {
  const change = (v) => [
    ["api/fnexpr.ts", null, [`const n = function () {} / factor; const ${NAME} = ${v}; // c`]],
    ["api/classexpr.js", null, [`const n = class {} / factor; const ${NAME} = ${v}; // c`]],
    ["api/decorated.ts", null, ["export class Creds {", `  @Dec(${B}x`, `\${function () {} / factor /* ' */}${B}) ${NAME}: string = ${v};`, "}"]],
    ["api/params.ts", null, ["export class Creds {", `  @Dec(${B}\${function (a,`, `  b) { return a; } / factor /* ' */}${B}) [${q}${NAME}${q}]() { return ${v}; }`, "}"]],
    ["api/property.ts", ["const n = obj.return", " / factor; const x = 1; // c"], ["const n = obj.return", ` / factor; const ${NAME} = ${v}; // c`]],
    ["api/optional.js", ["const n = obj?.of", " / factor; const x = 1; // c"], ["const n = obj?.of", ` / factor; const ${NAME} = ${v}; // c`]],
  ];
  const all = ["api/classexpr.js:1", "api/decorated.ts:3", "api/fnexpr.ts:1", "api/optional.js:2", "api/params.ts:3", "api/property.ts:2"];
  assert.deepEqual(stationOnChange(change(literal())), { hits: all, humanOnly: true });
  assert.deepEqual(stationOnChange(change(REF)), { hits: ["api/decorated.ts:3", "api/params.ts:3"], humanOnly: true });
});

test("markers stay linear: 20,000 function expressions dividing on one line, 10,000 keyword properties over lines, 10,000 decorator templates whose function's parameters run over lines, and functions nested 5,000 deep in each other's parameters, on one line and over lines, grow no faster than linearly (linear-time.mjs), and count as the reader decides: the decorators' interpolations hold a comment, so every member line counts", () => {
  const lit = literal();
  // Joined with "+", so that no template of this file's own holds a template in an interpolation.
  const properties = (n) => `const n = obj.return\n / factor; const ${NAME} = x; // c\n`.repeat(n);
  const decorators = (n) => `  @Dec(${B}\${function (a,\n  b) { return a; } / factor /* ' */}${B}) ${NAME}: string = x;\n`.repeat(n);
  const cases = [
    ["20,000 function expressions on one line", (n) => `const r = ${"function () {} / 2 + ".repeat(n)}0; const ${NAME} = ${lit}; // c`, 20000, () => 1],
    ["10,000 keyword properties over lines", (n) => properties(n) + `const n = obj?.of\n / factor; const ${NAME} = ${lit}; // c`, 10000, () => 1],
    // From the first comment in an interpolation on, every line that names a password counts: n + 1 in all.
    ["10,000 decorator templates, parameters over lines", (n) => "class C {\n" + decorators(n) + `  @Dec(${B}\${function (a,\n  b) { return a; } / factor /* ' */}${B}) ${NAME}: string = ${lit};\n}`, 10000, (n) => n + 1],
    ["functions nested 5,000 deep on one line", (n) => `const r = ${"function (a = ".repeat(n)}0${") {}".repeat(n)} / factor; const ${NAME} = ${lit}; // c`, 5000, () => 1],
    ["functions nested 5,000 deep over lines", (n) => `const r = ${"function (a =\n".repeat(n)}0${") {}\n".repeat(n)} / factor; const ${NAME} = ${lit}; // c`, 5000, () => 1],
  ];
  for (const [label, text, n, hits] of cases) {
    const read = (k) => markerHits("api/client.ts", text(k).split("\n").map((line, i) => ({ line: i + 1, text: line }))).filter((hit) => hit.row === "secrets").length;
    assert.equal(linear(label, read, n), hits(n), label);
  }
});

// ----- Automatic semicolon insertion before a function's or a class's keyword -------------------------------------

/**
 * A function's or a class's keyword after a line break that ends the statement before it (automatic semicolon
 * insertion), so the keyword starts a declaration and a "/" after its body opens a regular expression: [text with V for
 * the value, TypeScript only]. Read as an expression's, async<line break>function, return<line break>class and a keyword
 * a property names before the break (obj.return<line break>function) would make the "/" divide and the quote after it
 * hide the rest of the line.
 */
function asiDeclarations(name) {
  const tail = `/'/.test(s); const ${name} = V;`;
  const forms = [
    [`const n = async\nfunction f() {} ${tail}`, false],
    [`const n = async\nfunction f() {}\n/'/.test(s) && (${name} = V);`, false],
    [`const n = async /*\n*/ function f() {} ${tail}`, false],
    [`const n = async // c\nfunction* g() {} ${tail}`, false],
    [`const n = async /* a\n b */ function f() {} ${tail}`, false],
    [`function g() {\n  return\n  function f() {} ${tail}\n}`, false],
    [`function g() {\n  return\n  class C {} ${tail}\n}`, false],
    [`function g() {\n  return\n  async function f() {} ${tail}\n}`, false],
    [`function g() {\n  return async\n  function f() {} ${tail}\n}`, false],
    [`function g() {\n  return /*\n  */ function f() {} ${tail}\n}`, false],
    [`function g() {\n  return /*\n  */ class C extends B {} ${tail}\n}`, false],
    [`function g() {\n  if (a) return\n  function f() {} ${tail}\n}`, false],
    [`function* g() {\n  yield\n  function f() {} ${tail}\n}`, false],
    [`function* g() {\n  yield\n  class C {} ${tail}\n}`, false],
    [`x = y\nfunction f() {} ${tail}`, false],
    [`x = f()\nfunction g() {} ${tail}`, false],
    [`x = [1]\nfunction f() {} ${tail}`, false],
    [`x = {}\nfunction f() {} ${tail}`, false],
    [`x = 'a'\nfunction f() {} ${tail}`, false],
    [`x = 1\nfunction f() {} ${tail}`, false],
    [`x = /r/g\nfunction f() {} ${tail}`, false],
    [`i++\nfunction f() {} ${tail}`, false],
    [`x = function () {}\nfunction f() {} ${tail}`, false],
    [`x = class {}\nclass C {} ${tail}`, false],
    [`x = class {}\nfunction f() {} ${tail}`, false],
    [`x = y!\nfunction f() {} ${tail}`, true],
    [`function f(): string\nfunction f() { return ''; } ${tail}`, true],
  ];
  for (const kw of [...KEYWORDS, "async", "default", "export", "extends", "instanceof"]) {
    forms.push([`const n = obj.${kw}\nfunction f() {} ${tail}`, false]);
    forms.push([`const n = obj?.${kw}\nasync function f() {} ${tail}`, false]);
    forms.push([`const n = obj\n  .${kw}\nfunction f() {} ${tail}`, false]);
  }
  return forms;
}
/**
 * The same line breaks before a "{" or a "/": return and yield before a "{", break and continue (a label after one too)
 * before a "/", a property's name or a literal before a "{", TypeScript's non-null x! before a "/", and a prefix "++"
 * after a block comment that holds a line break: [text with V, TypeScript only].
 */
function asiNeighbours(name) {
  const tail = `/'/.test(s); const ${name} = V;`;
  return [
    [`function g() {\n  return\n  {} ${tail}\n}`, false],
    [`function g() {\n  return /* a\n  b */ {} ${tail}\n}`, false],
    [`function* g() {\n  yield\n  {} ${tail}\n}`, false],
    [`x = obj.return\n{} ${tail}`, false],
    [`x = 'a'\n{} ${tail}`, false],
    [`for (;;) {\n  break\n  ${tail}\n}`, false],
    [`for (;;) {\n  continue\n  ${tail}\n}`, false],
    [`for (;;) {\n  break /*\n  */ ${tail}\n}`, false],
    [`l: for (;;) {\n  break l\n  ${tail}\n}`, false],
    [`l: for (;;) {\n  continue l\n  ${tail}\n}`, false],
    [`const r = x! / a; const ${name} = V; // /`, true],
    [`const r = this.total! / n; const ${name} = V; const y = r / 2;`, true],
    [`const r = f()! / a; const ${name} = V; // /`, true],
    [`const r = x!\n  / a; const ${name} = V; // /`, true],
    [`if (!/'/.test(s)) ${name} = V;`, false],
    [`const n = 1 /*\n*/ ++x / a; const ${name} = V; // /`, false],
    [`const n = 1 /* a\n b */ ++x / a; const ${name} = V; // /`, false],
    [`if (a > /'/.test(s)) ${name} = V;`, false],
    [`const b = x as number > /'/.lastIndex; ${name} = V;`, true],
    [`const r = f<T>(x) / a; const ${name} = V; // /`, true],
  ];
}
/** The same in an arrow's or a generator's body in a template's interpolation, where statements stand: each holds a regular expression, outside the plain subset. */
const ASI_INTERPOLATIONS = [
  `(() => { const n = async\nfunction f() {} /'/.test(s); return n; })()`,
  `(() => { return\nfunction f() {} /'/.test(s); })()`,
  `(() => { return\nclass C {} /'/.test(s); })()`,
  `(() => { return async\nfunction f() {} /'/.test(s); })()`,
  `(() => { return /*\n*/ function f() {} /'/.test(s); })()`,
  `(() => { const n = obj.return\nfunction f() {} /'/.test(s); })()`,
  `(() => { const n = y\nfunction f() {} /'/.test(s); })()`,
  `(() => { return\n{} /'/.test(s); })()`,
  `(() => { for (;;) { break\n/'/.test(s); } })()`,
  `(function* () { yield\nfunction f() {} /'/.test(s); })()`,
];
/**
 * Where JavaScript may read the keyword or the "/" either way, or reads no such code: [text with V, TypeScript only].
 * A class's keyword after a name, a property's name or a close may follow a decorator (@dec<line break>class); of,
 * await and yield are names in some places; void before an overload's line break may be TypeScript's type; and a line
 * break ends no statement inside a template's interpolation or a group; and a ">" that closes TypeScript's type
 * arguments after as or satisfies may end an operand a "/" divides.
 */
function asiUnsure(name) {
  const tail = `/'/.test(s); const ${name} = V;`;
  return [
    [`const n = async\nclass C {} ${tail}`, false],
    [`const n = obj.typeof\nclass C {} ${tail}`, false],
    [`x = f()\nclass C {} ${tail}`, false],
    [`const of = 1; const r = of / a; const ${name} = V; // /`, false],
    [`var await = 1; const r = await / a; const ${name} = V; // /`, false],
    [`var yield = 1; const r = yield / a; const ${name} = V; // /`, false],
    [`async function g() {\n  const n = await\n  function f() {} / a; const ${name} = V; // /\n}`, false],
    [`function f(): void\nfunction f() {} ${tail}`, true],
    // A ">" that closes TypeScript's type arguments after as or satisfies: a "/" after it divides (x as Array<number> / 2),
    // while after a comparison's ">" it opens a regular expression.
    [`const r = x as Array<number> / a; const ${name} = V; // /`, true],
    [`const r = x satisfies Record<string, number> / a; const ${name} = V; // /`, true],
    [`const r = x as Map<string, Array<number>> / a; const ${name} = V; // /`, true],
    [`const t = ${B}\${async\nfunction f() {} /'/.test(s)}${B}; const ${name} = V;`, false],
    [`(async\nfunction f() {} ${tail});`, false],
  ];
}

test("secrets, automatic semicolon insertion, through markerHits and stationReport: a function's or a class's keyword after a line break that ends the statement before it (async, return, yield, a keyword a property names, a name, a close, a literal, a postfix \"++\" or \"!\", a block comment that holds a line break) starts a declaration, so the \"/\" after its body opens a regular expression and a password after it counts with a literal and not with a reference, whole and where only its line changes", () => {
  // The first form is the probe of the production wiring test at the end of this file.
  for (const [text, tsOnly] of asiDeclarations(NAME)) {
    for (const path of tsOnly ? ["api/client.ts"] : ["api/client.ts", "api/client.js"]) assertDecides(path, JSON.stringify(text), (v) => text.replace("V", v));
  }
});

test("secrets, automatic semicolon insertion's neighbours, through markerHits and stationReport: return or yield before a line break and a \"{\" opens a block, break and continue (and a label after one) end their statement before a \"/\", a property's name or a literal before a line break and a \"{\" ends it too, and TypeScript's non-null x! is an operand a \"/\" divides, so a password after them counts with a literal and not with a reference, whole and where only its line changes", () => {
  // Read as one statement, return<line break>{} would hide the literal.
  for (const [text, tsOnly] of asiNeighbours(NAME)) {
    for (const path of tsOnly ? ["api/client.ts"] : ["api/client.ts", "api/client.js"]) assertDecides(path, JSON.stringify(text), (v) => text.replace("V", v));
  }
});

test("secrets, automatic semicolon insertion in interpolations, through markerHits and stationReport: the same line breaks in an arrow's or a generator's body in a template's interpolation, before a regular expression there, leave a word, quoted and computed field, a method and a parameter after a decorator template counting with a literal and (outside the plain subset) with a reference, wherever the interpolation stands, also where only the member's line changes; so does a statement after such a template", () => {
  for (const inner of ASI_INTERPOLATIONS) {
    for (const [where, place] of PLACEMENTS) {
      for (const [label, member] of members(NAME)) assertCounts("api/client.ts", `${JSON.stringify(inner)} ${where}, ${label}`, (v) => member(place(inner), v));
    }
    for (const path of ["api/client.ts", "api/client.js"]) {
      for (const [label, st] of statements(NAME)) {
        if (path.endsWith(".ts") || !label.includes("let")) assertCounts(path, `${JSON.stringify(inner)}, ${label}`, (v) => st(`${B}\${${inner}}${B}`, v));
      }
    }
  }
});

test("secrets, automatic semicolon insertion failing closed: where JavaScript may read the keyword or the \"/\" either way (a class's keyword after a name, a property's name or a close, where a decorator may stand; of, await or yield as a name; void before an overload's line break; a \">\" that closes type arguments after as or satisfies) or reads no such code (a line break that would end a statement inside a template's interpolation or a group), the line counts with a reference too", () => {
  // Taking one reading where JavaScript may take either, async<line break>class C {} /'/... can hide the literal.
  const lit = literal();
  for (const [file, tsOnly] of asiUnsure(NAME)) {
    for (const path of tsOnly ? ["api/client.ts"] : ["api/client.ts", "api/client.js"]) {
      for (const v of [lit, REF]) {
        const text = file.replace("V", v);
        assert.deepEqual(wholeHit(path, text), hitAt(lineOf(text, NAME)), `${path}, ${JSON.stringify(file)}: ${v}`);
      }
    }
  }
});

/** A seeded mix of line breaks that end a statement before a function's or a class's keyword or a "{": the token before, the break, the keyword's form, what follows. */
function mixedAsi(name, count, seed) {
  const rnd = seeded(seed);
  const pick = (xs) => xs[rnd(xs.length)];
  const brk = () => pick(["\n", " /*\n*/ ", "\n// c\n", " /* a */\n"]);
  // [what stands before, whether it ends an operand (a class after it may follow a decorator), the body it needs].
  const befores = [
    ["const n = async", true, ""], ["x = y", true, ""], ["x = f()", true, ""], ["x = [1]", true, ""], ["x = 'a'", true, ""],
    ["x = 1", true, ""], ["i++", true, ""], ["x = y!", true, ""], ["x = function () {}", false, ""], ["x = class {}", false, ""],
    ["return", false, "fn"], ["return async", true, "fn"], ["if (a) return", false, "fn"], ["yield", false, "gen"],
  ];
  const forms = [["function f() {}", false], ["async function f() {}", false], ["function* g() {}", false], ["function f(a,\nb) {\n}", false], ["class C {}", true], ["class C extends B {}", true]];
  const tails = [(v) => `/'/.test(s); const ${name} = ${v};`, (v) => `/'/.test(s) && (${name} = ${v});`, (v) => `/'/.test(s); cfg['${name}'] = ${v};`];
  const cases = [];
  while (cases.length < count) {
    const [before, operand, body] = pick(befores);
    const kw = rnd(4) === 0 ? `obj${pick([".", "?.", "\n."])}${pick([...KEYWORDS, "async", "default"])}` : null;
    const [form, isClass] = pick(forms);
    // A class after an operand may follow a decorator: either reading (the failing-closed test above).
    if (isClass && (operand || kw)) continue;
    const head = kw ? `const n = ${kw}` : before;
    if (head.includes("async") && form.startsWith("async")) continue;
    const tail = pick(tails);
    const sep = brk();
    const wrap = (text) => (kw || !body ? text : body === "fn" ? `function g() {\n  ${text}\n}` : `function* g() {\n  ${text}\n}`);
    cases.push([JSON.stringify(wrap(`${head}${sep}${form} ${tail("V")}`)), (v) => wrap(`${head}${sep}${form} ${tail(v)}`)]);
  }
  return cases;
}

test("secrets: a seeded mix of 400 line breaks that end a statement before a function's or a class's keyword (with comments that hold a line break or end one), in plain code and in function and generator bodies, decides as JavaScript reads it", () => {
  for (const [label, form] of mixedAsi(NAME, 400, 33)) assertDecides(label.includes("y!") ? "api/client.ts" : "api/client.js", label, form);
});

/**
 * Run markerHits in a child Node with its heap capped at `mb` megabytes on `count` class extends clauses, each on its
 * own line, nested in `shape`'s template interpolation, and on count / FACTOR of them: { status, big, little, hits,
 * last } (big and little: the least CPU milliseconds of two runs at each size, linear-time.mjs; hits null
 * where the child died).
 */
function underHeap(mb, count, shape) {
  const child = `
    const [url, count, shape, factor] = process.argv.slice(1);
    const { markerHits } = await import(url);
    const NAME = ["pass", "word"].join("");
    const B = String.fromCharCode(96);
    const cpu = () => { const u = process.cpuUsage(); return (u.user + u.system) / 1000; };
    const read = (n) => {
      const nested = "\${" + "class extends\\n".repeat(n) + "B" + " {}".repeat(n) + "}";
      const text = shape === "decorator"
        ? "class C {\\n  @Dec(" + B + nested + B + ") " + NAME + ": string = x;\\n}"
        : "const t = " + B + nested + B + ";\\nclass C { " + NAME + ": string = x; }";
      const lines = text.split("\\n").map((line, i) => ({ line: i + 1, text: line }));
      const from = cpu();
      const hits = markerHits("api/client.ts", lines).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
      return { spent: cpu() - from, hits, last: lines.length };
    };
    const n = Number(count);
    const small = Math.max(1, Math.floor(n / Number(factor)));
    read(small);
    let big = Infinity, little = Infinity, out;
    for (let k = 0; k < 2; k++) {
      out = read(n);
      big = Math.min(big, out.spent);
      little = Math.min(little, read(small).spent);
    }
    console.log(JSON.stringify({ big, little, hits: out.hits, last: out.last }));
  `;
  const station = new URL("../lib/station.mjs", import.meta.url).href;
  // The timeout only ends a child that hangs; the bound is the growth the child measures.
  const result = spawnSync(process.execPath, [`--max-old-space-size=${mb}`, "--input-type=module", "-e", child, station, String(count), shape, String(FACTOR)], { encoding: "utf8", timeout: 600000 });
  const out = result.status === 0 ? JSON.parse(result.stdout.trim().split("\n").pop()) : null;
  return { status: result.status, big: out ? out.big : null, little: out ? out.little : null, hits: out ? out.hits : null, last: out ? out.last : null };
}

test("markers stay linear: function and class keywords waiting for their body count toward LEAD_FRAMES, so 10,000 class extends clauses over lines nested in a template's interpolation, before a statement and in a decorator before a member, finish under a 96 MB heap and grow no faster than linearly (linear-time.mjs), and the password line after them counts (past LEAD_FRAMES the lexer's state there is not kept); 40 nested decide as the reader does", () => {
  // Copying every waiting keyword into each line's lead without counting it toward LEAD_FRAMES grows quadratically and
  // runs the child out of heap; reading the line after them as known leaves it no hit.
  for (const shape of ["template", "decorator"]) {
    const big = underHeap(96, 10000, shape);
    assert.equal(big.status, 0, `${shape}: the child ran out of heap or failed`);
    assert.ok(grows(big.big, big.little), `${shape}: ${big.big} ms of CPU at 10,000 against ${big.little} ms at a quarter of it`);
    assert.deepEqual(big.hits, [big.last - (shape === "decorator" ? 1 : 0)], shape);
    const small = underHeap(96, 40, shape);
    assert.equal(small.status, 0, `${shape}, 40: the child failed`);
    assert.deepEqual(small.hits, [], `${shape}, 40 nested: a reference after them`);
  }
  // Below LEAD_FRAMES a literal after them counts and a reference does not, in plain code and in a decorator.
  const nested = (k) => `\${${"class extends\n".repeat(k)}B${" {}".repeat(k)}}`;
  assertDecides("api/client.ts", "40 nested before a statement", (v) => `const t = ${B}${nested(40)}${B}; let ${NAME}: string = ${v};`);
  assertDecides("api/client.ts", "40 nested in a decorator", (v) => `class C {\n  @Dec(${B}${nested(40)}${B}) ${NAME}: string = ${v};\n}`);
});

test("markers stay linear: 20,000 line breaks after async, return, a block comment over lines, a label and a keyword a property names before a function's or a class's keyword, and 20,000 x! divisions on one line, grow no faster than linearly (linear-time.mjs), and count only the literal at their end", () => {
  const lit = literal();
  // Joined with "+", so that no template of this file's own holds a template in an interpolation.
  const cases = [
    ["20,000 async line breaks", (n) => `const n = async\nfunction f() {} /'/.test(s); const ${NAME} = x;\n`.repeat(n) + `const n = async\nfunction f() {} /'/.test(s); const ${NAME} = ${lit};`],
    ["20,000 returns over lines", (n) => "function g() {\n" + `  return\n  class C {} /'/.test(s); const ${NAME} = x;\n`.repeat(n) + `  return\n  function f() {} /'/.test(s); const ${NAME} = ${lit};\n}`],
    ["20,000 block comments over lines", (n) => `x = async /* a\n b */ function f() {} /'/.test(s); const ${NAME} = x;\n`.repeat(n) + `x = async /* a\n b */ function f() {} /'/.test(s); const ${NAME} = ${lit};`],
    ["20,000 labels", (n) => "l: for (;;) {\n" + `  break l\n  /'/.test(s); const ${NAME} = x;\n`.repeat(n) + `  break l\n  /'/.test(s); const ${NAME} = ${lit};\n}`],
    ["20,000 keyword properties", (n) => `const n = obj.return\nfunction f() {} /'/.test(s); const ${NAME} = x;\n`.repeat(n) + `const n = obj.return\nfunction f() {} /'/.test(s); const ${NAME} = ${lit};`],
    ["20,000 x! on one line", (n) => `const r = ${"x! / a / ".repeat(n)}1; const ${NAME} = ${lit}; // /`],
  ];
  for (const [label, text] of cases) {
    const read = (k) => markerHits("api/client.ts", text(k).split("\n").map((line, i) => ({ line: i + 1, text: line }))).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
    assert.deepEqual(linear(label, read, 20000), [lineOf(text(20000), lit)], label);
  }
});

test("production wiring, check.mjs --station: automatic semicolon insertion, the probe whole and where only its second line changes, return and a property's name before a line break and a class or function keyword, break before a \"/\", TypeScript's x! / a, and a decorator template's arrow body edited member-only, count with a literal; the same with references do not, but for the decorator, whose interpolation holds a regular expression", () => {
  const probe = (v) => ["const n = async", `function f() {} /'/.test(s); const ${NAME} = ${v};`];
  const decorated = (v) => ["export class Creds {", `  @Dec(${B}\${(() => { return`, `function f() {} /'/.test(s); })()}${B}) ${NAME}: string = ${v};`, "}"];
  const change = (v) => [
    ["api/probe.js", null, probe(v)],
    ["api/edited.ts", probe(REF).map((line) => line.replace(REF, "process.env.Y")), probe(v)],
    ["api/returned.ts", null, ["function g() {", "  return", `  class C {} /'/.test(s); const ${NAME} = ${v};`, "}"]],
    ["api/property.js", null, ["const n = obj.return", `function f() {} /'/.test(s); const ${NAME} = ${v};`]],
    ["api/broken.js", null, ["for (;;) {", "  break", `  /'/.test(s); const ${NAME} = ${v};`, "}"]],
    ["api/nonnull.ts", null, [`const r = x! / a; const ${NAME} = ${v}; // /`]],
    ["api/decorated.ts", decorated("process.env.Y"), decorated(v)],
  ];
  const all = ["api/broken.js:3", "api/decorated.ts:3", "api/edited.ts:2", "api/nonnull.ts:1", "api/probe.js:2", "api/property.js:2", "api/returned.ts:3"];
  assert.deepEqual(stationOnChange(change(literal())), { hits: all, humanOnly: true });
  assert.deepEqual(stationOnChange(change(REF)), { hits: ["api/decorated.ts:3"], humanOnly: true });
});
