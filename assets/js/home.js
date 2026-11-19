(() => {
  'use strict';

  const root = document.documentElement;
  const revealItems = [...document.querySelectorAll('[data-home-reveal]')];
  const counters = [...document.querySelectorAll('[data-count-up]')];
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (!reduceMotion && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    document.querySelectorAll('[data-home-parallax]').forEach((surface) => {
      let frame = 0;
      surface.addEventListener('pointermove', (event) => {
        if (frame) window.cancelAnimationFrame(frame);
        frame = window.requestAnimationFrame(() => {
          const bounds = surface.getBoundingClientRect();
          const x = ((event.clientX - bounds.left) / bounds.width - 0.5) * 6;
          const y = ((event.clientY - bounds.top) / bounds.height - 0.5) * 6;
          surface.style.setProperty('--platform-shift-x', `${x.toFixed(1)}px`);
          surface.style.setProperty('--platform-shift-y', `${y.toFixed(1)}px`);
          frame = 0;
        });
      }, { passive: true });
      surface.addEventListener('pointerleave', () => {
        if (frame) window.cancelAnimationFrame(frame);
        frame = 0;
        surface.style.setProperty('--platform-shift-x', '0px');
        surface.style.setProperty('--platform-shift-y', '0px');
      }, { passive: true });
    });
  }

  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
    return;
  }

  root.classList.add('home-motion-ready');
  const counterFrames = new WeakMap();
  const animateCounter = (element) => {
    const target = Number.parseInt(element.dataset.countUp || '', 10);
    if (!Number.isFinite(target) || target < 0) return;
    const duration = 680;
    const start = performance.now();
    const frame = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - ((1 - progress) ** 3);
      element.textContent = String(Math.round(target * eased));
      if (progress < 1) counterFrames.set(element, window.requestAnimationFrame(frame));
    };
    counterFrames.set(element, window.requestAnimationFrame(frame));
  };

  const observer = new IntersectionObserver((entries, activeObserver) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      if (entry.target.hasAttribute('data-count-up')) animateCounter(entry.target);
      activeObserver.unobserve(entry.target);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -28px 0px' });

  revealItems.forEach((item) => observer.observe(item));
  counters.forEach((item) => observer.observe(item));
})();
