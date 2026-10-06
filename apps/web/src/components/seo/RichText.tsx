import type { ReactNode } from "react";
import { TextLink } from "@/components/seo/MoneyArticle";

const LINK = /\[([^\]]+)\]\((\/[^)\s]+)\)/g;

/** Renders internal markdown links inside answer copy. External URLs are left as text. */
export function RichText({ text }: { text: string }) {
  const nodes: ReactNode[] = [];
  let last = 0;
  for (const match of text.matchAll(LINK)) {
    const index = match.index ?? 0;
    if (index > last) nodes.push(text.slice(last, index));
    nodes.push(
      <TextLink key={`${match[2]}-${index}`} href={match[2]}>
        {match[1]}
      </TextLink>,
    );
    last = index + match[0].length;
  }
  if (last < text.length) nodes.push(text.slice(last));
  return <>{nodes}</>;
}
