import { redirect } from "next/navigation";

export default function TaurusIndex() {
  redirect("/admin/taurus/console");
}
