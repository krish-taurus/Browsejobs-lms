import { JsonLd } from "@/components/landing/JsonLd";
import V3Landing from "./v3/page";

/**
 * Home — the keynote landing (v3 template, promoted to live): hero → dashboard →
 * manifesto → path → AI scenes → bento → programs → career report → fees →
 * verify → stories (LLM question bank) → reviews → market signals → CTA.
 * /v3 remains as a preview alias of the same component.
 */
export default function Home() {
  return (
    <>
      <JsonLd />
      <V3Landing />
    </>
  );
}
