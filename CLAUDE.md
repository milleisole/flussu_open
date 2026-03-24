# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Flussu is a **low-code workflow automation server** written in PHP 8.1+. It allows users to design and execute multi-step automated workflows with integrations for AI providers (OpenAI, Claude, Gemini, DeepSeek, Grok, Qwen, etc.), payment systems (Stripe, Revolut), messaging (SMS, Email), cloud storage (Google Drive), webhooks (Zapier, IFTTT, Make.com, n8n), and web scraping.

## Common Commands

```bash
# Install dependencies
composer install

# Full installation (permissions + composer + cron)
sh batchinstall.sh

# Run AI provider tests
php src/Flussu/Test/test_ai_providers.php

# Run Google Drive tests
php src/Flussu/Test/test_google_drive.php
```

There is no PHPUnit, linting, or CI pipeline configured. Tests are manual CLI scripts.

## Architecture

**Entry point:** `webroot/api.php` — all requests route through here. It loads `.env` via phpdotenv, initializes the `Config` singleton (reads `/config/.services.json`), and dispatches to `FlussuController`.

**Request flow:**
```
webroot/api.php → FlussuController → Session → Executor → Worker → Command
```

- **Executor** (`src/Flussu/Flussuserver/Executor.php`) — orchestrates workflow command execution
- **Worker** (`src/Flussu/Flussuserver/Worker.php`) — core command processing engine (largest file ~1524 LOC)
- **Session** (`src/Flussu/Flussuserver/Session.php`) — manages workflow execution state via HTTP sessions
- **Command** (`src/Flussu/Flussuserver/Command.php`) — handles HTTP calls within workflows
- **Environment** (`src/Flussu/Flussuserver/Environment.php`) — sandboxed code evaluation for workflow expressions

**Key design patterns:**
- **Strategy pattern** via interfaces in `src/Flussu/Contracts/` — `IAiProvider`, `IPayProvider`, `ISmsProvider`, `IWebhookProvider`, `ICloudStorageProvider`, `IUriShrinkProvider`. All integrations implement these contracts.
- **Singleton** — `Config.php` provides immutable configuration access via `config('services.google.private_key')` dot notation (Laravel-style helper).
- **PSR-4 autoloading** — all classes under `Flussu\` namespace, mapped from `src/Flussu/`.

**AI providers** live in `src/Flussu/Api/Ai/` — each file (`FlussuOpenAi.php`, `FlussuClaudeAi.php`, `FlussuGeminAi.php`, etc.) implements `IAiProvider`. Controllers `AiChatController` and `AiMediaController` select the provider at runtime.

**API versioning:** current API is v4.0, implemented in `src/Flussu/Api/V40/` with endpoints for Engine, Session, Flow, Connections, and Statistics.

## Configuration

- **`.env`** — database connection, server name, timezone, debug settings. Copy from `.env.sample`.
- **`/config/.services.json`** — all API keys, SMTP credentials, payment provider config, AI model settings. Copy from `.services.json.sample`. This is the centralized secrets store (moved from hardcoded values in v4.5.1).
- **`General.php`** (~35KB) — global utility functions and configuration constants.

## Code Style

- 4 spaces indentation (see `.editorconfig`)
- Comments and some variable names are in Italian
- No strict typing enforced; PHP 8.1+ features used throughout

## Database

MariaDB 11+. Connection configured in `.env`. Key entities: workflows (WID), sessions (SID), users. Database abstraction via `src/Flussu/Beans/Databroker.php` and `Dbh.php`.

## File System Conventions

- `Uploads/` — user file uploads (subdirs: `flussus_01`, `flussus_02`, `temp`, `OCR`, `OCR-ri`)
- `Cache/` — cached workflow objects (invalidated on workflow updates, can be manually cleared)
- `Logs/` — application text logs (1 month retention)
- `Log_sys/` — system logs
- `webroot/flucli/` — client-side JS library (vanilla JS, no framework)
