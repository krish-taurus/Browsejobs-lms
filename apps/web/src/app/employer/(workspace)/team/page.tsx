"use client";

import { useCallback, useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import {
  EASE,
  InkPanel,
  Label,
  PageHead,
  Pill,
  PrimaryButton,
  Skeleton,
  Tile,
} from "@/components/employer/ui";
import { DEEP, TRUST, VERIFY, VIOLET } from "@/components/employer/charts";
import { employerApi, type EmployerRole, type MemberRow } from "@/lib/employer";

const ROLE_LABELS: Record<EmployerRole, string> = {
  owner: "Owner",
  recruiter: "Recruiter",
  hiring_manager: "Hiring manager",
};

const ROLE_HINTS: Record<EmployerRole, string> = {
  owner: "Manages the workspace, team and credits",
  recruiter: "Posts JDs and drives pipelines",
  hiring_manager: "Reviews shortlists and evidence",
};

const ROLE_ACCENT: Record<EmployerRole, string> = {
  owner: VIOLET,
  recruiter: TRUST,
  hiring_manager: DEEP,
};

/** Initials avatar — no image uploads in the workspace yet. */
function Avatar({ name, accent }: { name: string; accent: string }) {
  const initials = name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? "")
    .join("");

  return (
    <span
      aria-hidden
      className="font-display grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-[13px] font-bold"
      style={{ background: `${accent}14`, color: accent }}
    >
      {initials || "—"}
    </span>
  );
}

export default function EmployerTeamPage() {
  const { workspace } = useWorkspace();
  const reduce = useReducedMotion();
  const [members, setMembers] = useState<MemberRow[] | null>(null);
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<EmployerRole>("recruiter");
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const isOwner = workspace.my_role === "owner";

  const load = useCallback(() => {
    employerApi.members(workspace.id).then((res) => setMembers(res.data)).catch(() => setMembers([]));
  }, [workspace.id]);

  useEffect(load, [load]);

  async function invite(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    setMessage(null);
    try {
      await employerApi.invite(workspace.id, email, role);
      setMessage(`Invite sent to ${email}. The link is single-use and expires in 7 days.`);
      setEmail("");
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not send the invite.");
    } finally {
      setBusy(false);
    }
  }

  const counts = (members ?? []).reduce<Record<string, number>>((acc, m) => {
    acc[m.role] = (acc[m.role] ?? 0) + 1;
    return acc;
  }, {});

  return (
    <div className="space-y-7 pb-10">
      <PageHead
        kicker="Team"
        title={workspace.name}
        sub="Who can post, who can decide. Every invite is a single-use signed link, and role changes are written to the audit log."
      />

      <div className="grid items-start gap-4 md:grid-cols-6 md:gap-5">
        <div className="space-y-4 md:col-span-4 md:space-y-5">
          {isOwner && (
            <InkPanel glow={TRUST}>
              <form onSubmit={invite}>
                <Label dark>Invite a teammate</Label>
                <p className="font-display mt-2.5 text-xl font-bold leading-tight md:text-2xl">
                  Add someone to{" "}
                  <span className="bg-gradient-to-r from-[#4d8ef7] to-[#9d6bf5] bg-clip-text text-transparent">
                    {workspace.name}
                  </span>
                </p>

                <div className="mt-5 flex flex-wrap items-end gap-3">
                  <div className="min-w-[220px] flex-1">
                    <label htmlFor="invite-email" className="block text-[13px] font-semibold text-white/70">
                      Work email
                    </label>
                    <input
                      id="invite-email"
                      type="email"
                      required
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      className="mt-2 w-full rounded-2xl border border-white/12 bg-white/[0.06] px-4 py-3 text-sm text-white outline-none transition-shadow placeholder:text-white/25 focus:border-[#4d8ef7] focus:ring-4 focus:ring-[#4d8ef7]/25"
                      placeholder="colleague@company.com"
                    />
                  </div>
                  <div>
                    <label htmlFor="invite-role" className="block text-[13px] font-semibold text-white/70">
                      Role
                    </label>
                    <select
                      id="invite-role"
                      value={role}
                      onChange={(e) => setRole(e.target.value as EmployerRole)}
                      className="mt-2 rounded-2xl border border-white/12 bg-white/[0.06] px-4 py-3 text-sm text-white outline-none focus:border-[#4d8ef7] focus:ring-4 focus:ring-[#4d8ef7]/25"
                    >
                      <option className="text-[#0a1220]" value="recruiter">Recruiter</option>
                      <option className="text-[#0a1220]" value="hiring_manager">Hiring manager</option>
                      <option className="text-[#0a1220]" value="owner">Owner</option>
                    </select>
                  </div>
                  <PrimaryButton type="submit" disabled={busy}>
                    {busy ? "Sending…" : "Send invite"}
                  </PrimaryButton>
                </div>

                <p className="mt-3 text-[13px] text-white/40">
                  <span className="font-semibold text-white/70">{ROLE_LABELS[role]}</span>
                  {" — "}
                  {ROLE_HINTS[role].charAt(0).toLowerCase() + ROLE_HINTS[role].slice(1)}.
                </p>
                {message && (
                  <p className="mt-3 rounded-2xl bg-[#0da06e]/15 px-4 py-2.5 text-[13px] text-[#6ee7b7]">{message}</p>
                )}
                {error && (
                  <p className="mt-3 rounded-2xl bg-[#e05561]/15 px-4 py-2.5 text-[13px] text-[#fca5a5]">{error}</p>
                )}
              </form>
            </InkPanel>
          )}

          <Tile accent={TRUST} index={0} hover={false}>
            <div className="flex items-baseline justify-between">
              <div>
                <Label>Members</Label>
                <p className="font-display mt-1 text-lg font-bold">
                  {members === null ? "Loading" : `${members.length} in this workspace`}
                </p>
              </div>
              {!isOwner && (
                <span className="text-[11px] text-white/50">Owners manage the team</span>
              )}
            </div>

            {members === null ? (
              <div className="mt-5 space-y-2.5">
                {[0, 1, 2].map((i) => (
                  <Skeleton key={i} className="h-16" />
                ))}
              </div>
            ) : members.length === 0 ? (
              <p className="mt-4 text-sm text-white/60">No members loaded.</p>
            ) : (
              <ul className="mt-5 space-y-2.5">
                {members.map((m, i) => {
                  const accent = ROLE_ACCENT[m.role] ?? TRUST;
                  return (
                    <motion.li
                      key={m.id}
                      initial={reduce ? false : { opacity: 0, y: 12 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.55, ease: EASE, delay: i * 0.05 }}
                      className="flex items-center gap-4 rounded-2xl border border-white/[0.07] bg-white/[0.04] p-3.5"
                    >
                      <Avatar name={m.user?.name ?? "Member"} accent={accent} />
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold">{m.user?.name ?? "Member"}</p>
                        <p className="truncate font-mono text-[11px] text-white/50">{m.user?.email}</p>
                      </div>
                      <Pill tone={m.role === "owner" ? "trust" : "neutral"}>{ROLE_LABELS[m.role]}</Pill>
                    </motion.li>
                  );
                })}
              </ul>
            )}
          </Tile>
        </div>

        {/* Role legend — permissions stated plainly, not buried in docs */}
        <div className="space-y-4 md:col-span-2 md:space-y-5">
          {(["owner", "recruiter", "hiring_manager"] as EmployerRole[]).map((r, i) => (
            <Tile key={r} accent={ROLE_ACCENT[r]} index={i} hover={false}>
              <div className="flex items-baseline justify-between gap-3">
                <Label>{ROLE_LABELS[r]}</Label>
                <span className="font-mono text-[11px] font-semibold" style={{ color: ROLE_ACCENT[r] }}>
                  {counts[r] ?? 0}
                </span>
              </div>
              <p className="mt-2 text-[13px] leading-relaxed text-white/65">{ROLE_HINTS[r]}</p>
            </Tile>
          ))}

          <Tile accent={VERIFY} index={3} hover={false}>
            <Label>Invite security</Label>
            <p className="mt-2 text-[13px] leading-relaxed text-white/65">
              Invite links are signed, single-use and expire in 7 days. Accepting one consumes it atomically, so a
              forwarded link cannot be replayed.
            </p>
          </Tile>
        </div>
      </div>
    </div>
  );
}
