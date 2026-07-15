"use client";

import { useAuth } from "@/lib/auth";

export default function ProfilePage() {
  const { user } = useAuth();

  const rows = [
    { label: "Name", value: user?.name },
    { label: "Email", value: user?.email ?? "—" },
    { label: "Phone", value: user?.phone ?? "—" },
    { label: "Account type", value: user?.user_type },
  ];

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">Profile</p>
      <h1 className="display mt-2 text-3xl text-ink">Your details</h1>

      <div className="mt-8 divide-y divide-line rounded-2xl border border-line bg-white">
        {rows.map((r) => (
          <div key={r.label} className="flex items-center justify-between px-5 py-4">
            <span className="text-sm text-muted">{r.label}</span>
            <span className="font-medium text-ink">{r.value}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
