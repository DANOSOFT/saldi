---
name: feedback_ambiguity_threshold
description: "When confidence in what the user is referring to is below 40%, ask clarifying questions before acting"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 08d3afbb-1c7c-4f74-9e8d-a4f07139d5d7
---

If confidence in what variable, value, or resource the user is referring to is below ~40%, pause and present a short Q&A list for the user to answer before making a choice — do not guess.

**Why:** User sometimes omits details in prompts (e.g. "add a background" without specifying which color variable). A wrong guess wastes time and may produce silently incorrect results.

**How to apply:** Estimate confidence before each lookup or substitution. If below ~40%, list 2–4 short clarifying questions and wait for answers before proceeding.
