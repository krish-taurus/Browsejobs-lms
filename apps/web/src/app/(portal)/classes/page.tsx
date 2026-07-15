import { EmptyState } from "@/components/ui/EmptyState";

export default function ClassesPage() {
  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">My Classes</p>
      <h1 className="display mt-2 text-3xl text-ink">Live classes</h1>

      <div className="mt-8">
        <EmptyState
          title="No classes scheduled yet"
          body="Once you're enrolled in a batch, your upcoming live classes appear here with one-tap join links and reminders."
        />
      </div>
    </div>
  );
}
