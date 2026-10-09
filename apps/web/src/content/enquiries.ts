import { courses } from "@/content/landing";

/** Live catalogue only. Waitlist slugs, including agentic-ai, are not offered. */
export const enquiryCourses = courses.filter((course) => course.live);

export const COMPANY_SIZES = [
  { value: "1-10", label: "1–10 people" },
  { value: "11-50", label: "11–50 people" },
  { value: "51-200", label: "51–200 people" },
  { value: "201-1000", label: "201–1,000 people" },
  { value: "1000+", label: "1,000+ people" },
] as const;

export const HIRING_TIMELINES = [
  { value: "this-week", label: "This week" },
  { value: "this-month", label: "This month" },
  { value: "this-quarter", label: "This quarter" },
  { value: "exploring", label: "Just exploring" },
] as const;

export const LEARNER_STATUSES = [
  { value: "student", label: "Student" },
  { value: "working", label: "Working professional" },
  { value: "switcher", label: "Career switcher" },
] as const;

export const CALL_TIMES = [
  { value: "morning", label: "Morning, 9:00–12:00 IST" },
  { value: "afternoon", label: "Afternoon, 12:00–16:00 IST" },
  { value: "evening", label: "Evening, 16:00–19:00 IST" },
] as const;

export const COUNTRY_CODES = [
  { value: "+91", label: "India +91" },
  { value: "+1", label: "US / Canada +1" },
  { value: "+44", label: "UK +44" },
  { value: "+971", label: "UAE +971" },
  { value: "+65", label: "Singapore +65" },
  { value: "+61", label: "Australia +61" },
  { value: "+49", label: "Germany +49" },
  { value: "+977", label: "Nepal +977" },
  { value: "+94", label: "Sri Lanka +94" },
  { value: "+880", label: "Bangladesh +880" },
] as const;

export const EMPLOYER_INTENTS = {
  partner: "Onboard us as your hiring partner",
  tool: "Use our tool for your own hiring",
} as const;

export type EmployerIntent = keyof typeof EMPLOYER_INTENTS;

export function employerIntent(value: string | undefined): EmployerIntent | null {
  if (value === "partner" || value === "tool") return value;
  return null;
}

export function liveCourseSlug(value: string | undefined): string {
  if (!value) return "";
  return enquiryCourses.some((course) => course.slug === value) ? value : "";
}
