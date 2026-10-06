import type { Metadata } from "next";
import { GetHiredView } from "@/components/get-hired/GetHiredView";
import { GET_HIRED_FAQ } from "@/content/get-hired";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = {
  path: "/get-hired",
  title: "Get hired: free AI interview, then HR sees your score",
  description:
    "Take a free AI interview. You get a score and feedback. Clear it and we put you in front of HR with your score. Miss it and free counselling shows what's blocking you. A course comes only if you need it. The placement fee is due only after you accept an offer.",
} as const;

export const metadata: Metadata = moneyMetadata(page);

export default function GetHiredPage() {
  const jsonLd = jsonLdGraph([
    webPageNode(page),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Get hired", path: page.path },
    ]),
    faqNode(GET_HIRED_FAQ),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <GetHiredView />
    </>
  );
}
