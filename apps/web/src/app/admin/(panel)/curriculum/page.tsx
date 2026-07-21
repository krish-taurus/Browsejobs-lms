"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiBlob, apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type CourseRow = {
  id: number;
  code: string;
  name: string;
  status: string;
  tagline: string | null;
  modules_count: number;
  program: { id: number; name: string } | null;
};

type ImportSummary = { programs: number; courses: number; modules: number; topics: number; lessons: number };

function BulkImport() {
  const qc = useQueryClient();
  const [busy, setBusy] = useState(false);
  const [summary, setSummary] = useState<ImportSummary | null>(null);
  const [error, setError] = useState<string | null>(null);

  const downloadTemplate = async () => {
    const blob = await apiBlob("/api/v1/admin/syllabus/template");
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "browsejobs-syllabus-template.csv";
    a.click();
    URL.revokeObjectURL(url);
  };

  const upload = async (file: File) => {
    setBusy(true);
    setError(null);
    setSummary(null);
    try {
      const fd = new FormData();
      fd.append("file", file);
      const res = await apiJson<{ data: ImportSummary }>("/api/v1/admin/syllabus/import", {
        method: "POST",
        body: fd,
      });
      setSummary(res.data);
      void qc.invalidateQueries({ queryKey: ["admin", "courses"] });
    } catch (err) {
      if (err instanceof ApiError) {
        const details = (err.body as { errors?: Record<string, string[]> })?.errors?.file;
        setError(Array.isArray(details) ? details.slice(0, 6).join(" · ") : (err.firstError ?? err.message));
      } else {
        setError("Upload failed.");
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="mt-6 rounded-[14px] border border-dashed border-line bg-white p-5">
      <p className="kicker text-muted">Bulk import</p>
      <h2 className="mt-1 font-semibold text-ink">Build a whole syllabus from a spreadsheet</h2>
      <p className="mt-1 text-sm text-ink2/70">
        Download the template, fill it in Excel or Google Sheets (one row per lesson),
        and upload it. Programs, courses, modules and topics are created in one go —
        and every topic gets a Quiz, Assignment and Mock ready to fill. Re-uploading is safe.
      </p>
      <div className="mt-4 flex flex-wrap items-center gap-3">
        <button
          onClick={downloadTemplate}
          className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink transition-colors hover:border-trust"
        >
          Download CSV template
        </button>
        <label className="cursor-pointer rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep">
          {busy ? "Importing…" : "Upload filled CSV"}
          <input
            type="file"
            accept=".csv,text/csv"
            className="hidden"
            disabled={busy}
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) void upload(f);
              e.target.value = "";
            }}
          />
        </label>
      </div>
      {summary && (
        <p className="mono mt-3 rounded-[10px] bg-verify-bg px-3 py-2 text-xs text-verify">
          Imported: {summary.programs} programs · {summary.courses} courses · {summary.modules} modules ·{" "}
          {summary.topics} topics · {summary.lessons} lessons.
        </p>
      )}
      {error && (
        <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-xs text-warn">{error}</p>
      )}
    </section>
  );
}

export default function AdminCurriculumPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "courses"],
    queryFn: () => apiJson<{ data: CourseRow[] }>("/api/v1/admin/courses"),
  });

  return (
    <div className="mx-auto max-w-5xl">
      <p className="kicker text-trust">Curriculum</p>
      <h1 className="display mt-2 text-3xl text-ink">Programs &amp; syllabi</h1>

      <BulkImport />

      {isLoading ? (
        <div className="mt-8 space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="shimmer h-16 rounded-[14px]" />
          ))}
        </div>
      ) : !data?.data.length ? (
        <div className="mt-8">
          <EmptyState title="No courses yet" body="Seed or create courses to start building curriculum." />
        </div>
      ) : (
        <div className="mt-8 divide-y divide-line rounded-[14px] border border-line bg-white">
          {data.data.map((c) => (
            <Link
              key={c.id}
              href={`/admin/curriculum/${c.id}`}
              className="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-paper"
            >
              <span className="mono rounded-full bg-sky px-3 py-1 text-xs font-semibold text-deep">{c.code}</span>
              <span className="flex-1">
                <span className="block font-semibold text-ink">{c.name}</span>
                <span className="block text-sm text-muted">{c.tagline}</span>
              </span>
              <span
                className={`mono rounded-full px-2.5 py-1 text-[10px] uppercase tracking-widest ${
                  c.status === "live" ? "bg-verify-bg text-verify" : "bg-paper text-muted"
                }`}
              >
                {c.status === "live" ? "Live" : "Waitlist"}
              </span>
              <span className="mono text-sm text-muted">{c.modules_count} modules</span>
              <span className="text-trust">→</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
