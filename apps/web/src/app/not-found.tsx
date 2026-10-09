import type { Metadata } from "next";
import Link from "next/link";
import { ArgusFrame } from "@/components/argus/ArgusFrame";

export const metadata: Metadata = {
  title: { absolute: "Page not found · BrowseJobs" },
  description: "That page is not on BrowseJobs.",
  robots: { index: false, follow: false },
};

export default function NotFound() {
  return (
    <ArgusFrame>
      <section className="argus-section argus-faq-section">
        <div className="argus-scrim argus-faq-wrap">
          <p className="argus-kicker">404</p>
          <h1 className="argus-h1">This page is not here.</h1>
          <p className="argus-body">The address may be mistyped, or the page may have moved.</p>
          <div className="argus-row">
            <Link href="/" className="argus-btn argus-btn-primary">
              Back to the homepage
            </Link>
            <Link href="/courses" className="argus-more">
              See the courses
            </Link>
          </div>
        </div>
      </section>
    </ArgusFrame>
  );
}
