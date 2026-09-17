---
name: feedback_protected_update_files
description: "Protect backup/update include files from accidental inspection or edits"
metadata:
  node_type: memory
  type: feedback
  originSessionId: 2026-06-17-project-memory
---

Treat `includes/betweenUpdates2.php` as a backup file. Do not inspect, open, or edit it unless the user explicitly asks for that specific file.

Claude and Codex may both inspect/read `includes/opdat_*.php` files when needed, but must not write to or edit them unless the user explicitly asks for that specific change.

**Why:** These files are sensitive update/backup paths, and accidental changes or even casual inspection can pull work into the wrong ownership area.

**How to apply:** When searching or editing, exclude `includes/betweenUpdates2.php` unless the user's current request names it directly. `includes/opdat_*.php` may be read for context, but exclude it from edits unless the user explicitly requests changes there.
