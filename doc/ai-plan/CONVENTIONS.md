# ai/plan conventions: branches, worktrees, schema and the per-slice gate

Decided 2026-09-25 in the plan's "Branch strategy and the per-slice gate" decision. This file is the canonical copy. Every slice ticket links here.

## 1. Remotes and branches

| Thing | Value |
|---|---|
| Plan remote | `ai` → `https://github.com/DANOSOFT/saldi-AI.git` (private, org-visible: Danosoft staff can read it) |
| Integration branch | `ai/plan`, the default branch on saldi-AI |
| Slice branch | `ai/sNN-slug`. `NN` is the slice's fixed number from "Slice order on the ai/plan branch". Never `ai/plan/<x>`, because git cannot hold `ai/plan` and `ai/plan/<x>` at the same time. |
| Master sync branch | `ai/sync-YYYYMMDD` |
| Upstream remote | `origin` → `https://github.com/DANOSOFT/saldi.git` (push over https) |
| chaty_V2 remote | `DANOSOFT/chaty-AI` (private, to be created in chaty_V2 phase 0), with its own `ai/plan` |

Hard rules for saldi-AI:
- **Never push a `master` branch to saldi-AI.** The `deploy-ssl3.yml` workflow fires on pushes to `master` and targets the `[self-hosted, ssl3]` runner.
- Push `ai/plan` first, so it becomes the default branch.
- Disable the inherited `stale.yml`, `assign-author.yml` and `advisory-review.yml` in saldi-AI's Actions settings.
- saldi-AI has no rulesets (a private repo without GitHub Pro), so the orchestrator enforces the gate. GitHub does not.
- `git rerere` is enabled globally (`~/.config/git/config`), so every worktree has it.

## 2. Worktrees and the dev stack

- **Integration worktree:** `~/Clients/Danosoft/saldi-wt/ai-plan` on `ai/plan`. It is used only for master syncs and as the base for new slices. Nothing is built in it. Never build in the main checkout either.
- **Slice worktree:** `~/Clients/Danosoft/saldi-wt/ai-sNN-slug` on `ai/sNN-slug`.
  - Plan-only slices branch from `ai/plan`.
  - Upstream slices branch from `origin/master` (see §4).
  - After the merge, run `git worktree remove` and delete the local branch.
- **chaty_V2 worktrees:** `~/Clients/Danosoft/chaty-wt/ai-sNN-slug`.
- **Docker project `saldiplan`**, separate from `saldi_default` and from the decision-layer stack on port 5020:
  - One Postgres with its own volume, holding the master registry `saldi` plus these tenants:
    - `plan_tpl`: the template, a clone of `test_39`;
    - `plan_main`: the integration worktree's tenant, used by the sync-PR gate;
    - `plan_sNN`: one per slice, cloned from `plan_tpl`, so every `planUpdates.php` step runs on a fresh tenant;
    - `plan_real`: a clone of `ai_test_34` (medshop). It exists only when a slice ticket asks for real-shaped data. It holds PII, so it stays local.
  - One web container per worktree, bind-mounting that worktree:
    - port `5100` for `ai-plan`;
    - port `51NN` for slice `NN`.
  - Characterisation tests run with `SALDI_CHAR_TEMPLATE_DB=plan_tpl` and `SALDI_CHAR_TEST_DB=plan_sNN_char`.
- **`new-slice NN slug [--upstream]`** does the setup in one call:
  1. creates the worktree and branch;
  2. copies the gitignored `includes/connect.php`;
  3. clones the tenant;
  4. starts the web container;
  5. prints the port and login.
- **`gate NN`** runs §7.1–7.2 and writes the log that goes in the PR body.
- Both scripts are built by "Plan tooling: new-slice and gate scripts".

## 3. Slice flow (plan-only slices)

1. `new-slice NN slug`. The ticket's acceptance tests are written first and committed. The gate log shows them red on that commit.
2. Characterisation tests for the touched legacy code are committed next, green on the base (§7.3). Then the implementation.
3. `gate NN`. Then comes the review (§7.5) and, where the slice has UI, the browser test (§7.4). At most two fix loops.
4. Open a PR on saldi-AI from `ai/sNN-slug` into `ai/plan`. The body carries the gate evidence:
   - the gate log;
   - the review verdicts and how each finding was resolved;
   - the browser screenshots or video;
   - any characterisation exemptions.
5. Merge with `gh pr merge --squash --delete-branch` once `gh pr checks` is green. The squash title is `sNN slug: <goal>`, with the ticket path in the body. The ticket answer links to the PR.
6. A red gate after two loops means the PR stays open as a draft, with the red report. The phase runner stops.

## 4. Upstream slices (upstream-first)

A slice is an upstream candidate only if the **ticket says so before building**. It must need nothing that is on `ai/plan` but not yet on master.

1. `new-slice NN slug --upstream` cuts the branch from `origin/master`.
2. It passes the same gate.
3. The same branch opens **two PRs**:
   - one to `DANOSOFT/saldi` `master`: the team's normal review, a Jira key in the title where one exists (e.g. SD-591), a reviewer delegated via `saldi-delegate-reviews`, and the file history lines and pre-PR test report per `doc/ai/`;
   - one to saldi-AI `ai/plan`, squash-merged as soon as the gate passes. The team's review pace never blocks the plan.
4. **Never stack upstream PRs.** The base is always `master`. If slice B depends on an upstream slice A that has not merged, B either waits or is built plan-only. Before any merge, check `baseRefName`.
5. Master's ruleset is squash-only, and a push dismisses approvals. Resolve conflicts before asking for approval. A CodeRabbit CHANGES_REQUESTED review blocks the merge until someone dismisses it. **Never merge with `--admin`.**
6. After master squash-merges the upstream PR, run a master sync (§5) promptly. The identical change merges cleanly, and rerere replays any resolutions for review edits.
7. Schema in upstream slices goes straight into `betweenUpdates.php`, per the team convention (§6).

## 5. Master sync rhythm

- **Only between slices.** Never while a slice PR is open.
- **Triggers:**
  - at the start of every phase run, or before cutting a slice by hand;
  - right after one of our upstream PRs lands on master;
  - otherwise at least weekly.
- **Mechanics:**
  - In `ai-plan`, create `ai/sync-YYYYMMDD` and run `git fetch origin && git merge origin/master` (with rerere).
  - Open a PR on saldi-AI and merge it with **`gh pr merge --merge`, never `--squash`**. A squash drops master's ancestry, and every later sync would re-conflict. This is the one exception to squash-only on saldi-AI.
- **Gate on the sync PR:**
  - lint;
  - the full PHPUnit suite on `plan_main`;
  - an Astra login smoke test in the recorded browser.
  
  A red sync stops the phase runner.
- **Conflicts:** in shared legacy files, master wins, and the plan re-applies its change on top. Every resolution is listed in the sync PR body.

## 6. Schema changes

- **One file:** `includes/planUpdates.php` holds all of the plan's schema and data changes.
- **One line:** the plan's only edit to `includes/betweenUpdates.php` is `include_once(__DIR__ . '/planUpdates.php');`. It goes **near the top, right after the header docblock**, because team PRs append at the end and a line there would conflict on every sync.
- **Named, ordered, idempotent steps:** `s01_…`, `s03_automation_jobs`, and so on. One `settings` row records the last step applied, so an up-to-date tenant pays one select per login (this respects SD-596). Steps are never edited after they merge. A correction is a new step.
- **Both databases:** DDL goes through the existing `db_type` branching, so it works on Postgres and MySQL. The gate tests Postgres only, and MySQL support is a recorded assumption in "Danosoft decisions: assumptions for the branch".
- **Upstream slices** put their statements directly in `betweenUpdates.php`, the way the team does it. When a plan-only slice later goes upstream, its steps move from `planUpdates.php` into `betweenUpdates.php` in that PR.
- Never edit `includes/opdat_*.php` or `includes/betweenUpdates2.php`, and never bump `includes/version.php`.
- Names follow the coherence rules: English codes, no "ai" in automation primitives.

## 7. The per-slice gate

Every step runs in the slice's `saldiplan` web container. "Done" requires a passing test, never a claim.

### 7.1 Lint

- `php -l` on every PHP file changed in the diff.
- `phpcs` (PSR12, per `phpcs.xml.dist`) on **new** files only.
- Convention checks:
  - no secret or key literals in the diff;
  - `importfiler/tekster.csv` ids are unique (`cut -f1 | sort | uniq -d` is empty);
  - every edited legacy file has its header history line (`// YYYYMMDD CL|CDX/LH …`);
  - `opdat_*.php` and `betweenUpdates2.php` are untouched;
  - `betweenUpdates.php` has no change other than the include line, except in upstream slices.

### 7.2 PHPUnit

- The full suite (`phpunit.xml`), with zero failures and zero errors.
- **Zero skips in the DB-backed suites.** `CharacterizationEnv` skips when the stack isn't usable, so a skip there counts as red.
- **Red first:** the acceptance-test commit comes before the implementation. The gate log shows those tests failing on that commit and passing on the head.
- From the safety-net slice onward, a saldi-AI Actions job runs the same lint and PHPUnit with a Postgres service. The orchestrator requires `gh pr checks` to be green before it merges.

### 7.3 Characterisation tests around touched legacy code

- **Legacy** means any file that exists on origin/master at the slice's base commit.
- **Touched** means one of these:
  - a named function whose body changes in the diff;
  - for top-level page scripts, the page request path that changes.
  
  Calling a legacy function without changing it does not count.
- **Pin before change:**
  - The characterisation test is committed first and runs green on the base. It records current behaviour, including quirks.
  - An intended behaviour change updates the test's expectation in the same commit as the code, with a one-line reason.
  - Tests live under `tests/characterization/<dir>/*.test.php` and reuse the existing `run_*_page.php` harnesses.
- **Machine check:** the gate lists the touched functions and pages from the diff. It fails if any of them has no characterisation test that exercises it, matched by name.
- **Exemptions** (for example, code that needs an external API) are listed in the PR body with the reason. The reviewer has to accept each one.

### 7.4 Browser test (Astra)

- **Required when** the diff touches any of these:
  - a page script;
  - JS or CSS;
  - `tekster.csv` or findtekst ids;
  - any surface that renders a suggestion.
  
  It is also required on every master sync PR, as a login smoke test. Service-only or CLI slices state "no UI" in the PR body.
- **Astra is always the browser tester.** It uses the `.codex-run/astra-ui` recipe (Codex + Playwright MCP with video recording) against port `51NN` and tenant `plan_sNN`. It runs in Danish, as a user with limited rights where permissions are involved. It covers:
  - the ticket's scenarios;
  - the happy path;
  - one failure path;
  - no new console errors.
- **Evidence:** screenshots of each step, plus a video for any approve or apply action. They are uploaded to the saldi-AI PR.
- A closed Astra browser leaves a stale row in the master `online` table. Delete it before the next login.

### 7.5 Review

- **Two providers on every slice**, reviewing the same diff and the ticket:
  - an Opus `/code-review` at high effort, checked against the coherence rules and the `doc/ai/` conventions;
  - an Astra review at `high`.
  
  Opus may run alongside Astra, but **Astra never runs in `parallel()`**. On a UI slice, Astra runs two sequential stages: the review first, then the browser test after the fixes.
- **Reconcile:**
  - A Claude agent de-duplicates the findings and verifies each one against the code.
  - Findings are **blocking** if they are a correctness bug, a coherence-rule violation, a missing or skipped test, or a security issue. Style points are listed and don't block.
- **At most two fix loops.** After each fix, lint and PHPUnit re-run, and each reviewer re-checks only its own findings. If blocking findings are still open, or the reviewers disagree, the slice goes **red**. There is no merge on a split verdict.
- **Quota:** if Astra is capped, the stage waits (chunked relay waits). The gate never falls back to a weaker model.

## 8. chaty_V2 pairing

- **The same scheme as Saldi:**
  - its own `ai/plan` on `DANOSOFT/chaty-AI`, cut after phase 0 has committed what is live;
  - worktrees under `~/Clients/Danosoft/chaty-wt/`;
  - slice numbers from the one global slice order, with each ticket naming its repo.
- **Cross-repo slices:** the same branch name in both repos, merged Saldi first.
- **The contract lives in Saldi.** The tool-registry contract (F8) is generated from Saldi `ai/plan` as `doc/contracts/tool-registry.vN.json`. chaty_V2 keeps a pinned copy plus the Saldi commit it came from.
- **chaty gate:**
  - `pytest`;
  - the frontend build;
  - a contract test against the `saldiplan` stack, which goes red if the contract drifted;
  - Astra's browser test of the widget embedded in the slice's Saldi port;
  - the same two-provider review.
