"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Testimonial = {
  id: number;
  rating: number;
  body: string;
  status: "pending" | "approved" | "rejected";
  has_video: boolean;
  video_url: string | null;
  created_at: string | null;
  student: { id: number; name: string; email: string | null } | null;
  voucher_issue: { code: string; status: string } | null;
};

const TABS = ["pending", "approved", "rejected"] as const;
type Tab = (typeof TABS)[number];

function Stars({ n }: { n: number }) {
  return (
    <span className="text-amber" aria-label={`${n} out of 5 stars`}>
      {"★".repeat(n)}
      <span className="text-line">{"★".repeat(5 - n)}</span>
    </span>
  );
}

export default function AdminReviewsPage() {
  const queryClient = useQueryClient();
  const [tab, setTab] = useState<Tab>("pending");
  const [error, setError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "testimonials", tab],
    queryFn: () => apiJson<{ data: Testimonial[] }>(`/api/v1/admin/testimonials?status=${tab}`),
  });

  const refresh = () => queryClient.invalidateQueries({ queryKey: ["admin", "testimonials"] });
  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const approve = useMutation({
    mutationFn: (id: number) => apiJson(`/api/v1/admin/testimonials/${id}/approve`, { method: "POST" }),
    onSuccess: () => { setError(null); void refresh(); },
    onError,
  });

  const reject = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      apiJson(`/api/v1/admin/testimonials/${id}/reject`, { method: "POST", body: JSON.stringify({ reason }) }),
    onSuccess: () => { setError(null); void refresh(); },
    onError,
  });

  const items = data?.data ?? [];

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Review-for-Voucher</p>
      <h1 className="display mt-2 text-3xl text-ink">Testimonials</h1>
      <p className="mt-2 text-sm text-muted">
        Approving publishes to the public reviews wall and issues the reward voucher. The voucher is tied to the
        platform testimonial only — never a Google review.
      </p>

      <div className="mt-6 flex gap-2">
        {TABS.map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`rounded-full px-4 py-1.5 text-sm font-medium capitalize transition-colors ${
              tab === t ? "bg-ink text-white" : "bg-paper text-muted hover:text-ink"
            }`}
          >
            {t}
          </button>
        ))}
      </div>

      {error && (
        <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
          {error} <button onClick={() => setError(null)} className="mono underline">dismiss</button>
        </p>
      )}

      {isLoading ? (
        <div className="mt-6 space-y-3">
          {Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-28 rounded-[14px]" />)}
        </div>
      ) : items.length === 0 ? (
        <div className="mt-6">
          <EmptyState title={`No ${tab} testimonials`} body="Testimonials submitted after a bootcamp land here for review." />
        </div>
      ) : (
        <div className="mt-6 space-y-3">
          {items.map((t) => (
            <div key={t.id} className="rounded-[14px] border border-line bg-white p-5">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <span className="font-semibold text-ink">{t.student?.name ?? "—"}</span>
                  <span className="mono ml-3 text-sm"><Stars n={t.rating} /></span>
                </div>
                {t.voucher_issue && (
                  <span className="mono rounded-full bg-verify-bg px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-verify">
                    voucher {t.voucher_issue.code}
                  </span>
                )}
              </div>
              <p className="mt-3 text-sm text-ink">“{t.body}”</p>
              {t.has_video && t.video_url && (
                <a href={t.video_url} target="_blank" rel="noreferrer" className="mono mt-2 inline-block text-xs text-trust hover:underline">
                  ▶ watch video
                </a>
              )}

              {t.status === "pending" && (
                <div className="mt-4 flex gap-3 text-sm font-semibold">
                  <button
                    onClick={() => approve.mutate(t.id)}
                    disabled={approve.isPending}
                    className="rounded-full bg-trust px-4 py-1.5 text-white hover:bg-deep disabled:opacity-50"
                  >
                    Approve & issue voucher
                  </button>
                  <button
                    onClick={() => {
                      const reason = window.prompt("Reason for rejecting?");
                      if (reason) reject.mutate({ id: t.id, reason });
                    }}
                    className="rounded-full border border-line px-4 py-1.5 text-ink hover:bg-paper"
                  >
                    Reject
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
