import type { Metadata } from "next";
import { GetHiredView } from "@/components/get-hired/GetHiredView";
import { GET_HIRED_FAQ } from "@/content/get-hired";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = {
  path: "/get-hired",
  title: "Get hired: free AI interview, then HR sees your score",
  description:
    "Take a free AI interview and get a score with feedback. Clear it and HR sees your score; miss it and free counselling shows what to fix.",
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
