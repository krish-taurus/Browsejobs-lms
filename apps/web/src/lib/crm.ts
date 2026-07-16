/**
 * Shared CRM types + helpers for the admin leads UI (PRD §6.12). Mirrors the
 * Laravel resource shapes; the API is the source of truth for the envelope.
 */

export type LeadStage = {
  id: number;
  name: string;
  slug: string;
  position: number;
  is_won: boolean;
  is_lost: boolean;
  leads_count?: number;
};

export type LeadAssignee = { id: number; name: string } | null;

export type TimelineEvent = {
  id: number;
  type: string;
  body: string | null;
  meta: Record<string, unknown> | null;
  actor?: { id: number; name: string } | null;
  occurred_at: string | null;
};

export type CrmTask = {
  id: number;
  lead_id: number;
  title: string;
  due_at: string | null;
  completed_at: string | null;
  source: string;
  assignee?: LeadAssignee;
  lead?: { id: number; name: string; phone: string } | null;
};

export type Lead = {
  id: number;
  lead_type: string;
  name: string;
  phone: string;
  email: string | null;
  course_slug: string | null;
  message?: string | null;
  utm_source: string | null;
  utm_medium: string | null;
  utm_campaign: string | null;
  score: number;
  lead_stage_id: number | null;
  stage?: LeadStage | null;
  assigned_to: number | null;
  assignee?: LeadAssignee;
  first_touch_at: string | null;
  sla_due_at: string | null;
  sla_breached_at: string | null;
  merged_into_id: number | null;
  created_at: string | null;
  timeline?: TimelineEvent[];
  tasks?: CrmTask[];
  duplicates?: Lead[];
};

/** Speed-to-lead status for the inbox badge. */
export type SlaStatus =
  | { kind: "breached" }
  | { kind: "touched" }
  | { kind: "due"; minutesLeft: number }
  | { kind: "none" };

export function slaStatus(lead: Lead, now: number = Date.now()): SlaStatus {
  if (lead.sla_breached_at) return { kind: "breached" };
  if (lead.first_touch_at) return { kind: "touched" };
  if (!lead.sla_due_at) return { kind: "none" };
  const due = new Date(lead.sla_due_at).getTime();
  return { kind: "due", minutesLeft: Math.round((due - now) / 60000) };
}

const HEAT_THRESHOLD = 70;

export function isHot(lead: Lead): boolean {
  return lead.score >= HEAT_THRESHOLD;
}

export function leadTypeLabel(type: string): string {
  return type.charAt(0).toUpperCase() + type.slice(1);
}
