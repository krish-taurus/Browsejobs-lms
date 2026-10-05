"use client";

import { useCallback, useEffect, useState } from "react";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { TwoPanelBanner } from "@/components/employer/TwoPanelBanner";
import { employerApi, type InviteRow, type MemberRow } from "@/lib/employer";
import { TeamHeader } from "./components/TeamHeader";
import { TeamSummary } from "./components/TeamSummary";
import { InviteTeammateForm } from "./components/InviteTeammateForm";
import { RolesPanel } from "./components/RolesPanel";
import { InvitationDetails } from "./components/InvitationDetails";
import { MembersList } from "./components/MembersList";

/**
 * Team page — PRD-E emerald/ivory redesign (approved kit, Sept 2026). Unlike
 * the Dashboard, this page is NOT view-only: inviting, and an owner removing
 * a member, are real, working actions — the redesign only changed how they
 * look, not what they do. See IMPLEMENT-TEAM-PAGE.md §"This task is the
 * Team management page."
 */
export default function EmployerTeamPage() {
  const { workspace } = useWorkspace();
  const [members, setMembers] = useState<MemberRow[] | null>(null);
  const [invites, setInvites] = useState<InviteRow[] | null>(null);
  const [failed, setFailed] = useState(false);

  const isOwner = workspace.my_role === "owner";

  const load = useCallback(() => {
    employerApi.members(workspace.id)
      .then((res) => setMembers(res.data))
      .catch(() => setFailed(true));

    // Only an owner can see who has been invited but has not joined yet.
    if (isOwner) {
      employerApi.invites(workspace.id).then((res) => setInvites(res.data)).catch(() => setInvites([]));
    }
  }, [workspace.id, isOwner]);

  useEffect(() => {
    setMembers(null);
    setInvites(null);
    setFailed(false);
    load();
  }, [load]);

  if (failed) {
    return (
      <p className="rounded-[var(--bj-dash-radius)] border bg-white p-8 text-sm" style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}>
        The team page could not load. Refresh to try again.
      </p>
    );
  }

  return (
    <div className="space-y-5 pb-6">
      <TeamHeader />

      <TwoPanelBanner
        topLine="Good people."
        italicLine="Stronger together."
        subLine="Bring your hiring team into one workspace."
        imageSrc="/img/employer/team-collaboration-banner.png"
        imageAlt="Three colleagues collaborating around a laptop"
        objectPosition="50% 0%"
      />

      <TeamSummary members={members} />

      <div className="grid items-start gap-4 lg:grid-cols-[1.6fr_1fr]">
        <div className="space-y-4">
          {isOwner && (
            <InviteTeammateForm workspaceId={workspace.id} workspaceName={workspace.name} onInvited={load} />
          )}
          <MembersList
            workspaceId={workspace.id}
            workspaceName={workspace.name}
            members={members}
            invites={invites}
            isOwner={isOwner}
            onChanged={load}
          />
        </div>

        <div className="space-y-4">
          <RolesPanel />
          <InvitationDetails />
        </div>
      </div>
    </div>
  );
}
