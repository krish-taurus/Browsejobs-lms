import {
  DELIVERY_MODELS,
  EMPLOYER_DISCLAIMER,
  PIPELINE,
  PRICING,
  ROADMAP,
  USE_CASES,
} from "@/content/employers";

/** Pipeline copy is the same text as /employers, so this page cannot drift from the product. */
export function EmployerPipeline({ ids }: { ids?: readonly string[] }) {
  const stages = ids ? PIPELINE.filter((stage) => ids.includes(stage.id)) : PIPELINE;
  return (
    <ol className="not-prose mt-8 space-y-4">
      {stages.map((stage) => (
        <li key={stage.id} className="rounded-[14px] border border-line bg-white p-5">
          <p className="mono text-[11px] text-trust">
            {stage.step} · {stage.kicker}
          </p>
          <h3 className="mt-1 text-lg font-semibold text-ink">{stage.title}</h3>
          <p className="mt-2 text-[15px] leading-relaxed text-ink2">{stage.body}</p>
          <ul className="mt-3 list-disc space-y-1 pl-5 text-sm leading-relaxed text-ink2">
            {stage.points.map((point) => (
              <li key={point}>{point}</li>
            ))}
          </ul>
        </li>
      ))}
    </ol>
  );
}

export function EmployerModels() {
  return (
    <div className="not-prose mt-8 grid gap-4">
      {DELIVERY_MODELS.map((model) => (
        <div key={model.id} className="rounded-[14px] border border-line bg-white p-5">
          <p className="mono text-[11px] text-trust">{model.label}</p>
          <h3 className="mt-1 text-lg font-semibold text-ink">{model.title}</h3>
          <p className="mt-2 text-[15px] leading-relaxed text-ink2">{model.body}</p>
          <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-ink2">
            {model.points.map((point) => (
              <li key={point}>{point}</li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  );
}

export function EmployerPricing() {
  return (
    <div className="not-prose mt-8 space-y-4">
      <div className="rounded-[14px] border border-verify/30 bg-verify-bg p-5">
        <p className="kicker text-verify">{PRICING.free.label}</p>
        <p className="mono mt-2 text-3xl text-ink">{PRICING.free.price}</p>
        <p className="mt-2 text-sm leading-relaxed text-ink2">{PRICING.free.body}</p>
        <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-ink2">
          {PRICING.free.points.map((point) => (
            <li key={point}>{point}</li>
          ))}
        </ul>
      </div>
      <div className="rounded-[14px] border border-line bg-white p-5">
        <p className="kicker text-trust">{PRICING.paid.label}</p>
        <p className="mt-2 text-sm leading-relaxed text-ink2">{PRICING.paid.body}</p>
        <ul className="mt-4 space-y-3">
          {PRICING.paid.options.map((option) => (
            <li key={option.title} className="rounded-[10px] border border-line px-4 py-3">
              <p className="font-semibold text-ink">{option.title}</p>
              <p className="mono mt-1 text-ink">{option.headline}</p>
              <p className="mt-1 text-sm leading-relaxed text-ink2">{option.body}</p>
              <p className="mono mt-2 text-[11px] text-muted">{option.note}</p>
            </li>
          ))}
        </ul>
      </div>
      <div className="rounded-[14px] border border-line bg-paper p-5">
        <h3 className="font-semibold text-ink">{PRICING.crm.title}</h3>
        <p className="mt-2 text-sm leading-relaxed text-ink2">{PRICING.crm.body}</p>
      </div>
    </div>
  );
}

export function EmployerRoadmap() {
  return (
    <div className="not-prose mt-8 space-y-3">
      <p className="rounded-full border border-amber/40 bg-amber/10 px-3 py-1 text-xs font-semibold text-ink">
        Not built yet — do not treat these as available
      </p>
      <ul className="space-y-3">
        {ROADMAP.map((item) => (
          <li key={item.title} className="rounded-[14px] border border-dashed border-line bg-white p-5">
            <h3 className="font-semibold text-ink">{item.title}</h3>
            <p className="mt-2 text-sm leading-relaxed text-ink2">{item.body}</p>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function EmployerScenarios() {
  return (
    <div className="not-prose mt-8 space-y-4">
      {USE_CASES.map((item) => (
        <div key={item.title} className="rounded-[14px] border border-line bg-white p-5">
          <h3 className="font-semibold text-ink">{item.title}</h3>
          <p className="mt-2 text-sm leading-relaxed text-ink2">{item.scenario}</p>
          <ol className="mt-3 list-decimal space-y-1 pl-5 text-sm text-ink2">
            {item.flow.map((step) => (
              <li key={step}>{step}</li>
            ))}
          </ol>
          <p className="mt-3 text-sm leading-relaxed text-ink">{item.outcome}</p>
        </div>
      ))}
      <p className="mono text-[11px] leading-relaxed text-muted">{EMPLOYER_DISCLAIMER}</p>
    </div>
  );
}
