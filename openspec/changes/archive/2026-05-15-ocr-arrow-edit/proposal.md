## Why

OCR reading of arrow values is imperfect — the vision model occasionally misreads a digit or symbol (e.g. `9` → `0`, `X` → `10`). Currently there is no way to correct a misread arrow in the UI; the operator must re-upload the photo or accept the erroneous result. Enabling inline editing allows operators to fix individual OCR mistakes and immediately see the correct recomputed scores.

## What Changes

- Arrow value chips in the ScorecardsOCR results table become click-to-edit: clicking a chip opens a `<select>` with valid arrow options (`M`, `1`–`10`, `X`).
- On selection, `enrichScorecard()` is called client-side with the corrected arrows, and the full card is re-rendered with updated computed scores, error flags, and totals.
- Cards that have been manually corrected display a **"Manual entry"** badge alongside the existing validity badge.
- The matching localStorage history entry is patched to reflect the corrected totals and manual-correction flag.
- The history panel continues to render cards in read-only mode (no edit chips in history detail view).

## Non-goals

- Writing corrected scores to `Qualifications` or any ianseo DB table.
- Editing `recorded_*` values (what the archer wrote on the card) — only `arrows[]` are editable.
- Persisting edits across browser sessions (localStorage history is updated, but corrections are session-scoped).

## Capabilities

### New Capabilities

- `ocr-arrow-edit`: Inline editing of individual OCR-extracted arrow values with automatic score recalculation and a manual-correction badge.

### Modified Capabilities

- `scorecard-ocr-verification`: The results table gains editable arrow chips; the data flow adds a correction step between OCR parse and final display. Existing spec requirements are otherwise unchanged.

## Impact

- `ScorecardsOCR/js/render.js` — `arrowChip()` and `renderDetails()` gain an `editable` flag; no breaking changes to function signatures for callers using the default.
- `ScorecardsOCR/js/ianseo.js` — stores `_sc`, `_historyId`, `_rerender` on each card DOM element; adds event delegation for chip click/select-change; adds `updateHistoryEntry()`.
- `ScorecardsOCR/ScorecardsOcr.css` — new rules for `.chip--editable`, `.arrow-edit` (select), `.sbadge--corrected`.
- No PHP changes. No DB changes. No ianseo core changes.
