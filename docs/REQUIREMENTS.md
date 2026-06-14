# nothing-to-do — Requirements

Structured, complete requirements for the rebuild. Derived from the project brief.
This is the contract that implementation (§Step 7) and tests (§Step 9) are checked against.

---

## 1. Concept — the "3 Things" framework

Work is sorted by **size and shape**, not by project:

| Type | Meaning |
|---|---|
| **To-Do** | Small; several can be cleared in one work session. |
| **Task** | Larger, but still a single work step. |
| **Project** | Multi-part, has sub-tasks. **Not built now — architecture must allow it later.** |

New items land in the **Inbox** and are triaged into **To-Dos** or **Tasks**.

---

## 2. Data model — `Task`

| Field | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK → users | Owner. Every query is scoped to the authenticated user. |
| `title` | string | Task name. Required. |
| `list` | enum | `inbox` \| `todos` \| `tasks`. Default `inbox`. (Designed to accept `projects` later.) |
| `is_today` | boolean | Focus-for-today flag. Default `false`. Only meaningful when `list != inbox`. |
| `is_important` | boolean | Important flag. Default `false`. |
| `deadline` | date \| null | **Hard deadline** — external/real. Must be done that day. Always takes precedence. |
| `due_date` | date \| null | **Soft target** — self-imposed. Aimed for, but yields to a hard `deadline` on conflict. |
| `is_completed` | boolean | Done. Default `false`. |
| `sort_order` | integer \| null | Manual ordering (drag & drop). |
| `created_at` / `updated_at` | timestamp | |

**Deadline logic (hard vs soft):**
- `deadline` is the authoritative date. If both are set, `deadline` wins for urgency/display.
- "Effective date" used for sorting/urgency = `deadline ?? due_date`.
- Urgency window = effective date within the next **4 days** (inclusive of today).

**Future Projects (not now, but no schema breakage when added):**
- A fourth `list` value `projects`, and a separate `Project` model with `Task` children
  (`project_id` nullable FK). The `list` enum and the board's column abstraction must extend cleanly.

---

## 3. Sort order (within a list)

Completed tasks are **hidden** from the active board (kept in DB, togg-able view later).
Rationale: the board is a "what's left to do" surface; done items are noise. They are not deleted.

Active tasks sort by:
1. `is_today = true` (top; on desktop visually separated in a Today area)
2. `is_important = true`
3. Effective date (`deadline ?? due_date`) within the next 4 days
4. Everything else — by `sort_order`, then `created_at`

---

## 4. Interactions

| Action | Effect |
|---|---|
| **Click task body** | Toggle `is_important` |
| **Checkbox / circle** | Toggle `is_completed` |
| **3-dot menu** | Edit or delete the task |
| **Create task** | Defaults to `inbox`; target list selectable at input |
| **Drag & drop (desktop)** | Move between lists (changes `list`); drop into/out of Today area toggles `is_today` |
| **Swipe right — Inbox (mobile)** | Move to `todos` |
| **Swipe left — Inbox (mobile)** | Move to `tasks` |
| **Swipe right — To-Dos/Tasks (mobile)** | Set `is_today = true` |
| **Swipe left — To-Dos/Tasks (mobile)** | Open context menu (edit / delete) |

Server is the source of truth — all interactions validated and authorized server-side; the frontend
is never trusted.

---

## 5. Desktop layout (≥ 768px)

- **3 columns:** `Inbox` | `To-Dos` | `Tasks`.
- **Global add input** above all columns. Creates in `inbox` by default; target list selectable inline.
- **Today area:** at the top of `To-Dos` and `Tasks`, a visually separated region for `is_today` tasks
  (line / tint / label). `Inbox` has **no** Today area.
- **Drag & drop:**
  - Between columns → changes `list`.
  - Into a column's Today area → `is_today = true`.
  - Out of Today into the normal area → `is_today = false`.
  - Clear drop-zone highlight + cursor feedback while dragging.

---

## 6. Mobile layout (< 768px)

- **Bottom navigation, 4 pages:**

  | Icon | Page |
  |---|---|
  | Inbox | Inbox |
  | List | To-Dos |
  | Pencil | Tasks |
  | Calendar | Today (all `is_today` across lists) |

- **Per-page quick-add input.**
- **Swipe gestures must feel native and fluid:**
  - Card tracks the finger 1:1 (real touch tracking, not snap-on-tap).
  - A **colored action surface with an icon** is revealed behind the card (e.g. green + arrow for
    To-Dos/Today, grey + menu for options).
  - Past a threshold (~40% card width) the action fires; below it, the card springs back.
  - Gentle visual feedback during the swipe (opacity / scale).

---

## 7. User accounts

- Registration and login.
- A user sees **only their own tasks**.
- Authorization enforced on every DB operation. A guest (no account) can see/create nothing.

---

## 8. Quality bar (non-functional)

- **Fast capture, minimal clicks, clear overview.** No feature bloat.
- **Empty / loading / error states** designed, not afterthoughts.
- **Edge cases:** empty lists, very long titles, rapid repeated gestures, conflicting deadline/due_date.
- **Accessibility:** keyboard reachable, sufficient contrast, respects reduced motion.
- **Responsive:** one codebase, desktop 3-column ↔ mobile paged, no broken in-between states.
</content>
