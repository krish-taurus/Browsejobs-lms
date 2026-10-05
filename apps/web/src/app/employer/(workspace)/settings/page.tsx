"use client";

import { useWorkspace } from "@/components/employer/EmployerShell";
import { PageHead, Tile, Label } from "@/components/employer/ui";

/**
 * Read-only for now — there is no update-workspace endpoint yet, so this
 * shows what is real (the workspace record, who's on the team) rather than
 * a form that looks editable but silently does nothing when submitted.
 * Team membership itself is already editable on the Team page.
 */
export default function EmployerSettingsPage() {
  const { workspace } = useWorkspace();

  return (
    <div className="space-y-5 pb-10">
      <PageHead kicker="Workspace" title="Settings" sub="Your company workspace on BrowseJobs." />

      <Tile hover={false}>
        <Label>Company</Label>
        <p className="font-display mt-2 text-xl font-bold text-[#050505]">{workspace.name}</p>
        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-[11px] font-semibold uppercase tracking-widest text-[#8a8d91]">Your role</dt>
            <dd className="mt-1 text-sm text-[#050505]">{(workspace.my_role ?? "member").replace("_", " ")}</dd>
          </div>
          <div>
            <dt className="text-[11px] font-semibold uppercase tracking-widest text-[#8a8d91]">Status</dt>
            <dd className="mt-1 text-sm capitalize text-[#050505]">{workspace.status}</dd>
          </div>
        </dl>
      </Tile>

      <Tile hover={false}>
        <Label>Need something changed?</Label>
        <p className="mt-2 max-w-lg text-sm leading-relaxed text-[#65676b]">
          Company name, industry and billing details aren&apos;t self-service yet. Who&apos;s on your
          team, and what they can do, already is — manage that from{" "}
          <a href="/employer/team" className="font-semibold text-[#1877f2]">Team</a>. For anything
          else, reach out and we&apos;ll sort it out directly.
        </p>
        <a
          href="mailto:support@browsejobs.ai?subject=Workspace%20settings"
          className="mt-4 inline-flex items-center gap-2 rounded-full bg-[#1877f2] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#166fe5]"
        >
          Contact BrowseJobs
        </a>
      </Tile>
    </div>
  );
}
