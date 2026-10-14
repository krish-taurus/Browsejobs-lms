import { OFFICE_PHONE, whatsappHref } from "@/content/phones";

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
    a: "Free counselling shows what's blocking you. If a live course closes that gap, that is the next step — Data Engineering, DevOps & Cloud, Python Backend, Data Analytics, or Agentic AI. We name the one that matches.",
  },
  {
    q: "When do I pay?",
    a: "Registration is ₹30,000 and it covers the course, and only after the free masterclass and the free 7-hour bootcamp. The placement fee is three months of the CTC on the offer you accept, charged in EMIs only after you accept. 30-day money-back, any reason, in writing.",
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
    a: "Yes. Agentic AI is a six-month live course in Python, large language models, RAG and AI agents, online and at the Whitefield classroom. Cyber Security and ServiceNow are still waitlist. The other live courses are Data Engineering, DevOps & Cloud, Python Backend and Data Analytics.",
  },
] as const;

/** Office WhatsApp line for the student interview page. */
export const WHATSAPP_SCREEN = whatsappHref(OFFICE_PHONE, "I'd like to start the free AI interview.");
