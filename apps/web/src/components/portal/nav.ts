export type NavItem = {
  href: string;
  label: string;
  icon: string; // inline SVG path data (24x24)
  primary?: boolean; // shown in the mobile bottom bar
  short?: string; // compact label for the bottom bar
};

export type NavGroup = { label: string; items: NavItem[] };

const DASHBOARD: NavItem = { href: "/dashboard", label: "Dashboard", icon: "M3 10.5 12 4l9 6.5M5 9.5V20h14V9.5", primary: true };
const CLASSES: NavItem = { href: "/classes", label: "My Classes", icon: "M4 5h16v12H4zM8 20h8M12 17v3", primary: true, short: "Classes" };
const RECORDINGS: NavItem = { href: "/recordings", label: "Recordings", icon: "M4 6h16v12H4zM10 9l5 3-5 3z" };
const PRACTICE: NavItem = { href: "/labs", label: "Practice", icon: "M8 6 3 12l5 6M16 6l5 6-5 6M13 4l-2 16", primary: true };
const TUTOR: NavItem = { href: "/tutor", label: "AI Tutor", icon: "M12 3a7 7 0 0 0-7 7c0 2.4 1.2 4.1 3 5.3V18h8v-2.7c1.8-1.2 3-2.9 3-5.3a7 7 0 0 0-7-7ZM9 21h6" };
const MOCK: NavItem = { href: "/mock", label: "Mock Interviews", icon: "M12 3a4 4 0 0 1 4 4v3a4 4 0 0 1-8 0V7a4 4 0 0 1 4-4ZM6 10a6 6 0 0 0 12 0M12 16v3M8 21h8", primary: true, short: "Mock" };
const MENTORS: NavItem = { href: "/mentors", label: "Mentors", icon: "M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM2 21a7 7 0 0 1 14 0M16 3.5a4 4 0 0 1 0 7M17 14.5a7 7 0 0 1 5 6.5" };
const PLACEMENT: NavItem = { href: "/placement", label: "Placement", icon: "M4 8h16v12H4zM9 8V5a3 3 0 0 1 6 0v3M4 13h16" };
const JOBS: NavItem = { href: "/jobs-for-you", label: "Jobs for You", icon: "M4 8h16v12H4zM9 8V5a3 3 0 0 1 6 0v3M8 13h3", short: "Jobs" };
const CV: NavItem = { href: "/cv", label: "My CV", icon: "M6 3h9l3 3v15H6zM9 8h6M9 12h6M9 16h6M9 20h3" };
const GRADES: NavItem = { href: "/grades", label: "Grades", icon: "M6 3h9l3 3v15H6zM9 8h6M9 12h6M9 16h4" };
const REPORTS: NavItem = { href: "/reports", label: "Reports", icon: "M4 5h16v14H4zM8 9v6M12 7v8M16 11v4" };
const CERTIFICATES: NavItem = { href: "/certificates", label: "Certificates", icon: "M12 3l2.5 5 5.5.8-4 3.9.9 5.5L12 21l-4.9 2.6.9-5.5-4-3.9 5.5-.8zM8 20v-4M16 20v-4" };
const PULSE: NavItem = { href: "/pulse", label: "Pulse", icon: "M3 12h4l2-7 4 14 2-7h6" };
const ALERTS: NavItem = { href: "/notifications", label: "Alerts", icon: "M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6M10 20a2 2 0 0 0 4 0" };
const CHECKIN: NavItem = { href: "/checkin", label: "Check-in", icon: "M12 21s-7-4.5-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.5-9 9-9 9ZM8 11h2l1.5-3 2 5 1.5-2h2" };
const STORE: NavItem = { href: "/store", label: "Store", icon: "M4 7h16l-1 12H5L4 7ZM9 7a3 3 0 0 1 6 0" };
const SUPPORT: NavItem = { href: "/support", label: "Support", icon: "M12 3a9 9 0 0 0-9 9v5a2 2 0 0 0 2 2h2v-6H5v-1a7 7 0 0 1 14 0v1h-2v6h2a2 2 0 0 0 2-2v-5a9 9 0 0 0-9-9Z" };
const PROFILE: NavItem = { href: "/profile", label: "Profile", icon: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20a7 7 0 0 1 14 0" };

/** Grouped for the sidebar and the mobile menu sheet. */
export const navGroups: NavGroup[] = [
  { label: "Learn", items: [DASHBOARD, CLASSES, RECORDINGS, PRACTICE, TUTOR] },
  { label: "Progress", items: [GRADES, REPORTS, CERTIFICATES] },
  { label: "Career", items: [MOCK, MENTORS, PLACEMENT, JOBS, CV] },
  { label: "You", items: [PULSE, ALERTS, CHECKIN, STORE, SUPPORT, PROFILE] },
];

/** Flat list — the ⌘K command palette searches this. */
export const navItems: NavItem[] = navGroups.flatMap((g) => g.items);

/** The four destinations pinned to the mobile bottom bar (a "More" tab opens the rest). */
export const primaryTabs: NavItem[] = navItems.filter((i) => i.primary);
