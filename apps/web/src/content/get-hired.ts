/**
 * /get-hired — for people who want a job and resist "another course".
 * Language matches the homepage funnel: short sentences, everyday words.
 * Commercial terms are the homepage's. Vignette numbers are labelled samples.
 * Do not add placement stats here.
 */

/** Three steps. Copy is rendered in full for SEO and reduced motion. */
export const REVERSE_STEPS = [
  {
    n: "01",
    kicker: "Interview",
    title: "You take a free AI interview",
    body: "Same style as a real screening. You answer out loud or in text. It is an interview, not a class. You book it. There is no button on this page that scores you on your own.",
  },
  {
    n: "02",
    kicker: "Score",
    title: "You get a clear score and feedback",
    body: "You see how deep your answers went, how clearly you spoke, and which skills showed up. The bars on this page are a sample. A real score stays empty until the round is graded. The score is a read of this round. It is not a promise of a job.",
  },
  {
    n: "03",
    kicker: "Outcome",
    title: "Two ways it can go",
    body: "Clear: we put you in front of HR with your score. Not clear: free counselling shows what's blocking you. A course comes only if you need it to close that gap.",
  },
] as const;

export const GET_HIRED_FAQ = [
  {
    q: "Do I have to buy a course to find out if I can get a job?",
    a: "No. You start with a free AI interview. You get a score and feedback. You keep that whether you join a course or not. A course comes only if you need it to close a gap.",
  },
  {
    q: "What actually happens on the free AI interview?",
    a: "You book it. We sit a mock-style interview, the same style as a real screening, and we write down where you are strong and where interviews would stall you. It is a conversation with a written result, not a self-serve button on this page. There is no public scored mock you can click and finish alone today.",
  },
  {
    q: "What if I don't clear?",
    a: "Free counselling shows what's blocking you. If a live course closes that gap, that is the next step — Data Engineering, DevOps & Cloud, Python Backend, or Data Analytics. We name the one that matches. Agentic AI is on the waitlist. We will not send you to a course page that is not open.",
  },
  {
    q: "When do I pay?",
    a: "Registration is ₹30,000, and only after the free masterclass and the free 7-hour bootcamp. Or 3 EMIs of ₹10,000. The placement fee is your first 3 months' pay, due only after you accept an offer, paid as 6 monthly EMIs, with the ₹30,000 taken off that bill. 30-day money-back, any reason, in writing.",
  },
  {
    q: "Do you guarantee a job?",
    a: "Nobody can guarantee employment — the market decides. What we put in writing is the process: a free AI interview, a score, feedback, and a placement effort that works the profile. Hiring is not certain. We will not say that it is.",
  },
  {
    q: "What does 'in front of HR' mean?",
    a: "If you clear, we put you in front of HR with your score. You are ranked on skills and that interview, not on who clicked apply first. The bar belongs to the role. A sample animation on this page is not a real pass. If you don't clear, free counselling shows what's blocking you. A course comes only if you need it. Nobody can guarantee the hire.",
  },
  {
    q: "What do recruiters see if I have not been graded?",
    a: "Empty. If the interview has not been graded, the score stays empty. We will not write a number in to make a shortlist look finished, and we will not tell an employer you are ready because you paid.",
  },
  {
    q: "Is there an Agentic AI course I can join now?",
    a: "No. Agentic AI, Cyber Security and ServiceNow are waitlist. The live courses are Data Engineering, DevOps & Cloud, Python Backend and Data Analytics. If an AI-shaped gap shows up, we start from a live course or a counselling conversation — not from a page that is not open.",
  },
] as const;

/** Published contact number, already used as the WhatsApp line on forms. */
export const WHATSAPP_SCREEN =
  "https://wa.me/918618519825?text=" +
  encodeURIComponent("I'd like to start the free AI interview.");
