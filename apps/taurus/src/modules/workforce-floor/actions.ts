"use server";

/**
 * Workforce floor server actions (Sprint 058). The floor polls
 * `getWorkforceFloorAction` and approves through `decideFromFloorAction`,
 * which is the same resolveWorkflowRun the run page uses — same permission
 * (workflow.manage), same engine, same audit trail.
 */

import { revalidatePath } from "next/cache";
import { getStore } from "@/lib/db/store";
import { requireCurrentOrganization } from "@/lib/security/guards";
import { hasPermission } from "@/modules/organizations/roles";
import { createLlmGateway, isProductionRuntime } from "@/modules/model-gateway/credential-resolver";
import { resolveWorkflowRun, WorkflowError } from "@/modules/workflows/service";
import { loadFloorSnapshot, type FloorSnapshot } from "@/modules/workforce-floor/service";

export interface FloorReadResult {
  snapshot?: FloorSnapshot;
  /** Whether this person may approve or reject waiting steps. */
  canDecide?: boolean;
  error?: string;
}

export async function getWorkforceFloorAction(knownRunningRunIds: string[] = []): Promise<FloorReadResult> {
  const { organization, membership } = await requireCurrentOrganization();
  if (!hasPermission(membership.role, "employee.view")) return { error: "You do not have permission to view the workforce floor." };

  const snapshot = await loadFloorSnapshot(getStore(), organization, knownRunningRunIds.slice(0, 200));
  return { snapshot, canDecide: hasPermission(membership.role, "workflow.manage") };
}

export interface FloorDecisionResult {
  ok: boolean;
  message: string;
}

export async function decideFromFloorAction(runId: string, decision: "approve" | "reject"): Promise<FloorDecisionResult> {
  const { user, organization, membership } = await requireCurrentOrganization();
  if (!hasPermission(membership.role, "workflow.manage")) {
    return { ok: false, message: "Only someone who manages workflows can approve this." };
  }
  if (decision !== "approve" && decision !== "reject") return { ok: false, message: "That decision is not valid." };

  const store = getStore();
  try {
    const run = await resolveWorkflowRun(
      { store, gateway: createLlmGateway(store), isProduction: isProductionRuntime },
      { organizationId: organization.id, userId: user.id },
      runId,
      decision,
    );
    revalidatePath(`/dashboard/workflows/${run.workflowId}/runs/${run.id}`);
    return {
      ok: true,
      message: decision === "approve" ? "Approved. The workflow carries on." : "Rejected. The workflow stopped at this step.",
    };
  } catch (err) {
    return { ok: false, message: err instanceof WorkflowError ? err.message : "Something went wrong. Please try again." };
  }
}
