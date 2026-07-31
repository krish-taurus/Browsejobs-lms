"use client";

import { useRouter } from "next/navigation";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { employerApi, type EmployerJobRow } from "@/lib/employer";

type Item = { id: string; label: string; hint: string; go: () => void };

/**
 * ⌘K / Ctrl-K palette (PRD-E §3): jump to any screen or any JD in two
 * keystrokes. Jobs load lazily on first open so the palette costs
 * nothing until used.
 */
export function CommandPalette({ workspaceId }: { workspaceId: number }) {
  const router = useRouter();
  const reduce = useReducedMotion();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [selected, setSelected] = useState(0);
  const [jobs, setJobs] = useState<EmployerJobRow[] | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setOpen((o) => !o);
      }
      if (e.key === "Escape") setOpen(false);
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  useEffect(() => {
    if (open) {
      setQuery("");
      setSelected(0);
      requestAnimationFrame(() => inputRef.current?.focus());
      if (jobs === null) {
        employerApi.jobs(workspaceId).then((res) => setJobs(res.data)).catch(() => setJobs([]));
      }
    }
  }, [open, jobs, workspaceId]);

  const go = useCallback((href: string) => {
    setOpen(false);
    router.push(href);
  }, [router]);

  const items = useMemo<Item[]>(() => {
    const nav: Item[] = [
      { id: "nav-dash", label: "Dashboard", hint: "Go to", go: () => go("/employer/dashboard") },
      { id: "nav-jobs", label: "Jobs", hint: "Go to", go: () => go("/employer/jobs") },
      { id: "nav-team", label: "Team", hint: "Go to", go: () => go("/employer/team") },
      { id: "act-new-job", label: "Post a new JD", hint: "Action", go: () => go("/employer/jobs/new") },
    ];
    const jobItems: Item[] = (jobs ?? []).map((j) => ({
      id: `job-${j.id}`,
      label: j.title,
      hint: `JD · ${j.status}`,
      go: () => go(`/employer/jobs/${j.id}`),
    }));
    const all = [...nav, ...jobItems];
    const q = query.trim().toLowerCase();
    if (!q) return all;
    return all.filter((i) => i.label.toLowerCase().includes(q));
  }, [jobs, query, go]);

  useEffect(() => { setSelected(0); }, [query]);

  function onInputKey(e: React.KeyboardEvent) {
    if (e.key === "ArrowDown") {
      e.preventDefault();
      setSelected((s) => Math.min(s + 1, items.length - 1));
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setSelected((s) => Math.max(s - 1, 0));
    } else if (e.key === "Enter" && items[selected]) {
      items[selected].go();
    }
  }

  return (
    <AnimatePresence>
      {open && (
        <motion.div
          initial={reduce ? false : { opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: durations.fast, ease }}
          className="fixed inset-0 z-50 bg-ink/40 p-4 backdrop-blur-sm"
          onClick={() => setOpen(false)}
        >
          <motion.div
            initial={reduce ? false : { opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: durations.base, ease }}
            className="mx-auto mt-[10vh] w-full max-w-lg overflow-hidden rounded-panel border border-line bg-[#0a0f1c] shadow-soft"
            onClick={(e) => e.stopPropagation()}
            role="dialog"
            aria-label="Command palette"
          >
            <input
              ref={inputRef}
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              onKeyDown={onInputKey}
              placeholder="Jump to a screen or JD…"
              className="w-full border-b border-line px-5 py-4 text-sm outline-none"
              aria-label="Search commands"
            />
            <ul className="max-h-72 overflow-y-auto py-2">
              {items.length === 0 && (
                <li className="px-5 py-6 text-center text-sm text-muted">
                  Nothing matches “{query}”. Try a JD title or “jobs”.
                </li>
              )}
              {items.map((item, i) => (
                <li key={item.id}>
                  <button
                    onMouseEnter={() => setSelected(i)}
                    onClick={item.go}
                    className={`flex w-full items-center justify-between px-5 py-2.5 text-left text-sm ${
                      i === selected ? "bg-sky text-ink" : "text-ink"
                    }`}
                  >
                    <span className="font-medium">{item.label}</span>
                    <span className="mono text-[10px] uppercase tracking-widest text-muted">{item.hint}</span>
                  </button>
                </li>
              ))}
            </ul>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
