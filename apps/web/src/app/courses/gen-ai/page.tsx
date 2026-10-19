import type { Metadata } from "next";
import { CourseLandingPage, courseLandingMetadata } from "@/components/seo/CourseLandingPage";

export const metadata: Metadata = courseLandingMetadata("gen-course");

export default function Page() {
  return <CourseLandingPage id="gen-course" />;
}
