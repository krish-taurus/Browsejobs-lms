import Link from "next/link";
import "@/components/apple/apple.css";
import { EnquiryForm } from "@/components/apple/EnquiryForm";
import { SuccessStories } from "@/components/apple/SuccessStories";
import { WhatsAppMessages } from "@/components/apple/WhatsAppMessages";
import { careerCourseCards } from "@/content/courses";
import { COUNSELLING_COPY } from "@/content/home";
import { SITE_ORIGIN } from "@/lib/seo";
import { Reveal, StaggerIn } from "@/components/argus/Reveal";
import { SplitHeading } from "@/components/argus/SplitHeading";
import { HomeClose, HomeDays, HomeEclipse, HomeFaq, HomeFloor, HomeHero, HomePath, HomeScore } from "./scenes";

const ICONS: Record<string, "pipeline" | "cloud" | "chart" | "code"> = {
  "data-engineering": "pipeline",
  "devops-cloud": "cloud",
  "data-analytics": "chart",
  "python-backend": "code",
};

export function HomePage() {
  const cards = careerCourseCards();
  const itemList = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: "Career-driven courses",
    itemListElement: cards.map((course, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: course.name,
      url: `${SITE_ORIGIN}${course.href}`,
    })),
  };

  return (
    <>
      <HomeHero />
      <HomeScore />
      <HomePath />
      <section id="career-courses" className="argus-section argus-courses">
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(itemList) }} />
        <div className="argus-scrim argus-courses-intro">
          <p className="argus-kicker">Development plan</p>
          <Reveal
            heading={<SplitHeading as="h2" className="argus-h2" text="Career-driven courses." />}
            body={
              <p className="argus-body">
                Courses focused on getting you hired. Real-world scenarios: projects, mock interviews, and the skills recruiters test for.
              </p>
            }
          />
        </div>
        <StaggerIn as="ul" className="argus-course-grid">
          {cards.map((course, index) => (
            <li key={course.slug} className="argus-card">
              <span className="argus-card-no">{String(index + 1).padStart(2, "0")}</span>
              <CourseIcon kind={ICONS[course.slug] ?? "code"} />
              <p className="argus-kicker">
                {course.duration} · {course.format}
              </p>
              <h3>{course.name}</h3>
              <p>{course.line}</p>
              <div className="argus-row">
                <Link href={course.href} className="argus-more">
                  View course
                  <i aria-hidden>›</i>
                </Link>
                <Link href={`/courses/enquire?course=${course.slug}`} className="argus-more is-quiet">
                  Enquire
                  <i aria-hidden>›</i>
                </Link>
              </div>
            </li>
          ))}
        </StaggerIn>
        <div className="argus-row argus-course-cta">
          <Link href="/courses" className="argus-btn argus-btn-primary">
            Explore courses
          </Link>
          <Link href="#counselling" className="argus-more">
            Book free counselling
            <i aria-hidden>›</i>
          </Link>
          <Link href="/#interview-start" className="argus-more">
            Take the free AI interview
            <i aria-hidden>›</i>
          </Link>
        </div>
      </section>
      <section id="counselling" className="argus-section argus-counsel" data-scene="grid">
        <div className="argus-counsel-grid">
          <div className="argus-scrim">
            <p className="argus-kicker is-free">{COUNSELLING_COPY.kicker}</p>
            <Reveal
              heading={<SplitHeading as="h2" className="argus-h2" text={COUNSELLING_COPY.title} />}
              body={<p className="argus-body">{COUNSELLING_COPY.body}</p>}
            />
            <ul className="argus-ticks">
              <li>Book a free counselling session.</li>
              <li>A BrowseJobs counsellor calls you back.</li>
              <li>Tell us when to phone.</li>
            </ul>
          </div>
          <div className="argus-panel">
            <EnquiryForm type="counselling" appearance="argus" />
          </div>
        </div>
      </section>
      <HomeEclipse />
      <SuccessStories tone="argus" />
      <WhatsAppMessages variant="carousel" />
      <HomeFloor />
      <HomeDays />
      <HomeFaq />
      <HomeClose />
    </>
  );
}

function CourseIcon({ kind }: { kind: "pipeline" | "cloud" | "chart" | "code" }) {
  return (
    <svg className={`argus-icon is-${kind}`} viewBox="0 0 48 48" aria-hidden>
      {kind === "pipeline" ? (
        <path d="M6 34h10l4-8h8l4 8h10M14 34V18m20 16V14" />
      ) : null}
      {kind === "cloud" ? <path d="M16 32h16a8 8 0 0 0 1-16 10 10 0 0 0-19 3 7 7 0 0 0 2 13z" /> : null}
      {kind === "chart" ? <path d="M8 36V22m10 14V12m10 24V18m10 18V8" /> : null}
      {kind === "code" ? <path d="M18 16l-8 8 8 8m12-16 8 8-8 8" /> : null}
    </svg>
  );
}
