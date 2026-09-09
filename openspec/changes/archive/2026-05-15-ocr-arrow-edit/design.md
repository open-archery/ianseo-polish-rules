## Context

The ScorecardsOCR module extracts arrow values from scorecard photos using the OpenAI vision API. After OCR, `normalizeScorecard()` and `enrichScorecard()` (both in `scoring.js`) compute all derived scores from the raw `arrows[]` arrays. The rendered table is currently static HTML produced by `renderDetails()` in `render.js`.

OCR misreads happen occasionally. Once rendered, there is no way to correct a single arrow — the operator must re-scan. This change adds inline editing of individual arrow chips so one-click corrections are possible without a re-scan.

No PHP, DB, or ianseo core changes are required. Everything is client-side JS and CSS.

## Goals / Non-Goals

**Goals:**
- Make individual arrow chips in the results table click-to-edit.
- Re-run `enrichScorecard()` after each edit to update all derived values.
- Show a **"Manual entry"** badge on cards that have been edited.
- Patch the matching localStorage history entry to reflect corrected totals.
- Keep the history panel detail view read-only.

**Non-Goals:**
- Writing corrected scores to the ianseo database.
- Editing `recorded_*` fields (what the archer wrote on the card).
- Persisting edits beyond the current browser session.
- Undo/redo history for edits.

## Decisions

### D1 — Transient `<select>` instead of persistent edit mode

**Decision:** Clicking an arrow chip replaces it with a `<select>`. On `change`, the model is updated and the card is re-rendered from scratch (chips return; select disappears).

**Rationale:** A toggle "edit mode" per card adds state complexity (two rendering paths, a save button, cancel semantics) for a use case where corrections are rare and per-arrow. A transient select is minimal and self-closing — the user clicks, picks, done.

**Alternative considered:** Persistent edit mode with all chips turning into selects simultaneously. Rejected — too much visual noise for the common case of correcting a single misread arrow.

---

### D2 — Store `_sc`, `_rerender`, `_historyId` on the card DOM element

**Decision:** After OCR, the card element gets three properties:
- `card._sc` — the live scorecard object (mutated on edit)
- `card._rerender` — closure over `renderCard("done", sc)` so re-render works without arguments
- `card._historyId` — the UUID of the matching localStorage history entry

**Rationale:** Event delegation on `resultsEl` handles all card interactions from a single listener. Without card-level state, the handler would need to walk up the DOM and do a reverse-lookup. Storing on the element is the established pattern in this codebase (`data-idx` on history rows already does this).

**Alternative considered:** A `Map<DOMElement, sc>` store. Rejected — identical complexity, no advantage.

---

### D3 — `renderDetails(d, { editable })` flag, not a separate render function

**Decision:** `renderDetails` gains an optional second argument `{ editable = false }`. When `true`, arrow chips receive `data-end`, `data-row`, `data-arrow` positional attributes and the class `chip--editable`. History panel calls `renderDetails(d)` (editable defaults to false).

**Rationale:** A separate `renderDetailsEditable()` would duplicate all the table-building logic. A flag keeps the two paths in sync with minimal branching.

**Alternative considered:** Separate function. Rejected — maintenance burden, easy to diverge.

---

### D4 — Event delegation on `resultsEl` for chip interactions

**Decision:** Two delegated listeners on `resultsEl`:
1. `click` on `.chip--editable` → replace chip with a `<select>` positioned in-place.
2. `change` on `.arrow-edit` (the select) → read `data-*` attrs, mutate `card._sc`, call `enrichScorecard`, call `card._rerender()`, call `updateHistoryEntry()`.

**Rationale:** `resultsEl` already has a delegated `click` listener for `.log-btn`. Adding chip handling there is consistent and doesn't require per-card listener attachment (which would leak if cards were ever removed).

**Alternative considered:** Attach listeners inside `renderCard`. Rejected — each re-render would need to re-attach listeners.

---

### D5 — Correction badge alongside existing validity badge

**Decision:** `renderCard()` checks `data?._manually_corrected` and appends a `<div class="sbadge sbadge--corrected">Manual entry</div>` in `.card-actions` next to the existing validity badge.

**Rationale:** The badge is part of the card header which is already managed by `renderCard`. Re-using the existing badge system (`.sbadge`) keeps styling consistent.

---

### D6 — History entry patched, not replaced

**Decision:** `updateHistoryEntry(id, sc)` loads the full history array from localStorage, finds the entry by `id`, updates `scorecard`, `calculated_grand_total`, `errors_count`, `overall_valid`, and sets `manually_corrected: true`, then writes back.

**Rationale:** Replacing the full entry would reset `timestamp`, `filename`, and `archer_name`. Patching only the fields that change from correction preserves the original scan metadata.

## Files to Create / Modify

| File | Change |
|---|---|
| `ScorecardsOCR/js/render.js` | `arrowChip(v, editMeta?)` — add optional positional attrs; `renderDetails(d, {editable})` — pass `editMeta` when editable |
| `ScorecardsOCR/js/ianseo.js` | Store `_sc`/`_rerender`/`_historyId` on card; add delegated chip click/change handlers; add `updateHistoryEntry()`; add correction badge in `renderCard()` |
| `ScorecardsOCR/ScorecardsOcr.css` | `.chip--editable` (hover cursor + highlight), `.arrow-edit` (inline select), `.sbadge--corrected` (neutral badge) |

No PHP files, no DB, no menu.php changes.

## Risks / Trade-offs

- **Re-render flicker** — `card._rerender()` sets `card.innerHTML` entirely. On slow machines, there may be a brief flicker. Mitigation: the corrected card is small HTML; flicker is imperceptible in practice.
- **History desync** — if `card._historyId` is `null` (history disabled or card pre-dates the ID scheme), `updateHistoryEntry` is a no-op. Mitigation: guard with `if (card._historyId)`.
- **`normalizeArrow()` already in scope** — the select `change` handler calls `normalizeArrow(sel.value)` before storing, ensuring `"M"` / `"X"` / numeric strings are all normalized consistently.
