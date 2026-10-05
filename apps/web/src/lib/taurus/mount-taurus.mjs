import * as THREE from 'three';
import { createTaurusRobot } from './taurus-robot.mjs';

// Browser-only helper. Import and call after the component has mounted.
export function mountTaurus(container, { onError = () => {} } = {}) {
  if (!(container instanceof HTMLElement)) throw new TypeError('A dedicated HTML container is required.');
  const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
  renderer.setClearColor(0x000000, 0);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.12;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.domElement.style.cssText = 'width:100%;height:100%;display:block;';
  renderer.domElement.setAttribute('aria-hidden', 'true');
  container.append(renderer.domElement);

  const scene = new THREE.Scene();
  const camera = new THREE.OrthographicCamera(-3, 3, 3.4, -3.4, 0.1, 80);
  camera.position.set(3.6, 4.5, 18);
  camera.lookAt(0, 2.9, 0);
  const media = window.matchMedia('(prefers-reduced-motion: reduce)');
  const robot = createTaurusRobot({ reducedMotion: media.matches });
  scene.add(robot.root);

  const hemisphere = new THREE.HemisphereLight(0xffffff, 0x819b88, 2.5);
  const key = new THREE.DirectionalLight(0xfffcf2, 3.5);
  key.position.set(-3, 8, 7);
  key.castShadow = true;
  key.shadow.mapSize.set(1024, 1024);
  key.shadow.camera.left = -4;
  key.shadow.camera.right = 4;
  key.shadow.camera.top = 7;
  key.shadow.camera.bottom = -3;
  key.shadow.normalBias = 0.025;
  const fill = new THREE.DirectionalLight(0xd6eddf, 1.4);
  fill.position.set(4, 4, -3);
  scene.add(hemisphere, key, fill);

  const floorGeometry = new THREE.CylinderGeometry(2.6, 2.6, 0.035, 64);
  const floorMaterial = new THREE.MeshStandardMaterial({ color: 0xe6eee3, roughness: 1 });
  const floor = new THREE.Mesh(floorGeometry, floorMaterial);
  floor.position.set(0.15, 0.015, 0);
  floor.receiveShadow = true;
  scene.add(floor);

  let disposed = false;
  let visible = true;
  let raf = 0;
  const started = performance.now();
  const render = () => {
    if (disposed) return;
    robot.update((performance.now() - started) / 1000 + 0.5);
    renderer.render(scene, camera);
  };
  const stop = () => { cancelAnimationFrame(raf); raf = 0; };
  const tick = () => {
    raf = 0;
    if (disposed || !visible || document.hidden || media.matches) return;
    render();
    raf = requestAnimationFrame(tick);
  };
  const sync = () => {
    stop();
    if (!disposed && visible && !document.hidden) {
      render();
      if (!media.matches) raf = requestAnimationFrame(tick);
    }
  };
  const resize = () => {
    if (disposed) return;
    const width = Math.max(container.clientWidth, 1);
    const height = Math.max(container.clientHeight, 1);
    const aspect = width / height;
    const halfHeight = Math.max(3.35, 2.60 / aspect);
    camera.left = -halfHeight * aspect;
    camera.right = halfHeight * aspect;
    camera.top = halfHeight;
    camera.bottom = -halfHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height, false);
    render();
  };
  const resizeObserver = new ResizeObserver(resize);
  resizeObserver.observe(container);
  const intersectionObserver = new IntersectionObserver(entries => {
    visible = entries[0]?.isIntersecting ?? true;
    sync();
  });
  intersectionObserver.observe(container);
  const motionChanged = () => { robot.setReducedMotion(media.matches); sync(); };
  media.addEventListener('change', motionChanged);
  document.addEventListener('visibilitychange', sync);

  function dispose() {
    if (disposed) return;
    disposed = true;
    stop();
    resizeObserver.disconnect();
    intersectionObserver.disconnect();
    media.removeEventListener('change', motionChanged);
    document.removeEventListener('visibilitychange', sync);
    renderer.domElement.removeEventListener('webglcontextlost', contextLost);
    robot.dispose();
    floorGeometry.dispose();
    floorMaterial.dispose();
    key.shadow.map?.dispose();
    renderer.dispose();
    renderer.domElement.remove();
  }
  function contextLost(event) {
    event.preventDefault();
    dispose();
    onError(new Error('The robot renderer lost its graphics context.'));
  }
  renderer.domElement.addEventListener('webglcontextlost', contextLost);
  resize();
  sync();

  return {
    robot,
    setState(value) { robot.setState(value); sync(); },
    setSpeechEnergy(value) { robot.setSpeechEnergy(value); if (media.matches) render(); },
    wave() { robot.wave(); sync(); },
    dispose,
  };
}

