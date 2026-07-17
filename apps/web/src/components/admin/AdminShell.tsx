"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { AuthProvider, useAuth } from "@/lib/auth";

/** Grouped so 30+ destinations stay scannable (one flat list is not). */
const navGroups: { label: string; items: { href: string; label: string }[] }[] = [
  {
    label: "Teaching",
    items: [
      { href: "/admin/curriculum", label: "Curriculum" },
      { href: "/admin/syllabus", label: "Syllabus" },
      { href: "/admin/content", label: "Class notes" },
      { href: "/admin/assignments", label: "Assignments" },
      { href: "/admin/grading", label: "Grading" },
      { href: "/admin/quizzes", label: "Quizzes" },
      { href: "/admin/certificates", label: "Certificates" },
      { href: "/admin/knowledge", label: "Tutor KB" },
    ],
  },
  {
    label: "Students",
    items: [
      { href: "/admin/batches", label: "Batches" },
      { href: "/admin/students", label: "Candidates" },
    ],
  },
  {
    label: "Careers",
    items: [
      { href: "/admin/mocks", label: "Mocks" },
      { href: "/admin/interviews", label: "Interview bank" },
      { href: "/admin/placements", label: "Placements" },
      { href: "/admin/cvs", label: "CV approvals" },
      { href: "/admin/mentors", label: "Mentors" },
      { href: "/admin/mentoring", label: "My mentoring" },
    ],
  },
  {
    label: "Sales & CRM",
    items: [
      { href: "/admin/leads", label: "Leads" },
      { href: "/admin/tasks", label: "Tasks" },
      { href: "/admin/funnel", label: "Funnel" },
      { href: "/admin/engagement", label: "Engagement" },
      { href: "/admin/reviews", label: "Testimonials" },
    ],
  },
  {
    label: "Finance",
    items: [
      { href: "/admin/payments", label: "Payments" },
      { href: "/admin/dunning", label: "Dunning" },
      { href: "/admin/vouchers", label: "Vouchers" },
      { href: "/admin/monetization", label: "Monetization" },
      { href: "/admin/revenue", label: "Revenue" },
      { href: "/admin/points", label: "Points" },
    ],
  },
  {
    label: "Retention & Support",
    items: [
      { href: "/admin/support", label: "Support" },
      { href: "/admin/care", label: "Care desk" },
      { href: "/admin/risk", label: "Risk" },
    ],
  },
  {
    label: "Platform",
    items: [
      { href: "/admin/messages", label: "Messaging" },
      { href: "/admin/ai-usage", label: "AI usage" },
    ],
  },
];

const STAFF_ROLES = new Set([
  "super-admin",
  "admin",
  "trainer",
  "mentor",
  "counselor",
  "placement-officer",
  "support-agent",
]);

function Guarded({ children }: { children: ReactNode }) {
  const { user, loading, logout } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const reduce = useReducedMotion();
  const [menuOpen, setMenuOpen] = useState(false);

  // Close the mobile menu on navigation.
  useEffect(() => { setMenuOpen(false); }, [pathname]);

  const isStaff = user?.roles.some((r) => STAFF_ROLES.has(r)) ?? false;

  useEffect(() => {
    if (!loading && (!user || !isStaff)) router.replace("/admin");
  }, [loading, user, isStaff, router]);

  if (loading || !user || !isStaff) {
    return (
      <div className="grid min-h-screen place-items-center">
        <div className="shimmer h-12 w-12 rounded-full" />
      </div>
    );
  }

  return (
    <div className="min-h-screen md:grid md:grid-cols-[230px_1fr]">
      <aside className="hidden border-r border-line bg-ink text-white md:flex md:flex-col">
        <div className="flex items-center gap-2 px-6 py-5">
          <span className="grid h-8 w-8 place-items-center rounded-lg bg-white text-ink display">B</span>
          <div>
            <span className="display block leading-none">BrowseJobs</span>
            <span className="mono text-[10px] uppercase tracking-widest text-sky/60">Admin</span>
          </div>
        </div>
        <nav className="flex-1 space-y-4 overflow-y-auto px-3 pb-4">
          {navGroups.map((group) => (
            <div key={group.label}>
              <p className="mono px-3 pb-1 text-[10px] uppercase tracking-widest text-sky/40">{group.label}</p>
              <div className="space-y-0.5">
                {group.items.map((item) => {
                  const active = pathname.startsWith(item.href);
                  return (
                    <Link
                      key={item.href}
                      href={item.href}
                      className={`block rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                        active ? "bg-white/10 text-white" : "text-sky/60 hover:bg-white/5 hover:text-white"
                      }`}
                    >
                      {item.label}
                    </Link>
                  );
                })}
              </div>
            </div>
          ))}
        </nav>
        <button
          onClick={() => logout().then(() => router.replace("/admin"))}
          className="m-3 rounded-lg px-3 py-2.5 text-left text-sm text-sky/60 hover:bg-white/5 hover:text-white"
        >
          Sign out
        </button>
      </aside>

      <div className="flex min-h-screen flex-col">
        <header className="flex items-center justify-between border-b border-line bg-white/80 px-5 py-3 backdrop-blur-md">
          <p className="text-sm text-muted">
            <span className="font-semibold text-ink">{user.name}</span>
            <span className="mono ml-2 text-xs uppercase tracking-widest text-muted">
              {user.roles.join(" · ")}
            </span>
          </p>
          <button
            onClick={() => setMenuOpen((o) => !o)}
            className="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink md:hidden"
            aria-expanded={menuOpen}
          >
            {menuOpen ? "Close" : "Menu"}
          </button>
        </header>

        {/* Mobile grouped menu — replaces the old flat row that dumped all 30+ links. */}
        {menuOpen && (
          <div className="border-b border-line bg-white px-5 py-4 md:hidden">
            <div className="grid grid-cols-2 gap-x-4 gap-y-4">
              {navGroups.map((group) => (
                <div key={group.label}>
                  <p className="mono pb-1 text-[10px] uppercase tracking-widest text-muted">{group.label}</p>
                  <div className="space-y-0.5">
                    {group.items.map((item) => (
                      <Link
                        key={item.href}
                        href={item.href}
                        className={`block py-1 text-sm font-medium ${pathname.startsWith(item.href) ? "text-trust" : "text-ink"}`}
                      >
                        {item.label}
                      </Link>
                    ))}
                  </div>
                </div>
              ))}
            </div>
            <button
              onClick={() => logout().then(() => router.replace("/admin"))}
              className="mt-4 text-sm font-medium text-muted"
            >
              Sign out
            </button>
          </div>
        )}

        <motion.main
          key={pathname}
          initial={reduce ? false : { opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: durations.base, ease }}
          className="flex-1 px-5 py-8 md:px-8"
        >
          {children}
        </motion.main>
      </div>
    </div>
  );
}

export function AdminShell({ children }: { children: ReactNode }) {
  const [client] = useState(() => new QueryClient({
    defaultOptions: { queries: { retry: 1, staleTime: 15_000 } },
  }));

  return (
    <QueryClientProvider client={client}>
      <AuthProvider>
        <Guarded>{children}</Guarded>
      </AuthProvider>
    </QueryClientProvider>
  );
}
