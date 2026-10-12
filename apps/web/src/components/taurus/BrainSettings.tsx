"use client";

/* Workspace-scoped Taurus key management, shared by the owner admin and the client portal. */

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import "@/components/ap/pages/taurus-ui.css";
import { ApiError, apiJson, apiPostBlob } from "@/lib/api";

type Provider = {
  id: string;
  label: string;
  configured: boolean;
  mask: string | null;
  model: string | null;
  base_url: string | null;
  default_base_url: string | null;
  needs_base_url: boolean;
};
type Brain = {
  brain: { provider: string | null; model: string | null; active: { provider: string; model: string; source?: "workspace" | "platform" } | null; allows_platform?: boolean };
  providers: Provider[];
  voice: { configured: boolean; source?: "workspace" | "platform" | null; mask: string | null; voice_id: string | null; model: string | null };
  ingest?: { configured: boolean; mask: string | null; endpoint: string };
};
type TestResult = { ok: boolean; message: string; latency_ms: number };

const KEY_HINTS: Record<string, string> = {
  anthropic: "console.anthropic.com → API keys",
  openai: "platform.openai.com → API keys",
  gemini: "aistudio.google.com → Get API key",
  kimi: "platform.moonshot.ai → API keys",
  deepseek: "platform.deepseek.com → API keys",
  grok: "console.x.ai → API keys",
  groq: "console.groq.com → API keys",
  custom: "Any OpenAI-compatible endpoint (base URL required)",
};

const errText = (e: unknown, fallback: string) => (e instanceof ApiError ? (e.firstError ?? e.message) : fallback);

/** "key · …a91f" when a key is saved, otherwise "No key". The key itself is never sent to the browser. */
function KeyBadge({ configured, mask }: { configured: boolean; mask: string | null }) {
  return configured ? <span className="tx-badge tx-badge--ok tx-num">key · {mask}</span> : <span className="tx-badge">No key</span>;
}

/**
 * Taurus → Brain & voice. Keys go to the encrypted platform_settings store
 * via /api/v1/admin/taurus/brain and are never returned — the page only
 * ever sees "configured" and the last four characters.
 * Styled as Apple Settings-style grouped lists (components/ap/pages/taurus-ui.css).
 */
export function BrainSettings({
  base,
  heading = "Brain & voice",
  kicker = "Taurus AI",
  variant = "workspace",
  intro,
}: {
  base: string;
  heading?: string;
  kicker?: string;
  /** "platform" edits the BrowseJobs recruitment brain (no agent token, no voice sample). */
  variant?: "workspace" | "platform";
  intro?: string;
}) {
  const brainPath = variant === "platform" ? base : `${base}/brain`;
  const qc = useQueryClient();
  const { data, isLoading, error } = useQuery({
    queryKey: ["taurus", base, "brain"],
    queryFn: () => apiJson<{ data: Brain }>(brainPath).then((r) => r.data),
  });
  const [notice, setNotice] = useState<{ tone: "ok" | "warn"; text: string } | null>(null);
  const [token, setToken] = useState<string | null>(null);

  const refresh = (b: Brain) => qc.setQueryData(["taurus", base, "brain"], b);
  const put = (body: unknown) => apiJson<{ data: Brain }>(brainPath, { method: "PUT", body: JSON.stringify(body) }).then((r) => r.data);

  const rotate = useMutation({
    mutationFn: () => apiJson<{ data: { token: string; endpoint: string } }>(`${base}/ingest-token`, { method: "POST", body: "{}" }),
    onSuccess: (r) => {
      setToken(r.data.token);
      void qc.invalidateQueries({ queryKey: ["taurus", base, "brain"] });
    },
    onError: (e) => setNotice({ tone: "warn", text: errText(e, "Couldn't create a token.") }),
  });

  if (isLoading) {
    return (
      <div className="tx-narrow" aria-busy="true">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="shimmer tx-skel" style={{ height: 120, marginTop: i ? 14 : 0 }} />
        ))}
      </div>
    );
  }
  if (error || !data) {
    return (
      <div className="tx-narrow">
        <h1 className="tx-title">{heading}</h1>
        <p className="tx-note tx-note--warn">
          {error instanceof ApiError && (error.status === 403 || error.status === 404) ? "You don't have access to this workspace's keys." : "Couldn't load Taurus settings. Refresh to try again."}
        </p>
      </div>
    );
  }

  const active = data.brain.active;
  const onSaved = (b: Brain, text: string) => {
    refresh(b);
    setNotice({ tone: "ok", text });
  };
  const onError = (t: string) => setNotice({ tone: "warn", text: t });

  return (
    <div className="tx-narrow">
      <p className="tx-eyebrow">{kicker}</p>
      <h1 className="tx-title">{heading}</h1>
      <p className="tx-sub">
        {intro ??
          "Pick the language model Taurus thinks with, give it a voice, and connect your agents. Keys are encrypted on the server and never shown again; only the last four characters appear here."}
      </p>

      {notice && (
        <p className={`tx-note ${notice.tone === "ok" ? "tx-note--ok" : "tx-note--warn"}`} role="status">
          {notice.text}
        </p>
      )}

      {/* ------------------------------------------------ which brain */}
      <section className="tx-section">
        <div className="tx-section-head">
          <h2 className="tx-h2">Taurus brain</h2>
          <p className="tx-section-note">
            {data.brain.allows_platform ? "“Platform default” uses whichever provider the rest of BrowseJobs uses. Choose a provider to give this workspace its own." : "Taurus thinks with one of the providers below. Add a key, test it, then choose it here."}
          </p>
        </div>
        <div className="tx-group">
          <div className="tx-row">
            <span className="tx-row-label">Status</span>
            {active ? (
              <span className="tx-row-value" style={{ display: "inline-flex", alignItems: "center", gap: 8 }}>
                <span className="tx-dot tx-dot--ok" aria-hidden="true" />
                <span>
                  Taurus is <b style={{ color: "var(--tx-green)" }}>live</b>, thinking with <span className="tx-mono">{active.provider}</span> ·{" "}
                  <span className="tx-mono">{active.model}</span>.
                </span>
              </span>
            ) : (
              <span className="tx-row-value tx-inline-warn">No brain yet. Add a key to any provider below, then choose it here.</span>
            )}
          </div>
          <div className="tx-row tx-row--block">
            <BrainPicker allowPlatform={variant === "platform" || !!data.brain.allows_platform} data={data} onSaved={(b) => onSaved(b, "Brain updated.")} onError={onError} put={put} />
          </div>
        </div>
      </section>

      {/* ------------------------------------------------ providers */}
      <section className="tx-section">
        <div className="tx-section-head">
          <h2 className="tx-h2">Model providers</h2>
          <p className="tx-section-note">Add a key for any provider you want Taurus to use. Only the last four characters are ever shown.</p>
        </div>
        <div className="tx-group">
          {data.providers.map((p) => (
            <ProviderRow base={brainPath} key={p.id} provider={p} isBrain={active?.provider === p.id} put={put} onSaved={onSaved} onError={onError} />
          ))}
        </div>
      </section>

      {/* ------------------------------------------------ voice */}
      <VoiceCard base={brainPath} speakPath={variant === "workspace" ? `${base}/speak` : null} voice={data.voice} put={put} onSaved={onSaved} onError={onError} />

      {/* ------------------------------------------------ bots */}
      {data.ingest && (
        <section className="tx-section">
          <div className="tx-section-head">
            <h2 className="tx-h2">Connect your bots</h2>
            <p className="tx-section-note">
              Each bot posts updates to the endpoint below with the bot token. Creating a new token replaces the old one, so update your bots after.
            </p>
          </div>
          <div className="tx-group">
            <dl style={{ margin: 0 }}>
              <div className="tx-row">
                <dt className="tx-row-label">Endpoint</dt>
                <dd className="tx-row-value tx-mono" style={{ margin: 0 }}>
                  {data.ingest.endpoint}
                </dd>
              </div>
              <div className="tx-row">
                <dt className="tx-row-label">Bot token</dt>
                <dd className="tx-row-value" style={{ margin: 0 }}>
                  {data.ingest.configured ? <span className="tx-badge tx-badge--ok tx-num">saved · {data.ingest.mask}</span> : <span className="tx-badge">Not created yet</span>}
                </dd>
              </div>
            </dl>
            <div className="tx-row tx-row--block">
              {token && (
                <div className="tx-reveal" style={{ marginTop: 0, marginBottom: 14 }}>
                  <p>Copy this token now. It won&apos;t be shown again.</p>
                  <div className="tx-copy">
                    <input readOnly value={token} className="tx-input tx-mono" onFocus={(e) => e.currentTarget.select()} aria-label="New bot token" />
                    <button
                      type="button"
                      className="tx-btn tx-btn-primary"
                      onClick={() => {
                        navigator.clipboard?.writeText(token).then(
                          () => setNotice({ tone: "ok", text: "Token copied." }),
                          () => setNotice({ tone: "warn", text: "Copy blocked by the browser. Select the token and copy it." }),
                        );
                      }}
                    >
                      Copy
                    </button>
                  </div>
                </div>
              )}
              <div className="tx-actions">
                <button type="button" onClick={() => rotate.mutate()} disabled={rotate.isPending} className="tx-btn tx-btn-secondary">
                  {rotate.isPending ? "Creating…" : data.ingest.configured ? "Create a new token" : "Create bot token"}
                </button>
              </div>
            </div>
            <div className="tx-row tx-row--block">
              <details className="tx-disclosure">
                <summary>Example request</summary>
                <pre className="tx-code">
{`curl -X POST ${data.ingest.endpoint} \\
  -H "Authorization: Bearer $TAURUS_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{"events":[{"agent":{"slug":"linkedin","name":"LinkedIn","zone":"Content & Marketing"},
       "status":"needs","task":{"ref":"post-0412","title":"Carousel: 7 lessons",
       "status":"needs_approval","approval":{"action":"Publish the carousel","risk":"high"}}}]}'

# then poll for your decision:
curl "${data.ingest.endpoint.replace(/ingest$/, "decisions")}?since=2026-01-01T00:00:00Z" \\
  -H "Authorization: Bearer $TAURUS_TOKEN"`}
                </pre>
              </details>
            </div>
          </div>
        </section>
      )}
    </div>
  );
}

function BrainPicker({ allowPlatform, data, put, onSaved, onError }: { allowPlatform: boolean; data: Brain; put: (b: unknown) => Promise<Brain>; onSaved: (b: Brain) => void; onError: (t: string) => void }) {
  const [provider, setProvider] = useState(data.brain.provider ?? (allowPlatform ? "platform" : (data.providers.find((p) => p.configured)?.id ?? "")));
  const [model, setModel] = useState(data.brain.model ?? "");
  const save = useMutation({
    mutationFn: () => put({ brain_provider: provider, brain_model: model }),
    onSuccess: onSaved,
    onError: (e) => onError(errText(e, "Couldn't save the brain.")),
  });
  return (
    <div className="tx-fields tx-fields--brain">
      <div>
        <label className="tx-label" htmlFor="taurus-brain-provider">
          Provider
        </label>
        <select id="taurus-brain-provider" value={provider} onChange={(e) => setProvider(e.target.value)} className="tx-select tx-select--field">
          {allowPlatform && <option value="platform">Platform default</option>}
          {data.providers.map((p) => (
            <option key={p.id} value={p.id} disabled={!p.configured}>
              {p.label}
              {p.configured ? "" : " (no key)"}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="tx-label" htmlFor="taurus-brain-model">
          Model (optional)
        </label>
        <input id="taurus-brain-model" value={model} onChange={(e) => setModel(e.target.value)} placeholder="Use the provider's model" className="tx-input tx-mono" />
      </div>
      <button type="button" onClick={() => save.mutate()} disabled={save.isPending} className="tx-btn tx-btn-primary" style={{ minHeight: 44 }}>
        {save.isPending ? "Saving…" : "Use this brain"}
      </button>
    </div>
  );
}

function ProviderRow({
  base,
  provider: p,
  isBrain,
  put,
  onSaved,
  onError,
}: {
  base: string;
  provider: Provider;
  isBrain: boolean;
  put: (b: unknown) => Promise<Brain>;
  onSaved: (b: Brain, text: string) => void;
  onError: (t: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const [key, setKey] = useState("");
  const [model, setModel] = useState(p.model ?? "");
  const [baseUrl, setBaseUrl] = useState(p.base_url ?? "");
  const [test, setTest] = useState<TestResult | null>(null);

  const save = useMutation({
    mutationFn: () => {
      const body: Record<string, string> = { model };
      if (key.trim()) body.api_key = key.trim();
      if (p.needs_base_url || baseUrl !== (p.base_url ?? "")) body.base_url = baseUrl;
      return put({ providers: { [p.id]: body } });
    },
    onSuccess: (b) => {
      setKey("");
      setOpen(false);
      onSaved(b, `${p.label} saved.`);
    },
    onError: (e) => onError(errText(e, `Couldn't save ${p.label}.`)),
  });
  const remove = useMutation({
    mutationFn: () => apiJson<{ data: Brain }>(`${base}/providers/${p.id}`, { method: "DELETE" }).then((r) => r.data),
    onSuccess: (b) => onSaved(b, `${p.label} key removed.`),
    onError: (e) => onError(errText(e, `Couldn't remove ${p.label}.`)),
  });
  const runTest = useMutation({
    mutationFn: () => apiJson<{ data: TestResult }>(`${base}/test`, { method: "POST", body: JSON.stringify({ target: p.id }) }).then((r) => r.data),
    onSuccess: setTest,
    onError: (e) => setTest({ ok: false, message: errText(e, "Test failed."), latency_ms: 0 }),
  });

  return (
    <div className="tx-row">
      <div className="tx-row-main">
        <h3 className="tx-row-label">
          {p.label}
          {isBrain && <span className="tx-badge tx-badge--blue">Taurus brain</span>}
        </h3>
        <p className="tx-row-sub">{KEY_HINTS[p.id] ?? "API key"}</p>
        {p.model && !open && (
          <p className="tx-row-sub">
            Model · <span className="tx-mono">{p.model}</span>
          </p>
        )}
        {test && (
          <p className={`tx-row-sub ${test.ok ? "tx-inline-ok" : "tx-inline-warn"}`} role="status">
            {test.ok ? `Connected in ${test.latency_ms} ms.` : test.message}
          </p>
        )}
      </div>
      <KeyBadge configured={p.configured} mask={p.mask} />
      {open ? (
        <div className="tx-span-all" style={{ flexBasis: "100%" }}>
          <div className="tx-fields">
            <input
              id={`key-${p.id}`}
              type="password"
              value={key}
              onChange={(e) => setKey(e.target.value)}
              placeholder={p.configured ? "New key (leave blank to keep)" : "Paste API key"}
              autoComplete="off"
              className="tx-input tx-mono"
              aria-label={`${p.label} API key`}
            />
            <input id={`model-${p.id}`} value={model} onChange={(e) => setModel(e.target.value)} placeholder="Model" className="tx-input tx-mono" aria-label={`${p.label} model`} />
            {(p.needs_base_url || p.base_url) && (
              <input
                id={`base-${p.id}`}
                value={baseUrl}
                onChange={(e) => setBaseUrl(e.target.value)}
                placeholder={p.default_base_url ?? "https://…/v1"}
                className="tx-input tx-mono"
                aria-label={`${p.label} base URL`}
              />
            )}
            <div className="tx-actions">
              <button type="button" onClick={() => save.mutate()} disabled={save.isPending || (!p.configured && !key.trim())} className="tx-btn tx-btn-primary tx-btn-sm">
                {save.isPending ? "Saving…" : "Save"}
              </button>
              <button type="button" onClick={() => setOpen(false)} className="tx-btn tx-btn-quiet tx-btn-sm">
                Cancel
              </button>
            </div>
          </div>
        </div>
      ) : (
        <div className="tx-actions" style={{ flexBasis: "100%" }}>
          <button type="button" onClick={() => setOpen(true)} className="tx-btn tx-btn-secondary tx-btn-sm">
            {p.configured ? "Edit" : "Add key"}
          </button>
          {p.configured && (
            <>
              <button type="button" onClick={() => runTest.mutate()} disabled={runTest.isPending} className="tx-btn tx-btn-secondary tx-btn-sm">
                {runTest.isPending ? "Testing…" : "Test connection"}
              </button>
              <ConfirmRemove label={p.label} busy={remove.isPending} onConfirm={() => remove.mutate()} />
            </>
          )}
        </div>
      )}
    </div>
  );
}

function ConfirmRemove({ label, busy, onConfirm }: { label: string; busy: boolean; onConfirm: () => void }) {
  const [asking, setAsking] = useState(false);
  if (!asking)
    return (
      <button type="button" onClick={() => setAsking(true)} className="tx-btn tx-btn-danger tx-btn-sm">
        Remove
      </button>
    );
  return (
    <span className="tx-actions" style={{ fontSize: 13 }}>
      Remove the {label} key?
      <button type="button" onClick={onConfirm} disabled={busy} className="tx-btn tx-btn-danger-fill tx-btn-sm">
        {busy ? "Removing…" : "Remove"}
      </button>
      <button type="button" onClick={() => setAsking(false)} className="tx-btn tx-btn-quiet tx-btn-sm">
        Keep
      </button>
    </span>
  );
}

function VoiceCard({
  base,
  speakPath,
  voice,
  put,
  onSaved,
  onError,
}: {
  base: string;
  speakPath: string | null;
  voice: Brain["voice"];
  put: (b: unknown) => Promise<Brain>;
  onSaved: (b: Brain, text: string) => void;
  onError: (t: string) => void;
}) {
  const [key, setKey] = useState("");
  const [voiceId, setVoiceId] = useState(voice.voice_id ?? "");
  const [model, setModel] = useState(voice.model ?? "");
  const [test, setTest] = useState<TestResult | null>(null);
  const [playing, setPlaying] = useState(false);

  const save = useMutation({
    mutationFn: () => {
      const body: Record<string, string> = { voice_id: voiceId, model };
      if (key.trim()) body.api_key = key.trim();
      return put({ voice: body });
    },
    onSuccess: (b) => {
      setKey("");
      onSaved(b, "Voice saved.");
    },
    onError: (e) => onError(errText(e, "Couldn't save the voice.")),
  });
  const runTest = useMutation({
    mutationFn: () => apiJson<{ data: TestResult }>(`${base}/test`, { method: "POST", body: JSON.stringify({ target: "elevenlabs" }) }).then((r) => r.data),
    onSuccess: setTest,
    onError: (e) => setTest({ ok: false, message: errText(e, "Test failed."), latency_ms: 0 }),
  });
  const remove = useMutation({
    mutationFn: () => apiJson<{ data: Brain }>(`${base}/providers/elevenlabs`, { method: "DELETE" }).then((r) => r.data),
    onSuccess: (b) => onSaved(b, "Voice key removed."),
    onError: (e) => onError(errText(e, "Couldn't remove the voice key.")),
  });

  const hear = async () => {
    setPlaying(true);
    try {
      if (!speakPath) return;
      const blob = await apiPostBlob(speakPath, { text: "Taurus online. Three agents are working and one needs your approval." });
      if (!blob || blob.size === 0) {
        onError("No ElevenLabs voice yet, so Taurus will use the browser's voice.");
        setPlaying(false);
        return;
      }
      const url = URL.createObjectURL(blob);
      const a = new Audio(url);
      a.onended = a.onerror = () => {
        setPlaying(false);
        URL.revokeObjectURL(url);
      };
      await a.play();
    } catch (e) {
      onError(errText(e, "Couldn't play the voice sample."));
      setPlaying(false);
    }
  };

  return (
    <section className="tx-section">
      <div className="tx-section-head">
        <h2 className="tx-h2">Voice</h2>
        <p className="tx-section-note">elevenlabs.io → Profile → API keys. The voice ID is under Voices → your voice → ID.</p>
      </div>
      <div className="tx-group">
        <div className="tx-row">
          <span className="tx-row-label">ElevenLabs</span>
          <KeyBadge configured={voice.configured} mask={voice.mask} />
        </div>
        <div className="tx-row tx-row--block">
          <div className="tx-fields tx-fields--3">
            <div className="tx-span-all">
              <label className="tx-label" htmlFor="voice-key">
                API key
              </label>
              <input
                id="voice-key"
                type="password"
                value={key}
                onChange={(e) => setKey(e.target.value)}
                placeholder={voice.configured ? "New key (leave blank to keep)" : "Paste ElevenLabs API key"}
                autoComplete="off"
                className="tx-input tx-mono"
              />
            </div>
            <div className="tx-span-2">
              <label className="tx-label" htmlFor="voice-id">
                Voice ID
              </label>
              <input id="voice-id" value={voiceId} onChange={(e) => setVoiceId(e.target.value)} placeholder="e.g. 21m00Tcm4TlvDq8ikWAM" className="tx-input tx-mono" />
            </div>
            <div>
              <label className="tx-label" htmlFor="voice-model">
                Model
              </label>
              <input id="voice-model" value={model} onChange={(e) => setModel(e.target.value)} placeholder="eleven_turbo_v2_5" className="tx-input tx-mono" />
            </div>
          </div>
          {test && (
            <p className={`tx-row-sub ${test.ok ? "tx-inline-ok" : "tx-inline-warn"}`} style={{ marginTop: 12 }} role="status">
              {test.ok ? `Connected in ${test.latency_ms} ms.` : test.message}
            </p>
          )}
          <div className="tx-actions" style={{ marginTop: 16 }}>
            <button type="button" onClick={() => save.mutate()} disabled={save.isPending} className="tx-btn tx-btn-primary">
              {save.isPending ? "Saving…" : "Save voice"}
            </button>
            {voice.configured && (
              <>
                <button type="button" onClick={() => runTest.mutate()} disabled={runTest.isPending} className="tx-btn tx-btn-secondary">
                  {runTest.isPending ? "Testing…" : "Test connection"}
                </button>
                {speakPath && (
                  <button type="button" onClick={() => void hear()} disabled={playing} className="tx-btn tx-btn-secondary">
                    {playing ? "Playing…" : "Hear a sample"}
                  </button>
                )}
                <ConfirmRemove label="ElevenLabs" busy={remove.isPending} onConfirm={() => remove.mutate()} />
              </>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
