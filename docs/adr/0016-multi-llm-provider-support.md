# ADR 0016 — Multi-LLM provider support in the AI gateway

- **Status:** Accepted
- **Date:** 2026-07-16
- **Context:** Founder request: the AI layer must not be locked to Anthropic —
  OpenAI, Kimi (Moonshot), DeepSeek, Grok (xAI), etc. must be selectable.

## Decision

The `AiClient` transport behind the P3.1 `AiGateway` is now provider-switchable
via `AI_PROVIDER` with a registry in `config/ai.php`:

- **Drivers, not per-vendor clients:** two drivers cover everything —
  `anthropic` (Messages API) and `openai_compatible` (chat-completions
  dialect). OpenAI, Kimi, DeepSeek and Grok all speak the latter, so each is
  just `{api_key, base_url, model}` config. A `custom` entry accepts any other
  OpenAI-compatible endpoint (Ollama, Together, vLLM…). **Adding a vendor is
  config, not code.**
- Per-call `AiMessage->model` overrides still work on every driver.
- Budget enforcement, `ai_events` logging (purpose/model/tokens/cost/latency),
  and the human-approval flows are in the gateway, so they apply identically to
  every vendor. Consumers never see the provider.
- Default model names in config are env-overridable placeholders — set the
  exact model id per vendor in `.env` (`OPENAI_MODEL`, `KIMI_MODEL`,
  `DEEPSEEK_MODEL`, `GROK_MODEL`).

## Consequences

- Switching vendors is a two-line `.env` change (`AI_PROVIDER` + that vendor's
  API key); no consumer code changes.
- `ai_events.cost` uses the global `ai.pricing` micro-rupee rates; when running
  a non-Anthropic provider, set `AI_INPUT_MICROS_PER_MTOK` /
  `AI_OUTPUT_MICROS_PER_MTOK` to that vendor's pricing so margin tracking stays
  honest. Per-provider pricing tables can follow if providers are mixed
  concurrently.
- Provider selection is global (per deployment). Per-tenant or per-purpose
  provider routing would extend the same registry if ever needed.
