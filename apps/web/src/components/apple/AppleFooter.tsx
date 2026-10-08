import Link from "next/link";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { contact } from "@/content/landing";
import { recommendedCourses } from "@/content/home";

const COLUMNS = [
  {
    title: "Candidates",
    links: [
      { href: "/#interview-start", label: "Free AI interview" },
      { href: "/students", label: "Students" },
      { href: "/how-it-works", label: "How it works" },
      { href: "/get-hired", label: "Get hired" },
      { href: "/courses", label: "Courses" },
      { href: "/courses/enquire", label: "Ask about a course" },
      { href: "/jobs", label: "Jobs" },
    ],
  },
  {
    title: "Employers",
    links: [
      { href: "/employers", label: "AI Recruiter" },
      { href: "/employers/how-it-works", label: "How it works" },
      { href: "/demo", label: "Watch the demo" },
      { href: "/employers/faq", label: "FAQ" },
      { href: "/employers/enquire", label: "Enquire" },
    ],
  },
  {
    title: "Company",
    links: [
      { href: "/founder", label: "Dr Krish Bharggav" },
      { href: "/answers", label: "Answers" },
      { href: "/privacy-policy", label: "Privacy" },
      { href: "/terms", label: "Terms" },
    ],
  },
] as const;

export function AppleFooter() {
  const courses = recommendedCourses();
  return (
    <footer className="apple-foot border-t border-black/10">
      <div className="mx-auto grid max-w-[1100px] gap-10 px-5 py-12 sm:grid-cols-2 lg:grid-cols-4">
        {COLUMNS.map((column) => (
          <div key={column.title}>
            <h2>{column.title}</h2>
            <ul className="space-y-2">
              {column.links.map((item) => (
                <li key={item.href}>
                  <Link href={item.href} className="inline-flex min-h-11 items-center">
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
        <div>
          <h2>Courses</h2>
          <ul className="space-y-2">
            {courses.map((course) => (
              <li key={course.slug}>
                <Link href={course.href} className="inline-flex min-h-11 items-center">
                  {course.name}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </div>
      <div className="mx-auto max-w-[1100px] space-y-3 border-t border-black/10 px-5 py-6">
        <p>
          {contact.entity} · {contact.address} · {contact.phone} · {contact.email} · {contact.hours}
        </p>
        <div className="apple-note mx-0 max-w-none text-left">
          <sup>1</sup> <Disclaimer className="inline" />
        </div>
        <p>The hiring floor on these pages is sample data. A person always releases the offer.</p>
        <p>Every promise in writing. Every call recorded and AI-monitored.</p>
      </div>
    </footer>
  );
}
