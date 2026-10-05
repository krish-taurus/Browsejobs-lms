/**
 * The console's host, built entirely in code.
 *
 * Two earlier attempts failed for the same reason. Warping a photograph gave a
 * jaw that opened and shut; playing a generated clip gave real motion that had
 * nothing to do with the words. Both moved. Neither was *reading the message*,
 * and that is the only thing that makes a face look like it is talking to you.
 *
 * So this one is driven by the sentence itself. The text being spoken is
 * turned into a timeline of mouth shapes — visemes — and the mouth is posed
 * from that timeline against the audio's own clock. When she says "oh" the
 * mouth rounds; on "ee" it widens; on "m" it closes. The amplitude from the
 * analyser then rides on top, so an emphasised word opens further than a
 * mumbled one.
 *
 * There is no video and no image. Every polygon here is generated, which means
 * the mouth can be posed frame by frame instead of being whatever the
 * photographer or the model happened to capture.
 *
 * It is stylised rather than photoreal, deliberately. A hand-built human face
 * that reaches for photorealism lands in the uncanny valley; one that commits
 * to being a designed character does not.
 */

/** The mouth shapes worth distinguishing, as width/height/round multipliers. */

const VISEMES = {
  rest: { w: 1, h: 0.06, round: 0 },
  closed: { w: 0.96, h: 0.04, round: 0 },
  aa: { w: 1.02, h: 0.92, round: 0.15 },
  e: { w: 1.24, h: 0.42, round: 0 },
  i: { w: 1.16, h: 0.26, round: 0 },
  o: { w: 0.74, h: 0.78, round: 0.85 },
  u: { w: 0.6, h: 0.44, round: 1 },
  fv: { w: 1.02, h: 0.16, round: 0 },
  l: { w: 0.94, h: 0.5, round: 0.1 },
};

/**
 * The same visemes written as ARKit blendshapes, for a rigged face.
 *
 * These are the shape names every ARKit-compatible avatar ships with — Ready
 * Player Me, VRoid, Apple's own capture, the three.js face model. Posing a
 * real rig by name beats scaling an ellipse: "oh" funnels the lips forward,
 * "oo" puckers them, "m" presses them shut, and the jaw opens underneath by
 * the right amount for each.
 */
/** Every mouth shape any recipe uses — all of them are released each frame. */
const MOUTH_SHAPES = [
  "mouthClose", "mouthPress_L", "mouthPress_R", "mouthLowerDown_L", "mouthLowerDown_R",
  "mouthStretch_L", "mouthStretch_R", "mouthUpperUp_L", "mouthUpperUp_R",
  "mouthSmile_L", "mouthSmile_R", "mouthFunnel", "mouthPucker",
  "mouthRollLower", "mouthShrugUpper", "tongueOut",
];

const RIG = {
  rest: {},
  closed: { mouthClose: 0.55, mouthPress_L: 0.3, mouthPress_R: 0.3 },
  aa: { jawOpen: 0.72, mouthLowerDown_L: 0.3, mouthLowerDown_R: 0.3 },
  e: { jawOpen: 0.26, mouthStretch_L: 0.55, mouthStretch_R: 0.55, mouthUpperUp_L: 0.2, mouthUpperUp_R: 0.2 },
  i: { jawOpen: 0.16, mouthStretch_L: 0.4, mouthStretch_R: 0.4, mouthSmile_L: 0.18, mouthSmile_R: 0.18 },
  o: { jawOpen: 0.44, mouthFunnel: 0.72, mouthPucker: 0.28 },
  u: { jawOpen: 0.18, mouthPucker: 0.82, mouthFunnel: 0.34 },
  fv: { jawOpen: 0.1, mouthRollLower: 0.6, mouthUpperUp_L: 0.25, mouthUpperUp_R: 0.25 },
  l: { jawOpen: 0.3, tongueOut: 0.16, mouthShrugUpper: 0.2 },
};

/**
 * Which shape a letter makes.
 *
 * This is not phonetics — it is the handful of distinctions an eye can
 * actually catch at this size. Vowels carry nearly all of it; consonants
 * mostly close the mouth, which is what makes the gaps between words read.
 */
function visemeFor(char) {
  if ("aàáâä".includes(char)) return "aa";
  if ("eèéêë".includes(char)) return "e";
  if ("iìíîïy".includes(char)) return "i";
  if ("oòóôö".includes(char)) return "o";
  if ("uùúûüw".includes(char)) return "u";
  if ("mbp".includes(char)) return "closed";
  if ("fv".includes(char)) return "fv";
  if ("lrtdnszh".includes(char)) return "l";
  if (char === " ") return "rest";

  return "l";
}

/**
 * Spread a sentence across its own duration.
 *
 * Every character gets an equal slice, which is wrong in the way all even
 * spacing is wrong and right in the way that matters: the mouth changes shape
 * at roughly the rate the voice does, and lands on the correct shape for the
 * vowel being held. Real forced alignment would need the phoneme timings back
 * from the TTS, which this provider does not return.
 */
function timeline(text) {
  const letters = text.toLowerCase().replace(/[^a-zà-ü ]/g, "");
  if (letters === "") return [{ at: 0, viseme: "rest" }];

  const cues = [];
  let previous = "";

  for (let i = 0; i < letters.length; i += 1) {
    const viseme = visemeFor(letters[i]);

    // A run of the same shape is one hold, not twenty identical keyframes.
    if (viseme !== previous) {
      cues.push({ at: i / letters.length, viseme });
      previous = viseme;
    }
  }

  cues.push({ at: 1, viseme: "rest" });

  return cues;
}

export const AiHost = {
  level: 0,
  mode: "idle",

  _renderer: null,
  _avatar: null,
  _scene: null,
  _camera: null,
  _head: null,
  _mouth: null,
  _lips: null,
  _lids: [],
  _eyes: [],
  _accents: [],
  _cues: [],
  _clock: null,
  _pose: { w: 1, h: 0.06, round: 0 },
  _blink: 0,
  _nextBlink: 1400,
  _last: 0,
  _started: false,

  /** Morph-target influence arrays for every mesh on the loaded avatar. */
  _rig: [],
  /** True once a .glb avatar is driving the face instead of the primitives. */
  _rigged: false,

  /**
   * Speak a sentence.
   *
   * `progress` returns 0–1 through the utterance — the audio element's own
   * clock, so the mouth cannot drift away from the voice however long the clip
   * runs or however the browser schedules frames.
   */
  say(text, progress) {
    this._cues = timeline(text);
    this._clock = progress;
  },

  /** Stop talking and let the mouth settle closed. */
  hush() {
    this._cues = [];
    this._clock = null;
  },

  init(canvas) {
    const THREE = window.THREE;
    if (!THREE || this._started) return this._started;

    try {
      return this._build(THREE, canvas);
    } catch (error) {
      // A missing geometry or a refused WebGL context must fall back to the
      // robot, not throw through the caller and leave an empty frame.
      console.error("AI host could not start:", error);
      this._started = false;

      return false;
    }
  },

  _build(THREE, canvas) {

    this._started = true;

    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setPixelRatio(Math.min(2, window.devicePixelRatio || 1));
    this._renderer = renderer;

    const scene = new THREE.Scene();
    this._scene = scene;

    const camera = new THREE.PerspectiveCamera(30, 3 / 4, 0.1, 100);
    camera.position.set(0, 0.1, 9.2);
    this._camera = camera;

    // Cool key from the front-left, blue rim from behind — the lighting of the
    // console itself, so she belongs in the panel rather than sitting on it.
    scene.add(new THREE.AmbientLight(0x8fa8cc, 0.75));

    const key = new THREE.DirectionalLight(0xffffff, 1.15);
    key.position.set(-2.5, 3, 4.5);
    scene.add(key);

    const rim = new THREE.DirectionalLight(0x53a8ff, 1.5);
    rim.position.set(1.5, 1.2, -3.5);
    scene.add(rim);

    const skin = new THREE.MeshStandardMaterial({ color: 0xf2cdb4, roughness: 0.62, metalness: 0.02 });
    const hair = new THREE.MeshStandardMaterial({ color: 0x2c1d16, roughness: 0.72, metalness: 0.05 });
    const suit = new THREE.MeshStandardMaterial({ color: 0xf0f3f8, roughness: 0.45, metalness: 0.12 });
    const trim = new THREE.MeshStandardMaterial({ color: 0xb8c2d0, roughness: 0.4, metalness: 0.3 });
    const glow = new THREE.MeshBasicMaterial({ color: 0x4fb4ff });
    const dark = new THREE.MeshStandardMaterial({ color: 0x1a1520, roughness: 0.9 });

    const head = new THREE.Group();
    head.position.y = 0.55;
    scene.add(head);
    this._head = head;

    // --- skull -----------------------------------------------------
    const skull = new THREE.Mesh(new THREE.SphereGeometry(1, 48, 48), skin);
    skull.scale.set(0.82, 1, 0.86);
    head.add(skull);

    // A jaw narrower than the cranium is most of what reads as a face.
    const jaw = new THREE.Mesh(new THREE.SphereGeometry(0.78, 40, 32), skin);
    jaw.scale.set(0.86, 0.78, 0.82);
    jaw.position.set(0, -0.5, 0.04);
    head.add(jaw);

    // --- hair ------------------------------------------------------
    const cap = new THREE.Mesh(new THREE.SphereGeometry(1.04, 40, 32, 0, Math.PI * 2, 0, Math.PI * 0.62), hair);
    cap.scale.set(0.85, 1.02, 0.9);
    cap.position.y = 0.06;
    head.add(cap);

    const bun = new THREE.Mesh(new THREE.SphereGeometry(0.34, 28, 24), hair);
    bun.position.set(0, 0.92, -0.32);
    head.add(bun);

    for (const side of [-1, 1]) {
      const strand = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.05, 0.72, 12), hair);
      strand.position.set(side * 0.74, -0.18, 0.36);
      strand.rotation.z = side * 0.16;
      head.add(strand);
    }

    // --- eyes ------------------------------------------------------
    for (const side of [-1, 1]) {
      const white = new THREE.Mesh(new THREE.SphereGeometry(0.17, 24, 20), new THREE.MeshStandardMaterial({ color: 0xfdfdff, roughness: 0.25 }));
      white.position.set(side * 0.33, 0.1, 0.66);
      white.scale.set(1, 0.82, 0.5);
      head.add(white);

      const iris = new THREE.Mesh(new THREE.SphereGeometry(0.085, 20, 16), new THREE.MeshStandardMaterial({ color: 0x53341f, roughness: 0.3 }));
      iris.position.set(side * 0.33, 0.1, 0.73);
      iris.scale.set(1, 1, 0.45);
      head.add(iris);

      const pupil = new THREE.Mesh(new THREE.SphereGeometry(0.04, 16, 12), dark);
      pupil.position.set(side * 0.33, 0.1, 0.76);
      pupil.scale.set(1, 1, 0.4);
      head.add(pupil);
      this._eyes.push(iris);

      // The lid is a skin-coloured cap that drops over the eye to blink.
      const lid = new THREE.Mesh(new THREE.SphereGeometry(0.185, 24, 16, 0, Math.PI * 2, 0, Math.PI * 0.5), skin);
      lid.position.set(side * 0.33, 0.1, 0.66);
      lid.scale.set(1, 0.82, 0.55);
      head.add(lid);
      this._lids.push(lid);

      const brow = new THREE.Mesh(new THREE.CylinderGeometry(0.032, 0.032, 0.34, 8), hair);
      brow.position.set(side * 0.33, 0.33, 0.7);
      brow.rotation.z = Math.PI / 2 + side * 0.1;
      head.add(brow);
    }

    // --- nose ------------------------------------------------------
    const nose = new THREE.Mesh(new THREE.ConeGeometry(0.1, 0.3, 16), skin);
    nose.position.set(0, -0.16, 0.78);
    nose.rotation.x = Math.PI * 0.5;
    nose.scale.set(1, 1, 0.7);
    head.add(nose);

    // --- mouth -----------------------------------------------------
    // The dark interior, scaled per viseme; the lips ride around it.
    const mouth = new THREE.Mesh(new THREE.SphereGeometry(0.2, 28, 20), new THREE.MeshStandardMaterial({ color: 0x50202c, roughness: 0.85 }));
    mouth.position.set(0, -0.52, 0.66);
    mouth.scale.set(1, 0.06, 0.4);
    head.add(mouth);
    this._mouth = mouth;

    const lips = new THREE.Mesh(new THREE.TorusGeometry(0.2, 0.045, 10, 28), new THREE.MeshStandardMaterial({ color: 0xd48a86, roughness: 0.42 }));
    lips.position.set(0, -0.52, 0.68);
    lips.scale.set(1, 0.35, 0.6);
    head.add(lips);
    this._lips = lips;

    // --- headset ---------------------------------------------------
    const band = new THREE.Mesh(new THREE.TorusGeometry(1.02, 0.045, 10, 48, Math.PI), trim);
    band.rotation.z = Math.PI / 2;
    band.rotation.y = 0.1;
    band.position.y = 0.02;
    head.add(band);

    const cup = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.2, 0.12, 24), suit);
    cup.rotation.z = Math.PI / 2;
    cup.position.set(-0.86, 0.02, 0.02);
    head.add(cup);

    const cupLight = new THREE.Mesh(new THREE.TorusGeometry(0.13, 0.018, 8, 24), glow);
    cupLight.rotation.y = Math.PI / 2;
    cupLight.position.set(-0.93, 0.02, 0.02);
    head.add(cupLight);
    this._accents.push(cupLight);

    const boom = new THREE.Mesh(new THREE.CylinderGeometry(0.022, 0.022, 0.82, 10), trim);
    boom.position.set(-0.62, -0.44, 0.48);
    boom.rotation.set(0.25, 0, -0.72);
    head.add(boom);

    const mic = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, 0.14, 10), glow);
    mic.position.set(-0.3, -0.66, 0.62);
    mic.rotation.z = Math.PI / 2;
    head.add(mic);
    this._accents.push(mic);

    // --- neck and shoulders ---------------------------------------
    const neck = new THREE.Mesh(new THREE.CylinderGeometry(0.34, 0.42, 0.7, 24), skin);
    neck.position.set(0, -0.5, 0);
    scene.add(neck);

    const collar = new THREE.Mesh(new THREE.CylinderGeometry(0.52, 0.58, 0.42, 28), suit);
    collar.position.set(0, -1, 0);
    scene.add(collar);

    const torso = new THREE.Mesh(new THREE.CylinderGeometry(0.72, 1.5, 1.5, 32), suit);
    torso.position.set(0, -2.1, 0);
    scene.add(torso);

    for (const side of [-1, 1]) {
      const strip = new THREE.Mesh(new THREE.BoxGeometry(0.07, 0.9, 0.05), glow);
      strip.position.set(side * 0.72, -2.05, 0.72);
      strip.rotation.z = side * 0.24;
      scene.add(strip);
      this._accents.push(strip);
    }

    const chest = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.3, 0.05), glow);
    chest.position.set(0, -1.55, 0.78);
    scene.add(chest);
    this._accents.push(chest);

    const resize = () => {
      const box = canvas.getBoundingClientRect();
      if (box.width < 2) return;
      renderer.setSize(box.width, box.height, false);
      camera.aspect = box.width / box.height;
      camera.updateProjectionMatrix();
    };

    resize();
    new ResizeObserver(resize).observe(canvas);

    this._last = performance.now();
    this._draw();

    // The primitives above are the floor. If a rigged avatar loads, it takes
    // over — a real face posed by name beats geometry scaled by hand, and
    // swapping the character then means swapping one .glb.
    this._loadAvatar(THREE);

    return true;
  },

  /**
   * Swap in a rigged .glb, if one is present and the browser can decode it.
   *
   * Failure here is not an error: the coded head keeps talking. That matters
   * because the avatar is 300KB of texture-compressed mesh and the console
   * must not sit blank waiting for it on a slow connection.
   */
  _loadAvatar(THREE) {
    const Loader = THREE.GLTFLoader;
    if (!Loader) return;

    const loader = new Loader();

    // facecap-class avatars ship meshopt-compressed and Basis-compressed, so
    // both decoders have to be attached before the file will open at all.
    if (THREE.MeshoptDecoder) loader.setMeshoptDecoder(THREE.MeshoptDecoder);

    if (THREE.KTX2Loader) {
      try {
        const ktx2 = new THREE.KTX2Loader().setTranscoderPath("/js/basis/").detectSupport(this._renderer);
        loader.setKTX2Loader(ktx2);
      } catch {
        // No Basis support on this GPU — the mesh may still load untextured.
      }
    }

    loader.load(
      "/models/host.glb",
      (gltf) => {
        const face = gltf.scene;
        const rig = [];

        face.traverse((node) => {
          if (node.isMesh && node.morphTargetDictionary && node.morphTargetInfluences) {
            rig.push({ dict: node.morphTargetDictionary, influences: node.morphTargetInfluences });
          }
        });

        // An avatar with no blendshapes cannot form a word, so it is worse
        // than the primitives it would replace. Leave them alone.
        if (rig.length === 0) return;

        // Hide the hand-built head rather than deleting it — it is still the
        // fallback if anything about the avatar misbehaves later.
        this._head.visible = false;
        for (const accent of this._accents) accent.visible = accent.parent === this._scene;

        face.position.set(0, -0.35, 0);
        face.scale.setScalar(6.2);
        this._scene.add(face);
        this._avatar = face;
        this._rig = rig;
        this._rigged = true;
      },
      undefined,
      () => {
        // Missing or unreadable: the coded head is already on screen.
      },
    );
  },

  /** Set one ARKit blendshape by name, on every mesh that has it. */
  _shape(name, value) {
    for (const mesh of this._rig) {
      const index = mesh.dict[name];
      if (index !== undefined) mesh.influences[index] += (value - mesh.influences[index]) * 0.35;
    }
  },

  setMode(mode) {
    this.mode = mode;

    const colour = mode === "listen" ? 0x35d69a : mode === "think" ? 0x7fb0ff : mode === "speak" ? 0xffc46b : 0x4fb4ff;

    for (const accent of this._accents) accent.material.color.setHex(colour);
  },

  /** The shape the mouth should be holding at this instant. */
  _target() {
    if (this._clock && this._cues.length > 0) {
      const t = Math.min(1, Math.max(0, this._clock()));

      let current = this._cues[0];
      let next = this._cues[this._cues.length - 1];

      for (let i = 0; i < this._cues.length; i += 1) {
        if (this._cues[i].at <= t) {
          current = this._cues[i];
          next = this._cues[i + 1] ?? current;
        }
      }

      const span = Math.max(0.0001, next.at - current.at);
      const blend = Math.min(1, (t - current.at) / span);
      const a = VISEMES[current.viseme] ?? VISEMES.rest;
      const b = VISEMES[next.viseme] ?? VISEMES.rest;

      // Loudness rides on top: the same vowel opens further when stressed.
      const push = 0.55 + this.level * 0.75;

      return {
        w: a.w + (b.w - a.w) * blend,
        h: (a.h + (b.h - a.h) * blend) * push,
        round: a.round + (b.round - a.round) * blend,
      };
    }

    return VISEMES.rest;
  },

  /**
   * Pose the rigged face for this instant.
   *
   * Every shape in the current viseme is eased towards its target and every
   * other mouth shape eased back to zero, so shapes never accumulate — without
   * that, a sentence ends with the mouth stuck in the sum of everything it
   * just said.
   */
  _poseRig() {
    const target = this._target();
    const cue = this._currentCue();
    const recipe = RIG[cue] ?? RIG.rest;

    // Loudness scales the whole pose: a stressed vowel opens further.
    const push = Math.min(1.25, 0.6 + this.level * 0.8);

    for (const name of MOUTH_SHAPES) {
      this._shape(name, (recipe[name] ?? 0) * push);
    }

    // The jaw follows the interpolated height as well, so the mouth keeps
    // moving between two cues rather than snapping from one to the next.
    this._shape("jawOpen", Math.max((recipe.jawOpen ?? 0) * push, target.h * 0.75));

    const shut = this._blink > 0.02 ? 1 : 0;
    this._shape("eyeBlink_L", shut);
    this._shape("eyeBlink_R", shut);
  },

  /** Which viseme the timeline is currently holding. */
  _currentCue() {
    if (!this._clock || this._cues.length === 0) return "rest";

    const t = Math.min(1, Math.max(0, this._clock()));
    let cue = "rest";

    for (const entry of this._cues) {
      if (entry.at <= t) cue = entry.viseme;
    }

    return cue;
  },

  _draw() {
    requestAnimationFrame(() => this._draw());

    const now = performance.now();
    const dt = Math.min(64, now - this._last);
    this._last = now;

    // --- mouth ----------------------------------------------------
    const target = this._target();
    const ease = 0.32;

    this._pose.w += (target.w - this._pose.w) * ease;
    this._pose.h += (target.h - this._pose.h) * ease;
    this._pose.round += (target.round - this._pose.round) * ease;

    const width = 1 - this._pose.round * 0.34;

    this._mouth.scale.set(this._pose.w * width, Math.max(0.05, this._pose.h), 0.4);
    this._lips.scale.set(this._pose.w * width, Math.max(0.12, this._pose.h * 0.9), 0.6);

    if (this._rigged) this._poseRig();

    // --- blink ----------------------------------------------------
    // (the rigged face blinks through its own shapes, below)
    this._nextBlink -= dt;
    if (this._nextBlink <= 0) {
      this._blink = 1;
      this._nextBlink = 2400 + Math.random() * 3600;
    }
    this._blink = Math.max(0, this._blink - dt / 95);

    for (const lid of this._lids) lid.scale.y = 0.82 * (0.06 + (1 - this._blink) * 0.94) + 0.02;

    // --- life -----------------------------------------------------
    // A head that never moves is a mannequin; one that moves too much is a
    // toy. This is a slow figure-of-eight, plus a nod on emphasis.
    const t = now / 1000;
    this._head.rotation.y = Math.sin(t * 0.42) * 0.075;
    this._head.rotation.x = Math.sin(t * 0.31) * 0.045 + this.level * 0.03;
    this._head.position.y = 0.55 + Math.sin(t * 0.55) * 0.014;

    for (const iris of this._eyes) iris.position.x += (Math.sin(t * 0.37) * 0.012 - (iris.position.x - Math.sign(iris.position.x) * 0.33)) * 0.1;

    this._renderer.render(this._scene, this._camera);
  },
};
