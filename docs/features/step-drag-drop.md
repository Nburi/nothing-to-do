# Schritte verschieben (Drag and Drop)

(`FUNCTIONS.md` does not exist on `refactor/refactor`; as in step 4.1 the feature is documented here. Decisions and the
reasoning are in `docs/steps/4.3c.md`.)

On a **project page** (`/board/projects/{id}`) and a **group page** (`/board/groups/{id}`) the open steps are
moved, within their list and, on a project, **between stages and into „Ohne Etappe“**. The same move is reachable three ways:

| Way | How |
|---|---|
| Mouse | Drag the grip ⠿ at the left of a step. A dashed slot shows the place; a dock at the bottom lists every stage (drop on a chip = end of that stage). |
| Touch | Press and hold a step for about a third of a second until it lifts, then drag. A swipe always scrolls. |
| Keyboard | Tab to the grip, **Space** lifts, **↑ ↓** change the place (across stage borders), **Page ↑ ↓** change the stage, **Home / End** go to the top or bottom of the stage, **Space** drops, **Esc** cancels. Every step is announced. |
| Menu ⋯ | **Ganz nach oben / Ganz nach unten**, **In Etappe N verschieben**, **Ohne Etappe**. No dragging. |

- A step that **waits on another step** can be moved like any other. If the new stage contradicts the waiting (the waiting
  step would sit in an earlier stage than the step it waits on), the waiting is let go and the toast says so.
- The toast has **„Rückgängig“**: it puts the step back at its old stage and place and restores a let-go waiting.
- Nothing happens (no toast) when a step is dropped where it already is.
- The action behind it, `moveStep(id, "none" | "stage-ID", position)`, checks that the step and the stage belong to the
  user and the project, renumbers the stage's steps and is safe to repeat.
- If saving fails the step jumps back and a short line says so; while it saves, the step is faded.

Code: `ProjectEditor::place()` / `putBack()`, `StepMove`, `ManagesStepMoves`, `resources/js/step-drag.js`, `step-order.js`,
`shell/step-row.blade.php`, `shell/step-menu.blade.php`. Tests: `ProjectEditorTest`, `StepMoveTest`.
