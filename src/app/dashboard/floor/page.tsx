import Link from "next/link";
import { getStore } from "@/lib/db/store";
import { requireCurrentOrganization } from "@/lib/security/guards";
import { hasPermission } from "@/modules/organizations/roles";
import { buttonClasses, EmptyState, PageHeader } from "@/components/ui";
import { LiveWorkforceFloor } from "@/components/workforce-floor/LiveWorkforceFloor";

/**
 * Workforce floor (Sprint 058): every AI Employee as a robot at a desk in its
 * department's zone, what each is doing right now, and the workflow steps
 * waiting for a person — approvable from the floor. Three looks: Obsidian,
 * Titanium and Noir.
 */
export default async function WorkforceFloorPage() {
  const { organization, membership } = await requireCurrentOrganization();
  const canView = hasPermission(membership.role, "employee.view");
  const employees = canView ? await getStore().listEmployees(organization.id) : [];
  const team = employees.filter((e) => e.status !== "archived");

  return (
    <div>
      <PageHeader
        eyebrow="Workspace"
        title="Workforce floor"
        description="Your AI Employees at work, live. Each robot sits in its department's zone; its ring shows what it is doing, and anything waiting for you appears over the robot that asked."
      />
      {!canView ? (
        <EmptyState title="You can't see the workforce floor." description="Ask an owner or admin for access to AI Employees." />
      ) : team.length === 0 ? (
        <EmptyState
          title="Your floor is empty."
          description="Hire your first AI Employee and it takes a desk here."
          action={
            <Link href="/dashboard/hire" className={buttonClasses("primary", "md")}>
              Open the Hiring Studio
            </Link>
          }
        />
      ) : (
        <LiveWorkforceFloor organizationName={organization.name} />
      )}
    </div>
  );
}
