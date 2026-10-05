/* eslint-disable */
// @ts-nocheck
/**
 * The Taurus console visuals, ported from the CRM so the employer console
 * looks and behaves like the one the founder already uses.
 *
 * Three pieces, each mounting onto a canvas the page owns: a drifting
 * starfield, the node sphere, and the robot whose jaw moves on the voice.
 * Kept as plain JS on purpose — it is a direct port, and rewriting it in
 * typed React idiom would be a rewrite, not a move.
 */

/** A drifting field of stars behind the console. */
export function mountStars(cv, REDUCE) {
	const ctx = cv.getContext("2d");
	let W, H, st = [];

	function build() {
		const rect = cv.parentElement.getBoundingClientRect();
		W = cv.width = Math.max(1, rect.width * devicePixelRatio);
		H = cv.height = Math.max(1, rect.height * devicePixelRatio);
		cv.style.width = rect.width + "px";
		cv.style.height = rect.height + "px";
		st = Array.from({ length: 130 }, () => ({
			x: Math.random() * W, y: Math.random() * H,
			r: (Math.random() * 1.1 + 0.3) * devicePixelRatio,
			ph: Math.random() * 6.28, sp: 0.3 + Math.random() * 0.8,
			c: Math.random() < 0.85 ? "180,205,240" : "127,176,255",
		}));
	}

	function draw() {
		ctx.clearRect(0, 0, W, H);
		const T = performance.now() / 1000;
		for (const s of st) {
			const a = REDUCE ? 0.5 : 0.25 + 0.45 * (0.5 + 0.5 * Math.sin(T * s.sp + s.ph));
			ctx.fillStyle = "rgba(" + s.c + "," + a + ")";
			ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, 7); ctx.fill();
		}
		if (!REDUCE) requestAnimationFrame(draw);
	}

	build(); draw();
	addEventListener("resize", () => { build(); if (REDUCE) draw(); });
}
export function mountSphere(cv, getMode, REDUCE) {
	const ctx = cv.getContext("2d");
	const PAL = ["#7FB0FF", "#1B6DF0", "#5FD6A2", "#F5A623", "#B78CFF", "#FF7FA8", "#59E0FF"];
	const N = 200, TILT = 0.42;
	let W, H, R, nodes = [], edges = [], pulses = [], rotY = 0;

	function size() {
		const rect = cv.getBoundingClientRect();
		W = cv.width = Math.max(1, rect.width * devicePixelRatio);
		H = cv.height = Math.max(1, rect.height * devicePixelRatio);
		R = Math.min(W, H) * 0.4;
	}
	function build() {
		size(); nodes = []; edges = []; pulses = [];
		const GA = Math.PI * (3 - Math.sqrt(5));	// fibonacci sphere
		for (let i = 0; i < N; i++) {
			const y = 1 - (i / (N - 1)) * 2, rad = Math.sqrt(1 - y * y), th = GA * i;
			nodes.push({
				x: Math.cos(th) * rad, y: y, z: Math.sin(th) * rad,
				c: PAL[i % 7 < 2 ? 0 : i % PAL.length],
				ph: Math.random() * 6.28, sp: 0.6 + Math.random(),
			});
		}
		for (let i = 0; i < N; i++) {
			for (let j = i + 1; j < N; j++) {
				const a = nodes[i], b = nodes[j];
				const d = (a.x - b.x) ** 2 + (a.y - b.y) ** 2 + (a.z - b.z) ** 2;
				if (d < 0.16) edges.push([i, j]);
			}
		}
	}
	function project(n) {
		const cy = Math.cos(rotY), sy = Math.sin(rotY);
		const x = n.x * cy - n.z * sy, z = n.x * sy + n.z * cy, y = n.y;
		const cx = Math.cos(TILT), sx = Math.sin(TILT);
		const y2 = y * cx - z * sx, z2 = y * sx + z * cx;
		const s = 3.1 / (3.1 + z2);
		return { x: W / 2 + x * R * s, y: H * 0.52 + y2 * R * s, z: z2, s: s };
	}
	function halo() {
		const col = getMode() === "listen" ? "11,168,96" : getMode() === "speak" ? "245,166,35" : "27,109,240";
		const g = ctx.createRadialGradient(W / 2, H * 0.52, R * 0.2, W / 2, H * 0.52, R * 1.55);
		g.addColorStop(0, "rgba(" + col + ",.13)");
		g.addColorStop(0.6, "rgba(" + col + ",.05)");
		g.addColorStop(1, "rgba(0,0,0,0)");
		ctx.fillStyle = g;
		ctx.fillRect(0, 0, W, H);
	}
	function firePulse() {
		if (pulses.length > 30 || !edges.length) return;
		const e = edges[Math.floor(Math.random() * edges.length)];
		pulses.push({ e: e, t: 0, sp: 0.014 + Math.random() * 0.02, c: PAL[Math.floor(Math.random() * PAL.length)] });
	}
	let frame = 0;
	function draw() {
		ctx.clearRect(0, 0, W, H);
		halo();
		const T = performance.now() / 1000;
		const P = nodes.map(project);

		for (const pair of edges) {
			const a = P[pair[0]], b = P[pair[1]];
			const o = Math.max(0, 0.3 - ((a.z + b.z) / 2) * 0.22);
			if (o <= 0.015) continue;
			ctx.strokeStyle = "rgba(127,176,255," + o + ")";
			ctx.lineWidth = 1;
			ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
		}

		if (!REDUCE) {
			if (frame++ % 5 === 0) firePulse();
			pulses = pulses.filter((p) => p.t <= 1);
			for (const p of pulses) {
				p.t += p.sp;
				const a = P[p.e[0]], b = P[p.e[1]];
				const x = a.x + (b.x - a.x) * p.t, y = a.y + (b.y - a.y) * p.t;
				const g = ctx.createRadialGradient(x, y, 0, x, y, 6 * devicePixelRatio);
				g.addColorStop(0, p.c); g.addColorStop(1, "rgba(0,0,0,0)");
				ctx.globalAlpha = 0.9; ctx.fillStyle = g;
				ctx.beginPath(); ctx.arc(x, y, 6 * devicePixelRatio, 0, 7); ctx.fill();
				ctx.globalAlpha = 1;
			}
		}

		nodes.forEach((n, i) => {
			const p = P[i];
			const tw = REDUCE ? 1 : 0.66 + 0.34 * Math.sin(T * n.sp * 2 + n.ph);
			const r = (1.1 + 1.5 * tw) * p.s * devicePixelRatio;
			const front = Math.max(0.25, 1 - ((p.z + 1) / 2) * 0.9);
			ctx.globalAlpha = front * tw;
			ctx.shadowColor = n.c;
			ctx.shadowBlur = 10 * devicePixelRatio * tw * front;
			ctx.fillStyle = n.c;
			ctx.beginPath(); ctx.arc(p.x, p.y, Math.max(0.6, r), 0, 7); ctx.fill();
			ctx.shadowBlur = 0; ctx.globalAlpha = 1;
		});

		if (!REDUCE) { rotY += 0.0032; requestAnimationFrame(draw); }
	}
	build(); draw();
	addEventListener("resize", () => { build(); if (REDUCE) draw(); });
}

/**
 * Taurus, rendered from the provided model rather than built from
 * primitives here.
 *
 * The .glb (`/models/taurus-bot.glb`) carries geometry only — no
 * materials, textures, skin or animation; it was generated as bare named
 * meshes (trimesh export), so every colour below is assigned by this file
 * rather than coming from the file itself. It was also authored Z-up
 * (common for Blender-family tools); glTF/Three.js expect Y-up, so the
 * loaded scene is rotated -90° about X on arrival to stand it upright.
 *
 * With no skin or rig, life comes from three things instead: the whole
 * figure breathing/swaying as one piece, the chin easing down a little on
 * the loudness of the voice, and the headset light / collar lights /
 * mic tip — one shared emissive material — changing colour with mode, the
 * same signal the old procedural head used its glow rings for.
 */
export const Bot3D = {
	ready: false,
	modelReady: false,
	level: 0,        // how loud Taurus is right now, 0 to 1
	mode: "idle",
	aim: { x: 0, y: 0 },

	tone: {
		idle: 0x1877f2,
		listen: 0x0ba860,
		think: 0x7fb0ff,
		speak: 0xf5a623,
	},

	/** Parts framed for the bust shot the card actually shows — the lowered
	 *  arms/hands would otherwise pull the auto-fit camera out too far. */
	BUST_PARTS: [
		"torso_white", "side_panel_-0.55", "side_panel_0.55", "neck", "head", "chin",
		"hair_cap", "hair_bun", "hair_strand_l", "hair_strand_r",
		"eye_-0.18", "eye_shine_-0.18", "eye_0.18", "eye_shine_0.18", "nose", "lips",
		"earpad_-0.53", "earpad_inner_-0.53", "earpad_0.53", "earpad_inner_0.53",
		"headset_band", "mic_arm", "mic_tip",
		"collar_glow_l", "collar_glow_r", "shoulder_light_l", "shoulder_light_r",
		"horn_l", "horn_l2", "horn_r2", "horn_r",
	],

	// Returns whether it actually started, so a caller whose canvas wasn't
	// mounted yet (afterInteractive can fire a frame or two before React
	// commits it) knows to try again instead of leaving the frame blank for
	// the rest of the page's life.
	init() {
		const canvas = document.getElementById("tc-bot3d");
		if (!canvas) { console.warn("[Bot3D] init: #tc-bot3d canvas not found in the DOM yet"); return false; }
		if (typeof THREE === "undefined") { console.warn("[Bot3D] init: THREE is not defined — /js/three.min.js did not load"); return false; }
		if (typeof THREE.GLTFLoader === "undefined") { console.warn("[Bot3D] init: THREE.GLTFLoader is not defined — /js/GLTFLoader.js did not load"); return false; }

		// A leftover context from an earlier mount that was never released is
		// exactly how a browser's hard cap on live WebGL contexts gets hit
		// over enough navigations. Disposing whatever this object already
		// holds first means at most one of this component's contexts is ever
		// alive at a time, however many times init() runs.
		if (this.renderer) {
			console.info("[Bot3D] init: disposing a previous context before creating a new one");
			this.dispose();
		}

		try {
			this.renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true });
		} catch (e) {
			console.error("[Bot3D] init: WebGLRenderer construction threw", e);
			return false;
		}

		console.info("[Bot3D] init: renderer created, building scene");
		this.renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
		this.scene = new THREE.Scene();
		// fov/aspect are provisional — frameCamera() re-aims once the model's
		// real size is known; aspect is corrected on every size() anyway.
		this.camera = new THREE.PerspectiveCamera(26, 3 / 4, 0.05, 100);

		// Hemisphere light carries the scene on its own: unlike a directional
		// light it needs no aim point, so every surface gets a sensible base
		// level of light regardless of where the model ends up sitting once
		// it loads. The directional pair below only add shape/shading on top
		// of that floor, and are re-aimed at the model's real centre once
		// it's known (see onModelLoaded) rather than at their construction-
		// time default of world origin, which is nowhere near the bust.
		this.hemi = new THREE.HemisphereLight(0xffffff, 0x40465c, 1.15);
		this.scene.add(this.hemi);

		this.ambient = new THREE.AmbientLight(0xffffff, 0.35);
		this.scene.add(this.ambient);

		this.key = new THREE.DirectionalLight(0xffffff, 1.1);
		this.key.position.set(2.2, 3.2, 2.6);
		this.scene.add(this.key);
		this.scene.add(this.key.target);

		this.rim = new THREE.DirectionalLight(0xbcd4ff, 0.6);
		this.rim.position.set(-2.6, 1.6, -1.8);
		this.scene.add(this.rim);
		this.scene.add(this.rim.target);

		this.size();

		this._onResize = () => this.size();
		this._onMouseMove = (e) => this.watch(e);
		addEventListener("resize", this._onResize);
		addEventListener("mousemove", this._onMouseMove, { passive: true });

		// window "resize" only fires for the browser chrome, not for the
		// frame settling into its own layout — a page-transition animation
		// still mid-flight, a web font swapping in, anything that reflows
		// .tc-botframe without the viewport itself changing. size() silently
		// no-ops while that box measures zero, and nothing was retrying: the
		// renderer's drawing buffer stayed at its default, and the model
		// rendered into a canvas nobody could see. A ResizeObserver on the
		// frame itself catches that settle whenever it actually happens.
		if (typeof ResizeObserver !== "undefined") {
			this.resizeObserver = new ResizeObserver(() => this.size());
			const frame = document.querySelector(".tc-botframe");
			if (frame) this.resizeObserver.observe(frame);
		}

		this.ready = true;
		this.clock = new THREE.Clock();
		console.info("[Bot3D] init: done, loading model and starting draw loop");
		this.draw();
		this.loadModel();
		return true;
	},

	loadModel() {
		if (this._loading || this.modelReady) return;
		this._loading = true;

		new THREE.GLTFLoader().load(
			"/models/taurus-bot.glb",
			(gltf) => {
				this._loading = false;
				try {
					this.onModelLoaded(gltf);
				} catch (e) {
					console.error("[Bot3D] loadModel: setting up the loaded model threw", e);
				}
			},
			undefined,
			(err) => {
				this._loading = false;
				console.error("[Bot3D] loadModel: GLTFLoader failed", err);
			},
		);
	},

	onModelLoaded(gltf) {
		const root = gltf.scene;

		// Authored Z-up -> Three.js/glTF's own Y-up convention.
		root.rotation.x = -Math.PI / 2;
		this.scene.add(root);
		this.root = root;

		this.parts = {};
		root.traverse((obj) => {
			if (obj.isMesh) {
				obj.castShadow = false;
				obj.receiveShadow = false;
				this.parts[obj.name] = obj;
			}
		});

		this.applyMaterials();

		// The chin has no separate hinge to rotate around, so "mouth opens"
		// is a small honest vertical ease instead of true jaw rotation — its
		// resting local position, remembered once, is what every speak-level
		// nudge below is measured from.
		this.chinRestZ = this.parts.chin ? this.parts.chin.position.z : 0;
		this.lipsRestZ = this.parts.lips ? this.parts.lips.position.z : 0;

		this.frameCamera();
		this.setMode(this.mode);
		this.modelReady = true;
	},

	applyMaterials() {
		// metalness stays at 0 (or as near it as still reads "metal-ish")
		// everywhere: a metallic MeshStandardMaterial gets most of its look
		// from reflecting an environment map, and this scene has none — it
		// would render close to black rather than shiny with nothing to
		// reflect. Roughness/colour alone carry the material distinctions.
		const std = (opts) => new THREE.MeshStandardMaterial(opts);

		const names = Object.keys(this.parts);
		console.info(`[Bot3D] applyMaterials: colouring ${names.length} part(s):`, names);

		// Safety net, applied before the named overrides below: every
		// primitive in this file has no material of its own, and
		// GLTFLoader's default for that is metalness:1/roughness:1 — a bare
		// metal with no environment map to reflect, i.e. black, regardless
		// of how many lights are in the scene. Giving every part a plain
		// grey to start means a name that fails to match one of the
		// patterns below still ends up visibly grey, never invisible-black.
		const fallback = std({ color: 0xd7dbe2, roughness: 0.65 });
		for (const name of names) this.parts[name].material = fallback;

		const skin = std({ color: 0xe7b891, roughness: 0.6 });
		const hair = std({ color: 0x3b2a20, roughness: 0.55 });
		const uniformWhite = std({ color: 0xf5f7fb, roughness: 0.6 });
		const uniformPanel = std({ color: 0xc7d1e0, roughness: 0.55 });
		const darkBelt = std({ color: 0x2b3446, roughness: 0.6 });
		const headsetDark = std({ color: 0x373f4d, roughness: 0.5 });
		const eyeDark = std({ color: 0x241a14, roughness: 0.35 });
		const eyeShine = std({ color: 0xffffff, roughness: 0.2 });
		const lipsMat = std({ color: 0xc97b72, roughness: 0.5 });
		const horn = std({ color: 0xc9a44e, roughness: 0.4, metalness: 0.15 });

		// The one material every mode-coloured part shares, so a single
		// colour/intensity change below reads as one coherent status light
		// across the headset mic, collar and belt rather than several.
		this.glow = std({
			color: this.tone.idle, emissive: this.tone.idle, emissiveIntensity: 1.15, roughness: 0.3,
		});

		const assign = (pattern, material) => {
			for (const name in this.parts) {
				if (pattern.test(name)) this.parts[name].material = material;
			}
		};

		assign(/^torso_white$/, uniformWhite);
		assign(/^side_panel/, uniformPanel);
		assign(/^belt$/, darkBelt);
		assign(/^(neck|head|chin|nose)$/, skin);
		assign(/^hair_/, hair);
		assign(/^eye_-?0/, eyeDark);
		assign(/^eye_shine/, eyeShine);
		assign(/^lips$/, lipsMat);
		assign(/^(earpad|headset_band|mic_arm)/, headsetDark);
		assign(/^mic_tip$/, this.glow);
		assign(/^(collar_glow|shoulder_light|belt_light)/, this.glow);
		assign(/^(right_upper_arm|right_forearm|left_upper_arm|left_forearm)$/, uniformWhite);
		assign(/^(right_hand|left_hand|finger_)/, skin);
		assign(/^horn_/, horn);

		const stillFallback = names.filter((n) => this.parts[n].material === fallback);
		if (stillFallback.length > 0) {
			console.info(`[Bot3D] applyMaterials: ${stillFallback.length} part(s) matched no colour pattern, left grey:`, stillFallback);
		}
	},

	/**
	 * Frames the bust rather than the whole figure — computed from the
	 * model's real, post-rotation world bounds (via Box3) rather than
	 * hand-measured numbers, so it stays correct even if the source model
	 * is ever swapped for a different build at the same file path.
	 */
	frameCamera() {
		const box = new THREE.Box3();
		let any = false;
		for (const name of this.BUST_PARTS) {
			const mesh = this.parts[name];
			if (mesh) { box.expandByObject(mesh); any = true; }
		}
		if (!any) box.setFromObject(this.root);

		const center = box.getCenter(new THREE.Vector3());
		const size = box.getSize(new THREE.Vector3());
		const maxDim = Math.max(size.x, size.y, size.z) || 1;
		const fitDist = (maxDim / 2) / Math.tan((this.camera.fov * Math.PI / 180) / 2) * 1.45;

		this.cameraCenter = center;
		this.cameraDist = fitDist;
		this.camera.position.set(center.x, center.y + size.y * 0.04, center.z + fitDist);
		this.camera.lookAt(center);

		// Point the directional pair at wherever the bust actually ended up
		// — their construction-time default target (world origin) is nowhere
		// near it, which is what left the figure almost unlit before this.
		if (this.key) this.key.target.position.copy(center);
		if (this.rim) this.rim.target.position.copy(center);
	},

	/**
	 * Releases the live WebGL context and every listener init() attached, so
	 * leaving this page (or calling init() again) never leaves a context
	 * behind that nothing will ever free. Safe to call even if init() never
	 * successfully ran — every step guards on the thing it is cleaning up
	 * actually existing.
	 */
	dispose() {
		this.ready = false;
		this.modelReady = false;
		this._loading = false;

		if (this._onResize) removeEventListener("resize", this._onResize);
		if (this._onMouseMove) removeEventListener("mousemove", this._onMouseMove);
		this._onResize = null;
		this._onMouseMove = null;

		this.resizeObserver?.disconnect();
		this.resizeObserver = null;

		// Frees the actual GPU-side context — the part a browser's context
		// limit counts. renderer.dispose() alone does not do this; the
		// WEBGL_lose_context extension is the documented way to force it.
		try {
			this.renderer?.getContext()?.getExtension("WEBGL_lose_context")?.loseContext();
		} catch {
			// Best-effort — dispose() below still frees what it can either way.
		}
		this.renderer?.dispose();
		this.renderer = null;
		this.scene = null;
		this.camera = null;
		this.root = null;
		this.parts = null;
	},

	size() {
		const frame = document.querySelector(".tc-botframe");
		if (!frame || !this.renderer) return;

		const box = frame.getBoundingClientRect();
		if (!box.width) return;

		this.renderer.setSize(box.width, box.height, false);
		if (this.camera) {
			this.camera.aspect = box.width / box.height;
			this.camera.updateProjectionMatrix();
		}
	},

	/** The head turns towards the pointer, within reason. */
	watch(e) {
		const frame = document.querySelector(".tc-botframe");
		if (!frame) return;

		const box = frame.getBoundingClientRect();
		if (!box.width) return;

		const clamp = (n) => Math.max(-1, Math.min(1, n));
		this.aim.x = clamp((e.clientX - (box.left + box.width / 2)) / (box.width * 3));
		this.aim.y = clamp((e.clientY - (box.top + box.height / 2)) / (box.height * 3));
	},

	setMode(mode) {
		this.mode = mode;
		if (!this.modelReady || !this.glow) return;

		const tone = this.tone[mode] || this.tone.idle;
		this.glow.color.setHex(tone);
		this.glow.emissive.setHex(tone);
		if (this.key) this.key.color.setHex(mode === "idle" ? 0xffffff : tone);
	},

	draw() {
		// Stops rescheduling itself once dispose() has run, instead of an
		// animation loop that outlives the context it was drawing into and
		// runs forever doing nothing.
		if (!this.ready) return;
		requestAnimationFrame(() => this.draw());
		if (document.hidden || !this.renderer || !this.camera) return;

		const t = this.clock.getElapsedTime();

		if (this.root) {
			// idle life: a slow breath and a small sway, so the figure is
			// never perfectly still the way only a still image is.
			this.root.position.y = Math.sin(t * 1.1) * 0.025;
			this.root.rotation.z = Math.sin(t * 0.7) * 0.012;
			// Cursor-follow was part of the old procedural head; skipped here
			// rather than guessed at, since this model's own front-facing axis
			// after the Z-up correction has not been confirmed by eye yet, and
			// a rotation on the wrong axis would read as broken, not alive.

			// the chin eases down on the loudness of the voice — the one
			// honest stand-in for a jaw this model has no hinge to rotate.
			const open = this.mode === "speak" ? this.level : 0;
			if (this.parts?.chin) this.parts.chin.position.z = this.chinRestZ - open * 0.06;
			if (this.parts?.lips) this.parts.lips.position.z = this.lipsRestZ - open * 0.04;
		}

		if (this.glow) {
			const pulse = this.mode === "think" ? 3.2 : this.mode === "listen" ? 2 : 1.1;
			const base = this.mode === "speak" ? 0.9 + this.level * 1.1 : 1.15;
			this.glow.emissiveIntensity = base + Math.abs(Math.sin(t * pulse)) * (this.mode === "idle" ? 0.15 : 0.4);
		}

		if (this.cameraCenter) this.camera.lookAt(this.cameraCenter);

		this.renderer.render(this.scene, this.camera);
	},
};
