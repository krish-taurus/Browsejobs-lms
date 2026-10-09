"use client";

import { useEffect, useRef } from "react";

type Kind = "data-engineering" | "devops-cloud" | "data-analytics" | "python-backend";

function pointOn(nodes: Array<[number, number]>, width: number, height: number, distance: number) {
  let left = distance;
  for (let index = 0; index < nodes.length - 1; index += 1) {
    const from = nodes[index];
    const to = nodes[index + 1];
    const length = Math.hypot((to[0] - from[0]) * width, (to[1] - from[1]) * height);
    if (left <= length) {
      const mix = length === 0 ? 0 : left / length;
      return {
        x: (from[0] + (to[0] - from[0]) * mix) * width,
        y: (from[1] + (to[1] - from[1]) * mix) * height,
      };
    }
    left -= length;
  }
  const last = nodes[nodes.length - 1];
  return { x: last[0] * width, y: last[1] * height };
}

function pathLength(nodes: Array<[number, number]>, width: number, height: number) {
  let total = 0;
  for (let index = 0; index < nodes.length - 1; index += 1) {
    const from = nodes[index];
    const to = nodes[index + 1];
    total += Math.hypot((to[0] - from[0]) * width, (to[1] - from[1]) * height);
  }
  return total;
}

function drawPipeline(ctx: CanvasRenderingContext2D, width: number, height: number, time: number) {
  const nodes: Array<[number, number]> = [
    [0.1, 0.64],
    [0.32, 0.36],
    [0.56, 0.36],
    [0.84, 0.64],
  ];
  ctx.strokeStyle = "#d1d1d6";
  ctx.lineWidth = 1.5;
  ctx.lineJoin = "round";
  ctx.beginPath();
  nodes.forEach(([x, y], index) => {
    const px = x * width;
    const py = y * height;
    if (index === 0) ctx.moveTo(px, py);
    else ctx.lineTo(px, py);
  });
  ctx.stroke();
  nodes.forEach(([x, y]) => {
    ctx.beginPath();
    ctx.fillStyle = "#ffffff";
    ctx.strokeStyle = "#3a3a3c";
    ctx.lineWidth = 1.5;
    ctx.arc(x * width, y * height, 7, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
  });
  const total = pathLength(nodes, width, height);
  for (let index = 0; index < 3; index += 1) {
    const spot = pointOn(nodes, width, height, (time * 78 + (index * total) / 3) % total);
    ctx.beginPath();
    ctx.fillStyle = index === 1 ? "#111111" : "#8e8e93";
    ctx.arc(spot.x, spot.y, 3.1, 0, Math.PI * 2);
    ctx.fill();
  }
}

function lemniscate(theta: number, width: number, height: number) {
  const denom = 1 + Math.sin(theta) ** 2;
  const x = Math.cos(theta) / denom;
  const y = (Math.sin(theta) * Math.cos(theta)) / denom;
  return { x: width * 0.5 + x * width * 0.34, y: height * 0.52 + y * height * 0.7 };
}

function drawInfinity(ctx: CanvasRenderingContext2D, width: number, height: number, time: number) {
  ctx.beginPath();
  ctx.strokeStyle = "#d1d1d6";
  ctx.lineWidth = 1.5;
  for (let step = 0; step <= 180; step += 1) {
    const spot = lemniscate((step / 180) * Math.PI * 2, width, height);
    if (step === 0) ctx.moveTo(spot.x, spot.y);
    else ctx.lineTo(spot.x, spot.y);
  }
  ctx.stroke();
  const head = lemniscate(time * 1.35, width, height);
  ctx.beginPath();
  ctx.fillStyle = "rgba(17,17,17,0.12)";
  ctx.arc(head.x, head.y, 14, 0, Math.PI * 2);
  ctx.fill();
  ctx.beginPath();
  ctx.fillStyle = "#111111";
  ctx.arc(head.x, head.y, 4.2, 0, Math.PI * 2);
  ctx.fill();
}

function roundRect(ctx: CanvasRenderingContext2D, x: number, y: number, width: number, height: number, radius: number) {
  ctx.beginPath();
  ctx.moveTo(x + radius, y);
  ctx.arcTo(x + width, y, x + width, y + height, radius);
  ctx.arcTo(x + width, y + height, x, y + height, radius);
  ctx.arcTo(x, y + height, x, y, radius);
  ctx.arcTo(x, y, x + width, y, radius);
  ctx.closePath();
}

function drawBars(ctx: CanvasRenderingContext2D, width: number, height: number, time: number) {
  const count = 7;
  const bar = Math.min(28, width / 16);
  const gap = bar * 0.7;
  const used = count * bar + (count - 1) * gap;
  const start = (width - used) / 2;
  const floor = height * 0.8;
  for (let index = 0; index < count; index += 1) {
    const wave = 0.5 + 0.5 * Math.sin(time * 1.5 + index * 0.55);
    const tall = (0.22 + wave * 0.62) * height * 0.62;
    ctx.fillStyle = index % 3 === 1 ? "#3a3a3c" : "#8e8e93";
    roundRect(ctx, start + index * (bar + gap), floor - tall, bar, tall, 6);
    ctx.fill();
  }
}

function drawCode(ctx: CanvasRenderingContext2D, width: number, height: number, time: number, font: string) {
  const lines = ["GET /v1/orders", "def list_orders():", "    return query()", '{ "ok": true }'];
  const cycle = time % 5.2;
  const shown = Math.min(lines.length, Math.floor(cycle / 0.9) + 1);
  ctx.font = `500 ${Math.max(13, Math.min(16, width / 22))}px ${font}`;
  ctx.textBaseline = "top";
  lines.slice(0, shown).forEach((line, index) => {
    const local = cycle - index * 0.9;
    const chars = index === shown - 1 ? Math.max(1, Math.floor(Math.min(1, local / 0.7) * line.length)) : line.length;
    const text = line.slice(0, chars);
    const y = height * 0.18 + index * (height * 0.16);
    if (index === lines.length - 1 && chars > 4) {
      const metrics = ctx.measureText(text);
      ctx.fillStyle = "#f5f5f7";
      roundRect(ctx, width * 0.08 - 8, y - 6, metrics.width + 16, 28, 8);
      ctx.fill();
    }
    ctx.fillStyle = index === lines.length - 1 ? "#111111" : "#3a3a3c";
    ctx.fillText(text, width * 0.08, y);
  });
}

function paint(ctx: CanvasRenderingContext2D, slug: string, width: number, height: number, time: number, font: string) {
  ctx.clearRect(0, 0, width, height);
  if (slug === "data-engineering") drawPipeline(ctx, width, height, time);
  else if (slug === "devops-cloud") drawInfinity(ctx, width, height, time);
  else if (slug === "data-analytics") drawBars(ctx, width, height, time);
  else drawCode(ctx, width, height, time, font);
}

export function CourseVisual({ slug, enlarged = false, playing = false }: { slug: Kind | string; enlarged?: boolean; playing?: boolean }) {
  const wrap = useRef<HTMLDivElement>(null);
  const canvas = useRef<HTMLCanvasElement>(null);
  const playingRef = useRef(playing);
  const kickRef = useRef<() => void>(() => {});
  playingRef.current = playing;

  useEffect(() => {
    const node = wrap.current;
    const surface = canvas.current;
    if (!node || !surface) return;
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const font = getComputedStyle(document.body).getPropertyValue("--font-plex-mono").trim() || "ui-monospace, monospace";
    let visible = false;
    let raf = 0;
    let alive = true;

    const frame = (now: number) => {
      if (!alive) return;
      const width = node.clientWidth;
      const height = node.clientHeight;
      const dpr = Math.min(2, window.devicePixelRatio || 1);
      const nextWidth = Math.max(1, Math.round(width * dpr));
      const nextHeight = Math.max(1, Math.round(height * dpr));
      if (surface.width !== nextWidth || surface.height !== nextHeight) {
        surface.width = nextWidth;
        surface.height = nextHeight;
      }
      const ctx = surface.getContext("2d");
      if (ctx && width > 2) {
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        paint(ctx, slug, width, height, reduced ? 0.8 : now / 1000, font);
      }
      if (!reduced && (visible || playingRef.current)) raf = requestAnimationFrame(frame);
    };

    const kick = () => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(frame);
    };
    kickRef.current = kick;

    const observer = new IntersectionObserver(
      ([entry]) => {
        visible = Boolean(entry?.isIntersecting);
        if (visible || playingRef.current) kick();
      },
      { threshold: 0.25 },
    );
    observer.observe(node);
    kick();
    return () => {
      alive = false;
      cancelAnimationFrame(raf);
      observer.disconnect();
    };
  }, [slug]);

  useEffect(() => {
    if (playing) kickRef.current();
  }, [playing]);

  return (
    <div ref={wrap} className={enlarged ? "argus-visual is-large" : "argus-visual"} aria-hidden>
      <canvas ref={canvas} />
    </div>
  );
}
