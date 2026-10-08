/**
 * WhatsApp screenshots supplied with the owner manifest.
 * `alt` is the manifest quote. `person` is the first name as written there.
 * Do not add details that are not in the manifest.
 */
export type WhatsAppShot = {
  id: string;
  src: string;
  /** Manifest quote, verbatim. */
  alt: string;
  headline: string;
  /** First name as shown, or null when the manifest names nobody. */
  person: string | null;
  date: string;
  category: string;
  width: number;
  height: number;
  objectPosition?: string;
  blurRegion?: {
    top?: string;
    bottom?: string;
    left?: string;
    width?: string;
    height?: string;
  };
  published: boolean;
};

export const whatsappShots: WhatsAppShot[] = [
  {
    id: "01-pranjal-career-restart",
    src: "/success/whatsapp/01-pranjal-career-restart.webp",
    alt: "After Krish sir's class this is third company I am joining 😅 … 2 years back I was stuck in life I had strong regret I am not doing anything in life despite post graduation in computer science. Same month I came across Krish sir's master class & without single doubt I just joined it to give one chance to myself",
    headline: "Joining third company after course",
    person: "Pranjal",
    date: "2026-09-08",
    category: "career-switch",
    width: 697,
    height: 1080,
    published: true,
  },
  {
    id: "02-support-role-to-accenture",
    src: "/success/whatsapp/02-support-role-to-accenture.webp",
    alt: "I received Accenture offer letter for 15LPA. … I was stucked for 3.5 yeas in support role but now with the help of browsejobs i am starting my new career journey.",
    headline: "Support role to Accenture offer",
    person: null,
    date: "2026-02-11",
    category: "career-switch",
    width: 904,
    height: 1080,
    published: true,
  },
  {
    id: "03-sujit-offer-letter",
    src: "/success/whatsapp/03-sujit-offer-letter.webp",
    alt: "And today, that prayer was answered, because today, I received my offer letter. … I spent 12 lakhs to study engineering, and yet engineering never taught me how to earn.",
    headline: "Received offer letter after doubt",
    person: "Sujit",
    date: "2026-05-16",
    category: "placement",
    width: 592,
    height: 1080,
    published: true,
  },
  {
    id: "04-ranganayaki-eli-lilly",
    src: "/success/whatsapp/04-ranganayaki-eli-lilly.webp",
    alt: "Good morning sir…. I will be reporting to Eli Lilly starting from today.. Thank you so much sir … I received my first month's salaray today.",
    headline: "Joined Eli Lilly, first salary",
    person: "Ranganayaki",
    date: "2025-09-05",
    category: "placement",
    width: 598,
    height: 1080,
    published: true,
  },
  {
    id: "05-gayathri-senior-data-engineer",
    src: "/success/whatsapp/05-gayathri-senior-data-engineer.webp",
    alt: "Today I joined ahana as senior data engineer.",
    headline: "Joined as Senior Data Engineer",
    person: "gayathrim",
    date: "2026-03-03",
    category: "placement",
    width: 561,
    height: 1080,
    published: true,
  },
  {
    id: "06-akshay-mphasis",
    src: "/success/whatsapp/06-akshay-mphasis.webp",
    alt: "I'm really happy to share that I'll be joining Mphasis tomorrow, and I truly carry forward all your blessings and best wishes. 🙏",
    headline: "Joining Mphasis",
    person: "Akshay",
    date: "2026-04-28",
    category: "placement",
    width: 680,
    height: 1080,
    published: true,
  },
  {
    id: "07-kavi-placed",
    src: "/success/whatsapp/07-kavi-placed.webp",
    alt: "On my last birthday, I was attending your class and wondering whether I would get a job or not. Today, by the grace of God and with your blessings, I am happy to share that I have been placed and am doing well in my role.",
    headline: "Placed, one birthday later",
    person: "Kavi",
    date: "2025-12-28",
    category: "placement",
    width: 820,
    height: 1080,
    published: true,
  },
  {
    id: "08-amarnath-offer-joining",
    src: "/success/whatsapp/08-amarnath-offer-joining.webp",
    alt: "Finally I got an offer sir and tomorrow is my joining day Thank you so much",
    headline: "Offer received, joining tomorrow",
    person: "Amarnath",
    date: "2026-05-13",
    category: "placement",
    width: 716,
    height: 1080,
    published: true,
  },
  {
    id: "09-rohith-phdata-alkye",
    src: "/success/whatsapp/09-rohith-phdata-alkye.webp",
    alt: "With your guidance, I was able to crack opportunities at both phData and Alkye. You truly made the impossible thing possible for me, and I'm really grateful for that.",
    headline: "Cracked phData and Alkye",
    person: "Rohith",
    date: "2026-05-22",
    category: "placement",
    width: 800,
    height: 1080,
    published: true,
  },
  {
    id: "10-indira-concertai",
    src: "/success/whatsapp/10-indira-concertai.webp",
    alt: "Finally ...got my offer letter from ConcertAI sir🥰 without your guidance, I can't achieve this.",
    headline: "Offer letter from ConcertAI",
    person: "Indira",
    date: "2025-11-26",
    category: "placement",
    width: 1115,
    height: 751,
    published: true,
  },
  {
    id: "11-ajeet-cleared-all-rounds",
    src: "/success/whatsapp/11-ajeet-cleared-all-rounds.webp",
    alt: "I have received an offer after successfully clearing all the interview rounds. 🎉",
    headline: "Offer after clearing all rounds",
    person: "Ajeet",
    date: "2026-06-24",
    category: "placement",
    width: 653,
    height: 1080,
    published: true,
  },
  {
    id: "12-neeraj-landed-job",
    src: "/success/whatsapp/12-neeraj-landed-job.webp",
    alt: "just trusted the process and after completing course it took me a month to land a job finally but that whole month filled with interviews, regret mails, countless applications, learnt so much.",
    headline: "Landed a job after course",
    person: "Neeraj",
    date: "2025-08-03",
    category: "placement",
    width: 509,
    height: 1080,
    published: true,
  },
  {
    id: "13-harika-one-year-placed",
    src: "/success/whatsapp/13-harika-one-year-placed.webp",
    alt: "today its 1 year since I got placed and I truly feel proud and grateful remembering your lovely training days.",
    headline: "One year since getting placed",
    person: "Harika",
    date: "2026-02-19",
    category: "placement",
    width: 562,
    height: 1080,
    published: true,
  },
  {
    id: "14-gajanan-lead-data-engineer",
    src: "/success/whatsapp/14-gajanan-lead-data-engineer.webp",
    alt: "In this project I am lead data engineer and I am the only one who is handling entire project. And I got appreciation from client for the same.It's all because of you krish 🙏.",
    headline: "Now lead data engineer on project",
    person: "Gajanan",
    date: "2025-09-05",
    category: "mentor",
    width: 648,
    height: 1080,
    published: true,
  },
];

export function visibleShots(preview: boolean): WhatsAppShot[] {
  return whatsappShots.filter((shot) => shot.published || preview);
}

/** Career switches first (manifest order already leads with them), then the rest. */
export function showcaseShots(shots: WhatsAppShot[]): WhatsAppShot[] {
  const lead = shots.filter((shot) => shot.category === "career-switch");
  const rest = shots.filter((shot) => shot.category !== "career-switch");
  return [...lead, ...rest];
}

