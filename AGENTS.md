# Agent Instructions

Before making changes in this repo, read [doc/ai/MEMORY.md](doc/ai/MEMORY.md) and follow the links inside it. Those files record project conventions and past feedback from the team that should guide how you work here.

Before you push, run `node tools/guardrails/check.mjs`. CI runs the same command on every pull request ([.github/workflows/guardrails.yml](.github/workflows/guardrails.yml)); see [tools/guardrails/README.md](tools/guardrails/README.md).

Record durable project conventions and feedback under `doc/ai/`, keeping [MEMORY.md](doc/ai/MEMORY.md) as the index. Keep this file as the entry point rather than adding individual rules here.
