import { readFile } from "node:fs/promises";
import path from "node:path";
import { NextResponse } from "next/server";

/**
 * Course brochures at a clean, shareable URL: /brochures/data-engineering
 * opens the PDF in the browser rather than forcing a download.
 *
 * Adding a brochure needs no code: drop <slug>.pdf into public/brochures and
 * the URL works. The slug is whitelisted by a strict pattern before it ever
 * reaches the filesystem, so a crafted path like ../../.env cannot escape the
 * folder.
 */

/** Only lower-case words joined by single hyphens — no dots, no slashes. */
const SAFE_SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

/** "data-engineering" -> "BrowseJobs-Data-Engineering.pdf" for the saved file. */
function downloadName(slug: string): string {
  const words = slug
    .split("-")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join("-");

  return `BrowseJobs-${words}.pdf`;
}

export async function GET(_req: Request, ctx: { params: Promise<{ slug: string }> }) {
  const { slug } = await ctx.params;

  if (!SAFE_SLUG.test(slug)) {
    return NextResponse.json({ error: "unknown brochure" }, { status: 404 });
  }

  try {
    const file = await readFile(path.join(process.cwd(), "public", "brochures", `${slug}.pdf`));

    return new NextResponse(new Uint8Array(file), {
      headers: {
        "Content-Type": "application/pdf",
        // inline: it opens in the browser's PDF viewer; the filename is only
        // used if the visitor then saves it.
        "Content-Disposition": `inline; filename="${downloadName(slug)}"`,
        "Content-Length": String(file.byteLength),
        "Cache-Control": "public, max-age=3600, must-revalidate",
      },
    });
  } catch {
    return NextResponse.json({ error: "unknown brochure" }, { status: 404 });
  }
}
