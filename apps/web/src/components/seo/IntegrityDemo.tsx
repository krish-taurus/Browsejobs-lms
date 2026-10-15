"use client";

import { useEffect, useState } from "react";

/**
 * Looping storyboard of the three cheating patterns and the signal that exposes each.
 * Illustrative only: the candidate, the transcript and the timings are staged, not recorded.
 */
const SCENES = [
  {
    id: "gaze",
    label: "Reading from a screen",
    tag: "GAZE",
    quote: "…so the partition key decides how the data is spread across the nodes…",
    signal: "Eyes track a fixed line left to right, off-camera. Saccade rhythm matches reading, not recall.",
    verdict: "Reading pattern, 41s",
    gaze: "read",
    mouth: "sync",
    voice: false,
  },
  {
    id: "lip",
    label: "Lip-sync / pre-recorded or deepfaked",
    tag: "LIP-SYNC",
    quote: "I have worked with Spark for about four years now.",
    signal: "Audio phonemes lead the mouth shapes by 180 ms. Voice and face are not one source.",
    verdict: "Audio-visual drift 180 ms",
    gaze: "still",
    mouth: "drift",
    voice: false,
  },
  {
    id: "prompt",
    label: "Someone else feeding the answers",
    tag: "SECOND VOICE",
    quote: "Okay… so… what I would do is… use a window function.",
    signal: "A second voice is present, and the candidate's answer starts only after the whisper ends.",
    verdict: "Second voice, 3 prompts",
    gaze: "away",
    mouth: "sync",
    voice: true,
  },
] as const;

const GAZE_PATH: Record<string, string> = {
  read: "M138 124 L162 124 L186 124 M138 132 L172 132 L192 132 M138 140 L168 140",
  still: "M160 128 L161 128",
  away: "M160 128 L120 112 L112 128 L124 150",
};

export function IntegrityDemo() {
  const [i, setI] = useState(0);
  const [paused, setPaused] = useState(false);
  const scene = SCENES[i];

  useEffect(() => {
    if (paused) return;
    const t = setInterval(() => setI((n) => (n + 1) % SCENES.length), 5200);
    return () => clearInterval(t);
  }, [paused]);

  return (
    <div className="si-demo" onMouseEnter={() => setPaused(true)} onMouseLeave={() => setPaused(false)}>
      <style>{CSS}</style>

      <div className="si-tabs" role="tablist" aria-label="Cheating patterns">
        {SCENES.map((s, n) => (
          <button
            key={s.id}
            role="tab"
            aria-selected={n === i}
            className={n === i ? "on" : ""}
            onClick={() => setI(n)}
            type="button"
          >
            {s.label}
          </button>
        ))}
      </div>

      <div className="si-stage">
        <div className="si-cam" key={scene.id}>
          <span className="si-rec">
            <i /> LIVE · Integrity layer
          </span>
          <svg viewBox="0 0 320 240" aria-hidden="true">
            <rect width="320" height="240" fill="#0b1220" />
            <rect x="196" y="40" width="96" height="62" rx="4" fill="#111c33" stroke="#22314f" />
            <path d="M206 56 H280 M206 66 H268 M206 76 H276 M206 86 H258" stroke="#33466d" strokeWidth="3" />
            <circle cx="160" cy="118" r="44" fill="#d9b08c" />
            <path d="M112 112 q48 -58 96 0 q-4 -22 -48 -26 q-44 4 -48 26z" fill="#2a2420" />
            <ellipse cx="142" cy="116" rx="6" ry="3.4" fill="#fff" />
            <ellipse cx="178" cy="116" rx="6" ry="3.4" fill="#fff" />
            <circle className={`si-pupil ${scene.gaze}`} cx="142" cy="116" r="2.4" fill="#111" />
            <circle className={`si-pupil ${scene.gaze}`} cx="178" cy="116" r="2.4" fill="#111" />
            <ellipse className={`si-mouth ${scene.mouth}`} cx="160" cy="142" rx="12" ry="4" fill="#7a2f2f" />
            <path d="M104 200 q56 -40 112 0 v40 h-112z" fill="#22314f" />
            <path className="si-gaze" d={GAZE_PATH[scene.gaze]} fill="none" stroke="#ff5d5d" strokeWidth="1.6" strokeDasharray="3 3" />
            <rect className="si-box" x="112" y="76" width="96" height="92" rx="6" fill="none" stroke="#3ddc97" strokeWidth="1.5" />
            <rect className="si-box warn" x="146" y="132" width="28" height="20" rx="3" fill="none" stroke="#ffb020" strokeWidth="1.5" />
          </svg>
          <p className="si-cap">&ldquo;{scene.quote}&rdquo;</p>
        </div>

        <div className="si-side">
          <div className="si-wave" aria-hidden="true">
            <p className="k">Audio ↔ mouth</p>
            <div className={`bars ${scene.mouth}`}>
              {Array.from({ length: 28 }).map((_, n) => (
                <b key={n} style={{ animationDelay: `${(n % 7) * 70}ms` }} />
              ))}
            </div>
            <div className={`bars mouth ${scene.mouth}`}>
              {Array.from({ length: 28 }).map((_, n) => (
                <b key={n} style={{ animationDelay: `${(n % 7) * 70 + (scene.mouth === "drift" ? 180 : 0)}ms` }} />
              ))}
            </div>
          </div>
          <ul className="si-checks">
            <li className={scene.gaze === "read" ? "bad" : "ok"}>Eye-gaze path</li>
            <li className={scene.mouth === "drift" ? "bad" : "ok"}>Lip-sync alignment</li>
            <li className={scene.voice ? "bad" : "ok"}>Single-speaker audio</li>
            <li className="ok">Tab / window focus</li>
          </ul>
          <div className="si-flag" key={`${scene.id}-flag`}>
            <span className="tag">{scene.tag}</span>
            <strong>{scene.verdict}</strong>
            <p>{scene.signal}</p>
          </div>
        </div>
      </div>
    </div>
  );
}

const CSS = `
.si-demo{border:1px solid #1d2a44;border-radius:18px;background:#070d1a;color:#e8eefc;padding:14px;margin-top:28px}
.si-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.si-tabs button{font:600 13px/1 inherit;color:#9fb0d3;background:#0d1730;border:1px solid #1d2a44;padding:10px 14px;border-radius:999px;cursor:pointer}
.si-tabs button.on{color:#fff;background:#18284d;border-color:#3d63c9}
.si-stage{display:grid;grid-template-columns:1.25fr 1fr;gap:14px}
@media(max-width:760px){.si-stage{grid-template-columns:1fr}}
.si-cam{position:relative;border-radius:12px;overflow:hidden;background:#0b1220;border:1px solid #1d2a44}
.si-cam svg{display:block;width:100%;height:auto}
.si-rec{position:absolute;top:10px;left:10px;font:600 11px/1 ui-monospace,monospace;color:#cfe0ff;background:rgba(7,13,26,.75);padding:6px 9px;border-radius:6px;display:flex;gap:6px;align-items:center;z-index:2}
.si-rec i{width:7px;height:7px;border-radius:50%;background:#ff5d5d;animation:si-blink 1.2s infinite}
.si-cap{position:absolute;left:0;right:0;bottom:0;margin:0;padding:10px 14px;font-size:13px;line-height:1.35;background:linear-gradient(transparent,rgba(7,13,26,.95));color:#fff}
.si-pupil{transition:transform .4s}
.si-pupil.read{animation:si-read 2.4s steps(1) infinite}
.si-pupil.away{transform:translate(-4px,-2px)}
.si-gaze{opacity:.95;animation:si-dash 1.2s linear infinite}
.si-box{animation:si-pulse 1.6s ease-in-out infinite}
.si-box.warn{animation-duration:.9s}
.si-mouth.sync{animation:si-talk .38s ease-in-out infinite alternate}
.si-mouth.drift{animation:si-talk .38s ease-in-out infinite alternate;animation-delay:.18s}
.si-side{display:flex;flex-direction:column;gap:12px}
.si-wave{background:#0b1220;border:1px solid #1d2a44;border-radius:12px;padding:12px}
.si-wave .k{margin:0 0 8px;font:600 11px/1 ui-monospace,monospace;color:#7f93bd;text-transform:uppercase;letter-spacing:.06em}
.bars{display:flex;gap:3px;align-items:center;height:26px}
.bars+.bars{margin-top:6px}
.bars b{flex:1;background:#4b7bff;border-radius:2px;height:100%;animation:si-bar .7s ease-in-out infinite alternate;transform-origin:center}
.bars.mouth b{background:#3ddc97}
.bars.drift.mouth b{background:#ffb020}
.si-checks{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:8px}
.si-checks li{font-size:12.5px;padding:9px 10px 9px 28px;border-radius:8px;background:#0b1220;border:1px solid #1d2a44;position:relative;color:#cfe0ff}
.si-checks li::before{content:"";position:absolute;left:10px;top:50%;width:9px;height:9px;margin-top:-4.5px;border-radius:50%;background:#3ddc97}
.si-checks li.bad{border-color:#7a2a2a;color:#ffd5d5}
.si-checks li.bad::before{background:#ff5d5d;animation:si-blink .8s infinite}
.si-flag{border:1px solid #7a2a2a;background:linear-gradient(180deg,#2a1216,#1a0d10);border-radius:12px;padding:12px 14px;animation:si-in .5s ease both}
.si-flag .tag{display:inline-block;font:700 10.5px/1 ui-monospace,monospace;letter-spacing:.08em;color:#ff8b8b;border:1px solid #7a2a2a;border-radius:4px;padding:4px 6px;margin-right:8px}
.si-flag strong{font-size:14px;color:#fff}
.si-flag p{margin:8px 0 0;font-size:13px;line-height:1.45;color:#e8c9c9}
@keyframes si-blink{50%{opacity:.25}}
@keyframes si-dash{to{stroke-dashoffset:-12}}
@keyframes si-pulse{50%{opacity:.35}}
@keyframes si-talk{from{ry:2px}to{ry:7px}}
@keyframes si-bar{from{transform:scaleY(.2)}to{transform:scaleY(1)}}
@keyframes si-read{0%{transform:translate(-3px,3px)}33%{transform:translate(0,3px)}66%{transform:translate(3px,3px)}100%{transform:translate(-3px,5px)}}
@keyframes si-in{from{opacity:0;transform:translateY(6px)}}
@media(prefers-reduced-motion:reduce){.si-demo *{animation:none!important}}
`;
