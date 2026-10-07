"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { useAuth } from "@/lib/auth";

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

/** A deterministic QR-like pattern for the preview card — decorative, not scannable. */
function QrPattern({ className = "" }: { className?: string }) {
  const n = 9;
  const cells: Array<[number, number]> = [];
  for (let y = 0; y < n; y++) {
    for (let x = 0; x < n; x++) {
      const finder = (x < 3 && y < 3) || (x > 5 && y < 3) || (x < 3 && y > 5);
      if (!finder && (x * 7 + y * 13 + x * y) % 3 === 0) cells.push([x, y]);
    }
  }
  const finder = (fx: number, fy: number) => (
    <g key={`${fx}-${fy}`}>
      <rect x={fx} y={fy} width="3" height="3" rx="0.4" fill="none" stroke="currentColor" strokeWidth="0.6" />
      <rect x={fx + 1} y={fy + 1} width="1" height="1" fill="currentColor" />
    </g>
  );
  return (
    <svg viewBox="-0.5 -0.5 10 10" className={className} aria-hidden>
      {finder(0, 0)}
      {finder(6, 0)}
      {finder(0, 6)}
      {cells.map(([x, y]) => (
        <rect key={`${x}-${y}`} x={x + 0.1} y={y + 0.1} width="0.8" height="0.8" rx="0.15" fill="currentColor" />
      ))}
    </svg>
  );
}

/** Same layout as the issued PDF (certificates/default.blade.php), scaled to a card. */
function CertificateFace({
  name,
  course,
  issuedOn,
  number,
  preview = false,
  revoked = false,
}: {
  name: string;
  course: string;
  issuedOn: string;
  number: string;
  preview?: boolean;
  revoked?: boolean;
}) {
  return (
    <div
      className={`relative overflow-hidden rounded-2xl border border-line border-t-[6px] bg-white px-6 py-6 sm:px-8 sm:py-7 ${
        revoked ? "border-t-warn" : "border-t-trust"
      }`}
    >
      {preview && (
        <span className="mono absolute right-4 top-4 rounded-full bg-sky px-2.5 py-0.5 text-[10px] uppercase tracking-widest text-trust">
          Preview
        </span>
      )}
      <p className="mono text-[10px] uppercase tracking-[0.25em] text-trust">Certificate of completion</p>
      <p className="display mt-1 text-sm text-ink">BrowseJobs</p>

      <p className="mt-6 text-xs text-muted">This certifies that</p>
      <p className={`display mt-1 truncate text-2xl ${preview ? "text-trust/80" : "text-trust"}`}>{name}</p>
      <p className="mt-2 text-sm text-ink">
        has successfully completed <span className="font-semibold">{course}</span>.
      </p>
      <p className={`mt-3 text-xs font-semibold ${revoked ? "text-warn" : "text-verify"}`}>
        {revoked ? "✕ Revoked" : "✓ Verified completion · BrowseJobs"}
      </p>

      <div className="mt-6 flex items-end justify-between gap-4 border-t border-line pt-4">
        <div className="mono space-y-0.5 text-[11px] leading-relaxed text-muted">
          <p>
            Issued <span className="text-ink">{issuedOn}</span>
          </p>
          <p>
            No. <span className="text-ink">{number}</span>
          </p>
        </div>
        <QrPattern className={`size-14 shrink-0 ${preview ? "text-ink/25" : "text-ink"}`} />
      </div>
    </div>
  );
}

function linkedInUrl(c: Certificate): string {
  const issued = c.issued_on ? new Date(c.issued_on) : null;
  const params = new URLSearchParams({
    startTask: "CERTIFICATION_NAME",
    name: c.course ?? c.title,
    organizationName: "BrowseJobs",
    certUrl: c.verify_url,
    certId: c.number,
  });
  if (issued && !Number.isNaN(issued.getTime())) {
    params.set("issueYear", String(issued.getFullYear()));
    params.set("issueMonth", String(issued.getMonth() + 1));
  }
  return `https://www.linkedin.com/profile/add?${params.toString()}`;
}

const STEPS = [
  {
    title: "Complete your course",
    body: "Finish every topic in your course — classes, lessons and practice. Your progress counts automatically.",
    icon: (
      <path d="M4 19.5V6a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2Zm0 0A2 2 0 0 1 6 18h12M9 8h6" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
    ),
  },
  {
    title: "Get it issued instantly",
    body: "The moment the last topic is done, your certificate is created with a unique number — no forms, no waiting.",
    icon: <path d="M12 3 4 7v5c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V7l-8-4Zm-3 9 2 2 4-4" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />,
  },
  {
    title: "Share and get verified",
    body: "Download the PDF, add it to LinkedIn, and let recruiters scan the QR code to confirm it's genuine.",
    icon: <path d="M15 8a3 3 0 1 0-2.8-4M6 15a3 3 0 1 0 0 .1M18 21a3 3 0 1 0 0-6M8.6 13.5l6.8-3M8.6 16.5l6.8 3" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />,
  },
];

function EmptyState({ name }: { name: string }) {
  return (
    <div className="mt-8 grid gap-6 lg:grid-cols-[1.05fr_1fr]">
      <div className="relative rounded-3xl border border-line bg-gradient-to-br from-sky/70 via-white to-paper p-5 sm:p-8">
        <div aria-hidden className="pointer-events-none absolute -right-6 -top-6 size-32 rounded-full bg-trust/10 blur-2xl" />
        <div className="relative rotate-[-1.5deg] shadow-[0_24px_48px_-20px_rgba(16,24,40,0.28)] transition-transform duration-300 hover:rotate-0">
          <CertificateFace name={name} course="Your course" issuedOn="On completion" number="BJ-XXXX-XXXX" preview />
        </div>
        <p className="relative mt-5 text-center text-xs text-muted">This is how your certificate will look — with your course, date and a scannable QR.</p>
      </div>

      <div className="rounded-3xl border border-line bg-white p-6 sm:p-8">
        <p className="mono text-[11px] uppercase tracking-widest text-trust">How you earn it</p>
        <h2 className="display mt-1 text-xl text-ink">Your first certificate is on its way</h2>
        <ol className="mt-6 space-y-5">
          {STEPS.map((s, i) => (
            <li key={s.title} className="flex gap-4">
              <span className="relative flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky text-trust">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" className="size-5">
                  {s.icon}
                </svg>
                <span className="mono absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-ink text-[10px] text-white">
                  {i + 1}
                </span>
              </span>
              <div>
                <p className="font-semibold text-ink">{s.title}</p>
                <p className="mt-0.5 text-sm leading-relaxed text-muted">{s.body}</p>
              </div>
            </li>
          ))}
        </ol>
        <div className="mt-8 flex flex-wrap gap-3">
          <Link href="/classes" className="rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-deep">
            Continue learning
          </Link>
          <Link href="/dashboard" className="rounded-full border border-line px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-paper">
            Back to dashboard
          </Link>
        </div>
      </div>
    </div>
  );
}

function CertificateCard({ c, name }: { c: Certificate; name: string }) {
  const [copied, setCopied] = useState(false);
  const copy = () => {
    void navigator.clipboard?.writeText(c.verify_url).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    });
  };

  return (
    <div className="flex flex-col rounded-3xl border border-line bg-white p-4 sm:p-5">
      <CertificateFace
        name={name}
        course={c.course ?? c.title}
        issuedOn={c.issued_on ?? "—"}
        number={c.number}
        revoked={c.revoked}
      />
      {c.revoked ? (
        <p className="mt-4 rounded-xl bg-warn/5 px-4 py-3 text-sm text-warn">
          This certificate has been revoked and no longer verifies. Contact support if you think this is a mistake.
        </p>
      ) : (
        <div className="mt-4 flex flex-wrap gap-2">
          {c.download ? (
            <a href={c.download} className="rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white transition hover:bg-deep">
              Download PDF
            </a>
          ) : (
            <span className="rounded-full border border-line px-4 py-2 text-sm text-muted">Preparing PDF…</span>
          )}
          <a
            href={linkedInUrl(c)}
            target="_blank"
            rel="noopener noreferrer"
            className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink transition hover:bg-paper"
          >
            Add to LinkedIn
          </a>
          <button onClick={copy} className="rounded-full border border-line px-4 py-2 text-sm font-semibold text-ink transition hover:bg-paper">
            {copied ? "Copied ✓" : "Copy verify link"}
          </button>
          <a
            href={c.verify_url}
            target="_blank"
            rel="noopener noreferrer"
            className="rounded-full px-3 py-2 text-sm font-semibold text-trust transition hover:bg-sky"
          >
            Verify ↗
          </a>
        </div>
      )}
    </div>
  );
}

export default function CertificatesPage() {
  const { user } = useAuth();
  const [certs, setCerts] = useState<Certificate[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiJson<{ data: Certificate[] }>("/api/v1/me/certificates")
      .then((r) => setCerts(r.data))
      .catch(() => setCerts([]))
      .finally(() => setLoading(false));
  }, []);

  const name = user?.name || "Your name";
  const valid = certs.filter((c) => !c.revoked).length;

  return (
    <div className="mx-auto max-w-5xl">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="kicker text-trust">Achievements</p>
          <h1 className="display mt-2 text-3xl text-ink">My certificates</h1>
          <p className="mt-2 max-w-xl text-sm text-muted">
            Earned when you complete a course. Every certificate carries a QR code anyone can scan to confirm it&apos;s genuine.
          </p>
        </div>
        {!loading && certs.length > 0 && (
          <div className="flex gap-2">
            <span className="rounded-2xl border border-line bg-white px-4 py-2 text-center">
              <span className="display block text-xl text-ink">{certs.length}</span>
              <span className="mono text-[10px] uppercase tracking-widest text-muted">Earned</span>
            </span>
            <span className="rounded-2xl border border-verify/30 bg-verify-bg px-4 py-2 text-center">
              <span className="display block text-xl text-verify">{valid}</span>
              <span className="mono text-[10px] uppercase tracking-widest text-verify">Verified</span>
            </span>
          </div>
        )}
      </div>

      {loading ? (
        <div className="mt-8 grid gap-6 md:grid-cols-2">
          {Array.from({ length: 2 }).map((_, i) => (
            <div key={i} className="shimmer h-72 rounded-3xl" />
          ))}
        </div>
      ) : certs.length === 0 ? (
        <EmptyState name={name} />
      ) : (
        <div className="mt-8 grid gap-6 md:grid-cols-2">
          {certs.map((c) => (
            <CertificateCard key={c.code} c={c} name={name} />
          ))}
        </div>
      )}
    </div>
  );
}
