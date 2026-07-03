# Fastweb AI Factory Providers (MIIA / Qwen / GPT-OSS) — Design Spec

**Date:** 2026-07-03
**Status:** Approved (design), pending implementation plan
**Type:** New AI provider integration (one class, three Platform entries)

## 1. Purpose

Expose three models hosted on the Fastweb AI Factory (ISO-27001 pod) as
selectable Flussu AI providers, usable from workflows via `sendToAi` and from
the chat/media controllers like any other provider:

| Platform enum | Config key      | API model id                 |
|---------------|-----------------|------------------------------|
| `FW_MIIA=30`  | `fw_27001_miia` | `Fastweb/FastwebMIIA-7B`     |
| `FW_QWEN=31`  | `fw_27001_qwen` | `Qwen/Qwen3.6-35B-A3B-FP8`   |
| `FW_GPT=32`   | `fw_27001_gpt`  | `openai/gpt-oss-120b`        |

All three share the same OpenAI-compatible endpoint
(`https://bpod1.ai-factory.fastweb.it/v1/chat/completions`) and differ only in
model id and bearer token.

## 2. Constraints & decisions (locked)

- **Single parameterized class** `FlussuFastwebAi` (approach A): the three
  models are the same API, so one class is constructed with the config key.
  No per-model subclasses, no copy-paste providers.
- **Model id verbatim from config**: the user's `.services.json` blocks must
  carry the full vendor-prefixed id (`Fastweb/FastwebMIIA-7B`,
  `Qwen/Qwen3.6-35B-A3B-FP8`, `openai/gpt-oss-120b`). No prefix mapping in
  code.
- **Endpoint configurable with default**: per-block optional `base-uri`;
  fallback default `https://bpod1.ai-factory.fastweb.it/v1` hardcoded in the
  class.
- **Capabilities**: chat + vision. `canAnalyzeMedia()` is driven by a
  per-block config flag `vision` (bool). Image generation and native web
  search are NOT supported (`canGenerateImages()=false`, `canBrowseWeb()=false`,
  `chat_WebPreview()` returns empty like Mistral); the `Platform` resolvers
  redirect those requests to Together/Qwen (images) and Gemini (web).
- **Enum values**: `FW_MIIA=30`, `FW_QWEN=31`, `FW_GPT=32` (user-chosen; the
  gap after 12 leaves room for other families).
- **Small-model gate**: `fw_27001_miia` sets `"small": true`; the existing
  `Executor::_applySmallModelGate()` + `Platform::configKey()` machinery
  handles attachment rejection and 65535-char prompt truncation with no new
  code, provided `configKey()` maps the new enum entries.

## 3. Components

### 3.1 `src/Flussu/Api/Ai/FlussuFastwebAi.php` (new)

Modeled on `FlussuMistralAi` (raw Guzzle, OpenAI-compatible payload).

- `__construct(string $configKey, $model="", $chat_model="")`
  - reads `services.ai_provider.<configKey>.auth_key` (throws if empty),
    `model`, `chat-model` (falls back to `model`), `base-uri` (falls back to
    the hardcoded default), `vision` (bool, default `false`).
- `chat($preChat, $text, $role)` → `_chatContinue()` → POST
  `<base-uri>/chat/completions` with
  `{model, messages, max_tokens, temperature, stream:false}`.
  - temperature via `Config::init()->aiTemperature($configKey)`;
  - legacy `message`→`content` history normalization like the other providers;
  - always returns the `[history, replyText, tokenUsage]` tuple, including on
    error (`tokenUsage = ['model'=>, 'input'=>, 'output'=>]`).
- `analyzeMedia($preChat, $mediaPath, $prompt, $role)` — OpenAI/Mistral style:
  `image_url` parts with base64 data-URIs, **text part first, then images**;
  accepts a single path or an array of paths (multi-image single turn).
  Returns the standard tuple. If `vision` flag is off, returns an error tuple.
- `canAnalyzeMedia(): bool` → config `vision` flag.
- `canBrowseWeb()` → `false`; `chat_WebPreview()` → `[]`.
- `canGenerateImages()` → `false`; `generateImage()` → error array.
- Guzzle timeouts consistent with the other raw-HTTP providers
  (connect 20s / read 120s).

### 3.2 `src/Flussu/Controllers/Platform.php` (edit)

- Add `case FW_MIIA = 30; case FW_QWEN = 31; case FW_GPT = 32;`
- `configKey()`: map the three cases to `fw_27001_miia` / `fw_27001_qwen` /
  `fw_27001_gpt`.
- `resolveImageProvider()`: the three cases redirect to the image-capable
  default (TOGETHER), same as other chat-only providers.
- `resolveWebProvider()`: the three cases redirect to GEMINI.

### 3.3 `AiChatController` + `AiMediaController` (edit)

Three new `case` arms in each provider-selection `switch`, instantiating
`new FlussuFastwebAi('<configKey>', $model, $chat_model)`.
`$_linkify` stays 0 (only Claude uses 1).

### 3.4 `config/.services.json.sample` (edit)

Three example blocks with full vendor-prefixed ids:

```json
"fw_27001_miia": {
    "auth_key": "YOUR-FASTWEB-MIIA-JWT",
    "base-uri": "https://bpod1.ai-factory.fastweb.it/v1",
    "model": "Fastweb/FastwebMIIA-7B",
    "chat-model": "Fastweb/FastwebMIIA-7B",
    "vision": false,
    "small": true
},
"fw_27001_qwen": {
    "auth_key": "YOUR-FASTWEB-QWEN-JWT",
    "base-uri": "https://bpod1.ai-factory.fastweb.it/v1",
    "model": "Qwen/Qwen3.6-35B-A3B-FP8",
    "chat-model": "Qwen/Qwen3.6-35B-A3B-FP8",
    "vision": true
},
"fw_27001_gpt": {
    "auth_key": "YOUR-FASTWEB-GPT-JWT",
    "base-uri": "https://bpod1.ai-factory.fastweb.it/v1",
    "model": "openai/gpt-oss-120b",
    "chat-model": "openai/gpt-oss-120b",
    "vision": false
}
```

`base-uri` is optional (default in code); `vision` defaults to `false` when
absent. Which models actually accept images must be verified live with the
test script; the sample ships with `vision: true` only on Qwen as the most
likely candidate, to be corrected after the live test.

### 3.5 `src/Flussu/Test/test_fastweb_providers.php` (new)

Manual CLI test in the style of `test_ai_providers.php`:
- one chat round-trip per model (assert non-empty reply + token usage);
- one `analyzeMedia` attempt per model with a small test image, reporting
  which endpoints actually accept multimodal input (drives the final `vision`
  flag values);
- errors reported in English (project convention).

## 4. Data flow (unchanged paths)

`sendToAi` with platform 30/31/32 → `Platform::tryFrom()` →
`_applySmallModelGate()` (MIIA truncation/attachment block via config) →
`AiChatController::chat()` → `FlussuFastwebAi::chat()` → tuple back → marker
post-processing / history persistence exactly as for existing providers.
Image-generation or web-research requests never reach the class (resolver
redirect).

## 5. Error handling

- Missing/empty `auth_key` → constructor exception (same as all providers).
- HTTP/Guzzle failure → error tuple `[history, "Error: ...", usage]`, message
  in English, never a bare string.
- `analyzeMedia` on a `vision:false` block → error tuple stating the model
  does not accept media (defensive; `AiMediaController::canAnalyze()` should
  already prevent the call).

## 6. Out of scope

- Streaming, tool-use (`IAiProviderExtra`), reasoning/thinking toggles.
- Using these models as `flussu` (`inherit`) service-model backends.
- Any engine (Executor/Worker) change.

## 7. User follow-up after merge

In the real `/config/.services.json`: add the vendor prefix to the three
`model`/`chat-model` values and optionally set `vision` per block once the
live test says which models accept images.
