import type { ReactNode } from "react";

export type Story = {
  name: string;
  before: string;
  after: string;
  package: string | null;
  company: string | null;
  company_color: string | null;
  rounds: number | null;
  quote: string | null;
  screenshot_url: string | null;
  is_sample?: boolean;
};

function Initial({ name, color }: { name: string; color?: string | null }) {
  return (
    <span
      className="grid h-11 w-11 shrink-0 place-items-center rounded-full text-base font-bold text-white"
      style={{ background: color ?? "var(--bj-trust)" }}
    >
      {name.trim().charAt(0).toUpperCase()}
    </span>
  );
}

/**
 * A placement-story card (transformation → package → company → rounds → the
 * WhatsApp offer message). Shared by course pages and the home page. When
 * `is_sample` is set, a clear "Sample" badge marks it as illustrative so no
 * visitor mistakes it for a real, consented placement. `cta` is the footer action.
 */
export function StoryCard({ story, cta }: { story: Story; cta?: ReactNode }) {
  return (
    <article className="flex flex-col overflow-hidden rounded-[18px] border border-line bg-white shadow-soft">
      <div className="p-5">
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-center gap-3">
            <Initial name={story.name} color={story.company_color} />
            <div>
              <p className="font-semibold text-ink">{story.name}</p>
              <p className="text-sm text-muted">
                {story.before} → <span className="font-medium text-verify">{story.after}</span>
              </p>
            </div>
          </div>
          {story.is_sample && (
            <span className="mono shrink-0 rounded-full border border-line bg-paper px-2 py-0.5 text-[9px] uppercase tracking-widest text-muted">
              Sample
            </span>
          )}
        </div>

        <div className="mt-4 grid grid-cols-3 gap-2">
          {story.package && (
            <div className="rounded-[12px] border border-line bg-paper px-3 py-2">
              <span className="mono block text-[9px] uppercase tracking-widest text-muted">Package</span>
              <span className="mono mt-0.5 block text-base font-semibold text-verify">{story.package}</span>
            </div>
          )}
          {story.company && (
            <div className="rounded-[12px] border border-line bg-paper px-3 py-2">
              <span className="mono block text-[9px] uppercase tracking-widest text-muted">Joined</span>
              <span className="mt-0.5 block text-sm font-semibold text-ink">{story.company}</span>
            </div>
          )}
          {story.rounds != null && (
            <div className="rounded-[12px] border border-line bg-paper px-3 py-2">
              <span className="mono block text-[9px] uppercase tracking-widest text-muted">Cleared</span>
              <span className="mono mt-0.5 block text-base font-semibold text-ink">
                {story.rounds} <span className="text-[11px] font-normal text-muted">rounds</span>
              </span>
            </div>
          )}
        </div>
      </div>

      {/* The offer message — a real screenshot if uploaded, else the quote as a chat bubble. */}
      {story.screenshot_url ? (
        // Signed, short-lived S3 URL — next/image would need per-host remote config; a plain img is right here.
        // eslint-disable-next-line @next/next/no-img-element
        <img src={story.screenshot_url} alt={`${story.name}'s offer message`} className="mx-5 rounded-[10px] border border-line" />
      ) : story.quote ? (
        <div className="mx-5 rounded-[10px] bg-[#e5ddd4] p-3">
          <div className="ml-auto max-w-[92%] rounded-[8px] rounded-tr-[2px] bg-[#dcf8c6] px-3 py-2 text-[13px] text-[#111b21] shadow-[0_1px_0.5px_rgba(0,0,0,0.13)]">
            {story.quote}
          </div>
        </div>
      ) : null}

      {cta && <div className="flex items-center justify-between gap-3 p-5 pt-4">{cta}</div>}
    </article>
  );
}
