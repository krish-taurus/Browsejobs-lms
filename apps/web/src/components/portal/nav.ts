export type NavItem = {
  href: string;
  label: string;
  icon: string; // inline SVG path data (24x24)
  primary?: boolean; // shown in the mobile bottom bar
  short?: string; // compact label for the bottom bar
};

/** A heading with its own sub-links. The parent names the area; the children go somewhere. */
export type NavParent = { label: string; icon: string; children: NavItem[] };

export type NavEntry = NavItem | NavParent;

export function isNavParent(entry: NavEntry): entry is NavParent {
  return "children" in entry;
}

export type NavGroup = { label: string; items: NavEntry[] };

const DASHBOARD: NavItem = { href: "/dashboard", label: "Dashboard", icon: "M3 10.5 12 4l9 6.5M5 9.5V20h14V9.5", primary: true };
const CLASSES: NavItem = { href: "/classes", label: "My Classes", icon: "M4 5h16v12H4zM8 20h8M12 17v3", primary: true, short: "Classes" };
const BATCH_CHAT: NavItem = { href: "/batch-chat", label: "Batch Chat", icon: "M4 5h16v11H8l-4 4V5ZM8 9h8M8 12h5", short: "Chat" };
const RECORDINGS: NavItem = { href: "/recordings", label: "Recordings", icon: "M4 6h16v12H4zM10 9l5 3-5 3z" };
const PRACTICE: NavItem = { href: "/labs", label: "Practice", icon: "M8 6 3 12l5 6M16 6l5 6-5 6M13 4l-2 16", primary: true };
const QUIZZES: NavItem = { href: "/quizzes", label: "Quizzes", icon: "M9 5h6M7 3h10a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1ZM9 11l2 2 4-4" };
const TUTOR: NavItem = { href: "/tutor", label: "AI Tutor", icon: "M12 3a7 7 0 0 0-7 7c0 2.4 1.2 4.1 3 5.3V18h8v-2.7c1.8-1.2 3-2.9 3-5.3a7 7 0 0 0-7-7ZM9 21h6" };
const AI_INTERVIEWS: NavItem = { href: "/student-ai-mock", label: "AI Interviews", icon: "M12 3a4 4 0 0 1 4 4v3a4 4 0 0 1-8 0V7a4 4 0 0 1 4-4ZM6 10a6 6 0 0 0 12 0M12 16v3M8 21h8", primary: true, short: "AI Mock" };
const ONE_TO_ONE: NavItem = { href: "/interviews", label: "One to One Interviews", icon: "M4 6h16v14H4zM8 3v4M16 3v4M4 10h16M9 15l2 2 4-4", primary: true, short: "1:1" };

/** Both kinds of practice interview live under one heading. */
const MOCK_INTERVIEWS: NavParent = {
  label: "Mock Interviews",
  icon: "M12 3a4 4 0 0 1 4 4v3a4 4 0 0 1-8 0V7a4 4 0 0 1 4-4ZM6 10a6 6 0 0 0 12 0M12 16v3M8 21h8",
  children: [AI_INTERVIEWS, ONE_TO_ONE],
};
const MENTORS: NavItem = { href: "/mentors", label: "Mentors", icon: "M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM2 21a7 7 0 0 1 14 0M16 3.5a4 4 0 0 1 0 7M17 14.5a7 7 0 0 1 5 6.5" };
const PLACEMENT: NavItem = { href: "/placement", label: "Placement", icon: "M4 8h16v12H4zM9 8V5a3 3 0 0 1 6 0v3M4 13h16" };
const JOBS: NavItem = { href: "/jobs-for-you", label: "Jobs for You", icon: "M4 8h16v12H4zM9 8V5a3 3 0 0 1 6 0v3M8 13h3", short: "Jobs" };
const CV: NavItem = { href: "/cv", label: "My CV", icon: "M6 3h9l3 3v15H6zM9 8h6M9 12h6M9 16h6M9 20h3" };
const GRADES: NavItem = { href: "/grades", label: "Grades", icon: "M6 3h9l3 3v15H6zM9 8h6M9 12h6M9 16h4" };
const REPORTS: NavItem = { href: "/reports", label: "Reports", icon: "M4 5h16v14H4zM8 9v6M12 7v8M16 11v4" };
const CERTIFICATES: NavItem = { href: "/certificates", label: "Certificates", icon: "M12 3l2.5 5 5.5.8-4 3.9.9 5.5L12 21l-4.9 2.6.9-5.5-4-3.9 5.5-.8zM8 20v-4M16 20v-4" };
const PULSE: NavItem = { href: "/pulse", label: "Pulse", icon: "M3 12h4l2-7 4 14 2-7h6" };
const STORE: NavItem = { href: "/store", label: "Store", icon: "M4 7h16l-1 12H5L4 7ZM9 7a3 3 0 0 1 6 0" };
const SUPPORT: NavItem = { href: "/support", label: "Support", icon: "M12 3a9 9 0 0 0-9 9v5a2 2 0 0 0 2 2h2v-6H5v-1a7 7 0 0 1 14 0v1h-2v6h2a2 2 0 0 0 2-2v-5a9 9 0 0 0-9-9Z" };
const PROFILE: NavItem = { href: "/profile", label: "Profile", icon: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20a7 7 0 0 1 14 0" };

/** Grouped for the sidebar and the mobile menu sheet. */
export const navGroups: NavGroup[] = [
  { label: "Learn", items: [DASHBOARD, CLASSES, BATCH_CHAT, RECORDINGS, PRACTICE, QUIZZES, TUTOR] },
  { label: "Progress", items: [GRADES, REPORTS, CERTIFICATES] },
  { label: "Career", items: [MOCK_INTERVIEWS, MENTORS, PLACEMENT, JOBS, CV, STORE] },
  // Alerts and Check-in removed from the menu (Oct 2026); both pages still open by URL.
  { label: "You", items: [PULSE, SUPPORT, PROFILE] },
];

/** Flat list of destinations — the ⌘K command palette searches this. */
export const navItems: NavItem[] = navGroups.flatMap((g) =>
  g.items.flatMap((entry) => (isNavParent(entry) ? entry.children : [entry])),
);

/** The four destinations pinned to the mobile bottom bar (a "More" tab opens the rest). */
export const primaryTabs: NavItem[] = navItems.filter((i) => i.primary);
