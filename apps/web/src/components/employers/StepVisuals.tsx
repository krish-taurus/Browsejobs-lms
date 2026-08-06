import { InkLabel } from "@/components/employers/ui";
import type { Step } from "@/content/employers";

/**
 * The seven micro-visuals behind the walkthrough. Each is a still of a real
 * screen in the employer portal, rebuilt in tokens — not a screenshot, so it
 * stays legible at any width and flips with the theme.
 *
 * They are intentionally static: the walkthrough already animates on step
 * change, and a second layer of motion inside the frame would compete with
 * the copy for attention.
 */

function Frame({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between">
        <InkLabel>{label}</InkLabel>
        <span aria-hidden className="flex gap-1.5">
          <span className="h-1.5 w-1.5 rounded-full bg-white/15" />
          <span className="h-1.5 w-1.5 rounded-full bg-white/15" />
          <span className="h-1.5 w-1.5 rounded-full bg-white/15" />
        </span>
      </div>
      <div className="mt-5 flex-1">{children}</div>
    </div>
  );
}

function Row({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return (
    <div
      className={`rounded-2xl border border-white/[0.08] bg-white/[0.03] px-4 py-3 ${className}`}
    >
      {children}
    </div>
  );
}

function Chip({ children, tone = "muted" }: { children: React.ReactNode; tone?: "muted" | "trust" | "employer" | "verify" }) {
  const skin =
    tone === "trust"
      ? "border-trust/40 text-trust"
      : tone === "employer"
        ? "border-employer/50 text-employer"
        : tone === "verify"
          ? "border-verify/40 text-verify"
          : "border-white/12 text-white/50";
  return (
    <span className={`mono rounded-full border px-2.5 py-1 text-[10px] ${skin}`}>{children}</span>
  );
}

function Meter({ pct, tone = "trust" }: { pct: number; tone?: "trust" | "employer" | "verify" }) {
  const bar = tone === "employer" ? "bg-employer" : tone === "verify" ? "bg-verify" : "bg-trust";
  return (
    <span className="block h-1.5 overflow-hidden rounded-full bg-white/[0.07]">
      <span className={`block h-full rounded-full ${bar}`} style={{ width: `${pct}%` }} />
    </span>
  );
}

/* ------------------------------------------------------------------ 01 */

function WorkspaceVisual() {
  const members = [
    { name: "You", role: "Owner", tone: "trust" as const },
    { name: "Divya P.", role: "Recruiter", tone: "employer" as const },
    { name: "Karthik S.", role: "Hiring manager", tone: "muted" as const },
  ];
  return (
    <Frame label="Workspace">
      <Row className="!px-5 !py-4">
        <p className="display text-lg text-white">Meridian Logistics</p>
        <p className="mono mt-1 text-[10px] uppercase tracking-[0.12em] text-white/35">
          Logistics · 201–500 people
        </p>
      </Row>
      <ul className="mt-3 space-y-2">
        {members.map((m) => (
          <li key={m.name}>
            <Row>
              <div className="flex items-center justify-between gap-3">
                <span className="text-[13px] text-white/80">{m.name}</span>
                <Chip tone={m.tone}>{m.role}</Chip>
              </div>
            </Row>
          </li>
        ))}
      </ul>
      <p className="mt-4 text-[11px] leading-relaxed text-white/35">
        A recruiter can move the pipeline. A hiring manager reads it. Neither can see another
        company&apos;s workspace.
      </p>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 02 */

function JdVisual() {
  return (
    <Frame label="JD drafter">
      <Row>
        <p className="mono text-[10px] uppercase tracking-[0.12em] text-white/35">Title</p>
        <p className="mt-1 text-[15px] font-semibold text-white">Data Engineer</p>
      </Row>
      <div className="mt-3">
        <p className="mono text-[10px] uppercase tracking-[0.12em] text-white/35">
          Suggested core skills
        </p>
        <div className="mt-2 flex flex-wrap gap-1.5">
          {["SQL", "Python", "Airflow", "dbt", "Warehousing"].map((s) => (
            <Chip key={s} tone="trust">
              {s}
            </Chip>
          ))}
        </div>
      </div>
      <div className="mt-4">
        <p className="mono text-[10px] uppercase tracking-[0.12em] text-white/35">Optional</p>
        <div className="mt-2 flex flex-wrap gap-1.5">
          {["Spark", "Kafka", "Terraform"].map((s) => (
            <Chip key={s}>{s}</Chip>
          ))}
        </div>
      </div>
      <Row className="mt-4">
        <p className="text-[12px] leading-relaxed text-white/55">
          &ldquo;Own the batch pipelines that move operations data into the warehouse, and the
          models the ops dashboards read from…&rdquo;
        </p>
        <p className="mono mt-2 text-[10px] uppercase tracking-[0.12em] text-white/30">
          Drafted · edit before publishing
        </p>
      </Row>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 03 */

function MockVisual() {
  const weights = [
    { label: "SQL and data modelling", pct: 35 },
    { label: "Python for data", pct: 25 },
    { label: "Pipeline design", pct: 25 },
    { label: "Communication", pct: 15 },
  ];
  return (
    <Frame label="Assessment designer">
      <ul className="space-y-3.5">
        {weights.map((w) => (
          <li key={w.label}>
            <div className="flex items-baseline justify-between gap-3">
              <span className="text-[12px] text-white/75">{w.label}</span>
              <span className="mono text-[11px] text-white/50">{w.pct}%</span>
            </div>
            {/* The bar is the weight, unscaled — stretching 35% across most of
                the track to "fill the space" would misread the chart. */}
            <div className="mt-1.5">
              <Meter pct={w.pct} tone="employer" />
            </div>
          </li>
        ))}
      </ul>
      <Row className="mt-5">
        <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
          <span className="mono text-[11px] text-white/50">
            12 <span className="text-white/30">questions</span>
          </span>
          <span className="mono text-[11px] text-white/50">
            60% <span className="text-white/30">scenario</span>
          </span>
          <span className="mono text-[11px] text-white/50">
            40% <span className="text-white/30">short answer</span>
          </span>
        </div>
      </Row>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 04 */

function RoundsVisual() {
  const rounds = [
    { n: "R1", name: "Screening interview", kind: "AI interview", window: "48h", auto: "auto ≥ 70" },
    { n: "R2", name: "SQL depth", kind: "MCQ", window: "24h", auto: "auto ≥ 75" },
    { n: "R3", name: "Team conversation", kind: "Human", window: "—", auto: "manual" },
  ];
  return (
    <Frame label="Interview process">
      <ul className="space-y-2.5">
        {rounds.map((r) => (
          <li key={r.n}>
            <Row>
              <div className="flex items-center gap-3">
                <span className="mono text-[11px] text-white/30">{r.n}</span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-[13px] font-semibold text-white">{r.name}</p>
                  <p className="mono mt-0.5 text-[10px] uppercase tracking-[0.1em] text-white/35">
                    {r.kind} · window {r.window}
                  </p>
                </div>
                <Chip tone={r.auto === "manual" ? "muted" : "verify"}>{r.auto}</Chip>
              </div>
            </Row>
          </li>
        ))}
      </ul>
      <p className="mt-4 text-[11px] leading-relaxed text-white/35">
        A round already sat can be switched off, never deleted — the evidence stays.
      </p>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 05 */

function PublishVisual() {
  const pool = [
    { name: "Rahul M.", match: 92 },
    { name: "Sneha K.", match: 84 },
    { name: "Imran S.", match: 71 },
  ];
  return (
    <Frame label="Published · talent pool">
      <Row className="!px-5 !py-4">
        <div className="flex items-center justify-between gap-3">
          <div>
            <p className="text-[14px] font-semibold text-white">Data Engineer</p>
            <p className="mono mt-1 text-[10px] uppercase tracking-[0.12em] text-white/35">
              Bengaluru · 2–4 yrs · 3 openings
            </p>
          </div>
          <Chip tone="verify">live</Chip>
        </div>
      </Row>
      <p className="mono mt-4 text-[10px] uppercase tracking-[0.12em] text-white/35">
        Matched from the trained pool
      </p>
      <ul className="mt-2 space-y-2">
        {pool.map((p) => (
          <li key={p.name}>
            <Row>
              <div className="flex items-center justify-between gap-3">
                <span className="text-[13px] text-white/80">{p.name}</span>
                <span className="flex items-center gap-3">
                  <span className="mono text-[12px] text-white/60">{p.match}% match</span>
                  <Chip tone="trust">invite</Chip>
                </span>
              </div>
            </Row>
          </li>
        ))}
      </ul>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 06 */

function GradeVisual() {
  const dims = [
    { label: "SQL and data modelling", pct: 88, tone: "verify" as const },
    { label: "Python for data", pct: 81, tone: "verify" as const },
    { label: "Pipeline design", pct: 74, tone: "trust" as const },
    { label: "Debugging", pct: 66, tone: "trust" as const },
  ];
  return (
    <Frame label="Graded against your rubric">
      <div className="flex items-center gap-5">
        <div className="relative grid h-[72px] w-[72px] shrink-0 place-items-center rounded-full border-[6px] border-white/[0.07]">
          <span
            aria-hidden
            className="absolute inset-[-6px] rounded-full border-[6px] border-transparent border-l-trust border-t-trust"
            style={{ transform: "rotate(115deg)" }}
          />
          <span className="mono text-lg font-semibold text-white">78</span>
        </div>
        <div>
          <p className="text-[13px] font-semibold text-white">Overall score</p>
          <p className="mono mt-1 text-[10px] uppercase tracking-[0.12em] text-white/35">
            Graded by AI against this JD&apos;s rubric
          </p>
        </div>
      </div>
      <ul className="mt-5 space-y-3">
        {dims.map((d) => (
          <li key={d.label}>
            <div className="flex items-baseline justify-between gap-3">
              <span className="text-[12px] text-white/70">{d.label}</span>
              <span className="mono text-[11px] text-white/50">{d.pct}</span>
            </div>
            <div className="mt-1.5">
              <Meter pct={d.pct} tone={d.tone} />
            </div>
          </li>
        ))}
      </ul>
      <Row className="mt-5">
        <p className="mono text-[10px] uppercase tracking-[0.12em] text-verify">Rule fired</p>
        <p className="mt-1 text-[12px] text-white/55">
          Application graded ≥ 70 → advanced to Shortlisted
        </p>
      </Row>
    </Frame>
  );
}

/* ------------------------------------------------------------------ 07 */

function DecideVisual() {
  const stages = ["Applied", "Graded", "Shortlisted", "L1", "L2", "Offer", "Hired"];
  const at = 2;
  return (
    <Frame label="Candidate · decision">
      <ol className="flex flex-wrap items-center gap-1.5">
        {stages.map((s, i) => (
          <li key={s} className="flex items-center gap-1.5">
            <span
              className={`mono rounded-full px-2.5 py-1 text-[10px] ${
                i < at
                  ? "bg-white/[0.06] text-white/40"
                  : i === at
                    ? "bg-trust text-white"
                    : "border border-white/10 text-white/25"
              }`}
            >
              {s}
            </span>
            {i < stages.length - 1 && (
              <span aria-hidden className="h-px w-2 bg-white/10" />
            )}
          </li>
        ))}
      </ol>
      <Row className="mt-5">
        <p className="mono text-[10px] uppercase tracking-[0.12em] text-verify">
          Contact unlocked
        </p>
        <p className="mt-1 text-[12px] leading-relaxed text-white/55">
          Details stay closed until Shortlisted. Until then you assess the work, not the person.
        </p>
      </Row>
      <div className="mt-3 grid grid-cols-2 gap-2">
        <Row>
          <p className="mono text-[10px] uppercase tracking-[0.12em] text-white/30">Next round</p>
          <p className="mt-1 text-[12px] text-white/70">SQL depth · send</p>
        </Row>
        <Row>
          <p className="mono text-[10px] uppercase tracking-[0.12em] text-white/30">On file</p>
          <p className="mt-1 text-[12px] text-white/70">Answers · rubric · timeline</p>
        </Row>
      </div>
    </Frame>
  );
}

const VISUALS: Record<Step["visual"], () => React.ReactElement> = {
  workspace: WorkspaceVisual,
  jd: JdVisual,
  mock: MockVisual,
  rounds: RoundsVisual,
  publish: PublishVisual,
  grade: GradeVisual,
  decide: DecideVisual,
};

export function StepVisual({ kind }: { kind: Step["visual"] }) {
  const Visual = VISUALS[kind];
  return <Visual />;
}
