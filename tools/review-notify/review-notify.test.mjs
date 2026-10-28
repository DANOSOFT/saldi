// Run: node --test tools/review-notify/review-notify.test.mjs
import assert from "node:assert/strict";
import test from "node:test";

import { LABEL, chunkText, digestText, parseChatMap, plan, recipientFor, workingDaysBetween } from "./review-notify.mjs";

const map = parseChatMap(JSON.stringify({
  users: { alice: 1, lui: 2 },
  sponsors: { "app/agent-factory": "lui", "MMR-Codex": "lui" },
  escalate: "lui",
}));

/** @param {Partial<import("./review-notify.mjs").Pr>} over */
function pr(over = {}) {
  return {
    number: 1,
    title: "Fix posting",
    url: "https://github.com/o/r/pull/1",
    isDraft: false,
    author: "alice",
    labels: [],
    labelledAt: "2026-10-26T14:05:00Z",
    requested: [],
    changeRequests: [{ reviewer: "nicolai", submittedAt: "2026-10-26T14:00:00Z", comments: 2 }],
    lastCommitAt: "2026-10-26T10:00:00Z",
    ...over,
  };
}

const monday = new Date("2026-10-26T15:00:00Z");
const tuesday = new Date("2026-10-27T08:07:00Z");
const wednesday = new Date("2026-10-28T08:07:00Z");

test("working days skip weekends and count from the next day", () => {
  assert.equal(workingDaysBetween(new Date("2026-10-26T14:00Z"), new Date("2026-10-26T17:00Z")), 0);
  assert.equal(workingDaysBetween(new Date("2026-10-26T14:00Z"), new Date("2026-10-27T08:00Z")), 1);
  assert.equal(workingDaysBetween(new Date("2026-10-23T16:00Z"), new Date("2026-10-26T08:00Z")), 1); // Fri -> Mon
  assert.equal(workingDaysBetween(new Date("2026-10-23T16:00Z"), new Date("2026-10-28T08:00Z")), 3);
});

test("a person's change request notifies the author and adds the label", () => {
  const p = plan([pr()], { mode: "notify", now: monday, map });
  assert.equal(p.messages.length, 1);
  assert.equal(p.messages[0].type, "notify");
  assert.equal(p.messages[0].to, "alice");
  assert.match(p.messages[0].text, /Changes requested on PR #1: Fix posting/);
  assert.match(p.messages[0].text, /By nicolai \(2 comments\)\./);
  assert.deepEqual(p.addLabel.map((x) => x.number), [1]);
});

test("no change request, no message", () => {
  const p = plan([pr({ changeRequests: [] })], { mode: "notify", now: monday, map });
  assert.deepEqual(p, { messages: [], addLabel: [], removeLabel: [], overdue: [] });
});

test("an already labelled PR is not notified again", () => {
  const p = plan([pr({ labels: [LABEL] })], { mode: "notify", now: monday, map });
  assert.equal(p.messages.length, 0);
  assert.equal(p.addLabel.length, 0);
});

test("draft PRs are skipped", () => {
  const p = plan([pr({ isDraft: true })], { mode: "notify", now: monday, map });
  assert.equal(p.messages.length, 0);
});

test("re-requesting the reviewer clears the label and sends nothing", () => {
  const p = plan([pr({ labels: [LABEL], requested: ["nicolai"] })], { mode: "remind", now: wednesday, map });
  assert.equal(p.messages.length, 0);
  assert.deepEqual(p.removeLabel.map((x) => x.number), [1]);
});

test("an approved or dismissed change request clears the label", () => {
  const p = plan([pr({ labels: [LABEL], changeRequests: [] })], { mode: "notify", now: monday, map });
  assert.deepEqual(p.removeLabel.map((x) => x.number), [1]);
});

test("a fresh change request after a re-request notifies before the label is cleared", () => {
  // Author re-requested nicolai, but bob now asks for changes: bob still waits, so no clear,
  // and bob's request is newer than the label, so the author hears about it.
  const p = plan([pr({
    labels: [LABEL],
    requested: ["nicolai"],
    changeRequests: [
      { reviewer: "nicolai", submittedAt: "2026-10-26T14:00:00Z", comments: 1 },
      { reviewer: "bob", submittedAt: "2026-10-27T09:00:00Z", comments: 0 },
    ],
  })], { mode: "notify", now: tuesday, map });
  assert.equal(p.removeLabel.length, 0);
  assert.equal(p.messages.length, 1);
  assert.equal(p.messages[0].type, "notify");
  assert.match(p.messages[0].text, /By bob\./);
  assert.doesNotMatch(p.messages[0].text, /nicolai/);
  assert.deepEqual(p.addLabel.map((x) => x.number), [1]);
});

test("a second request while the label is on notifies once, then not again", () => {
  const two = [
    { reviewer: "nicolai", submittedAt: "2026-10-26T14:00:00Z", comments: 1 },
    { reviewer: "bob", submittedAt: "2026-10-27T09:00:00Z", comments: 0 },
  ];
  const before = plan([pr({ labels: [LABEL], changeRequests: two })], { mode: "notify", now: tuesday, map });
  assert.equal(before.messages.length, 1);
  assert.match(before.messages[0].text, /By bob\./);
  // After the run re-applies the label, its time is past both requests.
  const after = plan([pr({ labels: [LABEL], labelledAt: "2026-10-27T09:10:00Z", changeRequests: two })], { mode: "notify", now: tuesday, map });
  assert.equal(after.messages.length, 0);
});

test("a label with no known time never re-notifies", () => {
  const p = plan([pr({ labels: [LABEL], labelledAt: null })], { mode: "notify", now: wednesday, map });
  assert.equal(p.messages.length, 0);
});

test("agent PRs go to the sponsor", () => {
  const agentPr = pr({ author: "app/agent-factory" });
  assert.equal(recipientFor(agentPr, map), "lui");
  const p = plan([agentPr], { mode: "notify", now: monday, map });
  assert.equal(p.messages[0].to, "lui");
  assert.match(p.messages[0].text, /opened by agent-factory, which you run/);
});

test("remind mode nudges after one working day, escalates after two", () => {
  const labelled = pr({ labels: [LABEL] });
  assert.equal(plan([labelled], { mode: "remind", now: monday, map }).messages.length, 0);

  const t = plan([labelled], { mode: "remind", now: tuesday, map });
  assert.equal(t.messages.length, 1);
  assert.equal(t.messages[0].type, "remind");
  assert.match(t.messages[0].text, /requested changes 1 working day ago\. There is no new commit since\./);
  assert.equal(t.overdue.length, 0);

  const w = plan([labelled], { mode: "remind", now: wednesday, map });
  assert.equal(w.overdue.length, 1);
  assert.equal(w.overdue[0].days, 2);
});

test("reminder notices a push without a re-request", () => {
  const p = plan([pr({ labels: [LABEL], lastCommitAt: "2026-10-26T16:00:00Z" })], { mode: "remind", now: tuesday, map });
  assert.match(p.messages[0].text, /You have pushed since\. Re-request review when it is ready\./);
});

test("notify mode never sends reminders", () => {
  const p = plan([pr({ labels: [LABEL] })], { mode: "notify", now: wednesday, map });
  assert.equal(p.messages.length, 0);
});

test("reminder age and names come from requests still waiting, not re-requested ones", () => {
  // nicolai asked on Monday and still waits; bob asked on Tuesday but was re-requested.
  const p = plan([pr({
    labels: [LABEL],
    labelledAt: "2026-10-27T09:10:00Z",
    requested: ["bob"],
    changeRequests: [
      { reviewer: "nicolai", submittedAt: "2026-10-26T14:00:00Z", comments: 1 },
      { reviewer: "bob", submittedAt: "2026-10-27T09:00:00Z", comments: 0 },
    ],
  })], { mode: "remind", now: wednesday, map });
  assert.equal(p.messages.length, 1);
  assert.match(p.messages[0].text, /nicolai requested changes 2 working days ago/);
  assert.doesNotMatch(p.messages[0].text, /bob/);
  assert.deepEqual(p.overdue.map((o) => [o.pr.number, o.days, o.reviewers]), [[1, 2, ["nicolai"]]]);
});

test("with several waiting requests the age counts from the oldest", () => {
  const p = plan([pr({
    labels: [LABEL],
    labelledAt: "2026-10-28T07:00:00Z",
    changeRequests: [
      { reviewer: "bob", submittedAt: "2026-10-28T06:00:00Z", comments: 0 },
      { reviewer: "nicolai", submittedAt: "2026-10-26T14:00:00Z", comments: 1 },
    ],
  })], { mode: "remind", now: wednesday, map });
  assert.equal(p.overdue[0].days, 2);
  assert.match(p.messages[0].text, /bob, nicolai requested changes 2 working days ago/);
});

test("digest lists overdue PRs and unreachable people", () => {
  const text = digestText([{ pr: pr({ number: 7 }), days: 3, reviewers: ["nicolai"] }], ["bob", "bob"], map);
  assert.match(text, /^PRs waiting on their author for 2\+ working days:\n#7 alice, 3 working days \(nicolai\): Fix posting/);
  assert.match(text, /Not reachable on Telegram .*: bob$/);
  assert.equal(digestText([], [], map), "");
});

test("a long digest is split into pieces Telegram accepts, losing nothing", () => {
  const overdue = Array.from({ length: 120 }, (_, i) => ({ pr: pr({ number: i + 1, title: "x".repeat(60) }), days: 2, reviewers: ["nicolai"] }));
  const text = digestText(overdue, [], map);
  assert.ok(text.length > 4096);
  const parts = chunkText(text);
  assert.ok(parts.length > 1);
  for (const part of parts) assert.ok(part.length <= 4096);
  assert.equal(parts.join("\n"), text);
  assert.deepEqual(chunkText("short"), ["short"]);
  assert.deepEqual(chunkText("y".repeat(9000)).map((c) => c.length), [4000, 4000, 1000]);
});

test("an empty chat map parses", () => {
  assert.deepEqual(parseChatMap(undefined), { users: {}, sponsors: {} });
});
