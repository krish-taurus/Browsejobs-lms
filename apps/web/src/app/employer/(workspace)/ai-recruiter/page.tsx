import { redirect } from "next/navigation";

/**
 * The separate AI Recruiter page was removed: the Hiring floor is the one
 * floor. Old links and bookmarks land there.
 */
export default function AiRecruiterPage() {
  redirect("/employer/hiring-floor");
}
