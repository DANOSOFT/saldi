#!/usr/bin/env node
// @ts-check
// Telegram nudges for PRs that wait on their author.
//
// Every run (notify mode):
//   - a person (not a bot) requested changes and the PR has no `needs-author`
//     label yet, or the request is newer than the label: (re)apply the label,
//     then DM the author, or the human sponsor of an agent account. A DM that
//     does not arrive takes the label off again so the next run retries, and
//     someone not connected to the bot is left alone until they connect;
//   - the PR has the label but no open change request remains, or every
//     reviewer who asked for changes has been re-requested: remove the label.
// Once a day (remind mode) also:
//   - DM the author again when a change request is 1+ working day old and the
//     reviewer has not been re-requested;
//   - send the escalation contact a digest of PRs waiting 2+ working days and
//     of authors the bot cannot reach.
//
// The label is the only state, so no database is needed: its presence means the
// author was told, and the time it was last applied says which requests were. Run from
// .github/workflows/review-notify.yml. Without TELEGRAM_BOT_TOKEN, or with
// DRY_RUN=1, it only prints what it would do.
//
// The repository is public, so logs name PR numbers and GitHub logins only,
// never Telegram ids.
//
// Env:
//   GH_TOKEN            token with issues/pull-requests write (labels)
//   REPO                owner/name, e.g. DANOSOFT/saldi
//   TELEGRAM_BOT_TOKEN  bot token from @BotFather
//   TELEGRAM_CHAT_MAP   JSON: {"users": {"<login>": <telegram user id>},
//                              "sponsors": {"<agent login>": "<human login>"},
//                              "escalate": "<login>"}
//   MODE                notify (default) | remind
//   DRY_RUN             1 = print only
//   NOW                 ISO time override, for tests and replays

import process from "node:process";
import { fileURLToPath } from "node:url";

export const LABEL = "needs-author";
const BOT_LOGINS = new Set(["coderabbitai", "copilot-pull-request-reviewer", "github-actions", "dependabot"]);

/**
 * @typedef {{ reviewer: string, submittedAt: string, comments: number }} ChangeRequest
 * @typedef {{
 *   number: number, title: string, url: string, isDraft: boolean,
 *   author: string, labels: string[], labelledAt: string | null,
 *   requested: string[], changeRequests: ChangeRequest[], lastCommitAt: string | null
 * }} Pr
 * @typedef {{ users: Record<string, number>, sponsors: Record<string, string>, escalate?: string }} ChatMap
 * @typedef {{ type: "notify" | "remind", pr: Pr, to: string, text: string }} Message
 * @typedef {{ pr: Pr, days: number, reviewers: string[] }} Overdue
 * @typedef {{ messages: Message[], addLabel: Pr[], removeLabel: Pr[], overdue: Overdue[] }} Plan
 */

/** @param {string} login */
export function normalizeLogin(login) {
  return login.replace(/^app\//, "").replace(/\[bot\]$/, "");
}

/** @param {{ login?: string, __typename?: string } | null | undefined} actor */
function isBot(actor) {
  if (!actor || !actor.login) return true;
  return actor.__typename === "Bot" || /\[bot\]$/.test(actor.login) || BOT_LOGINS.has(normalizeLogin(actor.login));
}

/**
 * Weekdays after `from`'s date up to and including `to`'s date (UTC dates).
 * Mon 15:00 -> Tue 09:00 is 1; Fri 16:00 -> Mon 09:00 is 1; same day is 0.
 * @param {Date} from @param {Date} to
 */
export function workingDaysBetween(from, to) {
  const day = (/** @type {Date} */ d) => Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate());
  let count = 0;
  for (let t = day(from) + 86400000; t <= day(to); t += 86400000) {
    const weekday = new Date(t).getUTCDay();
    if (weekday !== 0 && weekday !== 6) count++;
  }
  return count;
}

/** @param {string} s @param {number} n */
function clip(s, n) {
  return s.length > n ? `${s.slice(0, n - 1)}…` : s;
}

/** @param {Pr} pr @param {ChatMap} map */
export function recipientFor(pr, map) {
  const author = normalizeLogin(pr.author);
  return map.sponsors[author] ?? author;
}

/** @param {Pr} pr @param {ChangeRequest[]} requests @param {string} to */
function notifyText(pr, requests, to) {
  const who = requests.map((c) => `${c.reviewer}${c.comments ? ` (${c.comments} comment${c.comments === 1 ? "" : "s"})` : ""}`).join(", ");
  const agent = normalizeLogin(pr.author) === to ? "" : ` It was opened by ${normalizeLogin(pr.author)}, which you run.`;
  return [
    `Changes requested on PR #${pr.number}: ${clip(pr.title, 90)}`,
    `By ${who}.${agent}`,
    pr.url,
    "Fix it today if you can, then re-request review.",
  ].join("\n");
}

/** @param {Pr} pr @param {ChangeRequest[]} waiting @param {number} days */
function remindText(pr, waiting, days) {
  const oldest = oldestRequest(waiting);
  const pushedSince = pr.lastCommitAt !== null && Date.parse(pr.lastCommitAt) > Date.parse(oldest.submittedAt);
  const state = pushedSince
    ? "You have pushed since. Re-request review when it is ready."
    : "There is no new commit since.";
  return [
    `Reminder: PR #${pr.number} still waits on you. ${waiting.map((c) => c.reviewer).join(", ")} requested changes ${days} working day${days === 1 ? "" : "s"} ago. ${state}`,
    pr.url,
  ].join("\n");
}

/**
 * The PR has waited on its author since the oldest request nobody has re-requested.
 * @param {ChangeRequest[]} requests
 */
function oldestRequest(requests) {
  return requests.reduce((a, b) => (Date.parse(b.submittedAt) < Date.parse(a.submittedAt) ? b : a));
}

/**
 * Decide what to send and which labels to change. Pure: no I/O.
 * @param {Pr[]} prs @param {{ mode: "notify" | "remind", now: Date, map: ChatMap }} opts
 * @returns {Plan}
 */
export function plan(prs, { mode, now, map }) {
  /** @type {Plan} */
  const out = { messages: [], addLabel: [], removeLabel: [], overdue: [] };
  for (const pr of prs) {
    const labelled = pr.labels.includes(LABEL);
    const waiting = pr.changeRequests.filter((c) => !pr.requested.includes(c.reviewer));
    if (labelled && waiting.length === 0) {
      out.removeLabel.push(pr);
      continue;
    }
    if (waiting.length === 0 || pr.isDraft) continue;
    const to = recipientFor(pr, map);
    // A request newer than the label has not been announced; without a label time, assume it was.
    const labelTime = pr.labelledAt === null ? Infinity : Date.parse(pr.labelledAt);
    const fresh = labelled ? waiting.filter((c) => Date.parse(c.submittedAt) > labelTime) : waiting;
    if (fresh.length) {
      out.messages.push({ type: "notify", pr, to, text: notifyText(pr, fresh, to) });
      out.addLabel.push(pr);
      continue;
    }
    if (mode !== "remind") continue;
    const days = workingDaysBetween(new Date(oldestRequest(waiting).submittedAt), now);
    if (days >= 1) out.messages.push({ type: "remind", pr, to, text: remindText(pr, waiting, days) });
    if (days >= 2) out.overdue.push({ pr, days, reviewers: waiting.map((c) => c.reviewer) });
  }
  return out;
}

/** @param {Overdue[]} overdue @param {string[]} unreachable @param {ChatMap} map */
export function digestText(overdue, unreachable, map) {
  const lines = [];
  if (overdue.length) {
    lines.push("PRs waiting on their author for 2+ working days:");
    for (const { pr, days, reviewers } of overdue.sort((a, b) => b.days - a.days)) {
      lines.push(`#${pr.number} ${recipientFor(pr, map)}, ${days} working days (${reviewers.join(", ")}): ${clip(pr.title, 60)}`);
    }
  }
  if (unreachable.length) {
    if (lines.length) lines.push("");
    lines.push(`Not reachable on Telegram (no mapping, or the bot was never started): ${[...new Set(unreachable)].join(", ")}`);
  }
  return lines.join("\n");
}

/**
 * Split on line breaks into pieces Telegram accepts (sendMessage takes at most
 * 4096 characters); a single longer line is cut.
 * @param {string} text @param {number} [limit]
 */
export function chunkText(text, limit = 4000) {
  /** @type {string[]} */
  const chunks = [];
  let current = "";
  for (const line of text.split("\n")) {
    const candidate = current ? `${current}\n${line}` : line;
    if (candidate.length <= limit) {
      current = candidate;
      continue;
    }
    if (current) chunks.push(current);
    let rest = line;
    while (rest.length > limit) {
      chunks.push(rest.slice(0, limit));
      rest = rest.slice(limit);
    }
    current = rest;
  }
  if (current) chunks.push(current);
  return chunks;
}

// ---------------------------------------------------------------- I/O ---

const QUERY = `query($owner: String!, $name: String!, $cursor: String) {
  repository(owner: $owner, name: $name) {
    pullRequests(states: OPEN, first: 50, after: $cursor) {
      pageInfo { hasNextPage endCursor }
      nodes {
        number title url isDraft
        author { login __typename }
        labels(first: 50) { nodes { name } }
        timelineItems(itemTypes: [LABELED_EVENT], last: 30) { nodes { ... on LabeledEvent { createdAt label { name } } } }
        reviewRequests(first: 30) { nodes { requestedReviewer { __typename ... on User { login } ... on Bot { login } } } }
        latestOpinionatedReviews(first: 30) { nodes { state submittedAt author { login __typename } comments { totalCount } } }
        commits(last: 1) { nodes { commit { committedDate } } }
      }
    }
  }
}`;

/** @param {string} token @param {string} query @param {Record<string, unknown>} variables */
async function graphql(token, query, variables) {
  const res = await fetch("https://api.github.com/graphql", {
    method: "POST",
    headers: { authorization: `bearer ${token}`, "content-type": "application/json" },
    body: JSON.stringify({ query, variables }),
  });
  const body = await res.json();
  if (!res.ok || body.errors) throw new Error(`GitHub GraphQL failed: ${res.status} ${JSON.stringify(body.errors ?? body)}`);
  return body.data;
}

/** @param {string} token @param {string} repo @returns {Promise<Pr[]>} */
export async function fetchOpenPrs(token, repo) {
  const [owner, name] = repo.split("/");
  /** @type {Pr[]} */
  const prs = [];
  /** @type {string | null} */
  let cursor = null;
  do {
    const data = await graphql(token, QUERY, { owner, name, cursor });
    const page = data.repository.pullRequests;
    for (const n of page.nodes) {
      prs.push({
        number: n.number,
        title: n.title,
        url: n.url,
        isDraft: n.isDraft,
        author: n.author?.login ?? "ghost",
        labels: n.labels.nodes.map((/** @type {{ name: string }} */ l) => l.name),
        labelledAt: n.timelineItems.nodes
          .filter((/** @type {any} */ e) => e.label?.name === LABEL)
          .reduce((/** @type {string | null} */ at, /** @type {any} */ e) => (at === null || e.createdAt > at ? e.createdAt : at), null),
        requested: n.reviewRequests.nodes.map((/** @type {any} */ r) => r.requestedReviewer?.login).filter(Boolean),
        changeRequests: n.latestOpinionatedReviews.nodes
          .filter((/** @type {any} */ r) => r.state === "CHANGES_REQUESTED" && !isBot(r.author))
          .map((/** @type {any} */ r) => ({ reviewer: r.author.login, submittedAt: r.submittedAt, comments: r.comments.totalCount })),
        lastCommitAt: n.commits.nodes[0]?.commit.committedDate ?? null,
      });
    }
    cursor = page.pageInfo.hasNextPage ? page.pageInfo.endCursor : null;
  } while (cursor);
  return prs;
}

/** @param {string} token @param {string} repo @param {number} number @param {"add" | "remove"} op */
async function setLabel(token, repo, number, op) {
  const base = `https://api.github.com/repos/${repo}/issues/${number}/labels`;
  const headers = { authorization: `bearer ${token}`, accept: "application/vnd.github+json" };
  const res = op === "add"
    ? await fetch(base, { method: "POST", headers, body: JSON.stringify({ labels: [LABEL] }) })
    : await fetch(`${base}/${encodeURIComponent(LABEL)}`, { method: "DELETE", headers });
  if (!res.ok && !(op === "remove" && res.status === 404)) throw new Error(`label ${op} on #${number} failed: ${res.status}`);
}

/**
 * Send one message and keep the label in step with what the author was told.
 *
 * notify: the label goes on first, so a label that fails never leads to a DM the
 * next run would repeat; a DM that did not arrive takes the label off again, so
 * the next run retries. Someone without a chat id is left alone: their PR gets no
 * label until they connect, which also keeps the PR timeline free of label churn.
 *
 * @param {Message} m
 * @param {{
 *   chatId: number | undefined,
 *   label: (op: "add" | "remove") => Promise<void>,
 *   send: (chatId: number, text: string) => Promise<"sent" | "unreachable" | "failed">
 * }} deps
 * @returns {Promise<"sent" | "unreachable" | "failed" | "label-failed">}
 */
export async function deliver(m, { chatId, label, send }) {
  if (!chatId) return "unreachable";
  if (m.type === "notify") {
    try {
      // Re-applying moves the label's time to now, which marks these requests as announced.
      if (m.pr.labels.includes(LABEL)) await label("remove");
      await label("add");
    } catch {
      return "label-failed";
    }
  }
  const result = await send(chatId, m.text);
  if (m.type === "notify" && result !== "sent") {
    try {
      await label("remove");
    } catch {
      // The request then counts as announced; the next working day's reminder still reaches the author.
    }
  }
  return result;
}

/**
 * Create the label if the repository does not have it yet.
 * @param {string} token @param {string} repo
 */
async function ensureLabel(token, repo) {
  const headers = { authorization: `bearer ${token}`, accept: "application/vnd.github+json" };
  const res = await fetch(`https://api.github.com/repos/${repo}/labels/${encodeURIComponent(LABEL)}`, { headers });
  if (res.ok) return;
  if (res.status !== 404) throw new Error(`label lookup failed: ${res.status}`);
  const created = await fetch(`https://api.github.com/repos/${repo}/labels`, {
    method: "POST",
    headers,
    body: JSON.stringify({ name: LABEL, color: "d93f0b", description: "Changes requested; waiting on the PR author (set by review-notify)" }),
  });
  // 422: another run created it in the meantime.
  if (!created.ok && created.status !== 422) throw new Error(`label create failed: ${created.status}`);
}

/**
 * @param {string} botToken @param {number} chatId @param {string} text
 * @returns {Promise<"sent" | "unreachable" | "failed">}
 */
async function sendTelegram(botToken, chatId, text) {
  try {
    const res = await fetch(`https://api.telegram.org/bot${botToken}/sendMessage`, {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({ chat_id: chatId, text, disable_web_page_preview: true }),
    });
    if (res.ok) return "sent";
    // 400 "chat not found" and 403 "bot was blocked / can't initiate" mean the person never pressed Start.
    return res.status === 400 || res.status === 403 ? "unreachable" : "failed";
  } catch {
    return "failed";
  }
}

/** @param {string | undefined} raw @returns {ChatMap} */
export function parseChatMap(raw) {
  if (!raw) return { users: {}, sponsors: {} };
  const map = JSON.parse(raw);
  /** @type {Record<string, string>} */
  const sponsors = {};
  for (const [k, v] of Object.entries(map.sponsors ?? {})) sponsors[normalizeLogin(k)] = String(v);
  return { users: map.users ?? {}, sponsors, escalate: map.escalate };
}

async function main() {
  const env = process.env;
  const repo = env.REPO ?? "DANOSOFT/saldi";
  const ghToken = env.GH_TOKEN ?? "";
  const botToken = env.TELEGRAM_BOT_TOKEN ?? "";
  const dryRun = env.DRY_RUN === "1" || !botToken;
  const mode = env.MODE === "remind" ? "remind" : "notify";
  const now = env.NOW ? new Date(env.NOW) : new Date();
  const map = parseChatMap(env.TELEGRAM_CHAT_MAP);
  if (!ghToken) throw new Error("GH_TOKEN is required");
  if (!botToken) console.log("TELEGRAM_BOT_TOKEN is not set: dry run.");

  const prs = await fetchOpenPrs(ghToken, repo);
  const p = plan(prs, { mode, now, map });
  console.log(`${repo}: ${prs.length} open PRs, mode ${mode}${dryRun ? ", dry run" : ""}.`);

  /** @type {string[]} */
  const unreachable = [];
  let failures = 0;
  if (!dryRun && p.messages.some((m) => m.type === "notify")) await ensureLabel(ghToken, repo);
  for (const m of p.messages) {
    const chatId = map.users[m.to];
    if (dryRun) {
      if (!chatId) unreachable.push(m.to);
      console.log(`${m.type} #${m.pr.number} -> ${m.to}: dry-run${chatId ? "" : " (not connected)"}`);
      console.log(m.text.replace(/^/gm, "    "));
      continue;
    }
    const result = await deliver(m, {
      chatId,
      label: (op) => setLabel(ghToken, repo, m.pr.number, op),
      send: (id, text) => sendTelegram(botToken, id, text),
    });
    if (result === "unreachable") unreachable.push(m.to);
    if (result === "failed" || result === "label-failed") failures++;
    console.log(`${m.type} #${m.pr.number} -> ${m.to}: ${result}`);
  }
  for (const pr of p.removeLabel) {
    console.log(`clear #${pr.number}`);
    if (dryRun) continue;
    try {
      await setLabel(ghToken, repo, pr.number, "remove");
    } catch (err) {
      failures++;
      console.log(`clear #${pr.number} failed: ${err instanceof Error ? err.message : err}`);
    }
  }

  if (mode === "remind") {
    const text = digestText(p.overdue, unreachable, map);
    const to = map.escalate;
    if (text && to) {
      const chatId = map.users[to];
      for (const part of chunkText(text)) {
        const result = dryRun ? "dry-run" : chatId ? await sendTelegram(botToken, chatId, part) : "unreachable";
        console.log(`digest -> ${to}: ${result}`);
      }
      if (dryRun) console.log(text.replace(/^/gm, "    "));
    }
  }
  if (p.messages.length === 0 && p.removeLabel.length === 0) console.log("Nothing to do.");
  if (failures) {
    console.error(`${failures} send or label step(s) failed; see above. They are retried on the next run.`);
    process.exitCode = 1;
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main().catch((err) => {
    console.error(err instanceof Error ? err.message : err);
    process.exit(1);
  });
}
