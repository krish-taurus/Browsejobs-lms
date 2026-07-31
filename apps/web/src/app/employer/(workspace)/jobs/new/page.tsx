"use client";

import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import {
  CheckCard,
  Field,
  InkPanel,
  Label,
  PageHead,
  Pill,
  PrimaryButton,
  Tile,
  controlCls,
} from "@/components/employer/ui";
import { DEEP, TRUST, VERIFY, VIOLET } from "@/components/employer/charts";
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

  const skillList = useMemo(
    () => skills.split(",").map((s) => s.trim()).filter(Boolean),
    [skills],
  );
  const locationList = useMemo(
    () => locations.split(",").map((s) => s.trim()).filter(Boolean),
    [locations],
  );

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const created = await employerApi.createJob(workspace.id, {
        title,
        description,
        skills: skillList,
        experience_min_years: expMin,
        experience_max_years: expMax,
        locations: locationList,
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
    <div className="space-y-7 pb-10">
      <PageHead
        kicker="New JD"
        title="Post a job."
        highlight="The mock writes itself."
        sub="Publishing generates a JD-specific mock interview from your skill list. Applicants sit it before they reach you, so every profile in your pipeline arrives already scored against this description."
      />

      <form onSubmit={submit} className="grid items-start gap-4 md:grid-cols-6 md:gap-5">
        <div className="space-y-4 md:col-span-4 md:space-y-5">
          <Tile accent={TRUST} index={0} hover={false} ghost="01">
            <Label>The role</Label>
            <div className="mt-4 space-y-5">
              <Field label="Job title" htmlFor="job-title">
                <input
                  id="job-title"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  required
                  maxLength={160}
                  className={controlCls}
                  placeholder="Data Engineer"
                />
              </Field>

              <Field
                label="Description"
                htmlFor="job-description"
                hint="Responsibilities, must-haves and working context. This is what candidates read — and what the grader reads."
              >
                <textarea
                  id="job-description"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  required
                  rows={9}
                  className={`${controlCls} resize-y leading-relaxed`}
                  placeholder="You will own the ingestion layer feeding our reporting warehouse…"
                />
              </Field>
            </div>
          </Tile>

          <Tile accent={VIOLET} index={1} hover={false} ghost="02">
            <Label>Skills the mock will test</Label>
            <div className="mt-4">
              <Field
                label="Key skills"
                htmlFor="job-skills"
                hint="Comma-separated. These drive the mock questions and the talent-pool match score."
              >
                <input
                  id="job-skills"
                  value={skills}
                  onChange={(e) => setSkills(e.target.value)}
                  className={controlCls}
                  placeholder="python, sql, airflow, aws"
                />
              </Field>
              <div className="mt-3 flex min-h-[26px] flex-wrap gap-1.5">
                {skillList.length === 0 ? (
                  <span className="text-[12px] text-white/35">
                    Skills appear here as you type — each one becomes a graded dimension.
                  </span>
                ) : (
                  skillList.map((s) => (
                    <Pill key={s} tone="trust">
                      {s}
                    </Pill>
                  ))
                )}
              </div>
            </div>
          </Tile>

          <Tile accent={DEEP} index={2} hover={false} ghost="03">
            <Label>Shape of the hire</Label>
            <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
              <Field label="Min exp (yrs)" htmlFor="exp-min">
                <input
                  id="exp-min"
                  type="number"
                  min={0}
                  max={40}
                  value={expMin}
                  onChange={(e) => setExpMin(Number(e.target.value))}
                  className={`${controlCls} font-mono`}
                />
              </Field>
              <Field label="Max exp (yrs)" htmlFor="exp-max">
                <input
                  id="exp-max"
                  type="number"
                  min={0}
                  max={40}
                  value={expMax}
                  onChange={(e) => setExpMax(Number(e.target.value))}
                  className={`${controlCls} font-mono`}
                />
              </Field>
              <Field label="Openings" htmlFor="openings">
                <input
                  id="openings"
                  type="number"
                  min={1}
                  max={500}
                  value={openings}
                  onChange={(e) => setOpenings(Number(e.target.value))}
                  className={`${controlCls} font-mono`}
                />
              </Field>
            </div>

            <div className="mt-5">
              <Field label="Locations" htmlFor="locations" hint="Comma-separated.">
                <input
                  id="locations"
                  value={locations}
                  onChange={(e) => setLocations(e.target.value)}
                  className={controlCls}
                  placeholder="Bengaluru, Pune"
                />
              </Field>
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <CheckCard
                checked={remote}
                onChange={setRemote}
                title="Remote role"
                sub="Shown on the listing and used when matching candidates."
              />
              <CheckCard
                checked={publishNow}
                onChange={setPublishNow}
                title="Publish immediately"
                sub="Generates the JD mock now. Leave off to keep it as a draft."
              />
            </div>
          </Tile>

          {error && (
            <p className="rounded-2xl border border-[#e05561]/25 bg-[#fdeaea] px-4 py-3 text-sm text-[#e05561]">
              {error}
            </p>
          )}

          <div className="flex flex-wrap items-center gap-3">
            <PrimaryButton type="submit" disabled={busy}>
              {busy ? "Saving…" : publishNow ? "Publish JD" : "Save as draft"}
            </PrimaryButton>
            <span className="text-[12px] text-white/50">
              {publishNow
                ? "The mock is generated in the background; the JD is live either way."
                : "Nothing goes live until you publish."}
            </span>
          </div>
        </div>

        {/* What publishing actually does — the product explained in place */}
        <div className="space-y-4 md:col-span-2 md:sticky md:top-24 md:space-y-5">
          <InkPanel glow={VIOLET}>
            <Label dark>What happens on publish</Label>
            <ol className="mt-5 space-y-5">
              {[
                {
                  n: "01",
                  h: "The mock is written",
                  p: "A JD-specific interview is generated from your skills and description — no question bank to curate.",
                },
                {
                  n: "02",
                  h: "Candidates sit it to apply",
                  p: "The mock is the application. Camera-on and proctored, so the score belongs to the person.",
                },
                {
                  n: "03",
                  h: "You see them ranked",
                  p: "Graded applicants sort to the top with a rubric breakdown and the transcript attached.",
                },
              ].map((s) => (
                <li key={s.n} className="flex gap-4">
                  <span className="font-mono text-[11px] font-semibold text-white/25">{s.n}</span>
                  <span>
                    <span className="block text-[13px] font-semibold">{s.h}</span>
                    <span className="mt-1 block text-[13px] leading-relaxed text-white/45">{s.p}</span>
                  </span>
                </li>
              ))}
            </ol>
          </InkPanel>

          <Tile accent={VERIFY} index={3} hover={false}>
            <Label>Already trained for you</Label>
            <p className="mt-2 text-[13px] leading-relaxed text-white/65">
              Once this JD is live, students who finished the BrowseJobs programme and match your skills appear
              under its <span className="font-semibold text-white">Talent pool</span> tab, CV built and
              readiness scored. You can invite them to apply.
            </p>
          </Tile>
        </div>
      </form>
    </div>
  );
}
