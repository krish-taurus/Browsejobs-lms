import { Suspense } from "react";
import { MissionControlFloor } from "@/components/recruiter/MissionControlFloor";

export default function MissionControlDemoPage() {
  return (
    <Suspense fallback={null}>
      <MissionControlFloor />
    </Suspense>
  );
}
