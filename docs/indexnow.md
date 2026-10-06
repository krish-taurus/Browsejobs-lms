# IndexNow

After a production deploy, tell IndexNow about every URL in the live sitemap so Bing and other participating engines can recrawl `browsejobs.ai`.

The key is public. IndexNow checks that `https://browsejobs.ai/<key>.txt` exists and contains the key. The file is `apps/web/public/577828df9399c418e383248dd145664f.txt`. Do not move it behind auth.

## Run it

From the repo root, after the new sitemap is live:

```bash
node apps/web/scripts/indexnow.mjs
```

Or from `apps/web`:

```bash
npm run indexnow
```

Preview the URL list without posting:

```bash
node apps/web/scripts/indexnow.mjs --dry-run
```

The script reads `https://browsejobs.ai/sitemap.xml` and POSTs the `browsejobs.ai` URLs to `https://api.indexnow.org/indexnow`.

## Deploy workflow

`.github/workflows/deploy.yml` runs the script on `main` after the production deploy, with `--soft`. If IndexNow is unreachable, that step exits 0 and does not fail the deploy. A pull request does not call IndexNow.

## Changing the key

Replace the public file with a new `<key>.txt` whose contents are the key, then deploy before you submit. The script discovers the file by matching the filename to its contents.
