import type { Metadata } from "next";
import { CourseLandingPage, courseLandingMetadata } from "@/components/seo/CourseLandingPage";

export const metadata: Metadata = courseLandingMetadata("agentic-india");

export default function Page() {
  return <CourseLandingPage id="agentic-india" />;
}
