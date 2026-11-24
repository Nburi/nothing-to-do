/**
 * Step 4.3c: the order rules of a project or group page as plain functions on a plain model. They
 * say beforehand what a drop would do (the preview while dragging, the keyboard announcements).
 * The server decides (ProjectEditor::place()); these follow the same two rules:
 *  - the board shows the next two steps (NextSteps): open, in To-dos or Tasks, not waiting on an open
 *    step, in the current stage or in no stage, the current stage's first;
 *  - a "wartet auf" link that the new stage order contradicts (the waiting step in an earlier stage
 *    than the step it waits on) is let go.
 *
 * model = { lanes: [{ key, index, name, ids }], steps: { [id]: { title, list, waits } } }
 * A lane is a stage (index 0, 1, ...) or the steps without a stage (index -1, key "none"); `waits`
 * is the id of the open step a step waits on, or null.
 */
const WORK = ['todos', 'tasks'];

export const short = (text, max = 30) => (text.length > max ? text.slice(0, max - 1).trimEnd() + '…' : text);

/** ":name" placeholders of a translation, replaced in one pass so a replaced value is never read again. */
export const fmt = (text, vars = {}) => String(text ?? '').replace(/:([a-z]+)/g, (match, key) => (key in vars ? vars[key] : match));

export const laneOf = (model, id) => model.lanes.find((lane) => lane.ids.includes(id)) ?? null;

export const hasStages = (model) => model.lanes.some((lane) => lane.index >= 0);

const copy = (model) => ({
    lanes: model.lanes.map((lane) => ({ ...lane, ids: [...lane.ids] })),
    steps: Object.fromEntries(Object.entries(model.steps).map(([id, step]) => [id, { ...step }])),
});

/** The stage the project is in: the first one with any open step (in any list), -1 when there is none. */
function currentIndex(model) {
    const stage = model.lanes.find((lane) => lane.index >= 0 && lane.ids.length > 0);

    return stage ? stage.index : -1;
}

const isBlocked = (model, id) => model.steps[id].waits !== null && laneOf(model, model.steps[id].waits) !== null;

/** The steps the board shows for this project or group. */
function nextTwo(model) {
    const now = currentIndex(model);
    const inCurrent = now >= 0 ? model.lanes.find((lane) => lane.index === now).ids : [];
    const loose = model.lanes.find((lane) => lane.index < 0)?.ids ?? [];

    return [...inCurrent, ...loose].filter((id) => WORK.includes(model.steps[id].list) && !isBlocked(model, id)).slice(0, 2);
}

/** Links whose waiting step sits in an earlier stage than the step it waits on; no stage never contradicts. */
function contradictions(model) {
    const found = [];
    model.lanes.forEach((lane) => lane.ids.forEach((id) => {
        const on = model.steps[id].waits === null ? null : laneOf(model, model.steps[id].waits);
        if (on && lane.index >= 0 && on.index >= 0 && lane.index < on.index) found.push({ waiter: id, blocker: model.steps[id].waits });
    }));

    return found;
}

/** The model after the step went to a lane at a slot (counted without the step itself; null = the end). */
export function moved(model, id, key, slot) {
    const next = copy(model);
    next.lanes.forEach((lane) => { lane.ids = lane.ids.filter((other) => other !== id); });
    const to = next.lanes.find((lane) => lane.key === key);
    to.ids.splice(slot === null || slot === undefined || slot > to.ids.length ? to.ids.length : Math.max(0, slot), 0, id);

    return next;
}

/** Lets go of the links the move of `id` contradicts: { model, cleared: [{ waiter, blocker }] }. */
export function settle(model, id) {
    const cleared = contradictions(model).filter((link) => link.waiter === id || link.blocker === id);
    if (cleared.length === 0) return { model, cleared };

    const next = copy(model);
    cleared.forEach((link) => { next.steps[link.waiter].waits = null; });

    return { model: next, cleared };
}

/**
 * What a drop does, in words: { tone: 'ok' | 'warn', same, head, lines }. `same` is true when the step
 * would stay where it is. The texts come from the page (`move.js` in lang/de and lang/en projects.php).
 */
export function describe(before, after, id, cleared, T) {
    const title = (stepId, max) => short(before.steps[stepId].title, max);
    const from = laneOf(before, id);
    const to = laneOf(after, id);
    const at = to.ids.indexOf(id);
    const same = from.key === to.key && from.ids.indexOf(id) === at;
    const place = { place: at + 1, count: to.ids.length };
    const out = { tone: 'ok', same, head: '', lines: [] };

    if (same) {
        out.head = T.stays;
    } else if (to.index >= 0) {
        out.head = fmt(T.head_stage, { number: to.index + 1, name: to.name, ...place });
    } else {
        out.head = fmt(hasStages(after) ? T.head_none : T.head_place, place);
    }

    if (from.key !== to.key) {
        if (to.index < 0) {
            out.lines.push(isBlocked(after, id) || !WORK.includes(after.steps[id].list) ? T.none_quiet : T.none_board);
        } else {
            out.lines.push(to.index > currentIndex(after) ? fmt(T.waits_stage, { number: to.index }) : T.current_stage);
        }
    }
    if (isBlocked(after, id)) {
        out.lines.push(fmt(T.still_waits, { title: title(after.steps[id].waits, 26) }));
    }
    cleared.forEach((link) => {
        out.tone = 'warn';
        out.lines.push(fmt(T.wait_cleared, { title: title(link.waiter, 24), on: title(link.blocker, 24) }));
    });

    const was = nextTwo(before);
    const now = nextTwo(after);
    now.filter((other) => !was.includes(other)).forEach((other) => out.lines.push(fmt(T.board_in, { title: title(other, 28) })));
    was.filter((other) => !now.includes(other)).forEach((other) => out.lines.push(fmt(T.board_out, { title: title(other, 28) })));

    return out;
}
