export function Footer() {
  return (
    <footer className="bg-ink text-white">
      <div className="mx-auto max-w-6xl px-5 py-16">
        <p className="display max-w-2xl text-2xl md:text-3xl">
          Learn the skill. Prove it. Get placed.
        </p>
        <p className="mt-3 max-w-xl text-sky/60">
          Proof, not promises — live teaching, an AI coach, and placement
          support until you land the role.
        </p>

        <div className="mt-10 flex flex-col justify-between gap-6 border-t border-white/10 pt-8 sm:flex-row sm:items-center">
          <div className="flex items-center gap-2">
            <span className="grid h-7 w-7 place-items-center rounded-md bg-white text-ink display text-sm">
              B
            </span>
            <span className="display">BrowseJobs</span>
          </div>
          <p className="mono text-xs text-sky/50">
            © {new Date().getFullYear()} iBrowseJobs Technologies Pvt Ltd
          </p>
        </div>
      </div>
    </footer>
  );
}
