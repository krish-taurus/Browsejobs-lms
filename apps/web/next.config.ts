import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Allow the local dev server to be reached via either host during e2e/dev.
  allowedDevOrigins: ["127.0.0.1", "localhost"],
  // Consume the shared workspace package's TypeScript source directly.
  transpilePackages: ["@browsejobs/shared"],
};

export default nextConfig;
