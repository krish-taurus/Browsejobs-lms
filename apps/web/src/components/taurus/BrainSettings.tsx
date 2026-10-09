"use client";

/* Workspace-scoped Taurus key management, shared by the owner admin and the client portal. */

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
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

const inputCls = "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";
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

/**
 * Taurus → Brain & voice. Keys go to the encrypted platform_settings store
 * via /api/v1/admin/taurus/brain and are never returned — the page only
 * ever sees "configured" and the last four characters.
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
      <div className="mx-auto max-w-3xl space-y-3">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="shimmer h-32 rounded-[14px]" />
        ))}
      </div>
    );
  }
  if (error || !data) {
    return (
      <div className="mx-auto max-w-3xl">
        <h1 className="display text-3xl text-ink">{heading}</h1>
        <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
          {error instanceof ApiError && (error.status === 403 || error.status === 404) ? "You don't have access to this workspace's keys." : "Couldn't load Taurus settings. Refresh to try again."}
        </p>
      </div>
    );
  }

  const active = data.brain.active;

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">{kicker}</p>
      <h1 className="display mt-2 text-3xl text-ink">{heading}</h1>
      <p className="mt-1 text-sm text-muted">
        {intro ??
          "Pick the language model Taurus thinks with, give it a voice, and connect your agents. Keys are encrypted on the server and never shown again; only the last four characters appear here."}
      </p>

      {notice && (
        <p className={`mt-4 rounded-[10px] px-3 py-2 text-sm ${notice.tone === "ok" ? "bg-verify-bg text-ink" : "bg-warn/10 text-warn"}`} role="status">
          {notice.text}
        </p>
      )}

      {/* ------------------------------------------------ which brain */}
      <section className="mt-6 rounded-[14px] border border-line bg-white p-5">
        <h2 className="display text-lg text-ink">Taurus brain</h2>
        <p className="mt-1 text-xs text-muted">
          {data.brain.allows_platform ? "“Platform default” uses whichever provider the rest of BrowseJobs uses. Choose a provider to give this workspace its own." : "Taurus thinks with one of the providers below. Add a key, test it, then choose it here."}
        </p>
        <div className="mt-3 rounded-[10px] border border-line bg-paper p-3 text-sm text-ink">
          {active ? (
            <>
              Taurus is <span className="font-semibold text-verify">live</span>, thinking with <span className="mono font-semibold">{active.provider}</span> ·{" "}
              <span className="mono">{active.model}</span>.
            </>
          ) : (
            <span className="text-warn">No brain yet. Add a key to any provider below, then choose it here.</span>
          )}
        </div>
        <BrainPicker allowPlatform={variant === "platform" || !!data.brain.allows_platform} data={data} onSaved={(b) => { refresh(b); setNotice({ tone: "ok", text: "Brain updated." }); }} onError={(t) => setNotice({ tone: "warn", text: t })} put={put} />
      </section>

      {/* ------------------------------------------------ providers */}
      <h2 className="display mt-8 text-xl text-ink">Model providers</h2>
      <div className="mt-3 grid gap-3 sm:grid-cols-2">
        {data.providers.map((p) => (
          <ProviderCard
            base={brainPath}
            key={p.id}
            provider={p}
            isBrain={active?.provider === p.id}
            put={put}
            onSaved={(b, text) => {
              refresh(b);
              setNotice({ tone: "ok", text });
            }}
            onError={(t) => setNotice({ tone: "warn", text: t })}
          />
        ))}
      </div>

      {/* ------------------------------------------------ voice */}
      <VoiceCard
        base={brainPath}
        speakPath={variant === "workspace" ? `${base}/speak` : null}
        voice={data.voice}
        put={put}
        onSaved={(b, text) => {
          refresh(b);
          setNotice({ tone: "ok", text });
        }}
        onError={(t) => setNotice({ tone: "warn", text: t })}
      />

      {/* ------------------------------------------------ bots */}
      {data.ingest && (
      <section className="mt-8 rounded-[14px] border border-line bg-white p-5">
        <h2 className="display text-lg text-ink">Connect your bots</h2>
        <p className="mt-1 text-xs text-muted">
          Each bot posts updates to the endpoint below with the bot token. Creating a new token replaces the old one, so update your bots after.
        </p>
        <dl className="mt-4 space-y-2 text-sm">
          <div className="flex flex-wrap justify-between gap-2">
            <dt className="text-muted">Endpoint</dt>
            <dd className="mono break-all text-ink">{data.ingest.endpoint}</dd>
          </div>
          <div className="flex flex-wrap justify-between gap-2">
            <dt className="text-muted">Bot token</dt>
            <dd className="mono text-ink">{data.ingest.configured ? `saved · ${data.ingest.mask}` : "Not created yet"}</dd>
          </div>
        </dl>
        {token && (
          <div className="mt-4 rounded-[10px] border border-verify/40 bg-verify-bg p-3">
            <p className="text-xs font-semibold text-ink">Copy this token now. It won&apos;t be shown again.</p>
            <div className="mt-2 flex gap-2">
              <input readOnly value={token} className={`${inputCls} mono`} onFocus={(e) => e.currentTarget.select()} aria-label="New bot token" />
              <button
                type="button"
                className="rounded-full bg-ink px-4 text-sm font-semibold text-white"
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
        <button
          type="button"
          onClick={() => rotate.mutate()}
          disabled={rotate.isPending}
          className="mt-4 rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50"
        >
          {rotate.isPending ? "Creating…" : data.ingest.configured ? "Create a new token" : "Create bot token"}
        </button>
        <details className="mt-4">
          <summary className="cursor-pointer text-sm font-semibold text-trust">Example request</summary>
          <pre className="mono mt-2 overflow-x-auto rounded-[10px] bg-ink p-4 text-[12px] leading-relaxed text-white">
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
    <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
      <label className="block">
        <span className="mb-1 block text-xs font-medium text-ink">Provider</span>
        <select id="taurus-brain-provider" value={provider} onChange={(e) => setProvider(e.target.value)} className={inputCls}>
          {allowPlatform && <option value="platform">Platform default</option>}
          {data.providers.map((p) => (
            <option key={p.id} value={p.id} disabled={!p.configured}>
              {p.label}
              {p.configured ? "" : " (no key)"}
            </option>
          ))}
        </select>
      </label>
      <label className="block">
        <span className="mb-1 block text-xs font-medium text-ink">Model (optional)</span>
        <input id="taurus-brain-model" value={model} onChange={(e) => setModel(e.target.value)} placeholder="Use the provider's model" className={`${inputCls} mono`} />
      </label>
      <button
        type="button"
        onClick={() => save.mutate()}
        disabled={save.isPending}
        className="rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
      >
        {save.isPending ? "Saving…" : "Use this brain"}
      </button>
    </div>
  );
}

function ProviderCard({
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
    <section className={`rounded-[14px] border bg-white p-4 ${isBrain ? "border-trust" : "border-line"}`}>
      <div className="flex items-start justify-between gap-2">
        <div>
          <h3 className="display text-base text-ink">{p.label}</h3>
          <p className="mt-0.5 text-[11px] text-muted">{KEY_HINTS[p.id] ?? "API key"}</p>
        </div>
        <span className={`mono shrink-0 rounded-full px-2.5 py-1 text-[10.5px] ${p.configured ? "bg-verify-bg text-verify" : "border border-line text-muted"}`}>
          {p.configured ? `key · ${p.mask}` : "no key"}
        </span>
      </div>
      {isBrain && <p className="mono mt-2 text-[10.5px] uppercase tracking-widest text-trust">Taurus brain</p>}
      {p.model && !open && <p className="mono mt-2 text-xs text-ink2">model · {p.model}</p>}
      {test && (
        <p className={`mt-2 text-xs ${test.ok ? "text-verify" : "text-warn"}`} role="status">
          {test.ok ? `Connected in ${test.latency_ms} ms.` : test.message}
        </p>
      )}
      {open ? (
        <div className="mt-3 space-y-2">
          <input
            id={`key-${p.id}`}
            type="password"
            value={key}
            onChange={(e) => setKey(e.target.value)}
            placeholder={p.configured ? "New key (leave blank to keep)" : "Paste API key"}
            autoComplete="off"
            className={`${inputCls} mono`}
            aria-label={`${p.label} API key`}
          />
          <input id={`model-${p.id}`} value={model} onChange={(e) => setModel(e.target.value)} placeholder="Model" className={`${inputCls} mono`} aria-label={`${p.label} model`} />
          {(p.needs_base_url || p.base_url) && (
            <input
              id={`base-${p.id}`}
              value={baseUrl}
              onChange={(e) => setBaseUrl(e.target.value)}
              placeholder={p.default_base_url ?? "https://…/v1"}
              className={`${inputCls} mono`}
              aria-label={`${p.label} base URL`}
            />
          )}
          <div className="flex gap-2">
            <button type="button" onClick={() => save.mutate()} disabled={save.isPending || (!p.configured && !key.trim())} className="rounded-full bg-trust px-4 py-1.5 text-sm font-semibold text-white disabled:opacity-50">
              {save.isPending ? "Saving…" : "Save"}
            </button>
            <button type="button" onClick={() => setOpen(false)} className="rounded-full px-3 py-1.5 text-sm text-muted hover:text-ink">
              Cancel
            </button>
          </div>
        </div>
      ) : (
        <div className="mt-3 flex flex-wrap gap-2">
          <button type="button" onClick={() => setOpen(true)} className="rounded-full border border-line px-3 py-1.5 text-sm font-semibold text-ink hover:border-trust">
            {p.configured ? "Edit" : "Add key"}
          </button>
          {p.configured && (
            <>
              <button type="button" onClick={() => runTest.mutate()} disabled={runTest.isPending} className="rounded-full border border-line px-3 py-1.5 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50">
                {runTest.isPending ? "Testing…" : "Test connection"}
              </button>
              <ConfirmRemove label={p.label} busy={remove.isPending} onConfirm={() => remove.mutate()} />
            </>
          )}
        </div>
      )}
    </section>
  );
}

function ConfirmRemove({ label, busy, onConfirm }: { label: string; busy: boolean; onConfirm: () => void }) {
  const [asking, setAsking] = useState(false);
  if (!asking)
    return (
      <button type="button" onClick={() => setAsking(true)} className="rounded-full px-3 py-1.5 text-sm text-muted hover:text-warn">
        Remove
      </button>
    );
  return (
    <span className="flex items-center gap-2 text-xs text-ink">
      Remove the {label} key?
      <button type="button" onClick={onConfirm} disabled={busy} className="rounded-full bg-warn px-3 py-1 font-semibold text-white disabled:opacity-50">
        {busy ? "Removing…" : "Remove"}
      </button>
      <button type="button" onClick={() => setAsking(false)} className="text-muted hover:text-ink">
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
    <section className="mt-8 rounded-[14px] border border-line bg-white p-5">
      <div className="flex items-start justify-between gap-2">
        <div>
          <h2 className="display text-lg text-ink">Voice · ElevenLabs</h2>
          <p className="mt-1 text-xs text-muted">elevenlabs.io → Profile → API keys. The voice ID is under Voices → your voice → ID. </p>
        </div>
        <span className={`mono shrink-0 rounded-full px-2.5 py-1 text-[10.5px] ${voice.configured ? "bg-verify-bg text-verify" : "border border-line text-muted"}`}>
          {voice.configured ? `key · ${voice.mask}` : "no key"}
        </span>
      </div>
      <div className="mt-4 grid gap-3 sm:grid-cols-3">
        <label className="block sm:col-span-3">
          <span className="mb-1 block text-xs font-medium text-ink">API key</span>
          <input id="voice-key" type="password" value={key} onChange={(e) => setKey(e.target.value)} placeholder={voice.configured ? "New key (leave blank to keep)" : "Paste ElevenLabs API key"} autoComplete="off" className={`${inputCls} mono`} />
        </label>
        <label className="block sm:col-span-2">
          <span className="mb-1 block text-xs font-medium text-ink">Voice ID</span>
          <input id="voice-id" value={voiceId} onChange={(e) => setVoiceId(e.target.value)} placeholder="e.g. 21m00Tcm4TlvDq8ikWAM" className={`${inputCls} mono`} />
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium text-ink">Model</span>
          <input id="voice-model" value={model} onChange={(e) => setModel(e.target.value)} placeholder="eleven_turbo_v2_5" className={`${inputCls} mono`} />
        </label>
      </div>
      {test && <p className={`mt-3 text-xs ${test.ok ? "text-verify" : "text-warn"}`}>{test.ok ? `Connected in ${test.latency_ms} ms.` : test.message}</p>}
      <div className="mt-4 flex flex-wrap gap-2">
        <button type="button" onClick={() => save.mutate()} disabled={save.isPending} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {save.isPending ? "Saving…" : "Save voice"}
        </button>
        {voice.configured && (
          <>
            <button type="button" onClick={() => runTest.mutate()} disabled={runTest.isPending} className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50">
              {runTest.isPending ? "Testing…" : "Test connection"}
            </button>
            {speakPath && <button type="button" onClick={() => void hear()} disabled={playing} className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50">
              {playing ? "Playing…" : "Hear a sample"}
            </button>}
            <ConfirmRemove label="ElevenLabs" busy={remove.isPending} onConfirm={() => remove.mutate()} />
          </>
        )}
      </div>
    </section>
  );
}
