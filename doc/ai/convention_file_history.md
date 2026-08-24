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

Continuation lines start with `//` followed by spaces padding out to the column where the description text starts on the first line, so wrapped text visually aligns:

```
// 20260713 CL/PHR Added invoice history convention support to
//                  the header history block for future changes.
```

**Why:** Lets the team (and future AI sessions) tell at a glance which changes were AI-assisted and by which tool, without disturbing the existing header layout or legacy history lines.

**How to apply:** Only applies to new history lines going forward — do not reformat existing legacy history lines (which use inconsistent separators like `-`, `_`, or none). When Claude or Codex makes a code change in a file that has this header structure, append one history line (with continuation lines if needed) using the current date, the appropriate `<AIHelper>/<Initials>` tag, and a short description of the change.
