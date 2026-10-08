"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import { MockHistory } from "@/components/mocks/MockHistory";
import { MockKindTabs } from "@/components/mocks/MockKindTabs";
import { MOCK_KIND_META, type MockKind } from "@/lib/mockKinds";

/**
 * The page behind /student-ai-mock/{kind}: that kind's start action (if it
 * has one) and a table of only that kind's sessions.
 */
export function MockKindPage({
  kind,
  counts,
  emptyText,
  children,
}: {
  kind: MockKind;
  counts?: Partial<Record<MockKind, number>>;
  emptyText: string;
  children?: ReactNode;
}) {
  const meta = MOCK_KIND_META[kind];

  return (
    <div className="mx-auto max-w-3xl">
      <Link href="/student-ai-mock" className="text-sm text-trust hover:underline">← Mock Interviews</Link>
      <h1 className="display mt-3 text-2xl text-ink">{meta.title}</h1>
      <p className="mt-1 text-sm text-muted">{meta.blurb}</p>

      <div className="mt-5">
        <MockKindTabs active={kind} counts={counts} />
      </div>

      {children}

      <h2 className="mt-8 text-sm font-semibold uppercase tracking-widest text-muted">Your {meta.label.toLowerCase()}</h2>
      <MockHistory kind={kind} emptyText={emptyText} />
    </div>
  );
}
