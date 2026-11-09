// tools/guardrails/lib/station.mjs - route a change by the door map (lib/doors.mjs).
//
// `node tools/guardrails/check.mjs --station` prints the station, the party, the
// human at the pass, the taster tier and the minimum proof for a change; with
// --json it prints one `saldi.station.v1` object for tools. It never fails
// a build. The first path rule that matches owns the plate:
//   1. any one-way row (path or marker)    Envejsdørene, party by PARTY_ORDER
//   2. a care row of Fællesgryden          Fællesgryden
//   3. a gate path, or the ci care row     Gartneren
//   4. anything else, text rows included   Skærm og tekst
// Restrictions come from everything matched, not only the owner: every row's
// proof joins the plate, the strictest tier wins, `deploy` or `secrets` make the
// order human-only, and of the approvers the matches name, the first in
// APPROVERS leads at the pass.
//
// The door questions (does it change the books, what is sent, an unlisted
// write?) are not path matches: the author and the reviewer answer them.
// The same paths, markers and base give the same output, byte for byte.
//
// History:
// 20261103 CL/LH Created.
// 20261106 CL/LH A comment line is passed over only where the file's lexer establishes all of it as comment.
//                PHP files are read by PHP's own tokens.
// 20261107 CL/LH markerHits may be handed the unchanged lines below a run, and an entry marked cut.
// 20261109 CL/LH oneWayRowsOf() reads removed lines too.

import { createHash } from "node:crypto";
import { readFileSync } from "node:fs";
import { posix } from "node:path";
import { COMMENT } from "../checks/migrations.mjs";
import * as doors from "./doors.mjs";

const DOOR_MAP = new URL("./doors.mjs", import.meta.url);
const ROWS = new Map([...doors.ONE_WAY_ROWS, ...doors.CARE_ROWS].map((row) => [row.id, row]));
const TIER_FIELDS = ["level", "effort", "longContext", "recheck"];

/** "{a,b}/x" -> ["a/x", "b/x"], innermost group first. */
export function expand(pattern) {
  const group = /\{([^{}]*)\}/.exec(pattern);
  if (!group) return [pattern];
  const before = pattern.slice(0, group.index);
  const after = pattern.slice(group.index + group[0].length);
  return group[1].split(",").flatMap((alternative) => expand(before + alternative + after));
}

const matchers = new Map();

/** A door-map pattern as a test on a repository path: a folder prefix, or an fnmatch glob. */
function matcher(pattern) {
  if (!matchers.has(pattern)) {
    const tests = expand(pattern).map((glob) => {
      if (glob.endsWith("/")) return (path) => path.startsWith(glob);
      const source = glob.replace(/[.+^$()|[\]\\]/g, "\\$&").replace(/\*/g, ".*").replace(/\?/g, ".");
      const regex = new RegExp(`^${source}$`, "s");
      return (path) => regex.test(path);
    });
    matchers.set(pattern, (path) => tests.some((test) => test(path)));
  }
  return matchers.get(pattern);
}

/**
 * A candidate path as the repository path it names (`./finans/bogfor.php` and `finans//bogfor.php`
 * are `finans/bogfor.php`), or null when it is not one: absolute, outside the repository after
 * `..`, empty, too long, or with whitespace, a backslash or a control character in it.
 */
export function canonicalPath(raw) {
  if (typeof raw !== "string" || raw.length === 0 || raw.length > 400 || raw.startsWith("/") || /[\s\\\0-\x1f\x7f]/.test(raw)) return null;
  const path = posix.normalize(raw);
  if (path === "." || path === "./" || path === ".." || path.startsWith("../")) return null;
  return path;
}

/** The first of `patterns` (as written) that matches `path`, or null. */
export function firstMatch(patterns, path) {
  return patterns.find((pattern) => matcher(pattern)(path)) ?? null;
}

/** The pattern by which `row` claims `path`, or null. */
export function rowPattern(row, path) {
  if (row.except && firstMatch(row.except, path)) return null;
  return firstMatch(row.paths, path);
}

export function isGatePath(path) {
  return !firstMatch(doors.GATE.except, path) && firstMatch(doors.GATE.paths, path) !== null;
}

/** The language whose lexer establishes a code file's comments (doors.mjs commentAt), or null where none does. */
function commentLanguage(path) {
  if (firstMatch(doors.PHP_FILES, path)) return "php";
  if (firstMatch(doors.JS_FILES, path)) return "js";
  if (firstMatch(doors.YAML_FILES, path)) return "yaml";
  return null;
}

/**
 * The one-way markers on `lines` of one file, as [{row, marker, line}] in row order. A spelling reads the files its
 * `in` names (all without), but those its `notIn` names. A spelling with
 * readsFrom(lines, index) reads the line with the entries around it (a statement over lines, read on through the end
 * of what the line opens); any other reads the line alone (pattern.test), the whole line, unless it opens with a
 * comment's mark and the file's lexer establishes that all of it is comment (doors.mjs commentAt). An unchanged line
 * above or below a run (context: true, changeset.mjs markerLines) is read by readsFrom only, never hit: a hit is an
 * added line's.
 *
 * @param {{line: number|null, text: string, context?: true, eof?: true, cut?: true}[]} lines  added lines (line null
 *   for a removed one), each run maybe between the unchanged lines above and below it; cut: the file goes on below this
 *   entry with lines not handed, so a value open there may go on unseen (doors.mjs cutAt)
 */
export function markerHits(path, lines, isVendored = () => false) {
  if (isVendored(path) || !lines.some((entry) => !entry.context)) return [];
  const code = doors.MARKER_FILES.extensions.some((extension) => path.endsWith(extension)) && !firstMatch(doors.MARKER_FILES.skip, path);
  const hits = [];
  // A line that opens with a comment's mark is passed over only where the file's lexer has established that all of it
  // is comment (doors.mjs commentAt, read once per entry); any doubt keeps its text.
  const language = commentLanguage(path);
  const passedOver = (index, text) => COMMENT.test(text) && doors.commentAt(lines, index, language);
  for (const row of doors.ONE_WAY_ROWS) {
    if (!row.markers.length) continue;
    if (!row.markersEverywhere) {
      if (!code) continue;
      if (row.markersIn === "row-paths" ? !rowPattern(row, path) : row.markersIn && !firstMatch(row.markersIn, path)) continue;
    }
    lines.forEach(({ line, text, context }, index) => {
      if (context) return;
      // A code marker reads the line unless it is established comment; a marker that counts everywhere reads all of it.
      if (!row.markersEverywhere && passedOver(index, text)) return;
      const seen = new Set();
      // A marker may have more than one spelling, each maybe for some files only; it hits a line once.
      for (const spelling of row.markers) {
        const { marker, pattern, in: files, notIn } = spelling;
        if (seen.has(marker) || (files && !firstMatch(files, path)) || (notIn && firstMatch(notIn, path))) continue;
        if (spelling.readsFrom ? spelling.readsFrom(lines, index) : pattern.test(text)) {
          seen.add(marker);
          hits.push({ row: row.id, marker, line });
        }
      }
    });
  }
  return hits;
}

/**
 * The one-way rows that make `path` one-way: by path, else by a marker on `lines` (the added lines in the head version)
 * or on `removed` (the removed lines in the base version, each read in the file it was removed from).
 * Used by the pr-body door cross-check.
 *
 * @returns {{row: object, pattern: string|null, marker: string|null}[]}
 */
export function oneWayRowsOf(path, lines, isVendored, removed = []) {
  const markers = [...markerHits(path, lines, isVendored), ...markerHits(path, removed, isVendored)];
  const result = [];
  for (const row of doors.ONE_WAY_ROWS) {
    const pattern = rowPattern(row, path);
    const marker = markers.find((hit) => hit.row === row.id)?.marker ?? null;
    if (pattern || marker) result.push({ row, pattern, marker: pattern ? null : marker });
  }
  return result;
}

function compare(a, b) {
  return a < b ? -1 : a > b ? 1 : 0;
}

function strictest(field, values) {
  const order = doors.STRICTNESS[field];
  return order[Math.min(...values.map((value) => order.indexOf(value)))];
}

function mergeTiers(tiers) {
  const merged = {};
  for (const field of TIER_FIELDS) merged[field] = strictest(field, tiers.map((tier) => tier[field]));
  return {
    level: merged.level,
    effort: merged.effort,
    longContext: merged.longContext,
    securityPass: tiers.some((tier) => tier.securityPass),
    recheck: merged.recheck,
  };
}

/** Problems with the door map, so a broken map is refused instead of routing wrong. */
export function validateDoorMap() {
  const problems = [];
  const ids = new Set();
  const validTier = (tier) =>
    tier && TIER_FIELDS.every((field) => doors.STRICTNESS[field].includes(tier[field])) && typeof tier.securityPass === "boolean";
  const validPattern = (pattern) => typeof pattern === "string" && /^[^/\s][^\s]*$/.test(pattern) && !/[{}]/.test(pattern.replace(/\{[^{}]*\}/g, ""));
  const validMarker = (m) =>
    typeof m.marker === "string" &&
    m.pattern instanceof RegExp &&
    !m.pattern.global &&
    !m.pattern.sticky &&
    (m.in === undefined || (Array.isArray(m.in) && m.in.length > 0 && m.in.every(validPattern))) &&
    (m.notIn === undefined || (Array.isArray(m.notIn) && m.notIn.length > 0 && m.notIn.every(validPattern)));
  for (const row of [...doors.ONE_WAY_ROWS, ...doors.CARE_ROWS]) {
    const name = `row ${row.id}`;
    if (ids.has(row.id)) problems.push(`${name} appears twice`);
    ids.add(row.id);
    if (!doors.APPROVERS.includes(row.reviewer)) problems.push(`${name} has reviewer ${row.reviewer}, not one of ${doors.APPROVERS.join(", ")}`);
    if (!Array.isArray(row.paths) || !row.paths.every(validPattern)) problems.push(`${name} has a malformed path pattern`);
    if (row.except && !row.except.every(validPattern)) problems.push(`${name} has a malformed except pattern`);
    if (row.door === "one-way") {
      if (!doors.PARTY_ORDER.includes(row.party) || row.station !== "envejsdorene") problems.push(`${name} has no Envejsdørene party`);
      if (!validTier(row.tier) || (row.markerTier && !validTier(row.markerTier))) problems.push(`${name} has an invalid tier`);
      if (!row.proof?.text || !doors.STRICTNESS.e2e.includes(row.proof.e2e)) problems.push(`${name} has no proof`);
      if (!row.markers.every(validMarker)) {
        problems.push(`${name} has a malformed marker`);
      }
      if (!row.paths.length && !row.markers.length) problems.push(`${name} matches nothing`);
    } else if (row.door === "care") {
      if (!doors.STATIONS[row.station] || row.station === "envejsdorene") problems.push(`${name} has no care station`);
      if (row.tier !== null && !validTier(row.tier)) problems.push(`${name} has an invalid tier`);
      if (!row.paths.length) problems.push(`${name} matches nothing`);
    } else {
      problems.push(`${name} has door ${row.door}`);
    }
  }
  for (const party of doors.PARTY_ORDER) {
    if (!doors.STATIONS.envejsdorene.parties[party]?.proof) problems.push(`party ${party} has no proof`);
  }
  if (!doors.HUMAN_ONLY_ROWS.every((id) => ids.has(id))) problems.push("HUMAN_ONLY_ROWS names an unknown row");
  for (const name of doors.APPROVERS) {
    const reviewer = doors.REVIEWERS.find((entry) => entry.name === name);
    if (!reviewer || !/^[A-Z]{2,4}$/.test(reviewer.initials) || !/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?$/.test(reviewer.login)) {
      problems.push(`approver ${name} has no reviewer entry with initials and a GitHub login`);
    }
  }
  return problems;
}

/** SHA-256 of the door map as it is read, so a caller can log which map routed the change. */
export function doorMapHash() {
  return createHash("sha256").update(readFileSync(DOOR_MAP)).digest("hex");
}

/**
 * The station report (`saldi.station.v1`) for a set of changed paths.
 *
 * @param {{input: object, paths: string[], lines?: Map<string, {line: number, text: string}[]>,
 *   isVendored?: (path: string) => boolean}} change  `lines`: the added lines per path (diff mode only)
 */
export function stationReport({ input, paths, lines = new Map(), isVendored = () => false }) {
  const changedPaths = [...new Set(paths)].sort(compare);
  const doorHits = new Map();
  const careHits = new Map();
  const gatePaths = [];
  const markersHit = [];
  const markerRows = new Set();
  const add = (map, id, hit) => (map.has(id) ? map.get(id).push(hit) : map.set(id, [hit]));

  for (const path of changedPaths) {
    for (const row of doors.ONE_WAY_ROWS) {
      const pattern = rowPattern(row, path);
      if (pattern) add(doorHits, row.id, { kind: "path", path, pattern });
    }
    for (const row of doors.CARE_ROWS) {
      const pattern = rowPattern(row, path);
      if (pattern) add(careHits, row.id, { kind: "path", path, pattern });
    }
    if (isGatePath(path)) gatePaths.push(path);
    for (const { row, marker, line } of markerHits(path, lines.get(path) ?? [], isVendored)) {
      add(doorHits, row, { kind: "marker", path, line, marker });
      markersHit.push({ kind: "marker", row, path, line, marker });
      markerRows.add(row);
    }
  }

  const markerIndex = (row, marker) => ROWS.get(row).markers.findIndex((m) => m.marker === marker);
  const byHit = (row) => (a, b) =>
    compare(a.path, b.path) || (a.line ?? 0) - (b.line ?? 0) || (a.kind === "marker" && b.kind === "marker" ? markerIndex(row, a.marker) - markerIndex(row, b.marker) : 0);
  const rowOrder = (id) => doors.ONE_WAY_ROWS.findIndex((row) => row.id === id);
  markersHit.sort((a, b) => compare(a.path, b.path) || a.line - b.line || rowOrder(a.row) - rowOrder(b.row) || markerIndex(a.row, a.marker) - markerIndex(b.row, b.marker));

  const doorRows = doors.ONE_WAY_ROWS.filter((row) => doorHits.has(row.id)).map((row) => ({
    row: row.id,
    party: row.party,
    hits: doorHits.get(row.id).sort(byHit(row.id)),
  }));
  const careRows = doors.CARE_ROWS.filter((row) => careHits.has(row.id)).map((row) => ({
    row: row.id,
    station: row.station,
    hits: careHits.get(row.id).sort(byHit(row.id)),
  }));
  const careStations = new Set(careRows.map((match) => match.station));
  const parties = doors.PARTY_ORDER.filter((party) => doorRows.some((match) => match.party === party));

  // The first matching rule owns the plate.
  let rule;
  let station;
  let doorFloor;
  if (doorRows.length) {
    [rule, station, doorFloor] = [1, "envejsdorene", "one-way"];
  } else if (careStations.has("faellesgryden")) {
    [rule, station, doorFloor] = [2, "faellesgryden", "two-way-with-care"];
  } else if (gatePaths.length || careStations.has("gartneren")) {
    [rule, station, doorFloor] = [3, "gartneren", careRows.length ? "two-way-with-care" : "none-detected"];
  } else {
    [rule, station, doorFloor] = [4, "skaerm-og-tekst", careRows.length ? "two-way-with-care" : "none-detected"];
  }
  const owner = doors.STATIONS[station];

  // The human at the pass: of the approvers the matches name, the first in APPROVERS leads.
  const said = new Set([...doorRows, ...careRows].map((match) => ROWS.get(match.row).reviewer));
  if (gatePaths.length) said.add(doors.GATE.reviewer);
  if (owner.reviewer) said.add(owner.reviewer);
  const lead = doors.APPROVERS.find((name) => said.has(name)) ?? doors.APPROVERS[0];
  const humanAtPass = { lead, second: doors.APPROVERS.find((name) => name !== lead) ?? null, label: doors.REVIEWER_LABEL };

  // The taster tier: the strictest of every source that matched.
  const tiers = [];
  for (const match of doorRows) {
    const row = ROWS.get(match.row);
    tiers.push(row.markerTier && markerRows.has(row.id) ? row.markerTier : row.tier);
  }
  for (const match of careRows) if (ROWS.get(match.row).tier) tiers.push(ROWS.get(match.row).tier);
  if (gatePaths.length) tiers.push(doors.GATE.tier);
  if (station === "skaerm-og-tekst") {
    const lightRows = careRows.filter((match) => owner.light.rows.includes(match.row)).flatMap((match) => match.hits.map((hit) => hit.path));
    const light = changedPaths.every((path) => firstMatch(owner.light.paths, path) || lightRows.includes(path));
    tiers.push(light ? owner.light.tier : owner.tier);
  }
  const tasterTier = mergeTiers(tiers);

  // The minimum proof, each source once: rows, parties, Fællesgryden, Gartneren, texts, the owner.
  const minimumProof = [];
  for (const match of doorRows) {
    const { proof } = ROWS.get(match.row);
    minimumProof.push({ source: { kind: "row", row: match.row }, text: proof.text, e2e: proof.e2e });
  }
  for (const party of parties) {
    const e2e = strictest("e2e", doorRows.filter((match) => match.party === party).map((match) => ROWS.get(match.row).proof.e2e));
    minimumProof.push({ source: { kind: "station", station: "envejsdorene", party }, text: doors.STATIONS.envejsdorene.parties[party].proof, e2e });
  }
  const careProof = (name) => careRows.some((match) => match.station === name && !ROWS.get(match.row).proof);
  if (careProof("faellesgryden")) {
    const { proof } = doors.STATIONS.faellesgryden;
    minimumProof.push({ source: { kind: "station", station: "faellesgryden", party: null }, text: proof.text, e2e: proof.e2e });
  }
  if (gatePaths.length || careProof("gartneren")) {
    const { proof, image } = doors.STATIONS.gartneren;
    const text = changedPaths.some((path) => firstMatch(image.paths, path)) ? `${proof.text} ${image.text}` : proof.text;
    minimumProof.push({ source: { kind: "station", station: "gartneren", party: null }, text, e2e: proof.e2e });
  }
  for (const match of careRows) {
    const { proof } = ROWS.get(match.row);
    if (proof) minimumProof.push({ source: { kind: "row", row: match.row }, text: proof.text, e2e: proof.e2e });
  }
  if (station === "skaerm-og-tekst") {
    minimumProof.push({ source: { kind: "station", station, party: null }, text: owner.proof.text, e2e: owner.proof.e2e });
  }

  return {
    schema: "saldi.station.v1",
    doorMap: { path: "tools/guardrails/lib/doors.mjs", sha256: doorMapHash() },
    input,
    changedPaths,
    rule,
    station,
    party: rule === 1 ? parties[0] : null,
    doorFloor,
    humanAtPass,
    tasterTier,
    humanOnly: doorRows.some((match) => doors.HUMAN_ONLY_ROWS.includes(match.row)),
    minimumProof,
    doorRows,
    careRows,
    gatePaths,
    markersHit,
  };
}

/** The same report as plain text, for a developer. */
export function formatStation(report) {
  const owner = doors.STATIONS[report.station];
  const party = report.party ? `, party ${report.party} ${owner.parties[report.party].name}` : "";
  const tier = report.tasterTier;
  const range =
    report.input.mode === "paths"
      ? `paths from stdin against ${report.input.base}`
      : `${report.input.base}..${report.input.head ?? "working tree"}`;
  const label = (source) => (source.kind === "row" ? source.row : doors.STATIONS[source.station].name + (source.party ? ` ${source.party}` : ""));
  const hit = (h) => (h.kind === "path" ? `${h.path} (${h.pattern})` : `${h.path}:${h.line} "${h.marker}"`);
  const out = [
    `station:           ${owner.name}${party} (rule ${report.rule})`,
    `door floor:        ${report.doorFloor}${report.doorFloor === "none-detected" ? " (the map is a floor: no match is not two-way)" : ""}`,
    `human at the pass: ${report.humanAtPass.lead}${report.humanAtPass.second ? ", second " + report.humanAtPass.second : ""} (${report.humanAtPass.label})`,
    `taster tier:       ${tier.level}, effort ${tier.effort}, long-context reader ${tier.longContext}, ` +
      `${tier.securityPass ? "security pass" : "no security pass"}, re-check ${tier.recheck}`,
    `human only:        ${report.humanOnly ? "yes: never cooked by an agent" : "no"}`,
    "minimum proof:",
    ...report.minimumProof.map((proof) => `  - ${label(proof.source)}: ${proof.text} [e2e: ${proof.e2e}]`),
  ];
  if (report.doorRows.length) {
    out.push("one-way rows:");
    for (const match of report.doorRows) out.push(`  - ${match.row} (${match.party}): ${match.hits.map(hit).join(", ")}`);
  }
  if (report.careRows.length) {
    out.push("care rows:");
    for (const match of report.careRows) out.push(`  - ${match.row} (${doors.STATIONS[match.station].name}): ${match.hits.map(hit).join(", ")}`);
  }
  if (report.gatePaths.length) out.push(`gate paths:        ${report.gatePaths.join(", ")}`);
  out.push(`changed paths:     ${report.changedPaths.length} (${range})`);
  return `${out.join("\n")}\n`;
}
