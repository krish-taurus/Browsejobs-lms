/** A static phone of the free interview. CSS only, so the hero stays sharp and light. */
export function PhoneInterview() {
  return (
    <figure className="mx-auto w-[300px]">
      <div className="rounded-[2.5rem] bg-[#1d1d1f] p-[11px] shadow-[0_24px_70px_rgba(0,0,0,0.18)]">
        <div className="overflow-hidden rounded-[1.9rem] bg-white text-[#1d1d1f]">
          <div className="mx-auto mt-2 h-5 w-24 rounded-full bg-[#1d1d1f]" aria-hidden />
          <div className="px-6 pb-10 pt-7 text-left">
            <p className="text-[12px] text-[#6e6e73]">Sample question</p>
            <p className="mt-3 text-[17px] leading-snug tracking-[-0.015em]">
              Walk me through a pipeline you would ship this month.
            </p>
            <p className="mt-10 text-[12px] text-[#6e6e73]">Clear mark</p>
            <p className="mt-1 text-[56px] font-semibold leading-none tracking-[-0.04em]">
              75<span className="text-[28px]">%</span>
            </p>
          </div>
        </div>
      </div>
      <figcaption className="mt-4 text-center text-[12px] text-[#6e6e73]">A sample round. Not a promise of a job.</figcaption>
    </figure>
  );
}
