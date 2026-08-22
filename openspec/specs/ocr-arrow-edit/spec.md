## ADDED Requirements

### Requirement: Arrow chips are editable in the results view
Individual arrow value chips in the OCR results table SHALL be interactive. Clicking a chip SHALL replace it with a `<select>` element containing all valid arrow options: `M`, `1`, `2`, `3`, `4`, `5`, `6`, `7`, `8`, `9`, `10`, `X`. The currently displayed value SHALL be pre-selected.

#### Scenario: Operator clicks a chip with value 9
- **WHEN** an OCR result card is displayed and the operator clicks an arrow chip showing `9`
- **THEN** the chip is replaced by a `<select>` with options M through X, with `9` pre-selected

#### Scenario: Chip select shows all valid arrow values
- **WHEN** the chip select is opened
- **THEN** the options are exactly: M, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, X (in this order)

---

### Requirement: Selecting a new arrow value triggers full score recalculation
After the operator selects a different value in the arrow select, the system SHALL update the underlying scorecard data model and re-run `enrichScorecard()` to recompute all derived values (end sums, razem, running totals, 10+X counts, X counts, grand total, error flags).

#### Scenario: Operator changes misread arrow from 0 to 8
- **WHEN** operator selects `8` in the chip select where OCR had read `0` (stored as `M`)
- **THEN** the end sum, razem, running total, and grand total are all recomputed and the card is re-rendered with updated values

#### Scenario: Correcting an arrow that caused an error clears the error
- **WHEN** an end had a `suma_error` because OCR misread an arrow and operator corrects it to the true value
- **THEN** the `suma_error` flag is cleared and the error is removed from the error list

#### Scenario: Correcting an arrow that was correct introduces an error
- **WHEN** operator changes an arrow from the correct OCR value to an incorrect one
- **THEN** a new `suma_error` or similar error flag appears for the affected end

---

### Requirement: Manually corrected cards display a "Manual entry" badge
Any card where at least one arrow has been edited manually SHALL display a **"Manual entry"** badge in the card header alongside the existing OCR validity badge.

#### Scenario: Badge appears after first edit
- **WHEN** operator changes any arrow value on a card
- **THEN** a "Manual entry" badge appears in the card header

#### Scenario: Badge persists through further edits
- **WHEN** operator makes a second or subsequent edit on the same card
- **THEN** the "Manual entry" badge remains visible

---

### Requirement: History entry is updated on arrow correction
When an arrow is corrected on a card that has a matching localStorage history entry, the system SHALL patch that entry with the updated `scorecard`, `calculated_grand_total`, `errors_count`, `overall_valid`, and a `manually_corrected: true` flag. The original `timestamp`, `filename`, `archer_name`, and `barcode_text` SHALL remain unchanged.

#### Scenario: History entry reflects corrected grand total
- **WHEN** operator corrects an arrow that increases the grand total by 1
- **THEN** the history entry for that card shows the incremented grand total

#### Scenario: History panel shows corrected validity badge
- **WHEN** operator corrects the last error on a card (making it fully valid)
- **THEN** the history list row for that card shows ✓ Poprawna

---

### Requirement: History panel detail view is read-only
When a history entry is expanded in the history panel, the arrow chips in the detail view SHALL NOT be interactive (no `chip--editable` class, no click handler).

#### Scenario: Chips in history detail view are not clickable
- **WHEN** operator clicks an arrow chip in the history panel detail view
- **THEN** nothing happens (no select appears, no value changes)
