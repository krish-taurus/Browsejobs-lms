export type NavItem = {
  href: string;
  label: string;
  icon: string; // inline SVG path data (24x24)
};

export const navItems: NavItem[] = [
  {
    href: "/dashboard",
    label: "Dashboard",
    icon: "M3 10.5 12 4l9 6.5M5 9.5V20h14V9.5",
  },
  {
    href: "/classes",
    label: "My Classes",
    icon: "M4 5h16v12H4zM8 20h8M12 17v3",
  },
  {
    href: "/recordings",
    label: "Recordings",
    icon: "M4 6h16v12H4zM10 9l5 3-5 3z",
  },
  {
    href: "/notifications",
    label: "Alerts",
    icon: "M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6M10 20a2 2 0 0 0 4 0",
  },
  {
    href: "/labs",
    label: "Practice",
    icon: "M8 6 3 12l5 6M16 6l5 6-5 6M13 4l-2 16",
  },
  {
    href: "/tutor",
    label: "AI Tutor",
    icon: "M12 3a7 7 0 0 0-7 7c0 2.4 1.2 4.1 3 5.3V18h8v-2.7c1.8-1.2 3-2.9 3-5.3a7 7 0 0 0-7-7ZM9 21h6",
  },
  {
    href: "/grades",
    label: "Grades",
    icon: "M6 3h9l3 3v15H6zM9 8h6M9 12h6M9 16h4",
  },
  {
    href: "/reports",
    label: "Reports",
    icon: "M4 5h16v14H4zM8 9v6M12 7v8M16 11v4",
  },
  {
    href: "/certificates",
    label: "Certificates",
    icon: "M12 3l2.5 5 5.5.8-4 3.9.9 5.5L12 21l-4.9 2.6.9-5.5-4-3.9 5.5-.8zM8 20v-4M16 20v-4",
  },
  {
    href: "/store",
    label: "Store",
    icon: "M4 7h16l-1 12H5L4 7ZM9 7a3 3 0 0 1 6 0",
  },
  {
    href: "/support",
    label: "Support",
    icon: "M12 3a9 9 0 0 0-9 9v5a2 2 0 0 0 2 2h2v-6H5v-1a7 7 0 0 1 14 0v1h-2v6h2a2 2 0 0 0 2-2v-5a9 9 0 0 0-9-9Z",
  },
  {
    href: "/profile",
    label: "Profile",
    icon: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20a7 7 0 0 1 14 0",
  },
];
