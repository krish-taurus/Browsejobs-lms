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
    href: "/profile",
    label: "Profile",
    icon: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20a7 7 0 0 1 14 0",
  },
];
