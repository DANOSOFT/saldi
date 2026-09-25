---
name: convention_file_history
description: "How to append a new history line to a file's header history block"
metadata:
  node_type: memory
  type: project
  originSessionId: 2026-07-13-file-history-convention
---

File headers in this codebase follow a fixed layout: company ASCII banner → versioning line → LICENSE → copyright disclaimer → separator → history block. All of these are contiguous `//` comment lines — no blank or uncommented line breaks the sequence anywhere in that stack.

New history entries are appended at the **bottom** of the existing history block (most recent last), immediately following the last existing history line, in this format:

```
// YYYYMMDD <Tag> <description>
```

- `<Tag>` is just initials (e.g. `PHR`) when no AI helper was involved.
- `<Tag>` is `<AIHelper>/<Initials>` (e.g. `CL/PHR`, `CDX/PHR`) when an AI helper assisted with the change.
- Fixed AI helper vocabulary: `CL` = Claude, `CDX` = Codex. Do not invent other abbreviations without updating this file.

Continuation lines start with `//` followed by spaces padding out to the column where the description text starts on the first line, so wrapped text visually aligns. Do not wrap based on line length — a continuation line is only used to start a new sentence (i.e. break after sentence-ending punctuation `.`, `!`, `?`), not because a line runs long:

```
// 20260713 CL/PHR Added invoice history convention support to the header history block.
//                  Also updated related tests.
```

**Why:** Lets the team (and future AI sessions) tell at a glance which changes were AI-assisted and by which tool, without disturbing the existing header layout or legacy history lines.

**How to apply:** Only applies to new history lines going forward — do not reformat existing legacy history lines (which use inconsistent separators like `-`, `_`, or none). When Claude or Codex makes a code change in a file that has this header structure, append one history line (with continuation lines if needed) using the current date, the appropriate `<AIHelper>/<Initials>` tag, and a short description of the change.

**Header maintenance (user instruction, 2026-09-16):** When changing a file, also update the revision date in the header line containing its path/name to the date of the change, preserving the file's date format. Set the copyright company to `Danosoft ApS` and update the ending year to the current year (2026 for changes made in 2026), retaining any existing starting year. Do not change the release/patch number or other license text as part of this maintenance. Apply this to files being changed, not as a repository-wide cleanup.

**Bootstrapping `<Initials>` in a new working copy (user instruction, 2026-09-22):** `<Initials>` is personal to the user and machine, not part of this repo — it belongs in that user's local AI memory, not in any committed file. Before writing a history line, if the assistant does not already have the user's initials for this project recorded in local memory:

1. Look at the current working directory's folder name. If it is 2-4 letters, it may be a plausible initials/project-tag candidate — but this is only a hint, not a fallback used automatically.
2. Ask the user directly what initials they want recorded in history for this project, regardless of whether step 1 produced a candidate.
3. Save the answer to local memory (not to a repo file) so future sessions in this working copy don't need to ask again.
