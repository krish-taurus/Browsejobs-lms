/**
 * Dumps everything a brochure prints — course syllabi, landing copy and the
 * brochure-only panels — into one JSON payload on stdout, for `generate.py`
 * to render. Node strips the TypeScript types natively (Node >= 22), so this
 * runs with no build step and no extra dependency.
 *
 * The point of this file is that the PDFs and the website read the SAME
 * source: change a module in `src/content/courses.ts` and the next brochure
 * build prints it. Nothing is retyped into the generator.
 *
 *   node scripts/brochure/export-content.mts > scripts/brochure/.content.json
 */

import { courseDetails } from "../../src/content/courses.ts";
import {
  careerPanels,
  brochureTestimonials,
  deploymentStory,
  entityLine,
  founderQuote,
  platformFeatures,
  syllabusEngine,
} from "../../src/content/brochure.ts";
import {
  careerServices,
  placementChannels,
  situationCards,
} from "../../src/content/courses.ts";
import {
  DISCLAIMER,
  FOOTER_LINE,
  contact,
  fees,
  freeLadder,
  promisesKept,
  promisesNever,
  reviewAggregates,
  stats,
  verifyChecks,
} from "../../src/content/landing.ts";

/** Only tracks with a real syllabus get a brochure — an empty one would sell air. */
const printable = courseDetails.filter((course) => course.hasSyllabus && course.modules.length > 0);

if (printable.length === 0) {
  console.error("No course has a syllabus loaded — nothing to print.");
  process.exit(1);
}

const payload = {
  courses: printable.map((course) => ({
    ...course,
    careerPanel: careerPanels[course.slug] ?? null,
  })),
  shared: {
    stats,
    reviewAggregates,
    disclaimer: DISCLAIMER,
    footerLine: FOOTER_LINE,
    contact,
    fees,
    freeLadder,
    promisesKept,
    promisesNever,
    verifyChecks,
    situationCards,
    placementChannels,
    careerServices,
    syllabusEngine,
    platformFeatures,
    deploymentStory,
    founderQuote,
    testimonials: brochureTestimonials,
    entityLine,
  },
};

process.stdout.write(JSON.stringify(payload, null, 2));
