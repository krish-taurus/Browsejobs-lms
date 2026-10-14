import type { Metadata } from "next";
import Link from "next/link";
import { Disclaimer } from "@/components/brand/Disclaimer";
import {
  ContactStrip,
  Contents,
  MoneyArticle,
  MoneyFaq,
  MoneyHero,
  MoneySection,
  RelatedLinks,
  TextLink,
} from "@/components/seo/MoneyArticle";
import { courseLandings, getCourseLanding, type CourseLanding } from "@/content/course-landings";
import { OFFICE_PHONE } from "@/content/phones";
import { courseNode, breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, programmeCourseNode, webPageNode } from "@/lib/seo";

export function courseLandingMetadata(id: string): Metadata {
  const page = getCourseLanding(id);
  return moneyMetadata({
    path: page.path,
    title: page.title,
    description: page.description,
    canonicalPath: page.canonicalPath,
  });
}

function courseJson(page: CourseLanding) {
  if (page.schema.idPath === "/courses/data-analytics") {
    const node = courseNode("data-analytics");
    if (!node) throw new Error("data-analytics course is missing");
    return node;
  }
  return programmeCourseNode(page.schema);
}

export function CourseLandingPage({ id }: { id: string }) {
  const page = getCourseLanding(id);
  const jsonLd = jsonLdGraph([
    webPageNode({
      path: page.path,
      title: page.h1,
      description: page.description,
      dateModified: page.updated,
    }),
    breadcrumbNode(page.crumbs),
    courseJson(page),
    faqNode(page.faqs),
  ]);

  return (
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker={page.kicker}
        title={page.h1}
        crumbs={page.crumbs}
        primary={{ kind: "link", href: "/get-hired", label: "Take the free AI interview" }}
        secondary={{ href: "/courses/enquire", label: "Ask about this course" }}
        lede={
          <>
            <p className="fine">
              Updated <time dateTime={page.updated}>14 Oct 2026</time>
              {" · "}
              <Link href="/founder">Dr Krish Bharggav</Link>
            </p>
            <p data-answer-box>{page.answer}</p>
          </>
        }
      />
      <Contents
        items={[
          { href: "#facts", label: "Key facts" },
          { href: "#where", label: "The course" },
          { href: "#syllabus", label: "Six-month syllabus" },
          { href: "#placement", label: "Placement" },
          { href: "#who", label: "Who it is for" },
          { href: "#choose", label: "How to choose" },
          { href: "#faq", label: "Questions" },
        ]}
      />

      <MoneySection id="facts" kicker="Key facts" heading="The facts, in one table">
        <table>
          <tbody>
            {page.facts.map((row) => (
              <tr key={row.label}>
                <th scope="row">{row.label}</th>
                <td>
                  {row.label === "Founder" ? <TextLink href="/founder">{row.value}</TextLink> : null}
                  {row.label === "Next step" ? <TextLink href="/get-hired">{row.value}</TextLink> : null}
                  {row.label === "Phone" ? <a href={`tel:${OFFICE_PHONE.tel}`}>{row.value}</a> : null}
                  {row.label !== "Founder" && row.label !== "Next step" && row.label !== "Phone" ? row.value : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        <p>
          Registration of ₹30,000 covers the course. It is not a promise of a job. Pay after placement is three months
          of the CTC on the offer you accept, charged in EMIs only after you accept that offer. The full fee page is{" "}
          <TextLink href="/pay-after-placement">Pay after placement</TextLink>.
        </p>
      </MoneySection>

      {page.sections
        .filter((section) => section.id === "where")
        .map((section) => (
          <MoneySection key={section.id} id={section.id} kicker={section.kicker} heading={section.heading}>
            {section.paragraphs.map((paragraph) => (
              <p key={paragraph.slice(0, 48)}>{linkKnownPaths(paragraph)}</p>
            ))}
          </MoneySection>
        ))}

      <MoneySection id="syllabus" kicker="Syllabus" heading="Syllabus by month">
        <p>
          Only the modules named by Dr Krish Bharggav are listed. A topic that is not in this table is not something
          this page claims you will be taught.
        </p>
        <ol>
          {page.months.map((month) => (
            <li key={month.month}>
              <strong>
                {month.month} — {month.title}.
              </strong>{" "}
              {month.body}
            </li>
          ))}
        </ol>
      </MoneySection>

      {page.sections
        .filter((section) => section.id !== "where")
        .map((section) => (
          <MoneySection key={section.id} id={section.id} kicker={section.kicker} heading={section.heading}>
            {section.paragraphs.map((paragraph) => (
              <p key={paragraph.slice(0, 48)}>{linkKnownPaths(paragraph)}</p>
            ))}
            {section.id === "placement" ? <Disclaimer /> : null}
          </MoneySection>
        ))}

      <MoneyFaq faqs={page.faqs} />
      <RelatedLinks links={page.related} />
      <ContactStrip />
    </MoneyArticle>
  );
}

/** Turn a few known paths written in prose into links, without a markdown parser. */
function linkKnownPaths(paragraph: string) {
  const parts = paragraph.split(/(\/get-hired|\/non-it-to-it|\/courses\/data-analytics|\/courses\/data-engineering)/g);
  if (parts.length === 1) return paragraph;
  return parts.map((part, index) =>
    part.startsWith("/") ? (
      <TextLink key={`${part}-${index}`} href={part}>
        {part}
      </TextLink>
    ) : (
      <span key={`${index}-${part.slice(0, 12)}`}>{part}</span>
    ),
  );
}

export const courseLandingIds = courseLandings.map((page) => page.id);
