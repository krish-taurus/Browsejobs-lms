/**
 * Real student stories. Leave `published: false` until the person's own
 * words, name, and outcome are in hand. Do not invent any of those fields.
 * Unpublished items render only on preview hosts.
 */
export type SuccessStory = {
  id: string;
  /** Journey title, e.g. "From homemaker to engineer". */
  title: string;
  beforeRole: string;
  afterRole: string;
  /** Process steps. Not a claim that a named person completed them. */
  path: readonly string[];
  published: boolean;
  /** Fill only when the story is real. */
  name?: string;
  quote?: string;
  photo?: string;
  how?: string;
  /** Opens this WhatsApp screenshot. */
  shotId?: string;
};

const PATH = ["AI interview", "Counselling", "Course", "Retake", "Hired"] as const;

export const successStories: SuccessStory[] = [
  {
    id: "support-role-accenture",
    title: "From a 3.5-year support role to an Accenture offer",
    beforeRole: "Support role",
    afterRole: "Accenture offer",
    path: [],
    published: true,
    quote: "I was stucked for 3.5 yeas in support role but now with the help of browsejobs i am starting my new career journey.",
    shotId: "02-support-role-to-accenture",
  },
  {
    id: "pranjal-career-restart",
    title: "Stuck after a CS post-grad → now joining a third company",
    beforeRole: "CS post-grad",
    afterRole: "Third company",
    path: [],
    published: true,
    name: "Pranjal",
    quote:
      "After Krish sir's class this is third company I am joining 😅 … 2 years back I was stuck in life I had strong regret I am not doing anything in life despite post graduation in computer science. Same month I came across Krish sir's master class & without single doubt I just joined it to give one chance to myself",
    shotId: "01-pranjal-career-restart",
  },
  {
    id: "homemaker-engineer",
    title: "From homemaker to engineer",
    beforeRole: "Homemaker",
    afterRole: "Engineer",
    path: PATH,
    published: false,
  },
  {
    id: "delivery-rider-engineer",
    title: "From delivery rider to engineer",
    beforeRole: "Delivery rider",
    afterRole: "Engineer",
    path: PATH,
    published: false,
  },
];

export function visibleStories(preview: boolean): SuccessStory[] {
  return preview ? successStories : successStories.filter((story) => story.published);
}
