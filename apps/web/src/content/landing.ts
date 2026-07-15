/**
 * Landing content. Static for now; P1.8+ swaps this for the CMS-driven,
 * tenant-themed content API (PRD §6.23). Shape kept API-friendly.
 */

export const proofStats = [
  { label: "Learners trained", value: 12480, suffix: "+" },
  { label: "Placement rate", value: 82, suffix: "%" },
  { label: "Hiring partners", value: 140, suffix: "+" },
  { label: "Avg. package", value: 7.4, prefix: "₹", suffix: " LPA", decimals: 1 },
] as const;

export const journey = [
  {
    step: "01",
    title: "Free masterclass",
    body: "Start with a live, no-cost masterclass. See the teaching, meet the mentors, decide for yourself.",
  },
  {
    step: "02",
    title: "Free bootcamp",
    body: "Go deeper in a hands-on bootcamp. Build real projects and earn your seat in a paid batch.",
  },
  {
    step: "03",
    title: "Paid batch + AI coach",
    body: "Live classes, a personal AI coach tracking every topic, mock interviews, and recordings you own.",
  },
  {
    step: "04",
    title: "Placement",
    body: "CV built from your work, real interview practice, and a hiring-partner pipeline until you're placed.",
  },
] as const;

export const features = [
  {
    kicker: "Personal AI Coach",
    title: "Knows exactly what to fix next",
    body: "A coach panel that reads your attendance, quizzes and labs, then shows the single next best action to close your gap.",
  },
  {
    kicker: "Real Interview Practice",
    title: "Rehearse the actual questions",
    body: "AI mock interviews drawn from a bank of real interviews at real companies — with a scorecard after every round.",
  },
  {
    kicker: "Recordings You Own",
    title: "Never miss a class",
    body: "Every session recorded, searchable by topic, watermarked and always yours while your fees are clear.",
  },
  {
    kicker: "Placement Engine",
    title: "A pipeline, not a promise",
    body: "An ATS-ready CV from your projects, a live job feed matched to you, and a placement team in your corner.",
  },
] as const;

export const included = [
  { feature: "Live mentor-led classes", plan: true },
  { feature: "Personal AI coach + weekly report", plan: true },
  { feature: "Class recordings (while fees clear)", plan: true },
  { feature: "AI mock interviews", plan: "5 / course" },
  { feature: "1:1 human mentor sessions", plan: "pay-as-you-go" },
  { feature: "Placement support", plan: true },
] as const;

export const promisesKept = [
  "Live teaching from working engineers — every batch.",
  "Your fees pause access, never delete your progress.",
  "Placement support until you're placed, not a fixed window.",
  "Transparent pricing with EMI — no hidden charges.",
] as const;

export const promisesNever = [
  "No fake “100% guaranteed job” claims.",
  "No selling your data to third parties.",
  "No locked-in loans dressed up as “income share”.",
  "No pre-recorded videos passed off as live classes.",
] as const;

export const faqs = [
  {
    q: "Are classes actually live?",
    a: "Yes — every paid batch is live and mentor-led over Zoom, with recordings added after each session. We never pass pre-recorded video off as live.",
  },
  {
    q: "What does it cost, and is EMI available?",
    a: "Course fees are shown upfront with a single-payment or 1/2/3-month EMI option. You see the full schedule before you pay a rupee.",
  },
  {
    q: "Do you guarantee a job?",
    a: "No one honestly can. We provide placement support — CV, mock interviews, and a hiring-partner pipeline — until you're placed, and we publish our real placement rate.",
  },
  {
    q: "What if I miss a class?",
    a: "Every class is recorded and searchable by topic. Your AI coach also flags exactly what to catch up on.",
  },
] as const;

export const pricing = {
  fee: 45000,
  emiMonths: 3,
} as const;
