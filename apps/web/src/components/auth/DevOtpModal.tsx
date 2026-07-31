"use client";

import { useEffect } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";

/**
 * Local-development OTP reveal. The API only includes `debug_code` in the
 * otp-request response while APP_DEBUG is on AND no SMS/WhatsApp/email gateway
 * is configured — so this modal shows itself in dev and vanishes everywhere a
 * real messaging integration exists. Never rendered otherwise.
 */
export function DevOtpModal({
  code,
  onUse,
  onClose,
}: {
  code: string | null;
  onUse: (code: string) => void;
  onClose: () => void;
}) {
  const reduce = useReducedMotion();

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") onClose();
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  // Rendered without AnimatePresence on purpose: a dev-only dialog must never
  // linger because an exit animation stalls — closing is instant.
  if (code === null) {
    return null;
  }

  return (
    <motion.div
      className="fixed inset-0 z-[100] grid place-items-center bg-ink/50 p-4 backdrop-blur-sm"
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      transition={{ duration: durations.fast }}
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label="Your one-time code (development)"
    >
      <motion.div
        className="w-full max-w-sm overflow-hidden rounded-[22px] bg-white shadow-soft"
        initial={reduce ? false : { opacity: 0, y: 18, scale: 0.98 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: durations.base, ease }}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="p-7 text-center">
          <p className="kicker text-trust">Dev mode · no SMS gateway</p>
          <h2 className="display mt-2 text-xl text-ink">Your one-time code</h2>
          <p className="mono mt-5 rounded-[14px] border border-line bg-paper py-4 text-3xl tracking-[0.35em] text-ink">
            {code}
          </p>
          <p className="mt-4 text-xs text-muted">
            No SMS integration is configured, so the code is shown here for
            local development. Once a gateway (MSG91 / Twilio / WhatsApp) is
            connected, codes are delivered to the phone and this dialog no
            longer appears.
          </p>
          <button
            onClick={() => onUse(code)}
            className="mt-6 w-full rounded-full bg-trust py-3 font-semibold text-white transition-colors hover:bg-deep"
          >
            Use this code
          </button>
          <button
            onClick={onClose}
            className="mt-3 w-full rounded-full border border-line py-3 text-sm font-medium text-ink transition-colors hover:bg-paper"
          >
            Close
          </button>
        </div>
      </motion.div>
    </motion.div>
  );
}
