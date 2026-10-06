import { explainerBeats } from "@/content/home";

/**
 * 9:16 portrait slot. The film drops in when an embed src is set.
 * Short captions sit on a scrim over the frame.
 * The cycle is CSS (SSR-identical) and stops under reduced motion.
 */
export function KineticPortrait({ src }: { src: string | null }) {
  return (
    <figure className="relative mx-auto w-full max-w-[300px] sm:max-w-[340px]">
      <div aria-hidden className="home-orbit pointer-events-none absolute -inset-3 rounded-[28px] opacity-80">
        <div className="absolute inset-0 rounded-[28px] bg-[conic-gradient(from_0deg,transparent,var(--bj-trust),transparent_42%,transparent_70%,var(--bj-deep),transparent)]" />
      </div>
      <div className="home-frame relative aspect-[9/16] overflow-hidden rounded-[22px] border border-white/15 bg-ink">
        {src ? (
          <iframe
            src={src}
            title="BrowseJobs hoodie explainer"
            className="absolute inset-0 h-full w-full"
            loading="lazy"
            allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowFullScreen
          />
        ) : (
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_20%,rgba(77,142,247,0.45),transparent_55%),linear-gradient(180deg,#101826,#05070d)]">
            <div className="home-grid absolute inset-0 opacity-70" />
            <div className="home-scan pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-transparent via-white/25 to-transparent" />
            <div className="absolute left-5 top-5">
              <p className="kicker text-sky">9:16 · hoodie explainer</p>
            </div>
            <div className="absolute inset-0 grid place-items-center">
              <span className="grid h-[4.5rem] w-[4.5rem] place-items-center rounded-full border border-white/25 bg-white/10 text-white">
                <svg viewBox="0 0 24 24" className="ml-1 h-8 w-8" fill="currentColor" aria-hidden>
                  <path d="M8 5.5v13l11-6.5-11-6.5z" />
                </svg>
              </span>
            </div>
          </div>
        )}

        <div className="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/35 to-transparent px-5 pb-6 pt-16">
          <div className="relative h-[4.5rem]" aria-hidden>
            {explainerBeats.map((beat) => (
              <p key={beat.n} className="home-beat absolute inset-x-0 bottom-0">
                <span className="mono text-[10px] uppercase tracking-[0.18em] text-sky">{beat.n}</span>
                <span className="display mt-1 block text-[1.65rem] leading-none text-white">{beat.title}</span>
              </p>
            ))}
          </div>
        </div>
      </div>
      <figcaption className="mono mt-4 text-center text-[11px] uppercase tracking-[0.16em] text-muted">
        {src ? "Hoodie explainer · 9:16" : "Video slot · short captions while you wait"}
      </figcaption>
    </figure>
  );
}
