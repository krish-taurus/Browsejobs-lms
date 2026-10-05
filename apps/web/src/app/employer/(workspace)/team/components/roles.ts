import type { EmployerRole } from "@/lib/employer";

export const ROLE_LABELS: Record<EmployerRole, string> = {
  owner: "Owner",
  recruiter: "Recruiter",
  hiring_manager: "Hiring manager",
};

/** Same facts the page always stated (PRD-E), reworded to the approved copy. */
export const ROLE_HINTS: Record<EmployerRole, string> = {
  owner: "Manages the workspace, team and credits",
  recruiter: "Posts jobs and manages pipelines",
  hiring_manager: "Reviews shortlists and evidence",
};

/** Roles an owner can actually invite someone into — ownership is never handed out through an invite. */
export const INVITABLE_ROLES: EmployerRole[] = ["recruiter", "hiring_manager"];
