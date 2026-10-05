import type { MetadataRoute } from "next";
import { SITE_ORIGIN } from "@/lib/seo";

/**
 * Private product surfaces. `/employers` (the public hiring page) stays
 * allowed: Google's longest-match rule prefers `Allow: /employers` over
 * `Disallow: /employer`.
 */
const PRIVATE = [
  "/admin",
  "/api/",
  "/candidate",
  "/employer",
  "/cv/",
  "/dashboard",
  "/tutor",
  "/mock",
  "/labs",
  "/grades",
  "/assignments",
  "/reports",
  "/support",
  "/notifications",
  "/profile",
  "/checkin",
  "/placement",
  "/certificates",
  "/recordings",
  "/classes",
  "/notes",
  "/flashcards",
  "/mcq",
  "/project",
  "/jobs-for-you",
  "/pulse",
  "/store",
  "/mentors",
  "/video",
];

const AI_CRAWLERS = [
  "GPTBot",
  "OAI-SearchBot",
  "ChatGPT-User",
  "ClaudeBot",
  "Claude-SearchBot",
  "Claude-User",
  "anthropic-ai",
  "Google-Extended",
  "PerplexityBot",
  "Perplexity-User",
  "Applebot-Extended",
  "CCBot",
  "Amazonbot",
  "Bytespider",
  "cohere-ai",
  "Meta-ExternalAgent",
];

function rule(userAgent: string) {
  return {
    userAgent,
    allow: ["/", "/employers"],
    disallow: PRIVATE,
  };
}

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [rule("*"), ...AI_CRAWLERS.map(rule)],
    sitemap: `${SITE_ORIGIN}/sitemap.xml`,
    host: SITE_ORIGIN,
  };
}
