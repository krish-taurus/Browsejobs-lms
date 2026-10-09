"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { MOCK_KINDS, MOCK_KIND_META, mockListPath, type MockKind } from "@/lib/mockKinds";

type Counts = Partial<Record<MockKind, number>>;

// Text practice is retired for students (Oct 2026) — only spoken interviews
// are offered. Old practice scorecards still open at their own URLs.
const STUDENT_KINDS = MOCK_KINDS.filter((kind) => kind !== "practice");

/**
 * Practice · Voice · Job · CV — one tab per interview kind, each its own URL.
 * Pass counts when the page already has them; otherwise they load here.
 */
export function MockKindTabs({ active, counts: given }: { active?: MockKind; counts?: Counts }) {
  const [loaded, setLoaded] = useState<Counts | null>(null);

  useEffect(() => {
    if (given) return;
    apiJson<{ data: { kind_counts?: Counts } }>("/api/v1/me/mocks")
      .then((r) => setLoaded(r.data.kind_counts ?? null))
      .catch(() => setLoaded(null));
  }, [given]);

  const counts = given ?? loaded;

  return (
    <nav aria-label="Interview types" className="flex gap-2 overflow-x-auto pb-1">
      {STUDENT_KINDS.map((kind) => {
        const on = kind === active;
        return (
          <Link
            key={kind}
            href={mockListPath(kind)}
            aria-current={on ? "page" : undefined}
            className={`flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors ${
              on ? "border-trust bg-trust text-white" : "border-line bg-white text-ink hover:border-trust"
            }`}
          >
            {MOCK_KIND_META[kind].label}
            {counts?.[kind] !== undefined && (
              <span className={`mono text-xs ${on ? "text-white/80" : "text-muted"}`}>{counts[kind]}</span>
            )}
          </Link>
        );
      })}
    </nav>
  );
}
