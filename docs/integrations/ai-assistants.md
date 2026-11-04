---
title: AI coding assistants
weight: 2
---

An AI coding assistant writes better code against this client when it reads these docs instead of guessing from what it remembers. They're published on Context7 as [mozex/iminify-php](https://context7.com/mozex/iminify-php), built from this repository's README and `docs/` folder, so they follow each release.

## Context7

Connect Context7's MCP server to your assistant (Claude Code, Cursor, VS Code and others take it), then name the library when you ask for code: "use the Context7 docs for mozex/iminify-php". To make it the habit, add a line to your project's instructions for the assistant, such as `CLAUDE.md`, `AGENTS.md` or a Cursor rule, saying to look the client up there before writing code that calls Iminify.

## Compressing images from the assistant

When you want the assistant to compress, convert or scan images itself rather than write code that does it, connect it to Iminify's own MCP server instead. [Connecting an app](https://www.iminify.com/docs/mcp#connecting-an-app) covers Claude, Claude Code, Cursor, VS Code and ChatGPT.
