## Context

PZŁucz tournaments use physical scorecards (4 per A4 sheet, one per archer). Participants fill in arrows per end and compute running totals themselves. A tablet operator enters scores into ianseo's `Qualifications` table end-by-end during the round. Errors — both participant arithmetic and operator data entry — currently surface only after results are published.

The PoC (`ScorecardsOCR/`) is a standalone HTML+JS app that calls the OpenAI vision API directly from the browser, extracts scorecard data, and detects arithmetic errors. It has no ianseo integration, no server component, and the API key is stored client-side. This design wraps the PoC logic into an ianseo-native module.

Physical scorecards have a printed barcode with text in the format `{bib}-{div}-{class}-{session}` (e.g. `5083-R-U21M-2`). Each A4 sheet holds 4 scorecards (quadrants: A/B/C/D). Scores are entered into ianseo end-by-end during the round; the DB state is always partial until the round ends.

## Goals / Non-Goals

**Goals:**
- Detect arithmetic errors on the physical scorecard (participant-recorded sum ≠ computed sum from arrows)
- Detect ianseo data-entry mismatches (computed sum from OCR ≠ `QuD{N}Score` in `Qualifications`)
- Show results in real time on screen, errors highlighted; no persistence required
- Store the OpenAI API key server-side, never in the browser
- Reuse the PoC's existing JS logic (`scoring.js`, `render.js`) with minimal changes

**Non-Goals:**
- Writing to `Qualifications` or any ianseo score table
- Persisting flags, audit trails, or scan history server-side
- Mobile-native or offline use
- Supporting non-PZŁucz scorecard layouts

## Decisions

### D1 — API call via PHP proxy, not direct from browser

**Decision:** The browser sends the resized base64 image to `AjaxOcrProxy.php`, which reads the API key from `PLOcrConfig` and forwards the request to OpenAI. JSON is returned to the browser.

**Rationale:** The PoC leaks the API key to the browser. For a shared ianseo admin panel the key must be server-side. The existing `SportzonaProxy.php` establishes this pattern in the module.

**Alternative considered:** Keep the key client-side (PoC approach). Rejected because ianseo is a shared admin tool and the API key would be visible to any operator.

---

### D2 — Barcode text as primary archer identifier

**Decision:** The LLM prompt is extended to extract the printed barcode text (e.g. `5083-R-U21M-2`) as a structured field. `AjaxGetScores.php` parses it to derive bib number and session index, then queries `Entries JOIN Qualifications` to return `QuD{N}Score`, `QuD{N}Gold`, `QuD{N}Xnine`.

**Rationale:** The barcode text is machine-printed, reliably OCR-readable, and encodes all identification data needed: archer bib, division, class, and session number. It eliminates fuzzy name matching. Every PZŁucz scorecard carries this code.

**Barcode format:** `{bib}-{div}-{class}-{session}` where `session` is the 1-based distance index matching `QuD{N}Score`.

**Alternative considered:** Fuzzy match by archer name. Rejected — handwritten names are unreliable for OCR, and matching to `EnFirstName + EnLastName` requires disambiguation UI.

---

### D3 — Three-way comparison, displayed per end

**Decision:** For each end, the UI shows three columns: OCR-calculated (computed from extracted arrows by `scoring.js`), OCR-recorded (what participant wrote in the Suma/Razem cells), and DB value (from `Qualifications`). DB cells show `—` when the score is not yet entered (null or 0).

**States:**
- All match → ✓ (no highlight)
- OCR calc ≠ OCR recorded → arithmetic error on card (red highlight, primary alert)
- OCR calc ≠ DB → data-entry mismatch in ianseo (orange highlight, secondary alert)
- DB = `—` → not yet entered, comparison deferred (grey)

**Rationale:** The arithmetic check (calc ≠ recorded) is always actionable. The DB comparison requires scores to be entered, which is partial during the round. Separating the two alerts prevents false noise when ianseo hasn't caught up.

---

### D4 — `PLOcrConfig` table for API key storage

**Decision:** A key/value table `PLOcrConfig` (columns: `PlocKey VARCHAR(100) PRIMARY KEY`, `PlocValue TEXT`) is auto-created on first load via `SHOW TABLES LIKE` pattern (matching `Lookup/Install.php`). Supported keys: `api_key`, `model` (default: `gpt-4.1-mini`).

**Alternative considered:** `ModulesParameters` (per-tournament). Rejected — the API key is global, not per-tournament.

**Alternative considered:** `Parameters` global store via `GetParameter`/`SetParameter`. Rejected — mixing a sensitive key into the shared parameters table is undesirable; a dedicated table is clearer and namespaced.

---

### D5 — Reuse PoC JS files with minimal changes

**Decision:** `scoring.js` is copied unchanged. `render.js` is adapted to add a DB-value column and remove the API-key error path. A new `ianseo.js` handles: posting images to the PHP proxy, parsing barcode text, fetching DB scores via `AjaxGetScores.php`, and merging DB data into the rendered card.

**Rationale:** The PoC JS is already well-structured and handles quadrant splitting, continuation, pagination, and history. Rewriting it would be wasteful.

---

### D6 — Image resize stays client-side

**Decision:** The browser resizes the image to ≤2400px (existing PoC `resizeImage()` function) before POSTing the base64 to the PHP proxy. The proxy receives already-resized base64, not the original file.

**Rationale:** Keeps PHP upload size small (~300KB vs 5–10MB original). Avoids PHP `upload_max_filesize` / `post_max_size` issues on ianseo servers. The resize logic already exists in the PoC.

## ianseo Integration Points

| Hook | How |
|---|---|
| Menu | `menu.php`: `$ret['QUAL'][]` for main page; `$ret['MODS'][]` for config |
| Session guard | `CheckTourSession(true)` on UI pages; `CheckTourSession(false)` on AJAX endpoints |
| DB read | `safe_r_sql` / `safe_fetch` on `Entries` and `Qualifications` |
| DB write | `safe_w_sql` on `PLOcrConfig` only (never on score tables) |
| Bootstrap | `dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php'` from `ScorecardsOCR/*.php` |
| Template | `head.php` / `tail.php` on the main UI page and config page |

## Files to Create / Modify

**New files:**
```
Modules/Sets/PL/ScorecardsOCR/
├── ScorecardsOcr.php         UI page: dropzone + results (head/tail template)
├── OcrConfig.php             Config UI: API key + model input, save form
├── Fun_ScorecardsOcr.php     Shared helpers: pl_ocr_get_config(), pl_ocr_save_config(),
│                             pl_ocr_install(), pl_ocr_lookup_scores()
├── AjaxOcrProxy.php          AJAX: receives base64 image, calls OpenAI, returns JSON
├── AjaxGetScores.php         AJAX: receives barcode_text, returns QuD{N} scores from DB
└── js/
    ├── scoring.js            Copied unchanged from PoC
    ├── render.js             Adapted: add DB column, remove API-key config path
    └── ianseo.js             New: proxy integration, barcode parse, DB overlay
```

**Modified files:**
```
menu.php                      Add two menu entries (QUAL + MODS)
```

## Extended LLM Prompt Fields

The PoC system prompt is extended to also extract:

```json
{
  "barcode_text": "5083-R-U21M-2",
  "target_label": "1C",
  "session_label": "70m-2"
}
```

`barcode_text` is the anchor for DB lookup. The other fields are confirmatory and shown in the UI.

## DB Schema

```sql
CREATE TABLE PLOcrConfig (
  PlocKey   VARCHAR(100) NOT NULL,
  PlocValue TEXT,
  PRIMARY KEY (PlocKey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

## Risks / Trade-offs

- **OpenAI outage / rate limit** → OCR fails gracefully; browser shows error card (existing PoC behaviour). No impact on ianseo data.
- **Barcode text OCR failure** → DB comparison skipped; arithmetic check still works. Operator sees `barcode_text: null` and can manually note the archer.
- **PHP upload limits** → Mitigated by D6 (client-side resize to ~300KB). If `post_max_size` is very low, the proxy will reject large payloads with a clear error.
- **Partial DB state** → By design; DB column shows `—` for un-entered ends. Arithmetic errors are always surfaced regardless of DB state.
- **API cost** → Each quadrant = one API call. A full sheet (4 archers) = 4 calls. At `gpt-4.1-mini` pricing this is negligible per tournament. Admin controls the model in config.
