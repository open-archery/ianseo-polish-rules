## 1. Research and Bootstrap

- [x] 1.1 Read `.github/agents/research/ianseo-internals.md` (DB helpers, session guards, module patterns)
- [x] 1.2 Read `Lookup/Install.php` and `Lookup/SportzonaProxy.php` as reference for auto-install and proxy patterns
- [x] 1.3 Read `ScorecardsOCR/js/scoring.js`, `render.js`, `app.js`, and `history.js` to understand the PoC fully

## 2. Shared Helpers and Auto-Install

- [x] 2.1 Create `ScorecardsOCR/Fun_ScorecardsOcr.php` with `pl_ocr_install()` (auto-creates `PLOcrConfig` via `SHOW TABLES LIKE`), `pl_ocr_get_config($key)`, and `pl_ocr_save_config($key, $value)`
- [x] 2.2 Create `ScorecardsOCR/Fun_ScorecardsOcr.php`: add `pl_ocr_lookup_scores($bib, $session, $tourId)` — queries `Entries JOIN Qualifications` and returns `QuD{N}Score`, `QuD{N}Gold`, `QuD{N}Xnine` for the given bib and session index

## 3. AJAX Endpoints

- [x] 3.1 Create `ScorecardsOCR/AjaxOcrProxy.php`: bootstrap → `CheckTourSession(false)` → read `api_key` from `PLOcrConfig` → forward base64 image + system prompt to OpenAI → echo JSON response; return structured error JSON on failure
- [x] 3.2 Create `ScorecardsOCR/AjaxGetScores.php`: bootstrap → `CheckTourSession(false)` → parse `barcode_text` param → call `pl_ocr_lookup_scores()` → return JSON `{score, gold, xnine, found: bool}`
- [x] 3.3 Extend the LLM system prompt in `AjaxOcrProxy.php` to extract `barcode_text` (format `{bib}-{div}-{class}-{session}`), `target_label`, and `session_label` alongside existing scorecard fields

## 4. Config UI

- [x] 4.1 Create `ScorecardsOCR/OcrConfig.php`: bootstrap → `CheckTourSession(true)` → `pl_ocr_install()` → handle POST save → render form with `head.php`/`tail.php`; API key field shows placeholder when key exists, not the key value; model field defaults to `gpt-4.1-mini`
- [x] 4.2 Add Polish UI labels and success/error messages to `OcrConfig.php`

## 5. Client-Side JS

- [x] 5.1 Copy `ScorecardsOCR/js/scoring.js` unchanged into the new module location (or confirm it stays in place)
- [x] 5.2 Adapt `render.js`: add DB column to the end-by-end table (OCR Calc | OCR Recorded | DB); add orange highlight class for DB mismatch; remove API-key config error path (proxy handles it)
- [x] 5.3 Create `ScorecardsOCR/js/ianseo.js`: replace direct OpenAI fetch with POST to `AjaxOcrProxy.php`; after OCR result arrives, call `AjaxGetScores.php` with `barcode_text`; merge DB values into the enriched scorecard object before rendering

## 6. Main UI Page

- [x] 6.1 Create `ScorecardsOCR/ScorecardsOcr.php`: bootstrap → `CheckTourSession(true)` → `pl_ocr_install()` → render page with `head.php`/`tail.php`; include dropzone, results container, pagination; load `scoring.js`, `render.js`, `ianseo.js`
- [x] 6.2 Add Polish headings, tips, and error messages to `ScorecardsOcr.php`
- [x] 6.3 Show a warning banner if no API key is configured (link to config page)

## 7. Menu Registration

- [x] 7.1 Add to `menu.php` under `$ret['QUAL'][]`: `'Weryfikacja kart|...ScorecardsOCR/ScorecardsOcr.php'`
- [x] 7.2 Add to `menu.php` under `$ret['MODS'][]`: `'Konfiguracja OCR|...ScorecardsOCR/OcrConfig.php'`

## 8. Self-Review

- [x] 8.1 Verify all PHP files use correct bootstrap path (`dirname×4 . '/config.php'`) and session guards
- [x] 8.2 Verify `AjaxGetScores.php` and `AjaxOcrProxy.php` issue no writes to `Qualifications` or `Entries`
- [x] 8.3 Verify `PLOcrConfig` columns follow naming convention (`PlocKey`, `PlocValue`)
- [x] 8.4 Verify all user-facing strings are in Polish
- [x] 8.5 Check against reviewer checklist in `.github/agents/reviewer.prompt.md` before committing
