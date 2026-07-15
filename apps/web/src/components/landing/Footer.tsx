import Link from "next/link";
import { FOOTER_LINE, contact } from "@/content/landing";

export function Footer() {
  return (
    <footer className="bg-ink text-white">
      <div className="mx-auto max-w-6xl px-5 py-16">
        <p className="display max-w-2xl text-2xl md:text-3xl">
          Built from real interviews.
        </p>
        <p className="mono mt-4 text-sm text-sky/70">{FOOTER_LINE}</p>

        <div className="mt-10 grid gap-8 border-t border-white/10 pt-8 sm:grid-cols-3">
          <div>
            <p className="kicker text-sky/60">Talk to us</p>
            <p className="mono mt-3 text-sm">{contact.phone}</p>
            <p className="mono mt-1 text-sm">{contact.email}</p>
            <p className="mt-2 text-sm text-sky/60">{contact.hours}</p>
          </div>
          <div>
            <p className="kicker text-sky/60">Visit</p>
            <p className="mt-3 text-sm text-sky/80">{contact.address}</p>
          </div>
          <div>
            <p className="kicker text-sky/60">Legal</p>
            <ul className="mt-3 space-y-1.5 text-sm">
              <li>
                <Link href="/privacy-policy" className="text-sky/80 hover:text-white">
                  Privacy Policy
                </Link>
              </li>
              <li>
                <Link href="/terms" className="text-sky/80 hover:text-white">
                  Terms
                </Link>
              </li>
              <li>
                <Link href="/refund-policy" className="text-sky/80 hover:text-white">
                  Refund Policy
                </Link>
              </li>
            </ul>
          </div>
        </div>

        <div className="mt-10 flex flex-col justify-between gap-4 border-t border-white/10 pt-7 sm:flex-row sm:items-center">
          <div className="flex items-center gap-2">
            <span className="grid h-7 w-7 place-items-center rounded-md bg-white text-ink display text-sm">
              B
            </span>
            <span className="display">BrowseJobs</span>
          </div>
          <p className="mono text-xs text-sky/50">
            © {new Date().getFullYear()} {contact.entity}
          </p>
        </div>
      </div>
    </footer>
  );
}
