/**
 * Renders CRM-authored page content (Website → Pages).
 *
 * Two formats:
 *  - Rich text (Summernote): HTML, sanitized server-side in the CRM on save
 *    (tag allowlist, event handlers and javascript: URLs stripped).
 *  - Legacy plain text: blank line = paragraph, "## " prefix = heading.
 */
export function CmsBody({ text }: { text: string }) {
  const trimmed = text.trim();

  if (trimmed.startsWith("<")) {
    return <div dangerouslySetInnerHTML={{ __html: trimmed }} />;
  }

  const blocks = trimmed
    .replace(/\r\n/g, "\n")
    .split(/\n\s*\n/)
    .map((b) => b.trim())
    .filter(Boolean);

  return (
    <>
      {blocks.map((block, i) =>
        block.startsWith("## ") ? (
          <h2 key={i}>{block.slice(3)}</h2>
        ) : (
          <p key={i}>{block}</p>
        ),
      )}
    </>
  );
}
