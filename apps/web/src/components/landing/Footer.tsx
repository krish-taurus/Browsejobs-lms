import Link from "next/link";
import { Wordmark } from "@/components/brand/Wordmark";
import { FOOTER_LINE, contact } from "@/content/landing";

export function Footer() {
  return (
    <footer className="bg-ink text-white">
      <div className="mx-auto max-w-6xl px-5 py-16">
        <p className="display max-w-2xl text-3xl md:text-4xl">
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
            <p className="kicker text-sky/60">Explore</p>
            <ul className="mt-3 space-y-1.5 text-sm">
              <li>
                <Link href="/courses" className="text-sky/80 hover:text-white">
                  Programs
                </Link>
              </li>
              <li>
                <Link href="/reviews" className="text-sky/80 hover:text-white">
                  Reviews
                </Link>
              </li>
              <li>
                <Link href="/employers" className="text-sky/80 hover:text-white">
                  For employers
                </Link>
              </li>
              <li>
                <Link href="/student" className="text-sky/80 hover:text-white">
                  Student sign in
                </Link>
              </li>
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
          <Wordmark tone="dark" />
          <p className="mono text-xs text-sky/50">
            © {new Date().getFullYear()} {contact.entity}
          </p>
        </div>
      </div>
    </footer>
  );
}
