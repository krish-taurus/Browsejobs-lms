import { screenSteps } from "@/content/home";
import { ScreenFrame } from "@/components/home/ScreenFrame";
import { ScrollReveal } from "@/components/motion/ScrollReveal";

/**
 * The three steps in normal document flow. Each row fades in on view.
 * Nothing is pinned: a sticky scene was covering the next section and
 * trapping the scroll on the way through.
 */
export function ScreenScroll() {
  return (
    <ol className="mt-12 space-y-16 md:mt-16 md:space-y-24">
      {screenSteps.map((step) => (
        <li key={step.id}>
          <ScrollReveal>
            <article className="grid items-center gap-8 lg:grid-cols-2 lg:gap-16">
              <div>
                <p className="mono text-sm font-semibold text-trust">{step.n}</p>
                <h3 className="display mt-2 text-3xl text-fg md:text-5xl">{step.title}</h3>
                <p className="mt-3 max-w-xl text-base leading-relaxed text-muted md:text-lg">{step.body}</p>
              </div>
              <ScreenFrame mode={step.mode} />
            </article>
          </ScrollReveal>
        </li>
      ))}
    </ol>
  );
}
