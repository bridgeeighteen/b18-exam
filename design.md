# Design — b18-exam（十八桥社区论坛入站测试系统）

A locked design system for this app. Every page redesign reads this file before
emitting code. Do not regenerate per page — extend or amend this file when the
system needs to grow.

## Genre

modern-minimal

## Macrostructure family

- Marketing pages (index.php): **Split Studio** — left-aligned two-column hero
  (headline + lede left, CTA right), then two quiet support cards on a hairline
  grid. Enrichment: none (typography only; the community logo mark carries the
  identity).
- App pages (info.php / exam.php / result.php): **form-led constrained column**
  — a centered single column (42rem forms, 54rem exam), one card surface, one
  CTA voice, hairline dividers. Variation knobs per page: hero-label, sticky
  timer pill (exam), code box (result).
- Admin pages: same app-page family, narrower column (30rem login card).

## Theme

Custom — anchored on the brand crimson pair.

- `--color-accent`   oklch(42% 0.185 16)  /* primary #a70034 */
- `--color-accent-2` oklch(63% 0.16 7)    /* secondary #ed556a */
- `--color-accent-ink` oklch(98% 0.008 20)
- `--color-focus`    oklch(45% 0.20 16)
- `--color-paper`    oklch(96% 0.008 20)
- `--color-paper-2`  oklch(93% 0.010 20)
- `--color-ink`      oklch(21% 0.012 20)
- `--color-ink-2`    oklch(40% 0.012 18)
- `--color-muted`    oklch(45% 0.012 18)
- `--color-rule`     oklch(86% 0.010 20)
- `--color-rule-2`   oklch(90% 0.010 20)

Accent footprint ≤ 5 % per viewport: primary CTA fills, links, focus rings,
timer pill, one code box. Everything else is ink-on-paper.

## Typography

- Display: Noto Sans SC, weight 700–900, roman, tracking -0.02em
- Body:    Noto Sans SC, weight 400–500, 1rem base, measure 45–75ch
- Mono:    none
- Hosting: local woff2 in `views/assets/fonts/`, `@font-face` in
  `views/assets/css/tokens.css`, `font-display: swap`
- Type scale anchor: `--text-display = clamp(2rem, 4vw + 0.5rem, 3.25rem)`

## Spacing

4-point named scale in `tokens.css` (`--space-*`). Pages use named tokens, never
raw values. Section rhythm: `--space-3xl` between major sections, `--space-md`
between fields.

## Motion

- Easings: `--ease-out: cubic-bezier(0.16, 1, 0.3, 1)`
- Durations: `--dur-short: 200ms`, `--dur-med: 320ms`
- Reveal pattern: none — pages are composed, not animated (modern-minimal)
- Reduced-motion: default

## Microinteractions stance

- Focus is a first-class state: `:focus-visible` shows `--color-focus` instantly
- Hover: color/shadow transition ≤ 200 ms, `--ease-out`
- No celebratory toasts; silent success

## CTA voice

- Primary: `--color-accent` fill, white text, pill radius (0.75rem), weight 500,
  padding 0.625rem 1.5rem, hover darkens toward `--color-ink` direction
- Secondary: 1.5px `--color-rule` border, ink text, same pill radius
- Disabled: paper-2 fill, muted text, no pointer events

## Per-page allowances

- Marketing pages MAY use enrichment (this project: none)
- App pages MUST NOT use enrichment
- Admin pages: typography only

## What pages MUST share

- The wordmark / logotype (`views/assets/logo_text.svg`)
- The accent pair (#a70034 primary · #ed556a secondary) and its placement
- Noto Sans SC everywhere
- The CTA voice (pill radius, padding rhythm, hover)
- The navbar hairline + footer Ft2 inline single line

## What pages MAY differ on

- Hero archetype (index uses Split Studio; app pages use the form-led column)
- Card surface treatment (app pages may use paper-2 surfaces)

## Nav + footer archetypes

- Nav: N1 adapted — wordmark left, three links right, white paper, hairline
  bottom rule, crimson active/link accents. Bootstrap navbar collapse preserved.
- Footer: Ft2 inline single line — version · license · copyright on one hairline
  rule. Text preserved per page.
