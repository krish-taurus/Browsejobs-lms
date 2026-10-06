import { siteJsonLd } from "@/lib/seo";

/** Organization, WebSite, and founder Person. In the root layout so every page carries it. */
export function SiteJsonLd() {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(siteJsonLd()) }}
    />
  );
}
