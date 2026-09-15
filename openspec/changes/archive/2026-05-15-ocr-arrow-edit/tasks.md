## 1. render.js — editable arrow chips

- [x] 1.1 Add optional `editMeta` parameter to `arrowChip(v, editMeta = null)`: when provided, add `data-end`, `data-row`, `data-arrow` attributes and class `chip--editable` to the chip span
- [x] 1.2 Add `{ editable = false }` options argument to `renderDetails(d, { editable = false } = {})` and thread it through to all `arrowChip()` calls in the ends table rows (pass `{ endIdx, row: "a"|"b", arrowIdx }` when editable)
- [x] 1.3 Verify `renderDetails(d)` called without options still works identically (history panel path)

## 2. ianseo.js — card state and event delegation

- [x] 2.1 After `enrichScorecard(sc)` in `analyze()`, attach `card._sc = sc` and `card._rerender = () => renderCard("done", sc)` to the card element
- [x] 2.2 Store the history entry id on the card: `card._historyId = id` (the UUID passed to `History.save()`)
- [x] 2.3 Pass `{ editable: true }` to `renderDetails` inside `renderCard` when called from main results (not history)
- [x] 2.4 Add delegated `click` listener on `resultsEl` for `.chip--editable`: replace clicked chip with a `<select class="arrow-edit">` pre-selecting the current value, with options M, 1–10, X
- [x] 2.5 Add delegated `change` listener on `resultsEl` for `.arrow-edit`: read `data-end`, `data-row`, `data-arrow`; walk up to `.card`; mutate `card._sc.ends[endIdx].sub_row_{row}.arrows[arrowIdx]` via `normalizeArrow(sel.value)`; set `card._sc._manually_corrected = true`; call `enrichScorecard(card._sc)`; call `card._rerender()`
- [x] 2.6 After re-render in the change handler, call `updateHistoryEntry(card._historyId, card._sc)` if `card._historyId` is set
- [x] 2.7 Implement `updateHistoryEntry(id, sc)`: load history array from localStorage, find entry by `id`, patch `scorecard`, `calculated_grand_total`, `errors_count`, `overall_valid`, `manually_corrected: true`; write back; call `History.refresh()`
- [x] 2.8 In `renderCard()`, check `data?._manually_corrected` and append `<div class="sbadge sbadge--corrected">Manual entry</div>` in `.card-actions` when true

## 3. CSS — styling

- [x] 3.1 Add `.chip--editable` rule: `cursor: pointer` + subtle hover highlight (e.g. `opacity: 0.8`, outline on hover)
- [x] 3.2 Add `.arrow-edit` rule: compact inline select matching chip dimensions (small font, no border radius excess, consistent height with chips)
- [x] 3.3 Add `.sbadge--corrected` rule: neutral badge style (e.g. grey/blue tones, distinct from `--valid` green and `--error` red)
