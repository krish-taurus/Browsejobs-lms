import { EmptyState } from "@/components/ui/EmptyState";

export default function RecordingsPage() {
  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Recordings</p>
      <h1 className="display mt-2 text-3xl text-ink">Class recordings</h1>
      <p className="mt-2 text-muted">
        Every session is recorded and searchable by module and topic. Watch
        progress is tracked; recordings are watermarked and available while your
        fees are clear.
      </p>

      <div className="mt-8">
        <EmptyState
          title="Your recordings will appear here"
          body="After your first live class, its recording lands here — searchable, resumable, and yours to revisit any time."
        />
      </div>
    </div>
  );
}
