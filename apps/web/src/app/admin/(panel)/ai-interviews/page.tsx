import { redirect } from "next/navigation";

/** /admin/ai-interviews opens on the first type's table. */
export default function AdminAiInterviewsIndex() {
  redirect("/admin/ai-interviews/practice");
}
