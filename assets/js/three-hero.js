/**
 * VoltCraft 3D - Interactive Three.js Cybernetic Electronic Core & Circuit Hero
 */
(function() {
  'use strict';

  const container = document.getElementById('hero-3d-canvas-container');
  if (!container || typeof THREE === 'undefined') return;

  // Scene, Camera, Renderer
  let scene, camera, renderer;
  let chipGroup, ring1, ring2, ring3, particleSystem;
  let mouseX = 0, mouseY = 0;
  let targetX = 0, targetY = 0;
  let isDragging = false, prevMouseX = 0, prevMouseY = 0;
  let windowHalfX = window.innerWidth / 2;
  let windowHalfY = window.innerHeight / 2;

  function init() {
    // 1. Scene setup
    scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0x060913, 0.035);

    // 2. Camera setup
    camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 1000);
    camera.position.set(0, 3, 11);
    camera.lookAt(0, 0, 0);

    // 3. Renderer setup
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.2;
    container.appendChild(renderer.domElement);

    // 4. Lights
    const ambientLight = new THREE.AmbientLight(0x0f172a, 2.0);
    scene.add(ambientLight);

    const cyanLight = new THREE.PointLight(0x00f0ff, 4, 30);
    cyanLight.position.set(5, 5, 5);
    scene.add(cyanLight);

    const purpleLight = new THREE.PointLight(0x8b5cf6, 4, 30);
    purpleLight.position.set(-5, -3, -3);
    scene.add(purpleLight);

    const coreLight = new THREE.PointLight(0x00f0ff, 3, 10);
    coreLight.position.set(0, 0, 0);
    scene.add(coreLight);

    // 5. Construct 3D Cyber Electronic Core
    chipGroup = new THREE.Group();

    // A. Main PCB Substrate Base (Octagonal / Box)
    const baseGeo = new THREE.BoxGeometry(4.2, 0.25, 4.2);
    const baseMat = new THREE.MeshStandardMaterial({
      color: 0x0a1128,
      metalness: 0.85,
      roughness: 0.25,
      wireframe: false
    });
    const pcbBase = new THREE.Mesh(baseGeo, baseMat);
    chipGroup.add(pcbBase);

    // B. Circuit Traces & Golden Grid on PCB
    const gridGeo = new THREE.PlaneGeometry(4.0, 4.0, 8, 8);
    const gridMat = new THREE.MeshBasicMaterial({
      color: 0x00f0ff,
      wireframe: true,
      transparent: true,
      opacity: 0.4
    });
    const circuitGrid = new THREE.Mesh(gridGeo, gridMat);
    circuitGrid.rotation.x = -Math.PI / 2;
    circuitGrid.position.y = 0.13;
    chipGroup.add(circuitGrid);

    // C. Central Processor Chip (Silicon Die)
    const chipGeo = new THREE.BoxGeometry(2.0, 0.45, 2.0);
    const chipMat = new THREE.MeshStandardMaterial({
      color: 0x111827,
      metalness: 0.9,
      roughness: 0.1
    });
    const cpuChip = new THREE.Mesh(chipGeo, chipMat);
    cpuChip.position.y = 0.3;
    chipGroup.add(cpuChip);

    // Central Hologram Core Heat Spreader
    const capGeo = new THREE.CylinderGeometry(0.7, 0.7, 0.15, 32);
    const capMat = new THREE.MeshStandardMaterial({
      color: 0x00f0ff,
      emissive: 0x0088aa,
      emissiveIntensity: 0.6,
      metalness: 0.9,
      roughness: 0.2
    });
    const coreCap = new THREE.Mesh(capGeo, capMat);
    coreCap.position.y = 0.55;
    chipGroup.add(coreCap);

    // D. Outer Floating Rings (Accelerator Gimbals)
    const ringMat1 = new THREE.MeshStandardMaterial({
      color: 0x00f0ff,
      emissive: 0x00f0ff,
      emissiveIntensity: 0.5,
      metalness: 0.7,
      roughness: 0.2,
      wireframe: true
    });
    const ringGeo1 = new THREE.TorusGeometry(3.2, 0.04, 16, 64);
    ring1 = new THREE.Mesh(ringGeo1, ringMat1);
    chipGroup.add(ring1);

    const ringMat2 = new THREE.MeshStandardMaterial({
      color: 0x8b5cf6,
      emissive: 0x8b5cf6,
      emissiveIntensity: 0.4,
      metalness: 0.8,
      roughness: 0.2,
      wireframe: true
    });
    const ringGeo2 = new THREE.TorusGeometry(3.6, 0.03, 16, 64);
    ring2 = new THREE.Mesh(ringGeo2, ringMat2);
    ring2.rotation.x = Math.PI / 3;
    chipGroup.add(ring2);

    const ringMat3 = new THREE.MeshBasicMaterial({
      color: 0x38bdf8,
      transparent: true,
      opacity: 0.35,
      wireframe: true
    });
    const ringGeo3 = new THREE.TorusGeometry(4.0, 0.02, 16, 64);
    ring3 = new THREE.Mesh(ringGeo3, ringMat3);
    ring3.rotation.y = Math.PI / 4;
    chipGroup.add(ring3);

    // E. Satellite Components (Floating SMD Capacitors & Chips)
    const satCount = 8;
    for (let i = 0; i < satCount; i++) {
      const angle = (i / satCount) * Math.PI * 2;
      const radius = 2.6;
      const smdGeo = new THREE.BoxGeometry(0.35, 0.2, 0.35);
      const smdMat = new THREE.MeshStandardMaterial({
        color: i % 2 === 0 ? 0xf59e0b : 0x10b981,
        emissive: i % 2 === 0 ? 0xf59e0b : 0x10b981,
        emissiveIntensity: 0.4
      });
      const smd = new THREE.Mesh(smdGeo, smdMat);
      smd.position.set(Math.cos(angle) * radius, 0.2, Math.sin(angle) * radius);
      chipGroup.add(smd);
    }

    chipGroup.rotation.x = 0.55;
    chipGroup.rotation.y = -0.4;
    scene.add(chipGroup);

    // 6. Floating Particle Network (Ambient Electronics Plasma Cloud)
    const particleCount = 280;
    const particleGeo = new THREE.BufferGeometry();
    const positions = new Float32Array(particleCount * 3);
    const colors = new Float32Array(particleCount * 3);

    const color1 = new THREE.Color(0x00f0ff);
    const color2 = new THREE.Color(0x8b5cf6);

    for (let i = 0; i < particleCount * 3; i += 3) {
      positions[i] = (Math.random() - 0.5) * 16;
      positions[i + 1] = (Math.random() - 0.5) * 12;
      positions[i + 2] = (Math.random() - 0.5) * 14;

      const mixed = Math.random() > 0.5 ? color1 : color2;
      colors[i] = mixed.r;
      colors[i + 1] = mixed.g;
      colors[i + 2] = mixed.b;
    }

    particleGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    particleGeo.setAttribute('color', new THREE.BufferAttribute(colors, 3));

    const particleMat = new THREE.PointsMaterial({
      size: 0.08,
      vertexColors: true,
      transparent: true,
      opacity: 0.85
    });

    particleSystem = new THREE.Points(particleGeo, particleMat);
    scene.add(particleSystem);

    // 7. Event Listeners
    window.addEventListener('resize', onWindowResize, false);
    window.addEventListener('mousemove', onMouseMove, false);

    // Drag rotation controls on container
    container.addEventListener('mousedown', (e) => {
      isDragging = true;
      prevMouseX = e.clientX;
      prevMouseY = e.clientY;
    });

    window.addEventListener('mouseup', () => { isDragging = false; });
    
    window.addEventListener('mousemove', (e) => {
      if (!isDragging || !chipGroup) return;
      const deltaX = e.clientX - prevMouseX;
      const deltaY = e.clientY - prevMouseY;
      chipGroup.rotation.y += deltaX * 0.008;
      chipGroup.rotation.x += deltaY * 0.008;
      prevMouseX = e.clientX;
      prevMouseY = e.clientY;
    });

    // Animate
    animate();
  }

  function onMouseMove(event) {
    mouseX = (event.clientX - windowHalfX) * 0.0006;
    mouseY = (event.clientY - windowHalfY) * 0.0006;
  }

  function onWindowResize() {
    if (!container || !camera || !renderer) return;
    windowHalfX = window.innerWidth / 2;
    windowHalfY = window.innerHeight / 2;
    camera.aspect = container.clientWidth / container.clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(container.clientWidth, container.clientHeight);
  }

  let clock = new THREE.Clock();

  function animate() {
    requestAnimationFrame(animate);

    const delta = clock.getDelta();
    const time = clock.getElapsedTime();

    // Subtle automatic rotation if not actively dragging
    if (!isDragging && chipGroup) {
      chipGroup.rotation.y += 0.005;
      chipGroup.position.y = Math.sin(time * 1.5) * 0.15;
    }

    // Spin individual gimbals/rings at different rates
    if (ring1) ring1.rotation.z += 0.012;
    if (ring2) ring2.rotation.x += 0.008;
    if (ring3) ring3.rotation.y += 0.015;

    // Rotate ambient particle cloud
    if (particleSystem) {
      particleSystem.rotation.y = time * 0.02;
    }

    // Smooth Camera Parallax easing
    targetX += (mouseX - targetX) * 0.04;
    targetY += (mouseY - targetY) * 0.04;
    camera.position.x = targetX * 6;
    camera.position.y = 3 - targetY * 4;
    camera.lookAt(0, 0, 0);

    renderer.render(scene, camera);
  }

  // Initialize once DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
