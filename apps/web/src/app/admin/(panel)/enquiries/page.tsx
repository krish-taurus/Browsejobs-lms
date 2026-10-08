"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiBlob, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type EnquiryRow = {
  id: number;
  type: "employer" | "course";
  status: "new" | "contacted" | "qualified" | "closed";
  name: string;
  email: string;
  phone: string;
  company: string | null;
  city: string | null;
  course_slug: string | null;
  notified_at: string | null;
  notify_error: string | null;
  created_at: string | null;
};

const STATUSES = ["new", "contacted", "qualified", "closed"] as const;

const inputCls =
  "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function AdminEnquiriesPage() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState({ type: "", status: "", search: "" });
  const [banner, setBanner] = useState<string | null>(null);

  const query = useMemo(() => {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => value && params.set(key, value));
    return params.toString();
  }, [filters]);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["admin", "enquiries", filters],
    queryFn: () => apiJson<{ data: EnquiryRow[] }>(`/api/v1/admin/enquiries${query ? `?${query}` : ""}`),
  });

  const update = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      apiJson(`/api/v1/admin/enquiries/${id}`, { method: "PATCH", body: JSON.stringify({ status }) }),
    onSuccess: () => {
      setBanner(null);
      void queryClient.invalidateQueries({ queryKey: ["admin", "enquiries"] });
    },
    onError: (error) => setBanner(error instanceof ApiError ? error.firstError ?? error.message : "Could not update that enquiry."),
  });

  async function download() {
    try {
      const blob = await apiBlob(`/api/v1/admin/enquiries/export${query ? `?${query}` : ""}`);
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = "enquiries.csv";
      link.click();
      URL.revokeObjectURL(url);
    } catch {
      setBanner("Could not download the CSV.");
    }
  }

  const rows = data?.data ?? [];

  return (
    <div className="mx-auto max-w-6xl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="kicker text-trust">CRM</p>
          <h1 className="display mt-2 text-3xl text-ink">Enquiries</h1>
        </div>
        <button
          type="button"
          onClick={() => void download()}
          className="rounded-full border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-paper"
        >
          Download CSV
        </button>
      </div>

      {banner ? <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{banner}</p> : null}

      <div className="mt-6 flex flex-wrap items-end gap-3 rounded-[14px] border border-line bg-white p-4">
        <input
          placeholder="Search name, email, phone, company…"
          value={filters.search}
          onChange={(event) => setFilters({ ...filters, search: event.target.value })}
          className={`${inputCls} min-w-[220px] flex-1`}
        />
        <select value={filters.type} onChange={(event) => setFilters({ ...filters, type: event.target.value })} className={inputCls}>
          <option value="">All types</option>
          <option value="employer">Employer</option>
          <option value="course">Course</option>
        </select>
        <select value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })} className={inputCls}>
          <option value="">All statuses</option>
          {STATUSES.map((status) => (
            <option key={status} value={status}>
              {status}
            </option>
          ))}
        </select>
      </div>

      {isLoading ? <p className="mt-8 text-sm text-muted">Loading enquiries…</p> : null}
      {isError ? <p className="mt-8 text-sm text-warn">The enquiry list could not be loaded.</p> : null}

      {!isLoading && !isError && rows.length === 0 ? (
        <div className="mt-8">
          <EmptyState
            title="No enquiries yet"
            body="Employer and course forms land here. Share the enquire pages when you are ready for the next conversation."
          />
        </div>
      ) : null}

      {rows.length > 0 ? (
        <div className="mt-6 overflow-x-auto rounded-[14px] border border-line bg-white">
          <table className="w-full min-w-[760px] text-left text-sm">
            <thead className="border-b border-line text-xs uppercase tracking-widest text-muted">
              <tr>
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Type</th>
                <th className="px-4 py-3 font-medium">Contact</th>
                <th className="px-4 py-3 font-medium">About</th>
                <th className="px-4 py-3 font-medium">Alert</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-line last:border-0">
                  <td className="px-4 py-3">
                    <p className="font-medium text-ink">{row.name}</p>
                    <p className="mono text-xs text-muted">{row.created_at ? new Date(row.created_at).toLocaleString("en-IN", { timeZone: "Asia/Kolkata" }) : ""}</p>
                  </td>
                  <td className="px-4 py-3 capitalize text-ink">{row.type}</td>
                  <td className="px-4 py-3">
                    <p className="mono text-ink">{row.phone}</p>
                    <p className="text-muted">{row.email}</p>
                  </td>
                  <td className="px-4 py-3 text-ink">{row.type === "employer" ? row.company : row.course_slug}</td>
                  <td className="px-4 py-3">
                    {row.notified_at ? (
                      <span className="text-ink">Sent</span>
                    ) : (
                      <span className="text-muted">
                        Not sent
                        {row.notify_error ? <span className="mt-1 block text-xs">{row.notify_error}</span> : null}
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <select
                      aria-label={`Status for ${row.name}`}
                      className={inputCls}
                      value={row.status}
                      onChange={(event) => update.mutate({ id: row.id, status: event.target.value })}
                    >
                      {STATUSES.map((status) => (
                        <option key={status} value={status}>
                          {status}
                        </option>
                      ))}
                    </select>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
