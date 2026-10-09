/**
 * Scroll engine for the Apple-direction pages (ported from the approved demo's
 * site.js). Sticky scenes scrub only on wide screens with motion allowed (the
 * `.ap.motion` state); elsewhere each scene plays once when it enters view,
 * or shows its final state under reduced motion. Content is complete at rest.
 *
 * Everything is scoped to the `.ap` root and returns a cleanup function.
 */
export function initApEffects(root) {
  const cleanups = [];
  const on = (target, type, fn, opts) => {
    target.addEventListener(type, fn, opts);
    cleanups.push(() => target.removeEventListener(type, fn, opts));
  };
  const observe = (io) => {
    cleanups.push(() => io.disconnect());
    return io;
  };
  const $$ = (sel) => [...root.querySelectorAll(sel)];
  const reduce = matchMedia("(prefers-reduced-motion: reduce)");
  const wide = matchMedia("(min-width: 834px)");
  const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
  const ease = (t) => 1 - Math.pow(1 - t, 3);
  const motion = () => root.classList.contains("motion");

  const setMode = () => root.classList.toggle("motion", !reduce.matches && wide.matches);
  setMode();

  /* ---------- mobile menu ---------- */
  const mnav = root.querySelector(".mnav");
  const openBtn = root.querySelector(".gnav-menu");
  if (mnav && openBtn) {
    const close = mnav.querySelector(".mnav-close");
    const set = (open) => {
      mnav.toggleAttribute("data-open", open);
      openBtn.setAttribute("aria-expanded", String(open));
      mnav.inert = !open;
      document.body.style.overflow = open ? "hidden" : "";
      if (open) close?.focus();
      else openBtn.focus();
    };
    mnav.inert = true;
    on(openBtn, "click", () => set(true));
    if (close) on(close, "click", () => set(false));
    on(mnav, "click", (e) => {
      if (e.target.closest("a")) {
        mnav.removeAttribute("data-open");
        openBtn.setAttribute("aria-expanded", "false");
        mnav.inert = true;
        document.body.style.overflow = "";
      }
    });
    on(window, "keydown", (e) => e.key === "Escape" && mnav.hasAttribute("data-open") && set(false));
    cleanups.push(() => (document.body.style.overflow = ""));
  }

  /* ---------- scene handlers ---------- */
  const handlers = {
    hero(el, p) {
      el.style.setProperty("--p", p.toFixed(3));
    },
    tilt(el, p) {
      el.style.setProperty("--p", p.toFixed(3));
    },
    score(el, p) {
      const f = clamp(p / 0.6);
      const pct = Math.round(75 * ease(f));
      el.querySelector(".score-dial .ring")?.style.setProperty("--fill", (pct / 100).toFixed(3));
      const big = el.querySelector(".score-read .big");
      if (big) big.textContent = pct + "%";
      el.classList.toggle("is-clear", pct >= 75);
      el.querySelectorAll("[data-at]").forEach((li) => li.classList.toggle("is-on", p >= Number(li.dataset.at)));
    },
    path(el, p) {
      el.style.setProperty("--p", p.toFixed(3));
      el.style.setProperty("--path-h", el.offsetHeight + "px");
      const items = el.querySelectorAll("li");
      const n = items.length;
      items.forEach((li, i) => li.classList.toggle("is-on", p >= (n === 1 ? 0 : i / (n - 1)) - 0.001));
    },
    flow(el, p) {
      const phase = p < 0.5 ? 0 : 1;
      const q = phase === 0 ? p / 0.5 : (p - 0.5) / 0.5;
      el.querySelectorAll(".flow-phase").forEach((ph, i) => ph.classList.toggle("is-on", i === phase));
      el.querySelectorAll(".seg span").forEach((s, i) => s.classList.toggle("is-on", i === phase));
      const tiles = el.querySelectorAll(".flow-phase")[phase]?.querySelectorAll(".phase-tile") ?? [];
      tiles.forEach((t, i) => t.classList.toggle("is-on", q >= i / 3 + 0.02 || p >= 0.999));
    },
    days(el, p) {
      const f = clamp(p / 0.8);
      const n = Math.round(90 - 87 * ease(f));
      const big = el.querySelector(".days-count .big");
      if (big) big.textContent = String(n);
      const usual = el.querySelectorAll(".usual li");
      usual.forEach((li, i) => li.classList.toggle("is-on", f >= (i + 1) / usual.length - 0.001));
      const ours = el.querySelectorAll(".ours li");
      ours.forEach((li, i) => li.classList.toggle("is-on", f >= (i + 1) / ours.length - 0.001));
      const unit = el.querySelector(".days-count .unit");
      if (unit) unit.textContent = f >= 1 ? "About 3 days" : "days";
      el.dataset.p = p.toFixed(3);
      el.__3d?.(p);
    },
    journey(el, p) {
      el.dataset.p = p.toFixed(3);
      if (el.__3d) el.__3d(p);
      else {
        const steps = el.querySelectorAll("[data-step]");
        const a = Math.round(Math.min(1, p * 1.08) * (steps.length - 1));
        steps.forEach((s, k) => s.classList.toggle("is-on", k === a));
      }
      el.style.setProperty("--p", p.toFixed(3));
    },
  };

  const scenes = $$("[data-scene]").filter((el) => handlers[el.dataset.scene]);
  const isSticky = (el) => el.hasAttribute("data-len");
  scenes.forEach((el) => isSticky(el) && el.style.setProperty("--len", el.dataset.len));

  const progressOf = (el) => {
    const r = el.getBoundingClientRect();
    const vh = innerHeight;
    if (isSticky(el)) {
      const total = el.offsetHeight - vh + (parseFloat(getComputedStyle(el).getPropertyValue("--pin-top")) || 48);
      return total > 0 ? clamp(-r.top / total) : 1;
    }
    const start = vh * 0.95;
    const end = vh * 0.45 - r.height / 2;
    return clamp((start - r.top) / (start - end));
  };

  let ticking = false;
  const scrub = () => {
    ticking = false;
    for (const el of scenes) {
      if (el.dataset.played === "final") continue;
      if (!motion() && isSticky(el)) continue;
      handlers[el.dataset.scene](el, progressOf(el));
    }
  };
  const onScroll = () => {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(scrub);
    }
  };

  const playOnce = (el, ms = 1800) => {
    if (el.dataset.played) return;
    el.dataset.played = "final";
    const t0 = performance.now();
    const step = (now) => {
      const t = clamp((now - t0) / ms);
      handlers[el.dataset.scene](el, t);
      if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };

  const io = observe(
    new IntersectionObserver(
      (entries) =>
        entries.forEach((en) => {
          if (!en.isIntersecting) return;
          const el = en.target;
          if (!motion() && isSticky(el)) {
            playOnce(el);
            io.unobserve(el);
          }
        }),
      { threshold: 0.25 },
    ),
  );

  const init = () => {
    setMode();
    scenes.forEach((el) => {
      delete el.dataset.played;
      if (reduce.matches) {
        el.dataset.played = "final";
        handlers[el.dataset.scene](el, 1);
      } else if (!motion() && isSticky(el)) {
        handlers[el.dataset.scene](el, 0);
        io.observe(el);
      }
    });
    scrub();
  };
  init();
  on(window, "scroll", onScroll, { passive: true });
  on(window, "resize", onScroll);
  on(reduce, "change", init);
  on(wide, "change", init);

  /* ---------- course art: animate only while visible ---------- */
  const artIO = observe(
    new IntersectionObserver((entries) => entries.forEach((en) => en.target.classList.toggle("is-inview", en.isIntersecting)), {
      rootMargin: "0px 0px -10% 0px",
    }),
  );
  $$("[data-art]").forEach((el) => artIO.observe(el));

  /* ---------- reveal: animate from a visible resting state ---------- */
  if (!reduce.matches && "animate" in Element.prototype) {
    const revIO = observe(
      new IntersectionObserver(
        (entries) =>
          entries.forEach((en) => {
            if (!en.isIntersecting) return;
            revIO.unobserve(en.target);
            const kids = en.target.hasAttribute("data-reveal-kids") ? [...en.target.children] : [en.target];
            kids.forEach((k, i) =>
              k.animate(
                [
                  { opacity: 0, transform: "translateY(28px)" },
                  { opacity: 1, transform: "none" },
                ],
                { duration: 900, delay: i * 90, easing: "cubic-bezier(0.28, 0.11, 0.32, 1)", fill: "backwards" },
              ),
            );
          }),
        { threshold: 0.12, rootMargin: "0px 0px -6% 0px" },
      ),
    );
    $$("[data-reveal], [data-reveal-kids]").forEach((el) => {
      if (el.getBoundingClientRect().top > innerHeight) revIO.observe(el);
    });
  }

  /* ---------- tilt toward the cursor (course tiles) ---------- */
  if (!reduce.matches && matchMedia("(pointer: fine)").matches) {
    $$("[data-tilt]").forEach((el) => {
      on(el, "pointermove", (e) => {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5;
        const y = (e.clientY - r.top) / r.height - 0.5;
        el.style.setProperty("--rx", (x * 6).toFixed(2) + "deg");
        el.style.setProperty("--ry", (-y * 6).toFixed(2) + "deg");
      });
      on(el, "pointerleave", () => {
        el.style.setProperty("--rx", "0deg");
        el.style.setProperty("--ry", "0deg");
      });
    });
  }

  /* ---------- employers: hiring-floor story ---------- */
  const floor = root.querySelector("[data-floor]");
  if (floor) {
    const steps = [...floor.querySelectorAll(".floor-step")];
    const states = [...floor.querySelectorAll(".floor-state")];
    const dots = [...floor.querySelectorAll(".floor-rail i")];
    const typed = floor.querySelector(".typed");
    const fullText = typed?.dataset.text ?? "";
    let typeTimer = 0;
    let chipTimers = [];
    cleanups.push(() => {
      clearInterval(typeTimer);
      chipTimers.forEach(clearTimeout);
    });
    const runState = (i) => {
      if (i === 0 && typed && motion()) {
        clearInterval(typeTimer);
        let k = 0;
        typed.textContent = "";
        typeTimer = setInterval(() => {
          typed.textContent = fullText.slice(0, ++k);
          if (k >= fullText.length) clearInterval(typeTimer);
        }, 55);
      }
      if (i === 1) {
        chipTimers.forEach(clearTimeout);
        chipTimers = [];
        const rows = states[1]?.querySelectorAll("[data-final]") ?? [];
        rows.forEach((chip) => {
          if (!motion()) return;
          chip.className = "chip calling";
          chip.textContent = "Calling…";
          chipTimers.push(
            setTimeout(() => {
              chip.className = chip.dataset.final === "Screened ✓" ? "chip done" : "chip calling";
              chip.textContent = chip.dataset.final;
            }, 700 + Number(chip.dataset.delay || 0)),
          );
        });
      }
      if (i === 2) {
        const ring = states[2]?.querySelector(".ring");
        if (ring && motion()) {
          ring.style.setProperty("--fill", "0");
          requestAnimationFrame(() => requestAnimationFrame(() => ring.style.setProperty("--fill", "0.75")));
        }
      }
    };
    const activate = (i) => {
      steps.forEach((s, k) => s.classList.toggle("is-on", k === i));
      states.forEach((s, k) => s.classList.toggle("is-on", k === i));
      dots.forEach((d, k) => d.classList.toggle("is-on", k <= i));
      runState(i);
    };
    const fio = observe(
      new IntersectionObserver(
        (entries) =>
          entries.forEach((en) => {
            if (en.isIntersecting && motion()) activate(steps.indexOf(en.target));
          }),
        { rootMargin: "-48% 0px -48% 0px" },
      ),
    );
    steps.forEach((s) => fio.observe(s));
    activate(0);
  }

  /* ---------- global nav goes dark over black chapters ---------- */
  const darks = $$(".s-black");
  if (darks.length && root.querySelector(".gnav:not(.gnav--static)")) {
    let nio;
    const under = new Set();
    const watch = () => {
      nio?.disconnect();
      under.clear();
      const line = 47;
      nio = new IntersectionObserver(
        (entries) => {
          entries.forEach((en) => (en.isIntersecting ? under.add(en.target) : under.delete(en.target)));
          root.classList.toggle("nav-dark", under.size > 0);
        },
        { rootMargin: `-${line}px 0px -${Math.max(innerHeight - line - 2, 0)}px 0px` },
      );
      darks.forEach((d) => nio.observe(d));
    };
    watch();
    on(window, "resize", watch);
    cleanups.push(() => nio?.disconnect());
  }

  /* ---------- local nav: highlight the section in view ---------- */
  const lnavLinks = $$(".lnav-links a[href^='#']");
  if (lnavLinks.length) {
    const targets = lnavLinks.map((a) => root.querySelector(a.getAttribute("href"))).filter(Boolean);
    const lio = observe(
      new IntersectionObserver(
        (entries) =>
          entries.forEach((en) => {
            if (!en.isIntersecting) return;
            lnavLinks.forEach((a) => a.classList.toggle("is-active", a.getAttribute("href") === "#" + en.target.id));
          }),
        { rootMargin: "-40% 0px -55% 0px" },
      ),
    );
    targets.forEach((t) => lio.observe(t));
  }

  /* ---------- sticky enquire (course page, phones) ---------- */
  const sticky = root.querySelector(".sticky-enquire");
  const hero = root.querySelector(".course-hero");
  if (sticky && hero) {
    observe(new IntersectionObserver(([en]) => sticky.toggleAttribute("data-show", !en.isIntersecting))).observe(hero);
  }

  return () => {
    cleanups.forEach((fn) => fn());
    root.classList.remove("motion", "nav-dark");
  };
}
