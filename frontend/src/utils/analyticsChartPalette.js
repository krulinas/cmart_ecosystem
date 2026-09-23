/**
 * Semantic Chart.js palette for Organizer Analytics (Phase 1).
 * Use colours by meaning — do not assign random rainbow slices.
 */

export const ANALYTICS_PALETTE = Object.freeze({
  primary: '#3970E4',
  positive: '#2E9D78',
  finance: '#2D439C',
  warning: '#E86F88',
  survey: '#925FD1',
  highlight: '#D5A800',
  neutral: '#E8EEF6',
  ink: '#1E293B',
  muted: '#64748B',
  track: '#E8EEF6',
  grid: '#E8EEF6',
});

/** Ordered categorical accents (mutually exclusive compositions). */
export const COMPOSITION_COLORS = Object.freeze([
  ANALYTICS_PALETTE.primary,
  ANALYTICS_PALETTE.positive,
  ANALYTICS_PALETTE.survey,
  ANALYTICS_PALETTE.highlight,
  ANALYTICS_PALETTE.finance,
  ANALYTICS_PALETTE.warning,
]);

export const withAlpha = (hex, alpha = 0.55) => {
  const raw = String(hex || '').replace('#', '');
  if (raw.length !== 6) return hex;
  const r = parseInt(raw.slice(0, 2), 16);
  const g = parseInt(raw.slice(2, 4), 16);
  const b = parseInt(raw.slice(4, 6), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

export const prefersReducedMotion = () => {
  if (typeof window === 'undefined' || !window.matchMedia) return false;
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
};

export const chartAnimationOption = (pointCount = 0) => {
  if (prefersReducedMotion() || pointCount > 40) return false;
  return { duration: 450 };
};
