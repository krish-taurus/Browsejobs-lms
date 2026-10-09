import Link from "next/link";

/** Visible “Learn more ›”. Extra words stay in the accessible name only. */
export function More({ href, children, about }: { href: string; children: string; about?: string }) {
  return (
    <Link href={href} className="apple-more">
      {children}
      {about ? <span className="sr-only"> {about}</span> : null}
      <span aria-hidden="true">›</span>
    </Link>
  );
}
