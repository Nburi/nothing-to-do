# UX Research Findings — nothing-to-do

**Method:** Four fictional personas were driven through the live app in a real browser (fresh
registrations, real data, real interactions — not automated test assertions) on 2026-09-02, covering
onboarding, all four list concepts, Agenda + class sharing, Zeitplan/Wochenplan + Pomodoro,
Task-Gruppen, Projects, Notfallmodus, module visibility, and the admin tools (Feature-Announcements,
Hilfe-Center, Support, API tokens). No code was changed as part of this research — this document and
its appendix are the only output.

**Personas:** Lena (17, stressed student-athlete, Eisenhower), Priya (16, wants the app for Agenda
only), Marco (20, first-year uni student living alone, Kanban), David (24, freelance power user +
granted admin for this pass, 3 Things). Full rationale for each choice is in the appendix.

Full raw, chronological session notes — with the reasoning behind every item below — are in
**[Appendix: Per-Persona Session Notes](#appendix-per-persona-session-notes)**.

---

## Priority to-do list

### 🐛 Bugs

**1. [HIGH] A Project captured while the Kanban list concept is active is completely unreachable —
no column, no link, no indicator, anywhere.**
- **Where:** QuickCapture's "Projekt" target, while `list_concept = kanban`.
- **Repro:** Set list concept to Kanban. Open QuickCapture (`N`), pick "Projekt", type a title, save.
  Nothing changes on the Kanban board — no Projekte column, no card, no trace at all.
- **Impact:** Confirmed with real data (persona: Marco) — a project genuinely exists in the database
  the whole time (verified by switching the account to "3 Things" in Settings, where both attempts
  appeared correctly), but a Kanban-only user has **no UI path back to it whatsoever**. The complete
  absence of feedback is bad enough on its own that even an attentive tester (actively looking for
  this exact class of bug) concluded the capture had silently failed and **created a duplicate
  project** trying to fix it.
- **Likely also affects Simple and Eisenhower** — neither is documented as having a Projekte surface
  either. Not independently re-verified this pass; worth a quick check.
- **Suggested fix direction:** either give every concept some minimal way to reach existing projects
  (even just a "Projekte" link in the header nav when any exist), or have QuickCapture's own
  confirmation echo make it explicit when a capture landed somewhere the current view can't show
  ("→ Projekt (nicht sichtbar in Kanban)"), or restrict the "Projekt" QuickCapture target to concepts
  that can actually display one.

**2. [LOW] Onboarding's own Kanban explainer names the wrong first column.**
- **Where:** `resources/views/livewire/onboarding.blade.php:231`
- The tutorial slide reads *"Jede Karte hat einen Status — **Offen**, In Arbeit oder Erledigt."* The
  real Kanban board's three columns are **"Backlog" / "In Arbeit" / "Erledigt"** — "Offen" appears
  nowhere in the actual product. Confirmed at the source line, not just on screen.
- A brand-new user is taught the wrong name for the one column that matters most (where every new
  card lands), in the one screen whose entire purpose is teaching them the concept correctly.
- **Suggested fix:** change `Offen` to `Backlog` on that line.

**3. [LOW-MEDIUM, systemic] No German localization for Laravel's own validation messages — English
text can appear inside an otherwise fully German UI wherever a built-in validation rule fires.**
- **Where confirmed:** the Wochenplan "+ Block" Kategorie-required check
  (`week-plan-event-form.blade.php:80`, message: *"The event category id field is required."*) and
  the Settings API token name field (`settings.blade.php:1073`, message: *"The new token name field
  is required."*).
- **Root cause, verified by reading source (not guessed):** in both cases the app's own inline
  `@error()` feedback block is present, correctly styled, and correctly positioned — this is **not**
  a raw exception or stack trace leaking past the UI (CLAUDE.md's own rule 3 concern), it's the
  designed feedback mechanism working exactly as built, just displaying Laravel's stock English
  string because the message itself was never localized. `grep`-confirmed: **there is no `lang/`
  directory in this project at all** — every line of *custom* UI copy is hand-written German, but
  Laravel's *built-in* validation vocabulary (`required`, `date_format`, `exists`, …) has never been
  overridden.
- **Impact:** any required-but-empty field, anywhere in the app, is one skipped click away from
  showing an English sentence to a German-speaking user. Two independent instances were hit in this
  session alone (different pages, different Livewire components, different personas) purely by normal
  use — this isn't a contrived edge case.
- **Suggested fix:** add a `lang/de/validation.php` translation file (the standard Laravel mechanism)
  rather than patching each `@error()` call site individually — it's a one-time, systemic fix. Worth
  also grepping the codebase for every other `@error()` block to confirm none of them rely on a rule
  that would produce the same English leak.

### ⚠️ Friction & discoverability issues

**4. [MEDIUM] A category's colour swatch, shown on its picker chip, can be mistaken for a "this one
is already selected" indicator — which directly contributed to bug #3 above.**
- The colour dot on a category chip (Wochenplan/Zeitplan "+ Block"/"+ Termin" forms) is *always*
  shown in that category's own assigned colour, whether or not the chip is actually selected. The
  chip's real selected state is a neutral border/background change, not a colour fill. With exactly
  one category configured (the most common state right after creating your first one), this reads
  very plausibly as "it's the only option, so it must already be picked" — which is exactly the
  mistake made in this research pass, by a tester actively trying to click things correctly.
- **Suggested fix:** a real selected-state affordance that's unambiguous regardless of the category's
  own colour (a checkmark, a distinct fill, a border that isn't easily confused with "this chip
  happens to be green").

**5. [MEDIUM] The streak/"Serie" mechanic is easy to never engage with at all under the Eisenhower
concept, with no explanation when it silently stays at zero.**
- The streak specifically counts days where every task flagged **"Heute"** got completed — but in
  Eisenhower, "Heute" has no dedicated zone or column (unlike 3 Things/Kanban, where it's a whole
  visible area you naturally drag into); it's one small toggle pill per card that's easy to never
  notice.
- Confirmed live: completed a real task, `/app/progress` correctly showed "1/5 Aufgaben heute
  erledigt" for the volume counter, but **"0 Tage Serie"** — with no copy anywhere on that page
  explaining that the streak specifically depends on the separate "Heute" flag, not on completions in
  general. A diligent user could reasonably feel the streak is broken or arbitrary.
- **Suggested fix:** either a one-line explainer on the Fortschritt page when the day has real
  completions but the streak/today-flag basis is zero ("Serie zählt nur Tage mit erledigten
  „Heute"-Aufgaben — noch keine markiert"), or make the "Heute" toggle more visually prominent in
  Eisenhower specifically.

**6. [LOW] The onboarding quiz's concept tie-break rule is invisible to the user and can pick a
less-obviously-fitting concept.**
- Two answers that vote for *different* concepts and are equally weighted resolve via the fixed
  declaration order of `OnboardingQuiz::ANSWERS` in source, not by anything visible in the UI. In this
  research pass, selecting "Ich will sehen, was gerade in Arbeit ist und was schon fertig" (Kanban)
  together with "Ich habe kleine Erledigungen und grosse, mehrteilige Vorhaben gleichzeitig" (3
  Things) — a very plausible combination for a mixed-motivation user — silently resolved to **3
  Things**, even though the first answer reads as the more clearly Kanban-flavoured of the two.
- Not a bug (the code does exactly what its own comment says), but a real, reachable case, not a
  contrived one — a two-answer tie is one normal click away for anyone whose reasons for wanting a
  to-do app aren't singular (most people's aren't).
- **Suggested fix:** no strong recommendation — flagging for awareness. Possible directions: break
  ties by something the user can perceive (e.g. last-clicked answer), or show which concept a
  selection currently resolves to *before* advancing past the quiz step, so a tie is at least visible
  and correctable in the moment.

**7. [LOW] QuickCapture's title input doesn't reliably keep focus for rapid successive captures when
the previous save was triggered by clicking "Erfassen" (vs. pressing Enter).**
- The panel is designed to stay open for back-to-back capture ("bleibt offen für den nächsten"), and
  that works well with Enter. With a mouse click on the button instead, the title field sometimes
  needs an extra click before typing continues — minor, but works against the feature's own "rapid
  capture" intent for a chunk of real users (anyone whose habit is "click, then type").

**8. [LOW] A Task-Gruppe's / project's inline "Hinzufügen …" quick-add field doesn't submit on Enter,
only via its own "+" button.**
- Inconsistent with the near-universal expectation that a single-line "add item" field submits on
  Enter. Low severity (the "+" is right there), but easy to trip over, especially for anyone used to
  the keyboard-first rhythm QuickCapture itself encourages elsewhere.

### 💡 Nice-to-haves

**9. Onboarding's closing "Bereit." summary leads with the list-concept name even for a user who may
never see the board at all.**
- Persona Priya's onboarding correctly hid every module except Agenda and set Agenda as her landing
  page — genuinely excellent, tightly-personalized outcome — but the final screen still reads "Du
  nutzt jetzt **Simple** mit 1 zusätzlichen Bereich", which reads a little oddly for someone about to
  land directly on Agenda and may rarely open the board. A one-line addition naming her actual landing
  destination would close the loop better than leaving her to infer it.

**10. Verify whether Simple and Eisenhower share Kanban's "Projects become unreachable" gap
(finding #1).**
- Not confirmed in this pass (time-boxed to Kanban, where it was found) — worth a quick, cheap check
  before scoping the fix, since the fix shape may want to cover all three non-3-Things concepts at
  once rather than being Kanban-specific.

---

## What worked well (for balance / don't accidentally regress these)

- **Onboarding personalization is the standout feature of this whole pass.** The quiz-driven
  pre-selection of list concept *and* which modules show in navigation is not just cosmetic — verified
  with two very different personas (Lena → Eisenhower + Zeitplan/Vorbereiten/Wochenplan on;
  Priya → Simple fallback + everything off except Agenda) producing genuinely different, well-fitted
  starting experiences with zero manual cleanup required afterward.
- **Cross-account features work as real, live, multi-user systems, not just in isolation.** The class
  Agenda (invite code → shared entries → correct privacy boundary on private entries → live
  per-member progress) and Feature-Announcements (admin publishes → a different, already-existing
  account sees the toast → deep-link navigates correctly) were both verified end-to-end across two
  separate real accounts, not read off the code or assumed from a single session.
- **Pomodoro, Notfallmodus, Task-Gruppen, and the Hilfe-Center/Support feedback loop all worked
  correctly on the first genuine attempt**, with no bugs found in any of them across two personas'
  worth of real use. Notfallmodus in particular — banner, numbered pinned tasks, live progress,
  project-card badge, header indicator — matched its documentation exactly.
- Empty-state and quadrant/column copy throughout the app is consistently well-written and
  context-specific (Eisenhower's per-quadrant tone, Kanban's per-column tone, Agenda's empty state),
  not generic placeholder text.

---

## Research limitations (tooling, not the app)

- The primary browser tool (Claude Browser pane) had a documented history of hanging in this project;
  in this session navigation, clicking, typing, and DOM reading stayed reliable throughout, but
  `computer.screenshot` specifically became unreliable partway through (blank frames / timeouts) and
  never fully stabilized. Functional/behavioral testing was not blocked by this, but pixel-level visual
  QA (exact spacing, precise animation timing, colour contrast) was verified less thoroughly than
  functional correctness — most visual claims below are backed by a screenshot that did render, but not
  every one was double-checked.
- The Claude-in-Chrome fallback did not respond within the 2-attempt budget (likely needs a permission
  grant only the user can complete) and was not used this session.
- Element-reference-based clicks occasionally resolved to stale/incorrect coordinates partway through
  the session (two different refs briefly resolving to the same pixel); this was caught by verifying
  Livewire/Alpine state via JS after each click and is a tooling artifact, not an app defect — flagged
  wherever it could plausibly be confused for one.
- **Not tested this pass** (reasonable follow-ups, not attempted due to time-boxing across four full
  personas rather than any blocker): Wochenplan's Ferien/pause flow, the Planer auto-fill feature, real
  push notification delivery (blocked by the browser's own permission state in this environment),
  mobile/touch viewport and gesture behavior, Header-Badges customization, and timezone/DST settings.

---

## Appendix: Per-Persona Session Notes

Raw, chronological notes as captured during the actual sessions, preserved so the reasoning behind
every item above is traceable back to what was actually observed. Persona order below is the order
they were run in.

### Persona 1: Lena, 17 — Gymnasium student, competitive swimmer

**Backstory:** Juggling homework across 6+ subjects, swim training 5x/week, meets/exams on
short notice. Stressed, easily overwhelmed, wants to know "what's actually urgent right now"
and to see her whole day (school + training + study blocks) in one place. Chosen to represent the
app's original target persona and to exercise Eisenhower + Zeitplan + Pomodoro + Agenda together.

- Registered fresh account (lena.meier.uxtest@example.com). Auto-redirected straight into the
  onboarding quiz — no confirmation email step, no dead end. Good: zero friction to get in.
- Onboarding quiz (Schritt 2/13): picked "Ich verliere den Überblick, was wichtig UND dringend
  ist", "Ich will meinen ganzen Tag durchplanen — Termine, Fokuszeit, alles", "Ich will
  dranbleiben und meinen Fortschritt sehen" — a genuinely plausible combo for this persona.
- Step counter changed from "13" to "11" the instant I advanced past the quiz (modules the
  quiz didn't touch got hidden). Momentary confusion: a `get_page_text` read mid-transition
  showed the OLD quiz content still under the NEW step number — but a screenshot right after showed
  the correct new content. Very likely a read-timing artifact of my tooling (Alpine step-swap racing
  a text read), not a real rendering bug — flagging for awareness, not as a confirmed bug.
- Konzept step correctly pre-selected the **Eisenhower-Matrix** tab based on the quiz answer.
  Nice, legible personalization touch — genuinely felt smart, not gimmicky.
- Preview thumbnails for all 4 concepts showed "—" placeholders (no tasks exist yet) — correct
  empty state, not broken/blank.
- Finished onboarding → landed on the Eisenhower board directly (no board flash of the wrong
  concept). Quadrant empty-state copy is genuinely tailored per quadrant ("Nichts brennt gerade."
  / "Der ruhigste Ort im Haus — bleib hier, wenn du kannst.") — nice detail, not lazy placeholder text.
- Added 4 real tasks via QuickCapture (`N` key): "Mathe Hausaufgaben: Übungsblatt 4" (deadline in
  3 days), "Bio-Prüfung: ganzes Kapitel wiederholen" (deadline in 13 days), "Wäsche waschen" (no
  date), "Schwimmtasche für morgen packen" (no date). All landed in the correct quadrant purely from
  deadline proximity + importance, confirmed against `Task::isUrgent()`'s window. Marking Bio-Prüfung
  "wichtig" via a single tap on the title correctly moved it live from Nicht-wichtig/Nicht-dringend to
  Wichtig/Nicht-dringend, no page reload, no drag needed. This "just works" and feels good — a student
  triaging homework by tapping titles rather than dragging matches how she'd actually use it one-handed
  on a laptop trackpad between subjects.
- **Friction (→ list item #7):** after QuickCapture auto-clears the title on save, the input does not
  reliably keep focus if you *click* the Erfassen button (as opposed to pressing Enter) — had to click
  back into the title field before typing the next task.
- **Bug (→ list items #3, #4):** Wochenplan "+ Block" modal, category-required validation surfaced
  the raw English Laravel message; see the priority list above for the full, corrected write-up
  (an earlier version of this note wrongly assumed the chip itself was mis-bound — corrected after
  reading the actual Blade source).
- Investigated something that looked like a bug (every task card seemed to show a static "Heute"
  label even on freshly created, not-flagged tasks) — turned out to be the always-visible "Für heute
  markieren" toggle button, which is *labelled* "Heute" whether or not the task is currently flagged.
  Not a bug, but worth a UX note: a toggle button that reads the same regardless of its own state
  (only a subtle border-colour change signals "already on") is easy to misread as a status badge —
  exactly what happened here on first glance.
- Zeitplan looked genuinely good: red "now" line, deadline-strip chips ("Mathe Hausaufgaben... in 2T")
  on the correct days, Training blocks rendering with a small clock icon. Created a one-off "Training"
  block for right-now (13:00–13:25) via Zeitplan's own "+ Termin" full form (this time clicking the
  category chip myself instead of trusting its pre-selected look) — worked first try.
- **Pomodoro flow works well end to end.** Dashboard focus card correctly showed "BEREIT · 13:00–13:25
  · Training" with a Start button once the block's time arrived; clicking Start flipped it to a live
  "FOKUS läuft" countdown ring (24:56 → 24:46 confirmed ticking in real time), with a stop button. This
  is a genuinely satisfying, "just works" moment — exactly the kind of payoff a stressed athlete-student
  persona would want between school and the pool.
- Agenda: added a Französisch homework entry due Friday via "+ Eintrag". Simple, fast form. It
  immediately appeared in the dashboard's "Bald fällige Hausaufgaben" strip — nice cross-feature
  cohesion, reinforces that Agenda isn't a dead-end side feature.
- Marked "Wäsche waschen" done → it correctly dropped into a collapsed "ERLEDIGT" section at the
  bottom of the Eisenhower board (a quadrant-less concept still needed *some* place for finished work,
  and this reads well).
- **Friction (→ list item #5):** the streak mechanic is easy to accidentally never engage with in
  Eisenhower mode. See the priority list above for the full write-up.
- **Wrap-up impression:** onboarding personalization, Eisenhower quadrant logic, Pomodoro, and
  Agenda all genuinely delivered on what a stressed student-athlete needs. The one real bug (Wochenplan
  category chip / validation message) and the one real friction point (streak/Heute-flag disconnect in
  Eisenhower) are both concrete, fixable items rather than vague "could be nicer" feedback.

### Persona 2: Priya, 16 — Lena's classmate, wants the app for Agenda only

**Backstory:** Doesn't want another productivity system to manage — just wants to see what homework
the class has, in one shared place, without extra friction. Would rather the app get out of her way
entirely for everything else. Chosen specifically to stress-test module visibility, the landing-page
setting, and the class-Agenda join flow as real, separate-account behavior rather than single-user code.

- Registered (priya.sharma.uxtest@example.com), onboarding quiz: selected **only** "Ich brauche eine
  geteilte Liste für Hausaufgaben mit meiner Klasse" — nothing else. Result: concept defaulted to
  **Simple** (this answer casts no concept vote, confirms `OnboardingQuiz`'s documented "no vote →
  simple" fallback), and — the important part — the Feature-Galerie step pre-toggled **Vorbereiten,
  Zeitplan, and Wochenplan & Ferien all OFF**, with only **Agenda ON**. This is exactly right for this
  persona and a genuinely well-tuned piece of personalization: a plausible real user with this one
  answer gets a nearly-decluttered app on the very first screen, no manual cleanup needed.
- Onboarding was noticeably shorter as a direct consequence (7 steps vs. Lena's 12) — nice, concrete
  proof that "the tutorial's length depends on the answer" isn't just marketing copy, it's a real,
  felt difference.
- Set "Startseite" to **Agenda** in the same step (the picker only offered Board + Agenda, since
  everything else was already off — correct). Verified via direct Livewire state inspection that the
  click bound correctly (`defaultPage: "agenda"`).
- Finished onboarding → **landed directly on `/app/agenda`**, not the board. Exactly the promise.
- Joined Lena's class via the invite code from Persona 1 (`G3RF85`, entered in "Klassen und Gruppen" →
  "Einer Klasse beitreten") — worked first try. Immediately saw Lena's shared "Geschichte" entry,
  correctly attributed "von Lena Meier", with a live "0/2" class-progress indicator. Lena's *private*
  "Vokabeln Lektion 6" entry correctly did **not** appear — privacy boundary verified working across
  two real, separate accounts, not just read from code.
- Checked the header/nav after all this: "Mehr" menu and profile dropdown now show **only** Agenda,
  Profil, Einstellungen, Hilfe — no Vorbereiten/Zeitplan/Wochenplan/Notfall/Fortschritt anywhere. This
  was the single strongest result across all four sessions: a genuinely different, second kind of
  user gets an app that feels purpose-built for her in ~2 minutes, with zero manual settings archaeology.
- **Nice-to-have (→ list item #9):** the onboarding "Bereit." summary screen still says "Du nutzt jetzt
  **Simple** mit 1 zusätzlichen Bereich" — technically accurate, but for a user who's about to land on
  Agenda and may never see the Simple board at all, leading with the list-concept name reads a little
  oddly.
- **Wrap-up impression:** this was the cleanest session of the four — no bugs hit, and the one feature
  this persona exists to test (quiz-driven module defaults + shared class Agenda) worked essentially
  perfectly, verified end-to-end with two real accounts rather than assumed from reading code.

### Persona 3: Marco, 20 — first-year university student, first time living alone

**Backstory:** Just moved into his own flat. Juggling coursework/assignments (real "in progress vs.
done" thinking), a group project, and the sudden avalanche of adult chores (groceries, rent, laundry,
figuring out the city). Wants to see what's actively being worked on at a glance. Chosen to exercise
Kanban (untested by any other persona) plus Projects and general module cleanup.

- Registered (marco.rossi.uxtest@example.com). Onboarding quiz: selected "Ich will sehen, was gerade
  in Arbeit ist und was schon fertig" + "Ich habe kleine Erledigungen und grosse, mehrteilige Vorhaben
  gleichzeitig" — a very plausible combo for this persona. **Found a real edge case in the tie-break
  rule** (→ list item #6): those two answers cast one vote each for *different* concepts (`kanban` vs
  `three_things`) — a genuine tie, resolved silently in favour of `three_things` via source
  declaration order, not anything visible to the user. Re-ran the quiz with "in Arbeit/fertig" +
  "ganzen Tag durchplanen" instead (2 clean votes for Kanban) to actually get Kanban and continue the
  session as intended.
- Feature-Galerie correctly pre-enabled Vorbereiten/Zeitplan/Wochenplan & Ferien (from the "durchplanen"
  answer) and left Agenda/Bastelideen/Notfallmodus/Fortschritt off — sensible for someone with no
  school-homework need.
- **Bug (→ list item #2):** onboarding's own Kanban explainer names the wrong column ("Offen" instead
  of "Backlog"). Confirmed at the source line, see priority list above.
- Added tasks via QuickCapture: "Statistik-Übungsblatt 3 abgeben" and "Kühlschrank putzen" — both
  landed correctly in BACKLOG. Empty-state copy per column is well-written and distinct ("Nichts
  gerade in Arbeit — zieh eine Karte her." / "Noch nichts erledigt.").
- **Bug, significant (→ list item #1):** captured a project, "Gruppenprojekt Marketing-Kurs", via
  QuickCapture. The Kanban board never gained a Projekte column, link, card, or any other trace of it
  — nothing on screen changed at all. Opened the edit sheet on an existing task to look for a way to
  assign/reach the project (the "Liste" picker only offers Inbox/To-Dos/Tasks/Projekte as coarse
  categories, not a specific-project picker), found nothing, and — because the UI gave literally zero
  feedback that the capture had worked — **concluded it must have failed and recreated it**, ending up
  with two identical duplicate projects. Only by switching the account's list concept to "3 Things" in
  Settings did both project cards appear, correctly, in a real Projekte column — confirming neither
  capture attempt had actually failed, the data was safe the whole time, just invisible. Switched back
  to Kanban afterward and confirmed the board still shows nothing for either project. Full impact
  analysis and suggested fix directions are in the priority list above.
- Drag-and-drop between Kanban columns works flawlessly: dragged "Kühlschrank putzen" from Backlog
  into "In Arbeit" (smooth, no lag, correct column counts updated instantly), then checked it done —
  it moved to "Erledigt" with a strikethrough + green check, exactly as expected. This part of Kanban
  is genuinely solid; the Projects gap above is the one real dark corner.
- **Wrap-up impression:** Kanban itself (columns, drag, empty states, completion) is well built and
  satisfying to use. The two real issues found — the onboarding copy mismatch and the
  Projects-invisible-in-Kanban gap — are both concrete and worth fixing; the second one is genuinely
  more than cosmetic (real data becomes practically unreachable for a real, first-class user path).

### Persona 4: David, 24 — freelance power user (also granted admin for this session)

**Backstory:** Runs his own small freelance/consulting work, wants total control over his setup,
happy to dig through Settings rather than follow a guided tour. Manages client projects, a WG
household, and his own taxes. Chosen to exercise the "deep" surfaces no other persona would
naturally reach: Task-Gruppen, Notfallmodus, and — with `is_admin` granted via the documented
`php artisan admin:grant` command — the admin-only Feature-Announcements, Hilfe-Center, and Support
tools, verified as a real second user would experience them.

- Registered, then explicitly **skipped** the onboarding quiz (realistic power-user behavior — dive
  into Settings directly). Skip flow matches its own design exactly: inline "Übersprungen"
  confirmation with a "Weiter zur App" link, no forced redirect.
- Granted admin via `php artisan admin:grant <email>` — worked cleanly, no DB surprises.
- Stayed on the default **3 Things** concept (deliberately, to test Task-Gruppen/Notfallmodus) and set
  up a real freelance workload:
  - **Task-Gruppen**: created "Kunden-Website Redesign" via QuickCapture's Gruppe target (new-group
    name + list picked correctly, verified via Livewire state before submit), added a second task and
    a note directly on the group's own page. The group page (Inbox/To-Dos/Tasks/Notizen columns,
    progress bar) is polished and mirrors the main board convincingly — genuinely pleasant to use.
    **Friction (→ list item #8):** pressing Enter in the group page's inline "Hinzufügen …" field does
    not submit it, only the "+" button does.
  - **Notfallmodus**: created a "Steuererklärung 2025" project with two tasks, started Notfallmodus on
    it from the project page's "…" menu. The arrange screen, the signal-toned dashboard banner, the
    numbered pinned tasks, the "Notfall" badge on the project card, and the header's red-dot indicator
    on "Mehr" all worked exactly as documented. Completing a task live-updated the banner text ("1 von
    2 erledigt · Weiter: …") and the project card's progress bar. **No bugs found here — this feature
    is genuinely well-built and satisfying.**
- **Feature-Announcements (admin), full loop verified across two accounts:** created a "Release"
  announcement ("Neu: Kanban-Ansicht") linked to Settings' `#list-concept` card, published it, then
  logged in as **Marco** (a different, already-existing account) and confirmed the toast appeared
  correctly at the bottom-left with the right icon/type styling, and that "Einstellungen ansehen →"
  correctly navigated to `/app/settings`. This is a real, working, cross-account feature — not just
  something that renders in isolation.
- **Hilfe-Center (admin), full loop verified:** created category "Erste Schritte" + one Markdown
  article ("Welches Listen-Konzept passt zu mir?"), published it, confirmed it renders correctly
  (bold list items, spacing) on the reader-facing `/app/help` page, then tested the "War dieser
  Artikel hilfreich? → Nein" flow: the inline note field appeared, submitting created a real
  `SupportRequest` and showed a proper confirmation — confirming the exact bug the project's own
  CLAUDE.md Known Issues section documented fixing is still fixed. Checked the ticket in
  `/app/help/support` (correct auto-generated subject) and then, as admin, in `/app/admin/support` —
  responded to it and changed its status to "Erledigt", both of which persisted correctly.
- **Testing note (my own mistake, not a bug):** while writing the Hilfe-Center article title, a
  `Ctrl+A` inside the contenteditable heading field didn't select-all reliably and new text got
  inserted mid-string instead of replacing the placeholder. Fixed with a triple-click instead.
  Flagging only because a real user doing the same Ctrl+A-then-type motion (a very standard way to
  replace a field's content) could hit the same surprise — worth a quick manual check of whether that
  title field behaves the way users expect on select-all.
- **Bug (→ list item #3):** a second, cleaner instance of the same unlocalized-validation-message
  issue, this time on Settings' API token form ("The new token name field is required."). Checked the
  source: same pattern as the Wochenplan case — a correctly-styled `@error()` block, just displaying
  Laravel's stock English string. This is what elevated finding #3 from "one odd message" to "no
  German validation messages exist anywhere in this project" — confirmed by the total absence of a
  `lang/` directory.
- **Wrap-up impression:** the "power user" surfaces (Task-Gruppen, Notfallmodus, Feature-Announcements,
  Hilfe-Center, Support, API tokens) are, on the whole, excellent — deep, cohesive, well cross-linked,
  and every multi-step admin flow tried worked end-to-end on the first real attempt. The findings from
  this session are both minor and highly fixable; nothing here felt structurally under-built.
