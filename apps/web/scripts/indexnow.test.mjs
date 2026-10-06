import assert from "node:assert/strict";
import { test } from "node:test";
import { indexNowPayload, loadIndexNowKey, urlsFromSitemap } from "./indexnow.mjs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

test("sitemap locs for browsejobs.ai are kept", () => {
  const xml = `<?xml version="1.0"?>
<urlset>
  <url><loc>https://browsejobs.ai/answers</loc></url>
  <url><loc>https://browsejobs.ai/</loc></url>
  <url><loc>https://example.com/ignore</loc></url>
</urlset>`;
  assert.deepEqual(urlsFromSitemap(xml), [
    "https://browsejobs.ai/answers",
    "https://browsejobs.ai/",
  ]);
});

test("the committed key file matches its name", () => {
  const publicDir = join(dirname(fileURLToPath(import.meta.url)), "..", "public");
  const key = loadIndexNowKey(publicDir);
  assert.equal(key, "577828df9399c418e383248dd145664f");
  const payload = indexNowPayload({ host: "browsejobs.ai", key, urls: ["https://browsejobs.ai/"] });
  assert.equal(payload.keyLocation, `https://browsejobs.ai/${key}.txt`);
  assert.equal(payload.host, "browsejobs.ai");
});
