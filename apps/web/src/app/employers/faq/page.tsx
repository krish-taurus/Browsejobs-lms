import type { Metadata } from "next";
import Link from "next/link";
import { AppleShell } from "@/components/apple/AppleShell";
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
      <AppleShell>
        <section className="px-5 pb-20 pt-16 md:pt-24">
          <div className="mx-auto max-w-[720px]">
            <h1 className="apple-display text-center text-[clamp(2.75rem,6vw,5rem)]">Questions.</h1>
            <div className="mt-10 divide-y divide-black/10 border-y border-black/10">
              {EMPLOYER_FAQ.map((item) => (
                <details key={item.q} className="group py-5">
                  <summary className="flex min-h-11 cursor-pointer list-none items-start justify-between gap-4 text-[19px] font-semibold [&::-webkit-details-marker]:hidden">
                    {item.q}
                    <span aria-hidden className="text-[#6e6e73] group-open:rotate-45">
                      +
                    </span>
                  </summary>
                  <p className="max-w-[60ch] pb-2 pt-3 text-[17px] leading-relaxed text-[#424245]">{item.a}</p>
                </details>
              ))}
            </div>
            <p className="mt-10 text-center">
              <Link href="/employers/enquire" className="apple-pill">
                Get started
              </Link>
            </p>
          </div>
        </section>
      </AppleShell>
    </>
  );
}
