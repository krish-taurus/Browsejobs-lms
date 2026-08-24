import type { NextConfig } from "next";
import { cutoverRedirects } from "./src/lib/redirects";

const nextConfig: NextConfig = {
  // Build somewhere else, then swap the folder in. Building straight into
  // .next while `next start` is serving from it hands live visitors a blank
  // page: the running server looks for chunks that the build has just replaced.
  distDir: process.env.NEXT_DIST_DIR ?? ".next",
  // Allow the local dev server to be reached via either host during e2e/dev.
  allowedDevOrigins: ["127.0.0.1", "localhost"],
  // Consume the shared workspace package's TypeScript source directly.
  transpilePackages: ["@browsejobs/shared"],
  // Legacy → new 301 map + host canonicalisation for the browsejobs.ai cutover (P4.10).
  async redirects() {
    return cutoverRedirects;
  },
};

export default nextConfig;
