"use client";

import { useEffect, useRef, useState } from "react";
import { useReducedMotion } from "framer-motion";
import { Kicker } from "@/components/brand/Kicker";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { CITIES, EDGES, type CityPulse } from "@/content/market-pulse";
import { useMarketIntel } from "@/lib/market-intel";

/**
 * Market Pulse — the signature "command center" moment. A custom-canvas
 * constellation of India's hiring hubs: nodes breathe, signals travel the
 * network, skills surface beside cities; hovering a hub lights its edges and
 * fills the inspector panel. 2D canvas (DPR-aware, capped at 2), pauses when
 * offscreen or the tab hides, and renders one composed static frame under
 * reduced motion. Qualitative signal only — no invented counts (spec §3).
 */

type Node = CityPulse & { px: number; py: number; r: number; phase: number };
type Signal = { edge: number; t: number; speed: number };

const SKILL_FLOAT_EVERY = 2.6; // seconds between floating skill labels

export function MarketPulse() {
  const reduce = useReducedMotion();
  const wrapRef = useRef<HTMLDivElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const { cities } = useMarketIntel();
  const [selected, setSelected] = useState<CityPulse>(CITIES[0]);
  const selectedRef = useRef(selected.key);
  const citiesRef = useRef(cities);
  citiesRef.current = cities;

  useEffect(() => {
    const canvas = canvasRef.current;
    const wrap = wrapRef.current;
    if (!canvas || !wrap) return;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    const css = getComputedStyle(document.documentElement);
    const TRUST = css.getPropertyValue("--bj-trust").trim() || "#1b6df0";
    const SKY = css.getPropertyValue("--bj-sky").trim() || "#e7f1fe";

    let nodes: Node[] = [];
    let signals: Signal[] = [];
    let raf = 0;
    let running = false;
    let width = 0;
    let height = 0;
    let hovered: string | null = null;
    let time = 0;
    let lastTs = 0;
    let skillTimer = 0;
    let float: { text: string; x: number; y: number; born: number } | null = null;

    const layout = () => {
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      width = wrap.clientWidth;
      height = wrap.clientHeight;
      canvas.width = Math.round(width * dpr);
      canvas.height = Math.round(height * dpr);
      canvas.style.width = `${width}px`;
      canvas.style.height = `${height}px`;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      // Constellation occupies the right side on wide screens, full width on small.
      const wide = width > 900;
      const x0 = wide ? width * 0.44 : width * 0.06;
      const w = wide ? width * 0.5 : width * 0.88;
      const y0 = height * 0.08;
      const h = height * 0.84;
      nodes = citiesRef.current.map((c, i) => ({
        ...c,
        px: x0 + c.x * w,
        py: y0 + c.y * h,
        r: 4 + c.weight * 2.5,
        phase: (i * Math.PI * 2) / citiesRef.current.length,
      }));
    };

    const nodeByKey = (k: string) => nodes.find((n) => n.key === k)!;

    const edgePath = (a: Node, b: Node) => {
      // Gentle outward bow so the network reads organic, not schematic.
      const mx = (a.px + b.px) / 2 + (b.py - a.py) * 0.12;
      const my = (a.py + b.py) / 2 - (b.px - a.px) * 0.12;
      return { mx, my };
    };

    const pointOnEdge = (a: Node, b: Node, t: number) => {
      const { mx, my } = edgePath(a, b);
      const u = 1 - t;
      return {
        x: u * u * a.px + 2 * u * t * mx + t * t * b.px,
        y: u * u * a.py + 2 * u * t * my + t * t * b.py,
      };
    };

    const draw = (dt: number) => {
      time += dt;
      ctx.clearRect(0, 0, width, height);

      // Faint graticule — the "instrument" feel.
      ctx.strokeStyle = "rgba(231,241,254,0.05)";
      ctx.lineWidth = 1;
      for (let gx = 0; gx < width; gx += 96) {
        ctx.beginPath();
        ctx.moveTo(gx, 0);
        ctx.lineTo(gx, height);
        ctx.stroke();
      }
      for (let gy = 0; gy < height; gy += 96) {
        ctx.beginPath();
        ctx.moveTo(0, gy);
        ctx.lineTo(width, gy);
        ctx.stroke();
      }

      // Edges.
      EDGES.forEach(([ka, kb]) => {
        const a = nodeByKey(ka);
        const b = nodeByKey(kb);
        const hot = hovered === ka || hovered === kb;
        const { mx, my } = edgePath(a, b);
        ctx.beginPath();
        ctx.moveTo(a.px, a.py);
        ctx.quadraticCurveTo(mx, my, b.px, b.py);
        ctx.strokeStyle = hot ? "rgba(27,109,240,0.55)" : "rgba(27,109,240,0.16)";
        ctx.lineWidth = hot ? 1.6 : 1;
        ctx.stroke();
      });

      // Signals traveling the network.
      signals.forEach((s) => {
        const [ka, kb] = EDGES[s.edge];
        const p = pointOnEdge(nodeByKey(ka), nodeByKey(kb), s.t);
        ctx.beginPath();
        ctx.arc(p.x, p.y, 1.8, 0, Math.PI * 2);
        ctx.fillStyle = SKY;
        ctx.globalAlpha = 0.9 * Math.sin(Math.PI * s.t);
        ctx.fill();
        ctx.globalAlpha = 1;
      });

      // Nodes.
      nodes.forEach((n) => {
        const breathe = reduce ? 1 : 1 + 0.12 * Math.sin(time * 1.4 + n.phase);
        const r = n.r * breathe;
        const hot = hovered === n.key || selectedRef.current === n.key;

        // Expanding halo ring every few seconds on rising hubs.
        if (!reduce && n.trend === "rising") {
          const cycle = (time * 0.4 + n.phase) % 1;
          ctx.beginPath();
          ctx.arc(n.px, n.py, r + cycle * 26, 0, Math.PI * 2);
          ctx.strokeStyle = TRUST;
          ctx.globalAlpha = 0.35 * (1 - cycle);
          ctx.lineWidth = 1;
          ctx.stroke();
          ctx.globalAlpha = 1;
        }

        const glow = ctx.createRadialGradient(n.px, n.py, 0, n.px, n.py, r * 4);
        glow.addColorStop(0, "rgba(27,109,240,0.5)");
        glow.addColorStop(1, "rgba(27,109,240,0)");
        ctx.beginPath();
        ctx.arc(n.px, n.py, r * 4, 0, Math.PI * 2);
        ctx.fillStyle = glow;
        ctx.fill();

        ctx.beginPath();
        ctx.arc(n.px, n.py, r, 0, Math.PI * 2);
        ctx.fillStyle = hot ? SKY : TRUST;
        ctx.fill();

        ctx.font = '500 11px "IBM Plex Mono", ui-monospace, monospace';
        ctx.fillStyle = hot ? "rgba(255,255,255,0.95)" : "rgba(231,241,254,0.6)";
        ctx.fillText(n.name.toUpperCase(), n.px + r + 7, n.py + 4);
      });

      // A skill label floating up near a hub.
      if (float) {
        const age = time - float.born;
        if (age > 2.4) float = null;
        else {
          ctx.font = '500 11px "IBM Plex Mono", ui-monospace, monospace';
          ctx.fillStyle = TRUST;
          ctx.globalAlpha = age < 0.4 ? age / 0.4 : 1 - (age - 0.4) / 2;
          ctx.fillText(float.text, float.x, float.y - age * 14);
          ctx.globalAlpha = 1;
        }
      }
    };

    const tick = (ts: number) => {
      const dt = lastTs ? Math.min((ts - lastTs) / 1000, 0.05) : 0.016;
      lastTs = ts;

      signals.forEach((s) => (s.t += s.speed * dt));
      signals = signals.filter((s) => s.t < 1);
      if (signals.length < 7 && Math.random() < 0.06) {
        signals.push({ edge: Math.floor(Math.random() * EDGES.length), t: 0, speed: 0.25 + Math.random() * 0.3 });
      }

      skillTimer += dt;
      if (skillTimer > SKILL_FLOAT_EVERY && !float) {
        skillTimer = 0;
        const n = nodes[Math.floor(Math.random() * nodes.length)];
        const s = n.skills[Math.floor(Math.random() * n.skills.length)];
        float = { text: `${s.name} ${s.trend === "rising" ? "↑" : "→"}`, x: n.px + 14, y: n.py - 14, born: time };
      }

      draw(dt);
      if (running) raf = requestAnimationFrame(tick);
    };

    const start = () => {
      if (running || reduce) return;
      running = true;
      lastTs = 0;
      raf = requestAnimationFrame(tick);
    };
    const stop = () => {
      running = false;
      cancelAnimationFrame(raf);
    };

    layout();
    draw(0); // static first frame (also the only frame under reduced motion)

    const io = new IntersectionObserver(([e]) => (e.isIntersecting ? start() : stop()), { threshold: 0.05 });
    io.observe(wrap);
    const onVis = () => (document.hidden ? stop() : start());
    document.addEventListener("visibilitychange", onVis);
    const ro = new ResizeObserver(() => {
      layout();
      if (!running) draw(0);
    });
    ro.observe(wrap);

    const onMove = (e: PointerEvent) => {
      const rect = canvas.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      const hit = nodes.find((n) => (n.px - x) ** 2 + (n.py - y) ** 2 < (n.r + 14) ** 2);
      const next = hit?.key ?? null;
      if (next !== hovered) {
        hovered = next;
        canvas.style.cursor = hit ? "pointer" : "default";
        if (hit) {
          selectedRef.current = hit.key;
          setSelected(citiesRef.current.find((c) => c.key === hit.key)!);
        }
        if (reduce) draw(0);
      }
    };
    canvas.addEventListener("pointermove", onMove);

    return () => {
      stop();
      io.disconnect();
      ro.disconnect();
      document.removeEventListener("visibilitychange", onVis);
      canvas.removeEventListener("pointermove", onMove);
    };
  }, [reduce, cities]);

  return (
    <section aria-label="Live market signal across India's hiring hubs" className="relative overflow-hidden bg-ink text-white">
      <div ref={wrapRef} className="relative mx-auto min-h-[560px] max-w-7xl md:min-h-[640px]">
        <canvas ref={canvasRef} className="absolute inset-0" aria-hidden />

        {/* Inspector — floats over the constellation. */}
        <div className="relative z-10 flex min-h-[560px] flex-col justify-center px-5 py-16 md:min-h-[640px] md:max-w-md md:px-10">
          <ScrollReveal>
            <Kicker tone="sky">Live market signal</Kicker>
            <h2 className="display mt-3 text-3xl text-white md:text-5xl">
              The market, as the engine sees it
            </h2>
            <p className="mt-4 text-sky/70">
              Eight hiring hubs, one network. The engine watches what moves and
              rebuilds the syllabus around whatever is rising.
            </p>
          </ScrollReveal>

          {/* Tappable hub picker — the reliable way to select a city on touch,
              where the canvas has no hover. */}
          <ScrollReveal delay={0.05}>
            <div className="mt-6 flex flex-wrap gap-2">
              {(cities.length ? cities : CITIES).map((c) => (
                <button
                  key={c.key}
                  type="button"
                  onClick={() => setSelected(c)}
                  className={`mono rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors ${
                    selected.key === c.key
                      ? "bg-trust text-white"
                      : "border border-white/15 text-sky/70 hover:border-white/40 hover:text-white"
                  }`}
                >
                  {c.name}
                </button>
              ))}
            </div>
          </ScrollReveal>

          <ScrollReveal delay={0.08}>
            <div className="mt-6 rounded-[14px] border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
              <div className="flex items-baseline justify-between">
                <span className="display text-xl">{selected.name}</span>
                <span className="mono text-[10px] uppercase tracking-[0.18em] text-sky/60">
                  {selected.trend === "rising" ? "Signal rising ↑" : "Signal steady →"}
                </span>
              </div>
              <ul className="mt-4 space-y-2">
                {selected.skills.map((s) => (
                  <li key={s.name} className="flex items-center justify-between border-t border-white/5 pt-2 first:border-t-0 first:pt-0">
                    <span className="text-sm text-sky/85">{s.name}</span>
                    <span className={`mono text-xs ${s.trend === "rising" ? "text-trust" : "text-sky/45"}`}>
                      {s.trend === "rising" ? "rising ↑" : "steady →"}
                    </span>
                  </li>
                ))}
              </ul>
              <p className="mono mt-4 text-[10px] leading-relaxed text-sky/40">
                Tap a hub above to inspect it. Illustrative signal map —
                directions drawn from roles the engine tracks, not live counts.
              </p>
            </div>
          </ScrollReveal>
        </div>
      </div>
    </section>
  );
}
