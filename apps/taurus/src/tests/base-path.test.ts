import { afterEach, describe, expect, it, vi } from "vitest";

/** BASE_PATH is read once at import, so each case loads a fresh copy of the module. */
async function load(basePath: string | undefined) {
  vi.resetModules();
  if (basePath === undefined) vi.stubEnv("NEXT_PUBLIC_BASE_PATH", "");
  else vi.stubEnv("NEXT_PUBLIC_BASE_PATH", basePath);
  return import("@/lib/base-path");
}

afterEach(() => {
  vi.unstubAllEnvs();
});

describe("withBasePath (Sprint 058 — served at browsejobs.ai/taurusai)", () => {
  it("leaves every path alone when the app is served at the root", async () => {
    const { BASE_PATH, withBasePath } = await load(undefined);
    expect(BASE_PATH).toBe("");
    expect(withBasePath("/signup")).toBe("/signup");
    expect(withBasePath("/api/public/channels/x/messages")).toBe("/api/public/channels/x/messages");
  });

  it("prefixes app paths with the mount path, once", async () => {
    const { BASE_PATH, withBasePath } = await load("/taurusai/");
    expect(BASE_PATH).toBe("/taurusai");
    expect(withBasePath("/signup")).toBe("/taurusai/signup");
    expect(withBasePath("/dashboard?x=1")).toBe("/taurusai/dashboard?x=1");
    expect(withBasePath("/taurusai/dashboard")).toBe("/taurusai/dashboard");
    expect(withBasePath("/taurusai")).toBe("/taurusai");
  });

  it("never touches anchors, other schemes or absolute URLs", async () => {
    const { withBasePath } = await load("/taurusai");
    for (const href of ["#pricing", "mailto:a@b.co", "tel:+911234", "https://slack.com/x", "//cdn.example.com/x.js", "relative/path"]) {
      expect(withBasePath(href)).toBe(href);
    }
  });
});
