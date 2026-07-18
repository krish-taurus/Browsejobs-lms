import type { NextConfig } from "next";
import { cutoverRedirects } from "./src/lib/redirects";

const nextConfig: NextConfig = {
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
