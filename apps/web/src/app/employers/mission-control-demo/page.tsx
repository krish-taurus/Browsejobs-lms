import { Suspense } from "react";
import { MissionControlDemo } from "@/components/employer/mission-control/MissionControlDemo";

export default function MissionControlDemoPage() {
  return (
    <Suspense fallback={null}>
      <MissionControlDemo />
    </Suspense>
  );
}
