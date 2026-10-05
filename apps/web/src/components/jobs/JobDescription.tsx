import type { CSSProperties } from "react";

/**
 * Renders a job description that carries its own structure.
 *
 * JDs written by the console come back sectioned — "## Key Responsibilities"
 * followed by "- " bullets — because that is how candidates expect to read
 * one. Rendered as a single pre-wrapped blob, all of that structure arrived as
 * literal hashes and hyphens.
 *
 * This is deliberately not a Markdown library. A JD uses exactly two
 * constructs, a heading and a bullet, and every line here becomes a React
 * element — so there is no HTML parsing and nothing to inject through, which
 * matters because this text comes out of a language model.
 *
 * It carries no colours of its own. The public job page and the candidate view
 * have different palettes, and both pass their own; headings are distinguished
 * by weight and size so they read correctly against either.
 *
 * Descriptions written before the sectioned format, and any an employer types
 * by hand, have no headings at all: those fall through as plain paragraphs and
 * look exactly as they did before.
 */

type Block =
  | { kind: "heading"; text: string }
  | { kind: "list"; items: string[] }
  | { kind: "paragraph"; text: string };

function parse(description: string): Block[] {
  const blocks: Block[] = [];
  let list: string[] = [];
  let paragraph: string[] = [];

  const flushList = () => {
    if (list.length > 0) blocks.push({ kind: "list", items: list });
    list = [];
  };

  const flushParagraph = () => {
    if (paragraph.length > 0) blocks.push({ kind: "paragraph", text: paragraph.join(" ") });
    paragraph = [];
  };

  for (const raw of description.split("\n")) {
    const line = raw.trim();

    if (line === "") {
      flushList();
      flushParagraph();
      continue;
    }

    if (line.startsWith("#")) {
      flushList();
      flushParagraph();
      blocks.push({ kind: "heading", text: line.replace(/^#+\s*/, "").trim() });
      continue;
    }

    // "- ", "* " and "• " all mean the same thing to whoever wrote it.
    const bullet = line.match(/^[-*•]\s+(.*)$/);

    if (bullet) {
      flushParagraph();
      list.push(bullet[1].trim());
      continue;
    }

    flushList();
    paragraph.push(line);
  }

  flushList();
  flushParagraph();

  return blocks;
}

export function JobDescription({
  description,
  className,
  style,
}: {
  description: string;
  className?: string;
  style?: CSSProperties;
}) {
  const blocks = parse(description);

  return (
    <div className={className} style={style}>
      <div className="space-y-4">
        {blocks.map((block, i) => {
          if (block.kind === "heading") {
            return (
              <h3 key={i} className="pt-3 text-[1.15em] font-bold tracking-tight first:pt-0">
                {block.text}
              </h3>
            );
          }

          if (block.kind === "list") {
            return (
              <ul key={i} className="space-y-2">
                {block.items.map((item, j) => (
                  <li key={j} className="flex gap-3">
                    <span
                      aria-hidden="true"
                      className="mt-[0.55em] size-1.5 shrink-0 rounded-full bg-current opacity-40"
                    />
                    <span>{item}</span>
                  </li>
                ))}
              </ul>
            );
          }

          return <p key={i}>{block.text}</p>;
        })}
      </div>
    </div>
  );
}
