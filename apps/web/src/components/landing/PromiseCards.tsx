import { promisesKept, promisesNever } from "@/content/landing";
import "@/components/ap/pages/marketing.css";

/**
 * S7 — the promise pair, the trust device that converts skeptics, in the
 * Apple-direction design (renders inside ApShell). `layout="article"` joins
 * the alternating .ap-sec rows of a MoneyArticle; `layout="chapter"` is a
 * paper chapter on a full page.
 */
export function PromiseCards({ layout = "article" }: { layout?: "article" | "chapter" }) {
  return (
    <section className={layout === "article" ? "ap-sec" : "s-paper chapter"}>
      <div className="wrap">
        <div className="pg-head">
          <p className="eyebrow">In writing</p>
          <h2 className="h-section">What we promise — and what we never will</h2>
        </div>
        <div className="pg-promises" data-reveal-kids="">
          <div className="tile tile-pad">
            <h3 className="h-card">What we promise in writing</h3>
            <ul className="ticks">
              {promisesKept.map((p) => (
                <li key={p}>{p}</li>
              ))}
            </ul>
          </div>
          <div className="tile tile-pad">
            <h3 className="h-card">What we will never tell you</h3>
            <ul className="ticks pg-crosses">
              {promisesNever.map((p) => (
                <li key={p}>{p}</li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </section>
  );
}
