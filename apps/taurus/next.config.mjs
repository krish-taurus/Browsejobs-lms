/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // Mounted at browsejobs.ai/taurusai in production (see src/lib/base-path.ts);
  // unset = served at the root, as on Vercel and in local dev.
  ...(process.env.NEXT_PUBLIC_BASE_PATH ? { basePath: process.env.NEXT_PUBLIC_BASE_PATH.replace(/\/+$/, "") } : {}),
  experimental: {
    // Keep the document-extraction libraries out of the webpack bundle; they load
    // their own assets at runtime and only ever run server-side.
    serverComponentsExternalPackages: ["pdf-parse", "mammoth", "pg", "mysql2"],
  },
};

export default nextConfig;
