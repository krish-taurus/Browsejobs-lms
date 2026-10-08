import type { Metadata } from "next";
import { ArgusFrame } from "@/components/argus/ArgusFrame";
import { ArgusButton } from "@/components/argus/ui";
import { EMPLOYER_FAQ } from "@/content/employer-landing";
import { breadcrumbNode, canonical, faqNode, jsonLdGraph, webPageNode } from "@/lib/seo";

const TITLE = "Employer questions";
const DESCRIPTION =
  "Straight answers on the 3-day path, the 75% clear mark, the four bots, and what BrowseJobs will not promise. Nobody can guarantee a hire.";
const PATH = "/employers/faq";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: { title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
};

export default function EmployerFaqPage() {
  const jsonLd = jsonLdGraph([
    webPageNode({ path: PATH, title: TITLE, description: DESCRIPTION }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Employers", path: "/employers" },
      { name: "FAQ", path: PATH },
    ]),
    faqNode(EMPLOYER_FAQ),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <ArgusFrame className="argus-faq-page">
        <section className="argus-section argus-faq-hero">
          <div className="argus-faq-wrap">
            <h1 className="argus-h1">Questions.</h1>
            <div className="argus-faq-list">
              {EMPLOYER_FAQ.map((item) => (
                <details key={item.q} className="argus-faq-item">
                  <summary>
                    {item.q}
                    <span aria-hidden>+</span>
                  </summary>
                  <p>{item.a}</p>
                </details>
              ))}
            </div>
            <p className="argus-start">
              <ArgusButton href="/employers/enquire">Get started</ArgusButton>
            </p>
          </div>
        </section>
      </ArgusFrame>
    </>
  );
}
