import type { ScreenMode } from "@/content/home";

/**
 * Product-UI vignette of the job-readiness screen. Static markup so the
 * sample is in the server HTML. Labelled "Sample" — a demonstration, not a result.
 * Colours are white-on-ink so the night canvas token shift cannot invert them.
 */
export function ScreenFrame({ mode, compact = false }: { mode: ScreenMode | "hr"; compact?: boolean }) {
  return (
    <div className="home-frame overflow-hidden rounded-[22px] border border-white/10 bg-ink text-white">
      <div className="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
        <p className="kicker text-sky">
          {mode === "interview" && "AI interview"}
          {mode === "score" && "Screen score"}
          {mode === "outcome" && "Two outcomes"}
          {mode === "hr" && "What HR receives"}
        </p>
        <span className="mono text-[10px] uppercase tracking-[0.16em] text-white/45">Sample</span>
      </div>
      <div className={compact ? "p-4" : "p-5 md:p-6"}>
        {mode === "interview" && <InterviewBody />}
        {mode === "score" && <ScoreBody />}
        {mode === "outcome" && <OutcomeBody />}
        {mode === "hr" && <HrBody />}
      </div>
    </div>
  );
}

function InterviewBody() {
  return (
    <div className="space-y-3">
      <Bubble who="Interviewer" text="Walk me through a pipeline you would ship this month." />
      <Bubble who="You" text="Land the raw files, transform them, and load a warehouse the analyst can trust." you />
      <div className="flex items-center justify-between pt-1">
        <span className="mono text-[11px] text-white/50">Listening · 00:42</span>
        <span className="rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white/70">
          Live round
        </span>
      </div>
    </div>
  );
}

function Bubble({ who, text, you = false }: { who: string; text: string; you?: boolean }) {
  return (
    <div className={`max-w-[95%] rounded-[14px] px-3.5 py-2.5 ${you ? "ml-auto bg-trust/30" : "bg-white/8"}`}>
      <p className="mono text-[10px] uppercase tracking-[0.14em] text-white/45">{who}</p>
      <p className="mt-1 text-sm leading-snug text-white">{text}</p>
    </div>
  );
}

function ScoreBody() {
  const rows = [
    { label: "Depth", value: "Holds", tone: "text-verify" },
    { label: "Communication", value: "Mixed", tone: "text-amber" },
    { label: "Role skills", value: "Gap", tone: "text-amber" },
  ];
  return (
    <div>
      <p className="mono text-[11px] uppercase tracking-[0.16em] text-white/45">Not a placement rate</p>
      <ul className="mt-4 space-y-3">
        {rows.map((row) => (
          <li key={row.label} className="flex items-center justify-between gap-4 border-b border-white/10 pb-3">
            <span className="text-sm text-white/80">{row.label}</span>
            <span className={`mono text-sm font-semibold ${row.tone}`}>{row.value}</span>
          </li>
        ))}
      </ul>
      <p className="mt-4 border-l-2 border-amber pl-3 text-sm leading-snug text-white/80">
        Coach note — what to fix, not a rejection. Window functions and pipeline design need another pass.
      </p>
    </div>
  );
}

function OutcomeBody() {
  return (
    <div className="space-y-3">
      <div className="rounded-[14px] border border-verify/40 bg-verify/15 px-4 py-3">
        <p className="kicker text-verify">Clear</p>
        <p className="mt-1 text-sm font-semibold text-white">We put you in front of HR</p>
        <p className="mt-1 text-xs leading-snug text-white/70">
          Your score goes with you. Hiring teams see the interview, not a cold CV.
        </p>
      </div>
      <div className="rounded-[14px] border border-amber/40 bg-white/5 px-4 py-3">
        <p className="kicker text-amber">Not clear</p>
        <p className="mt-1 text-sm font-semibold text-white">What&apos;s blocking you</p>
        <p className="mt-1 text-xs leading-snug text-white/65">We name what got in the way. We don&apos;t pretend you cleared.</p>
      </div>
    </div>
  );
}

function HrBody() {
  return (
    <div className="space-y-3 text-sm">
      <Row label="Role" value="Data Engineer" />
      <Row label="Screen" value="Clear" verify />
      <Row label="Queue" value="Priority" />
      <div className="rounded-[14px] border border-white/10 bg-white/5 px-3.5 py-3">
        <p className="mono text-[10px] uppercase tracking-[0.14em] text-white/45">Attached</p>
        <p className="mt-1 text-white">Scored interview · not a CV on its own</p>
      </div>
      <p className="text-xs leading-snug text-white/50">
        If you don&apos;t clear, this card is not sent. You keep the note on what to fix.
      </p>
    </div>
  );
}

function Row({ label, value, verify = false }: { label: string; value: string; verify?: boolean }) {
  return (
    <div className="flex items-center justify-between">
      <span className="text-white/60">{label}</span>
      <span className={`mono ${verify ? "font-semibold text-verify" : "text-white"}`}>{value}</span>
    </div>
  );
}
