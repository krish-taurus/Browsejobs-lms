import type { Tenant } from "@browsejobs/shared";

/**
 * Minimal public tenant shape used by public pages before the API is wired.
 * Proves the @browsejobs/shared workspace package resolves end-to-end.
 */
export type PublicTenant = Pick<Tenant, "slug" | "name" | "theme">;

export const DEFAULT_TENANT_SLUG =
  process.env.NEXT_PUBLIC_DEFAULT_TENANT ?? "browsejobs";
