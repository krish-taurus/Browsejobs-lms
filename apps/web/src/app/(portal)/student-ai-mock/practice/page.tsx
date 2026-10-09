import { redirect } from "next/navigation";

/** Text practice is retired for students — voice interviews replace it. */
export default function PracticeInterviewsPage() {
  redirect("/student-ai-mock/voice");
}
