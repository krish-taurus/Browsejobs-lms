"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { employerApi } from "@/lib/employer";

export default function NewJobPage() {
  const router = useRouter();
  const { workspace } = useWorkspace();
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [skills, setSkills] = useState("");
  const [expMin, setExpMin] = useState(0);
  const [expMax, setExpMax] = useState(4);
  const [locations, setLocations] = useState("");
  const [remote, setRemote] = useState(false);
  const [openings, setOpenings] = useState(1);
  const [publishNow, setPublishNow] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const created = await employerApi.createJob(workspace.id, {
        title,
        description,
        skills: skills.split(",").map((s) => s.trim()).filter(Boolean),
        experience_min_years: expMin,
        experience_max_years: expMax,
        locations: locations.split(",").map((s) => s.trim()).filter(Boolean),
        remote,
        openings,
      });
      if (publishNow) {
        await employerApi.publishJob(workspace.id, created.data.id);
      }
      router.push(`/employer/jobs/${created.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <p className="mono text-[11px] uppercase tracking-widest text-trust">New JD</p>
        <h1 className="display mt-1 text-2xl text-ink">Post a job</h1>
        <p className="mt-1 text-sm text-muted">
          Publishing generates a JD-specific mock interview automatically — applicants arrive graded against it.
        </p>
      </div>

      <form onSubmit={submit} className="space-y-5 rounded-panel border border-line bg-white p-6 shadow-soft">
        <Field label="Job title">
          <input value={title} onChange={(e) => setTitle(e.target.value)} required maxLength={160}
            className="w-full rounded-input border border-line px-3 py-2 text-sm" placeholder="Data Engineer" />
        </Field>

        <Field label="Description">
          <textarea value={description} onChange={(e) => setDescription(e.target.value)} required rows={8}
            className="w-full rounded-input border border-line px-3 py-2 text-sm"
            placeholder="Responsibilities, must-have skills, working context…" />
        </Field>

        <Field label="Key skills (comma-separated — these drive the mock questions)">
          <input value={skills} onChange={(e) => setSkills(e.target.value)}
            className="w-full rounded-input border border-line px-3 py-2 text-sm" placeholder="python, sql, airflow, aws" />
        </Field>

        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <Field label="Min exp (yrs)">
            <input type="number" min={0} max={40} value={expMin} onChange={(e) => setExpMin(Number(e.target.value))}
              className="w-full rounded-input border border-line px-3 py-2 text-sm" />
          </Field>
          <Field label="Max exp (yrs)">
            <input type="number" min={0} max={40} value={expMax} onChange={(e) => setExpMax(Number(e.target.value))}
              className="w-full rounded-input border border-line px-3 py-2 text-sm" />
          </Field>
          <Field label="Openings">
            <input type="number" min={1} max={500} value={openings} onChange={(e) => setOpenings(Number(e.target.value))}
              className="w-full rounded-input border border-line px-3 py-2 text-sm" />
          </Field>
          <Field label="Remote">
            <label className="mt-2 flex items-center gap-2 text-sm text-ink">
              <input type="checkbox" checked={remote} onChange={(e) => setRemote(e.target.checked)} />
              Remote role
            </label>
          </Field>
        </div>

        <Field label="Locations (comma-separated)">
          <input value={locations} onChange={(e) => setLocations(e.target.value)}
            className="w-full rounded-input border border-line px-3 py-2 text-sm" placeholder="Bengaluru, Pune" />
        </Field>

        <label className="flex items-center gap-2 text-sm text-ink">
          <input type="checkbox" checked={publishNow} onChange={(e) => setPublishNow(e.target.checked)} />
          Publish immediately and generate the JD mock
        </label>

        {error && <p className="text-sm text-warn">{error}</p>}

        <button disabled={busy}
          className="w-full rounded-input bg-trust px-4 py-2.5 text-sm font-semibold text-white shadow-soft disabled:opacity-50">
          {busy ? "Saving…" : publishNow ? "Publish JD" : "Save as draft"}
        </button>
      </form>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="mb-1 block text-sm font-medium text-ink">{label}</label>
      {children}
    </div>
  );
}
