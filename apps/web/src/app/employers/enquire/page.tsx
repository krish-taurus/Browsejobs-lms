import type { Metadata } from "next";
import { EnquiryForm } from "@/components/apple/EnquiryForm";
import { AppleShell } from "@/components/apple/AppleShell";
import { employerIntent } from "@/content/enquiries";
import { breadcrumbNode, canonical, jsonLdGraph, webPageNode } from "@/lib/seo";

const TITLE = "Enquire to hire";
const DESCRIPTION =
  "Tell BrowseJobs the role, the city, and when you want to hire. We call you back. Nobody can guarantee a hire — the market decides.";
const PATH = "/employers/enquire";

export const metadata: Metadata = {
  title: { absolute: `${TITLE} · BrowseJobs` },
  description: DESCRIPTION,
  alternates: { canonical: canonical(PATH) },
  openGraph: { title: TITLE, description: DESCRIPTION, url: canonical(PATH) },
};

export default async function EmployerEnquirePage({
  searchParams,
}: {
  searchParams: Promise<{ path?: string }>;
}) {
  const params = await searchParams;
  const jsonLd = jsonLdGraph([
    webPageNode({ path: PATH, title: TITLE, description: DESCRIPTION }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Employers", path: "/employers" },
      { name: "Enquire", path: PATH },
    ]),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <AppleShell>
        <section className="px-5 pb-20 pt-16 md:pt-24">
          <div className="mx-auto max-w-[720px] text-center">
            <h1 className="apple-display text-[clamp(2.75rem,6vw,5rem)]">Enquire to hire.</h1>
            <p className="mx-auto mt-4 max-w-[36rem] text-[19px] leading-snug text-[#424245]">
              Share the role and when you want to start. We call you back. Hiring still depends on the market.
            </p>
          </div>
          <div className="mt-12">
            <EnquiryForm type="employer" intent={employerIntent(params.path)} />
          </div>
        </section>
      </AppleShell>
    </>
  );
}
