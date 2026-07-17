"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Certificate = {
  code: string;
  number: string;
  title: string;
  course: string | null;
  issued_on: string | null;
  status: "pending" | "rendered";
  revoked: boolean;
  verify_url: string;
  download: string | null;
};

export default function CertificatesPage() {
  const [certs, setCerts] = useState<Certificate[]>([]);
  const [loading, setLoading] = useState(true);
  const [copied, setCopied] = useState<string | null>(null);

  useEffect(() => {
    apiJson<{ data: Certificate[] }>("/api/v1/me/certificates")
      .then((r) => setCerts(r.data))
      .catch(() => setCerts([]))
      .finally(() => setLoading(false));
  }, []);

  const copy = (c: Certificate) => {
    void navigator.clipboard?.writeText(c.verify_url).then(() => {
      setCopied(c.code);
      setTimeout(() => setCopied(null), 1500);
    });
  };

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">Achievements</p>
      <h1 className="display mt-2 text-3xl text-ink">My certificates</h1>

      {loading ? (
        <div className="mt-8 space-y-3">{Array.from({ length: 2 }).map((_, i) => <div key={i} className="shimmer h-28 rounded-2xl" />)}</div>
      ) : certs.length === 0 ? (
        <div className="mt-8"><EmptyState title="No certificates yet" body="Finish a course and your certificate is issued automatically — it'll appear here, verifiable by QR." /></div>
      ) : (
        <div className="mt-8 space-y-4">
          {certs.map((c) => (
            <div key={c.code} className={`rounded-2xl border p-5 ${c.revoked ? "border-warn/30 bg-warn/5" : "border-verify/30 bg-verify-bg"}`}>
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="display text-lg text-ink">{c.course ?? c.title}</p>
                  <p className="mono mt-1 text-[11px] uppercase tracking-widest text-muted">{c.number} · {c.issued_on ?? "—"}</p>
                </div>
                {c.revoked ? (
                  <span className="mono rounded-full bg-warn/10 px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-warn">revoked</span>
                ) : (
                  <span className="mono rounded-full bg-verify-bg px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-verify">verified</span>
                )}
              </div>
              {!c.revoked && (
                <div className="mt-4 flex flex-wrap gap-2">
                  {c.download ? (
                    <a href={c.download} className="rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white hover:bg-deep">Download</a>
                  ) : (
                    <span className="rounded-full border border-line px-4 py-2 text-sm text-muted">Preparing…</span>
                  )}
                  <button onClick={() => copy(c)} className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-paper">
                    {copied === c.code ? "Copied ✓" : "Copy verify link"}
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
