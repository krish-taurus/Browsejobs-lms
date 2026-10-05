import { PageHead, Tile, Label, PrimaryButton } from "@/components/employer/ui";
import { CrownIcon } from "@/components/employer/icons";

const PERKS = [
  "Higher monthly interview volume",
  "Priority AI grading — no queue during peak hours",
  "Dedicated onboarding for your hiring team",
];

/**
 * There's no self-serve billing yet, so this doesn't pretend to have
 * pricing tiers or a checkout — it says plainly what Pro adds and routes
 * to a real person instead of a fake "Buy now" button.
 */
export default function EmployerUpgradePage() {
  return (
    <div className="space-y-5 pb-10">
      <PageHead kicker="Plan" title="Upgrade to Pro" sub="You're currently on the free plan." />

      <Tile hover={false}>
        <span className="grid size-11 place-items-center rounded-2xl bg-[#e7f3ff] text-[#1877f2]">
          <CrownIcon className="size-5" />
        </span>
        <p className="font-display mt-4 text-xl font-bold text-[#050505]">What Pro adds</p>
        <ul className="mt-4 space-y-2.5">
          {PERKS.map((p) => (
            <li key={p} className="flex items-start gap-2.5 text-sm text-[#050505]">
              <span aria-hidden className="mt-1.5 size-1.5 shrink-0 rounded-full bg-[#1877f2]" />
              {p}
            </li>
          ))}
        </ul>
        <p className="mt-5 max-w-lg text-sm leading-relaxed text-[#65676b]">
          Pricing depends on your hiring volume, so we set it up with you directly rather than over
          a card form.
        </p>
        <PrimaryButton
          href="mailto:support@browsejobs.ai?subject=Upgrade%20to%20Pro"
          className="mt-5"
        >
          Talk to us about Pro
        </PrimaryButton>
      </Tile>
    </div>
  );
}
