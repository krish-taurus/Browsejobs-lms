import { redirect } from "next/navigation";

/**
 * The Hiring floor is now part of the AI Recruiter page (the same Ask bar,
 * voice commands and Go ahead / Not now cards). Old links and bookmarks land there.
 */
export default function HiringFloorPage() {
  redirect("/employer/ai-recruiter");
}
