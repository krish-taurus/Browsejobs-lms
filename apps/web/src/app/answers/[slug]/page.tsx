import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { AnswerView } from "@/components/seo/AnswerView";
import { ANSWERS_UPDATED, answerPages, answerPath, getAnswerPage } from "@/content/answers";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

export function generateStaticParams() {
  return answerPages.map((page) => ({ slug: page.slug }));
}

export const dynamicParams = false;

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const page = getAnswerPage((await params).slug);
  if (!page) return {};
  return moneyMetadata({
    path: answerPath(page.slug),
    title: page.title,
    description: page.description,
  });
}

export default async function AnswerPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const page = getAnswerPage((await params).slug);
  if (!page) notFound();

  const path = answerPath(page.slug);
  const jsonLd = jsonLdGraph([
    webPageNode({
      path,
      title: page.title,
      description: page.description,
      dateModified: ANSWERS_UPDATED,
    }),
    breadcrumbNode([
      { name: "Home", path: "/" },
      { name: "Answers", path: "/answers" },
      { name: page.title, path },
    ]),
    faqNode(page.faqs),
  ]);

  return <AnswerView page={page} jsonLd={jsonLd} />;
}
