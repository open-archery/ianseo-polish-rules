# Tasks: Diploma Titles

## Task 1 — Read context

Read the following before writing any code:

- `.github/agents/research/ianseo-internals.md`
- `.github/agents/research/pzlucz-rules.md`
- `openspec/specs/diplomas/design.md`
- `openspec/changes/diploma-titles/design.md`
- `Diplomas/DiplomaSetup.php` (current state)
- `Diplomas/DiplomaConfig.php` (current state)
- `Diplomas/PLDiplomaPdf.php` (current state)
- `Diplomas/PrnIndividualDipl.php` (current state)
- `Diplomas/PrnTeamDipl.php` (current state)

---

## Task 2 — `DiplomaSetup.php`: migrations + new helpers

**File:** `Diplomas/DiplomaSetup.php`

1. In `pl_diploma_ensure_tables()`, add after the existing `PLDiplomaEventText` block:

   ```php
   // Add title columns if not present (diploma-titles change)
   safe_w_sql("ALTER TABLE PLDiplomaConfig ADD COLUMN IF NOT EXISTS PlDcTitlesEnabled TINYINT(1) NOT NULL DEFAULT 0");
   safe_w_sql("ALTER TABLE PLDiplomaEventText ADD COLUMN IF NOT EXISTS PlDeTitlePrefix VARCHAR(100) NOT NULL DEFAULT ''");
   safe_w_sql("ALTER TABLE PLDiplomaEventText ADD COLUMN IF NOT EXISTS PlDeTitleText VARCHAR(255) NOT NULL DEFAULT ''");
   ```

2. Update `pl_diploma_get_config()`:
   - Add `'TitlesEnabled' => 0` to `$defaults`
   - Read `PlDcTitlesEnabled` from DB row

3. Update `pl_diploma_save_config()`:
   - Include `PlDcTitlesEnabled = intval($data['TitlesEnabled'])` in both UPDATE and INSERT

4. Update `pl_diploma_get_event_texts()`:
   - Return `['customText' => …, 'titlePrefix' => …, 'titleText' => …]` per event code instead of a plain string

5. Update `pl_diploma_save_event_text()`:
   - Accept `$titlePrefix` and `$titleText` params
   - Include them in INSERT/UPDATE/DELETE logic (delete row only if all three fields are empty)

6. Add `pl_diploma_get_title_defaults($rawEventCode)`:
   - Parse division (first char) and class (remainder, handling `X` suffix for mixed)
   - Return `['prefix' => …, 'text' => …]` from the hardcoded table in the design

7. Add `pl_diploma_build_title($rank, $prefix, $text, $year, $isTeam, $isMixed)`:
   - Return `''` if `$text` is empty or `$rank > 3`
   - Assemble: `[Zespołowego] [prefix] [infix] [text] [w mikście] na rok [year]`
   - Prefix with `"i zdobywa tytuł "`

---

## Task 3 — `DiplomaConfig.php`: UI for title config

**File:** `Diplomas/DiplomaConfig.php`

1. In the POST handler, read and save:
   - `TitlesEnabled` (checkbox → 0 or 1)
   - `TitlePrefix_{evCode}` and `TitleText_{evCode}` per event

2. Update the `pl_diploma_save_event_text()` call to pass the new params.

3. In the main config table, add a row after the place range fields:
   ```
   Tytuły na dyplomach: [ ] Włącz tytuły dla miejsc 1–3
   ```

4. In the event text table:
   - Add two columns: `Prefiks tytułu` and `Tekst tytułu`
   - Pre-fill from `pl_diploma_get_title_defaults($ev['rawCode'])` when `$currentText['titlePrefix']` and `$currentText['titleText']` are both empty
   - Below each row (or as a `<small>` hint), show the constructed rank-1 title using `pl_diploma_build_title(1, …)` with `$isTeam=false`, `$isMixed=false`

---

## Task 4 — `PLDiplomaPdf.php`: render title line

**File:** `Diplomas/PLDiplomaPdf.php`

1. Add `$titleText = ''` as the last parameter of `printDiploma()`.

2. After the `w kategorii {ClassText}` line and before the optional body text block, add:

   ```php
   if (!empty($titleText)) {
       $this->Ln(6);
       $this->SetFont('dejavusans', 'I', 13);
       $this->Cell($contentW, 8, $titleText, 0, 1, 'C');
   }
   ```

   Use italic 13pt, centered — visually distinct from the surrounding lines without overpowering.

---

## Task 5 — `PrnIndividualDipl.php`: pass title to PDF

**File:** `Diplomas/PrnIndividualDipl.php`

1. Load `$config['TitlesEnabled']` after the existing config load.

2. Load event texts via `pl_diploma_get_event_texts()` (already done — now returns title fields too).

3. For each result row, before calling `printDiploma()`:
   ```php
   $titleText = '';
   if ($config['TitlesEnabled']) {
       $evKey = 'I:' . $result['IndEvent'];
       $evData = isset($eventTexts[$evKey]) ? $eventTexts[$evKey] : ['titlePrefix' => '', 'titleText' => ''];
       $year = pl_diploma_extract_year($config['Dates']);
       $titleText = pl_diploma_build_title($result['Rank'], $evData['titlePrefix'], $evData['titleText'], $year, false, false);
   }
   ```

4. Pass `$titleText` as the new last argument to `printDiploma()`.

---

## Task 6 — `PrnTeamDipl.php`: pass title to PDF

**File:** `Diplomas/PrnTeamDipl.php`

Same pattern as Task 5. Key differences:

- `$isTeam = true`
- `$isMixed = ($team['IsMixed'] == 1)`
- Event key uses `T:` or `M:` prefix depending on `IsMixed`

```php
$evType = $team['IsMixed'] ? 'M' : 'T';
$evKey  = $evType . ':' . $team['EventId'];
```

---

## Task 7 — Add `pl_diploma_extract_year()` helper

**File:** `Diplomas/DiplomaSetup.php`

Add a small utility used by the printer files:

```php
function pl_diploma_extract_year($datesString) {
    if (preg_match('/\b(20\d{2})\b/', $datesString, $m)) {
        return intval($m[1]);
    }
    // Fallback to current year
    return intval(date('Y'));
}
```

This can be added in Task 2 alongside the other helpers, or as a follow-up here.

> **Note:** This task can be folded into Task 2 if convenient.

---

## Task 8 — Self-review

Before committing, verify against the Reviewer checklist (`.github/agents/reviewer.prompt.md`):

- [ ] All DB writes use `StrSafe_DB()` / `intval()` — no raw user input in SQL
- [ ] `pl_diploma_ensure_tables()` runs migrations idempotently (`ADD COLUMN IF NOT EXISTS`)
- [ ] `printDiploma()` signature is backward-compatible (`$titleText = ''` default)
- [ ] No files outside `Diplomas/` modified
- [ ] All UI strings in Polish; code comments in English
- [ ] `pl_diploma_get_event_texts()` callers in `DiplomaConfig.php` updated for new return shape
- [ ] Verify the 7 scenarios from design.md `Verification` section are manually testable
