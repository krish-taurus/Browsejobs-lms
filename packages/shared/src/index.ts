/**
 * @browsejobs/shared
 *
 * Single source of truth for DTOs shared between the Laravel API and the
 * Next.js web app. In later phases these types are generated from the API's
 * Laravel API Resources; for now they are hand-authored scaffolding examples.
 *
 * Keep this package framework-agnostic: types and pure helpers only.
 */

/** The 8 platform roles (PRD §4). */
export type Role =
  | "super_admin"
  | "admin"
  | "trainer"
  | "mentor"
  | "counselor"
  | "placement_officer"
  | "support_agent"
  | "student";

/** Lead pipeline stages (PRD §5 / §6.12). */
export type LeadStage =
  | "lead"
  | "masterclass_registered"
  | "attended"
  | "bootcamp"
  | "reserved_payment_pending"
  | "enrolled"
  | "active"
  | "placement_ready"
  | "placed";

/** Batch types (PRD §6.1). */
export type BatchType = "masterclass" | "bootcamp" | "paid";

/** Per-member roster status (PRD §6.1). */
export type BatchMemberStatus =
  | "reserved"
  | "payment_pending"
  | "enrolled"
  | "dropped"
  | "transferred"
  | "completed";

/** A tenant's whitelabel branding theme, consumed as CSS variables (PRD §3). */
export interface TenantTheme {
  colors: Record<string, string>;
  fonts: {
    display: string;
    body: string;
    mono: string;
  };
}

/** Public-facing tenant DTO. */
export interface Tenant {
  id: number;
  name: string;
  slug: string;
  customDomain: string | null;
  theme: TenantTheme;
  featureFlags: Record<string, boolean>;
}

/**
 * Consistent API error envelope (CLAUDE.md backend conventions):
 * `{ error: { code, message, details } }`.
 */
export interface ApiError {
  error: {
    code: string;
    message: string;
    details?: Record<string, unknown>;
  };
}

/** Successful API resource envelope. */
export interface ApiResource<T> {
  data: T;
}

/** Type guard for the API error envelope. */
export function isApiError(value: unknown): value is ApiError {
  return (
    typeof value === "object" &&
    value !== null &&
    "error" in value &&
    typeof (value as ApiError).error?.code === "string"
  );
}
