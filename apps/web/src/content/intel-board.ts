/**
 * Intelligence Board — typed contract for the market-intelligence API.
 * ILLUSTRATIVE until the backend news/funding aggregator ships: sector-level
 * signals only (no named companies, no invented counts). Directions, not
 * guarantees — the shared <Disclaimer/> renders with this board.
 */

export type FundingSignal = {
  sector: string;
  stage: string;
  hub: string;
  /** Rough lag between a raise and hiring ramp, in months. */
  hiringLagMonths: number;
  roles: string[];
};

export const FUNDING_SIGNALS: FundingSignal[] = [
  { sector: "Fintech", stage: "Late-stage raises", hub: "Bengaluru", hiringLagMonths: 3, roles: ["Data Engineering", "Backend"] },
  { sector: "Healthtech", stage: "Growth rounds", hub: "Hyderabad", hiringLagMonths: 4, roles: ["Data Analytics", "Cloud"] },
  { sector: "GCC expansion", stage: "New centres announced", hub: "Pune / NCR", hiringLagMonths: 5, roles: ["DevOps", "Data Engineering"] },
  { sector: "AI infrastructure", stage: "Fresh capital", hub: "Bengaluru", hiringLagMonths: 2, roles: ["Python", "MLOps"] },
];

export type OutlookSeries = {
  track: string;
  /** Relative signal index 0–100; last three points are the projection. */
  points: number[];
  direction: "rising" | "steady";
};

export const DEMAND_OUTLOOK: OutlookSeries[] = [
  { track: "Data Engineering", points: [38, 44, 47, 55, 58, 66, 71, 78, 84], direction: "rising" },
  { track: "DevOps & Cloud", points: [42, 45, 50, 52, 58, 61, 66, 70, 74], direction: "rising" },
  { track: "Data Analytics", points: [40, 43, 45, 49, 51, 54, 58, 61, 63], direction: "rising" },
  { track: "Python Backend", points: [36, 38, 41, 42, 45, 47, 49, 52, 54], direction: "steady" },
];

export const LOOP = [
  { step: "SIGNAL", title: "Read the market", body: "Funding news, hiring posts and ~50 interviews a day become one demand picture." },
  { step: "SYLLABUS", title: "Rebuild the training", body: "What is rising enters the syllabus next month. What faded gets cut." },
  { step: "PROOF", title: "Train against real questions", body: "Mocks are scored on what companies actually asked, not textbook sets." },
  { step: "OFFER", title: "Place into the demand", body: "Your profile is worked toward the sectors that are ramping — until you accept." },
] as const;
