import type { Metadata } from "next";
import { CourseLandingPage, courseLandingMetadata } from "@/components/seo/CourseLandingPage";

export const metadata: Metadata = courseLandingMetadata("da-bangalore");

export default function Page() {
  return <CourseLandingPage id="da-bangalore" />;
}
