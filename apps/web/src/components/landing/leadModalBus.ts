/**
 * Tiny event bus so any CTA (server-rendered sections included) can open the
 * single LeadModal instance without threading React context through the page.
 */
export type LeadVariant = "masterclass" | "counselling" | "waitlist";

export type LeadModalDetail = {
  variant: LeadVariant;
  courseSlug?: string;
};

export const LEAD_MODAL_EVENT = "bj:open-lead-modal";

export function openLeadModal(detail: LeadModalDetail): void {
  window.dispatchEvent(new CustomEvent(LEAD_MODAL_EVENT, { detail }));
}
