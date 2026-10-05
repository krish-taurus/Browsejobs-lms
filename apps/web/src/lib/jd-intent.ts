/**
 * Read a hiring request written the way somebody says it out loud.
 *
 * "2 senior React developers in Bangalore, 3-5 years, remote" carries a role,
 * a headcount, a place, a range and a work mode. Pulling those out here lets
 * the console ask about what is genuinely missing instead of marching every
 * employer through the same seven-field form.
 *
 * Nothing here writes the JD — that is the model behind /jd-draft. This only
 * decides what to send it, and what still needs asking.
 */

export type JdIntent = {
  title: string;
  experienceMin: number | null;
  experienceMax: number | null;
  locations: string[];
  openings: number | null;
  remote: boolean;
  notes: string;
};

/** Phrases that describe the asking, not the role. */
const NOISE = [
  "i want to hire",
  "we want to hire",
  "i would like to hire",
  "i need",
  "we need",
  "i am looking for",
  "we are looking for",
  "looking for",
  "post a jd for",
  "post a job for",
  "post jd for",
  "post a jd",
  "post jd",
  "create a jd for",
  "create jd for",
  "open a role for",
  "hire",
  "hiring",
  "recruit",
  "please",
  "urgently",
  "asap",
];

/** A place named after "in" / "at", stopping before the next clause. */
const CITY =
  /\b(?:in|at|based in|located in)\s+([A-Za-z][A-Za-z .'-]{2,30}?)(?=\s*(?:,|\.|;|$|\bwith\b|\bfor\b|\bexp\b|\bexperience\b|\byears?\b|\byrs?\b|\bremote\b|\bopenings?\b|\bpositions?\b))/gi;

function readExperience(text: string): { min: number | null; max: number | null } {
  const range = text.match(/(\d{1,2})\s*(?:-|–|to)\s*(\d{1,2})\s*\+?\s*(?:years?|yrs?)/i);
  if (range) {
    // Somebody typing "5-3 years" means the same as "3-5"; the API refuses a
    // max below the min, and a validation error there reads as our mistake.
    const [low, high] = [Number(range[1]), Number(range[2])].sort((x, y) => x - y);

    return { min: low, max: high };
  }

  const plus = text.match(/(\d{1,2})\s*\+\s*(?:years?|yrs?)/i);
  if (plus) return { min: Number(plus[1]), max: null };

  const single = text.match(/(?:min(?:imum)?|at least|above|over)?\s*(\d{1,2})\s*(?:years?|yrs?)/i);
  if (single) return { min: Number(single[1]), max: null };

  return { min: null, max: null };
}

function readOpenings(text: string): number | null {
  const explicit = text.match(/(\d{1,3})\s*(?:openings?|positions?|vacanc(?:y|ies)|seats?|roles?)/i);
  if (explicit) return Number(explicit[1]);

  // "hire 3 backend engineers" — a count sitting in front of the role itself.
  const leading = text.match(/\b(?:hire|need|want|looking for)\s+(?:a\s+)?(\d{1,3})\s+[a-z]/i);
  if (leading) return Number(leading[1]);

  return null;
}

function readLocations(text: string): string[] {
  const found: string[] = [];

  for (const match of text.matchAll(CITY)) {
    const place = match[1].trim().replace(/\s+/g, " ");

    // "in a hurry", "in total" — prepositional phrases that are not places.
    if (place.length > 2 && !/^(a|an|the|total|hurry|mind|house|india)\b/i.test(place)) {
      found.push(place.replace(/\b\w/g, (c) => c.toUpperCase()));
    }
  }

  return [...new Set(found)].slice(0, 5);
}

/**
 * The role is whatever sits before the first thing that plainly is not the
 * role — a comma, a location, a year range, a headcount, a work mode.
 */
function readTitle(text: string): string {
  const cuts = [
    text.search(/,/),
    text.search(/\bwith\b/i),
    text.search(/\b(?:in|at|based in|located in)\s+[A-Za-z]/i),
    text.search(/\d{1,2}\s*(?:-|–|to|\+)?\s*\d{0,2}\s*\+?\s*(?:years?|yrs?)/i),
    text.search(/\b(?:remote|work from home|wfh|onsite|hybrid)\b/i),
    text.search(/\d{1,3}\s*(?:openings?|positions?|vacanc(?:y|ies)|seats?)/i),
  ].filter((index) => index > 0);

  let head = cuts.length > 0 ? text.slice(0, Math.min(...cuts)) : text;

  for (const phrase of NOISE) {
    head = head.replace(new RegExp("\\b" + phrase + "\\b", "gi"), " ");
  }

  head = head
    .replace(/\s{2,}/g, " ")
    .replace(/^[\s,.;:–-]+|[\s,.;:–-]+$/g, "")
    // a leading headcount or article: "2 senior developers", "a Frontend Engineer"
    .replace(/^(?:\d{1,3}\s+)?(?:an?\s+)?/i, "")
    // plural role names read oddly on a JD: "Engineers" -> "Engineer"
    .replace(/\b(engineer|developer|designer|analyst|manager|architect|scientist|tester|intern|lead)s\b/gi, "$1")
    .replace(/^[\s,.;:–-]+|[\s,.;:–-]+$/g, "");

  return head.trim();
}

/**
 * Spelled-out numbers, so a spoken answer counts.
 *
 * Every number pattern here matches digits. Asked "how many years?" people
 * answer "five", and Chrome transcribes it as the word — which matched
 * nothing and silently became zero. A wrong number is worse than no number:
 * it is inherited by the mock interview and by every applicant's score.
 */
const NUMBER_WORDS: Record<string, number> = {
  zero: 0, one: 1, two: 2, three: 3, four: 4, five: 5, six: 6, seven: 7,
  eight: 8, nine: 9, ten: 10, eleven: 11, twelve: 12, thirteen: 13,
  fourteen: 14, fifteen: 15, sixteen: 16, seventeen: 17, eighteen: 18,
  nineteen: 19, twenty: 20, thirty: 30, forty: 40, fifty: 50,
  // How people actually say small counts out loud.
  couple: 2, few: 3, dozen: 12,
};

const TENS = new Set(["twenty", "thirty", "forty", "fifty"]);

/**
 * Rewrite spelled-out numbers as digits: "five to seven" becomes "5 to 7".
 *
 * Deliberately leaves "a" and "an" alone — "a Data Engineer" is a role, not
 * a headcount of one, and turning it into "1 Data Engineer" would put a
 * number where the employer never gave one.
 */
export function digitsFromWords(text: string): string {
  const words = text.split(/(\s+)/);
  const out: string[] = [];

  for (let i = 0; i < words.length; i += 1) {
    const word = words[i];
    const key = word.toLowerCase().replace(/[^a-z]/g, "");
    const value = NUMBER_WORDS[key];

    if (value === undefined) {
      out.push(word);
      continue;
    }

    // "twenty five" is one number, not two. Look past the space between.
    const nextWord = words[i + 2];
    const nextKey = nextWord?.toLowerCase().replace(/[^a-z]/g, "") ?? "";
    const nextValue = NUMBER_WORDS[nextKey];

    if (TENS.has(key) && nextValue !== undefined && nextValue >= 1 && nextValue <= 9) {
      out.push(String(value + nextValue));
      i += 2;
      continue;
    }

    out.push(String(value));
  }

  return out.join("");
}

export function readJdIntent(text: string): JdIntent {
  const clean = digitsFromWords(text.replace(/\s+/g, " ").trim());
  const experience = readExperience(clean);

  return {
    title: readTitle(clean),
    experienceMin: experience.min,
    experienceMax: experience.max,
    locations: readLocations(clean),
    openings: readOpenings(clean),
    remote: /\b(?:remote|work from home|wfh)\b/i.test(clean),
    notes: clean,
  };
}
