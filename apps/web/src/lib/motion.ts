/**
 * Motion tokens (CLAUDE.md §6.23). One signature easing, a fixed duration
 * scale, and a standard stagger — never inline ad-hoc values in components.
 */
export const durations = {
  fast: 0.15,
  base: 0.25,
  slow: 0.4,
  slower: 0.7,
} as const;

/** The single signature easing curve for the whole product. */
export const ease = [0.22, 1, 0.36, 1] as const;

export const stagger = 0.07;

/** Scroll-reveal: fade + 18px rise (spec §2.4), plays once. Transform + opacity only. */
export const revealVariants = {
  hidden: { opacity: 0, y: 18 },
  show: {
    opacity: 1,
    y: 0,
    transition: { duration: durations.slow, ease },
  },
} as const;

export const staggerContainer = {
  hidden: {},
  show: {
    transition: { staggerChildren: stagger },
  },
} as const;
