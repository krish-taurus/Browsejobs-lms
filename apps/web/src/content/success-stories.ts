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
};

const PATH = ["AI interview", "Counselling", "Course", "Retake", "Hired"] as const;

export const successStories: SuccessStory[] = [
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
