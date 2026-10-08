/** Unpublished stories render only on local preview and Cloudflare quick tunnels. */
export function isPreviewHost(host: string): boolean {
  const name = host.split(",")[0]?.trim().toLowerCase().replace(/:\d+$/, "") ?? "";
  if (name === "localhost" || name === "127.0.0.1" || name === "[::1]" || name === "::1") return true;
  return name.endsWith(".trycloudflare.com");
}
