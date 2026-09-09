## Why

During PZŁucz qualification rounds, physical scorecards are the authoritative record. Participants calculate their own sums per end, and tablet operators independently enter totals into ianseo. Arithmetic errors on the card and entry mismatches currently go undetected until after results are published. A real-time LLM-based OCR tool allows officials to scan scorecards as they are collected and immediately surface calculation errors and ianseo data-entry mismatches — while participants are still on the field and corrections can be made.

## What Changes

- New `ScorecardsOCR/` sub-module under `Modules/Sets/PL/` providing an ianseo-native verification page
- PHP server-side proxy for OpenAI vision API calls (API key stored in DB, never exposed to browser)
- Config UI for storing and updating the API key
- Client-side scorecard rendering with three-way comparison: OCR-calculated vs participant-recorded vs ianseo DB value
- Barcode text parsed from scorecard image to identify archer and session without manual input
- Errors displayed on screen only — no writes to `Qualifications` or any score table, ever

## Capabilities

### New Capabilities

- `scorecard-ocr-verification`: Upload a scorecard photo (4 archers per sheet); LLM extracts arrow values and recorded sums per end; system computes correct sums and compares against participant-recorded totals and live ianseo `Qualifications` data; arithmetic errors and DB mismatches highlighted on screen in real time

### Modified Capabilities

_(none — existing features unaffected)_

## Impact

- **New files**: `ScorecardsOCR/` directory (7 PHP files, 3 JS files)
- **New DB table**: `PLOcrConfig` (key/value store for API key and model; auto-installed on first page load)
- **No changes to**: `Qualifications`, `Entries`, or any ianseo core table
- **New dependency**: OpenAI API (external HTTPS call from PHP server; key configured by admin)
- **menu.php**: Two new entries added under `QUAL` (verification page) and `MODS` (config page)
- **Spec produced by**: Developer agent (no PZŁucz domain rules govern this feature)
- **Non-goals**: Writing scores to ianseo, persisting flags or audit trails, mobile-native app, offline use
