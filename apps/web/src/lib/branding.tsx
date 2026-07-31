"use client";

import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { apiJson } from "@/lib/api";

/**
 * Runtime whitelabel branding (PRD §2). Fetches the host tenant's brand name,
 * logo, and palette from GET /api/v1/branding and applies the colours onto the
 * `--bj-*` CSS variables every component already consumes — so a tenant's
 * name/logo/colour changes go live without a rebuild. Falls back silently to
 * the built-in BrowseJobs theme when the API is unreachable.
 */

type Branding = {
  name: string;
  logo_url: string | null;
};

type BrandingPayload = {
  name: string;
  slug: string;
  branding: {
    logo_url?: string | null;
    colors?: Record<string, string>;
  } | null;
};

/** tenant branding JSON colour keys → the CSS variables the UI consumes. */
const COLOR_VARS: Record<string, string> = {
  ink_navy: "--bj-ink",
  deep_navy: "--bj-deep",
  trust_blue: "--bj-trust",
  sky: "--bj-sky",
  verify_green: "--bj-verify",
  warn_red: "--bj-warn",
  amber: "--bj-amber",
  paper: "--bj-paper",
  line: "--bj-line",
  muted: "--bj-muted",
};

const BrandingContext = createContext<Branding>({ name: "BrowseJobs", logo_url: null });

export function BrandingProvider({ children }: { children: ReactNode }) {
  const [brand, setBrand] = useState<Branding>({ name: "BrowseJobs", logo_url: null });

  useEffect(() => {
    let alive = true;
    apiJson<BrandingPayload>("/api/v1/branding")
      .then((payload) => {
        if (!alive) return;
        const colors = payload.branding?.colors ?? {};
        for (const [key, cssVar] of Object.entries(COLOR_VARS)) {
          const value = colors[key];
          if (typeof value === "string" && /^#[0-9a-fA-F]{3,8}$/.test(value)) {
            document.documentElement.style.setProperty(cssVar, value);
          }
        }
        setBrand({ name: payload.name || "BrowseJobs", logo_url: payload.branding?.logo_url ?? null });
        if (payload.name) document.title = document.title.replace("BrowseJobs", payload.name);
      })
      .catch(() => {
        // Unreachable API → keep the built-in theme.
      });
    return () => { alive = false; };
  }, []);

  return <BrandingContext.Provider value={brand}>{children}</BrandingContext.Provider>;
}

export function useBranding(): Branding {
  return useContext(BrandingContext);
}
