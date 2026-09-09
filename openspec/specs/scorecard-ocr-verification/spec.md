## ADDED Requirements

### Requirement: API key configuration
The system SHALL provide a config page where an administrator can enter and save an OpenAI API key and model name. The key SHALL be stored in the `PLOcrConfig` DB table and MUST NOT be sent to the browser after saving. The table SHALL be auto-created on first page load if absent.

#### Scenario: First load with no table
- **WHEN** any ScorecardsOCR page is loaded and `PLOcrConfig` does not exist
- **THEN** the table is created automatically and the page renders without error

#### Scenario: Admin saves API key
- **WHEN** admin submits the config form with a non-empty API key
- **THEN** the key is written to `PLOcrConfig` and a success message is shown in Polish

#### Scenario: Config page shows masked key
- **WHEN** an API key is already saved and the config page is loaded
- **THEN** the key field shows a placeholder indicating a key is set (not the actual key value)

---

### Requirement: Scorecard image upload and OCR extraction
The system SHALL accept one or more scorecard images via drag-and-drop or file picker. Each image SHALL be split into four quadrants client-side (matching the 4-archer A4 sheet layout). Each quadrant SHALL be resized to ≤2400px client-side before upload. Each quadrant SHALL be sent to `AjaxOcrProxy.php` which proxies the image to the OpenAI vision API using the server-stored key and returns structured JSON.

#### Scenario: Single image uploaded
- **WHEN** a user drops or selects a scorecard image
- **THEN** it is split into 4 quadrants and each is sent to the OCR proxy independently

#### Scenario: Multiple images uploaded
- **WHEN** multiple images are selected
- **THEN** all are processed concurrently and results appear as cards as each completes

#### Scenario: No API key configured
- **WHEN** a request reaches `AjaxOcrProxy.php` and no API key exists in `PLOcrConfig`
- **THEN** the proxy returns an error JSON and the card displays a Polish error message

#### Scenario: OpenAI API error
- **WHEN** the OpenAI API returns a non-2xx response
- **THEN** the card displays an error state with the HTTP status; no data is written anywhere

---

### Requirement: Arithmetic error detection
The system SHALL compute the correct sum for each sub-row (A and B) and end (Razem) from the extracted arrow values. It SHALL compare each computed value against the participant-recorded value from the scorecard. Any mismatch SHALL be highlighted as an arithmetic error.

#### Scenario: Correct arithmetic
- **WHEN** all participant-recorded sums match the computed values
- **THEN** the card shows a green "Poprawne" (Valid) badge and no error rows

#### Scenario: Sub-row sum error
- **WHEN** the participant-recorded Suma for a sub-row does not equal the computed sum of that row's arrows
- **THEN** the cell is highlighted in red showing both the recorded and correct values

#### Scenario: End total (Razem) error
- **WHEN** the recorded Razem does not equal the sum of sub-row A and sub-row B computed values
- **THEN** the Razem cell is highlighted in red

#### Scenario: Running total error
- **WHEN** the recorded running total for an end does not equal the cumulative sum of all preceding ends
- **THEN** the running total cell is highlighted in red

#### Scenario: Partially filled scorecard
- **WHEN** only some ends have arrows filled in (card is mid-round)
- **THEN** only filled ends are checked; empty ends are shown as blank without error

---

### Requirement: Barcode-based archer identification
The system SHALL extract the printed barcode text from each scorecard quadrant as part of the OCR response. The barcode text format is `{bib}-{div}-{class}-{session}` (e.g. `5083-R-U21M-2`). The browser SHALL send the barcode text to `AjaxGetScores.php` to retrieve the archer's live scores from `Qualifications`.

#### Scenario: Barcode successfully read
- **WHEN** the LLM returns a valid barcode_text value
- **THEN** the browser automatically requests DB scores and populates the DB comparison column

#### Scenario: Barcode not readable
- **WHEN** the LLM returns null for barcode_text
- **THEN** the DB comparison column shows "—" for all ends and no lookup is attempted

#### Scenario: Archer not found in DB
- **WHEN** the bib number from the barcode does not match any entry in the current tournament
- **THEN** the DB column shows "—" and a non-blocking note indicates "Zawodnik nie znaleziony"

---

### Requirement: Three-way comparison display
The system SHALL display per-end results in a table with three value columns: OCR-calculated (from arrows), participant-recorded (from scorecard cells), and DB value (from `Qualifications`). The DB column SHALL show "—" when the score is not yet entered. Arithmetic errors SHALL be highlighted red; DB mismatches SHALL be highlighted orange.

#### Scenario: DB score matches OCR calculated
- **WHEN** `QuD{N}Score` equals the OCR-calculated total for that session
- **THEN** the DB column shows the value with no error highlight

#### Scenario: DB score differs from OCR calculated
- **WHEN** `QuD{N}Score` does not equal the OCR-calculated total and the DB value is not null/zero
- **THEN** the DB total cell is highlighted orange showing both values

#### Scenario: DB score not yet entered
- **WHEN** `QuD{N}Score` is null or zero for the session
- **THEN** the DB column shows "—" with no error highlight

#### Scenario: Both arithmetic and DB mismatch present
- **WHEN** the card has both an arithmetic error and a DB mismatch
- **THEN** both are shown independently; arithmetic errors take red, DB mismatches take orange

---

### Requirement: No writes to score tables
The system SHALL NEVER write to `Qualifications`, `Entries`, or any ianseo core score table. All output is display-only on screen.

#### Scenario: Commit attempt (internal guard)
- **WHEN** `AjaxGetScores.php` or `AjaxOcrProxy.php` is called
- **THEN** only SELECT queries are issued against `Qualifications` and `Entries`
