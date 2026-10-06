/**
 * Submit every URL in the live browsejobs.ai sitemap to IndexNow.
 *
 * The key is public on purpose: IndexNow checks that
 * https://browsejobs.ai/<key>.txt contains the key. It is not a secret.
 *
 * After a production deploy:
 *   node apps/web/scripts/indexnow.mjs
 *
 * Preview the URL list without posting:
 *   node apps/web/scripts/indexnow.mjs --dry-run
 *
 * From CI, pass --soft so an unreachable IndexNow API exits 0.
 *   node apps/web/scripts/indexnow.mjs --soft
 */
import { readdirSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const HOST = "browsejobs.ai";
const SITEMAP_URL = process.env.INDEXNOW_SITEMAP_URL ?? `https://${HOST}/sitemap.xml`;
const ENDPOINT = "https://api.indexnow.org/indexnow";

export function urlsFromSitemap(xml) {
  return [...xml.matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/g)]
    .map((match) => match[1].trim())
    .filter((url) => url.startsWith(`https://${HOST}/`) || url === `https://${HOST}`);
}

export function loadIndexNowKey(publicDir) {
  const files = readdirSync(publicDir).filter((name) => /^[a-f0-9]{8,128}\.txt$/.test(name));
  const matches = files.filter((name) => {
    const key = name.slice(0, -4);
    const body = readFileSync(join(publicDir, name), "utf8").trim();
    return body === key;
  });
  if (matches.length !== 1) {
    throw new Error(`Expected one IndexNow key file in ${publicDir}, found ${matches.length}`);
  }
  return matches[0].slice(0, -4);
}

export function indexNowPayload({ host, key, urls }) {
  return {
    host,
    key,
    keyLocation: `https://${host}/${key}.txt`,
    urlList: urls,
  };
}

async function main() {
  const soft = process.argv.includes("--soft");
  const dryRun = process.argv.includes("--dry-run");
  const publicDir = join(dirname(fileURLToPath(import.meta.url)), "..", "public");
  const key = loadIndexNowKey(publicDir);

  let xml;
  try {
    const response = await fetch(SITEMAP_URL, { signal: AbortSignal.timeout(20_000) });
    if (!response.ok) throw new Error(`Sitemap responded ${response.status}`);
    xml = await response.text();
  } catch (error) {
    console.error(`Could not read ${SITEMAP_URL}`);
    console.error(error instanceof Error ? error.message : error);
    process.exit(soft ? 0 : 1);
  }

  const urls = urlsFromSitemap(xml);
  if (urls.length === 0) {
    console.error("Sitemap contained no browsejobs.ai URLs");
    process.exit(soft ? 0 : 1);
  }

  const payload = indexNowPayload({ host: HOST, key, urls });
  if (dryRun) {
    console.log(`Dry run: ${urls.length} URLs for ${payload.keyLocation}`);
    for (const url of urls) console.log(url);
    return;
  }

  try {
    const response = await fetch(ENDPOINT, {
      method: "POST",
      headers: { "Content-Type": "application/json; charset=utf-8" },
      body: JSON.stringify(payload),
      signal: AbortSignal.timeout(20_000),
    });
    const body = await response.text();
    if (response.ok || response.status === 202) {
      console.log(`IndexNow accepted ${urls.length} URLs (${response.status})`);
      return;
    }
    console.error(`IndexNow responded ${response.status}: ${body.slice(0, 500)}`);
    process.exit(soft ? 0 : 1);
  } catch (error) {
    console.error("IndexNow request failed");
    console.error(error instanceof Error ? error.message : error);
    process.exit(soft ? 0 : 1);
  }
}

const isCli = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isCli) {
  await main();
}
