"use client";

import { use, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

type Verification = {
  valid: boolean;
  revoked?: boolean;
  student_name?: string;
  course?: string;
  issued_on?: string;
  tenant?: string;
  number?: string;
};

export default function VerifyPage({ params }: { params: Promise<{ code: string }> }) {
  const { code } = use(params);
  const [result, setResult] = useState<Verification | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiJson<{ data: Verification }>(`/api/v1/verify/${code}`)
      .then((r) => setResult(r.data))
      .catch(() => setResult({ valid: false }))
      .finally(() => setLoading(false));
  }, [code]);

  if (loading) {
    return (
      <div className="grid min-h-screen place-items-center px-5">
        <div className="shimmer h-64 w-full max-w-md rounded-2xl" />
      </div>
    );
  }

  const genuine = result?.valid === true;
  const revoked = result?.revoked === true;

  return (
    <div className="grid min-h-screen place-items-center bg-paper px-5">
      <div className="w-full max-w-md rounded-2xl border border-line bg-white p-8 text-center shadow-lg">
        <p className="kicker text-trust">Certificate verification</p>

        {genuine ? (
          <>
            <div className="mx-auto mt-6 grid h-16 w-16 place-items-center rounded-full bg-verify-bg">
              <span className="text-3xl text-verify">✓</span>
            </div>
            <h1 className="display mt-4 text-2xl text-ink">Genuine certificate</h1>
            <p className="mt-4 text-sm text-muted">This certifies that</p>
            <p className="display mt-1 text-xl text-trust">{result?.student_name}</p>
            <p className="mt-3 text-sm text-ink">completed <strong>{result?.course}</strong></p>
            <div className="mono mt-4 text-xs text-muted">
              {result?.tenant} · issued {result?.issued_on} · {result?.number}
            </div>
          </>
        ) : (
          <>
            <div className="mx-auto mt-6 grid h-16 w-16 place-items-center rounded-full bg-warn/10">
              <span className="text-3xl text-warn">{revoked ? "⦸" : "✕"}</span>
            </div>
            <h1 className="display mt-4 text-2xl text-ink">{revoked ? "Certificate revoked" : "Not found"}</h1>
            <p className="mt-3 text-sm text-muted">
              {revoked
                ? "This certificate has been revoked and is no longer valid."
                : "We couldn't verify a certificate with this code. Check the link and try again."}
            </p>
          </>
        )}
      </div>
    </div>
  );
}
