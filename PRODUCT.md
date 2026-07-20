# Product

## Register

product

## Users

One user: a Swiss upper-secondary student and competitive orienteering athlete managing his own
work. He uses the app every day, often quickly between school, training, and homework — on his
phone on the go, on desktop at his desk. The job: capture incoming items fast, triage them into
the "3 Things" framework (To-Do / Task / Project), pick today's focus, and see at a glance what
is urgent. Secondary surfaces: a daily schedule (Zeitplan), and per-project pages with task lists
and a Markdown brainstorm space.

## Product Purpose

A personal productivity system built around sorting work by **size and shape** (To-Do / Task /
Project) rather than by category. Items land in an Inbox and get triaged. Success means the app
is trusted enough to be the single daily surface for "what's left to do": fast capture, minimal
clicks, a clear overview, zero feature bloat. It must feel reliable and quiet enough to open
every single day without friction.

## Brand Personality

**Calm, fast, precise.** The visual language is "Topografie" — an orienteering map: map-paper
background, contour/forest/overprint/signal accent colors, thin hairlines, tabular figures for
times and counts. Day and night themes flip like a map in daylight vs. at night. The interface
should feel like a well-drawn map: dense with meaning, never decorative, instantly legible.

## Anti-references

- Team project-management tools (Jira/Asana/Monday): no boards-of-boards, no assignees, no
  statuses, no feature bloat.
- Gamified to-do apps: no streaks, badges, confetti, or motivational copy.
- Blocking browser dialogs (`confirm()`) — banned; destructive actions use the armed
  double-click pattern.
- Anything loud: heavy shadows, gradients, decorative motion, oversized hero typography.

## Design Principles

1. **Speed over ceremony** — capture and triage in the fewest possible interactions; every
   added click must earn its place.
2. **The map metaphor carries meaning** — color is semantic (forest = go/today, signal =
   urgent/destructive, overprint = highlight/selection, contour = dates), never decoration.
3. **Quiet reliability** — the app is used daily; consistency and predictability beat novelty.
   Same affordances everywhere (armed delete, edit sheet, quick-add).
4. **Server is the source of truth** — UI state is ephemeral, data mutations are authorized and
   validated server-side; the frontend is never trusted.
5. **Density with legibility** — one screen shows the whole picture (3 columns + projects on
   desktop, paged tabs on mobile); nothing hidden behind unnecessary navigation.

## Accessibility & Inclusion

- Keyboard reachable interactions; visible focus states.
- Sufficient contrast in both day and night themes (WCAG AA for body text).
- Respects `prefers-reduced-motion` (already implemented globally in `app.css`).
- Touch targets sized for one-handed mobile use; swipe gestures always have a tap alternative.
- German UI copy (the user's language); dates in Swiss format (d.m.).
