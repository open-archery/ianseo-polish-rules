# Design: Diploma Titles

## Overview

Extend the `Diplomas/` module with two configurable fields per event (`TitlePrefix`, `TitleText`), a global enable toggle, and a construction function that assembles the full title string. The PDF renderer receives the pre-built string and prints it below the rank line.

## Title Construction

```
pl_diploma_build_title(rank, prefix, text, year, isTeam, isMixed)
```

Assembly order:

| Part | Condition | Example |
|------|-----------|---------|
| `Zespołowego` | team AND NOT mixed | Zespołowego |
| `prefix` | non-empty | Młodzieżowego |
| place infix | always | Mistrza / Wicemistrza / II Wicemistrza |
| `text` | always (if non-empty) | Polski Juniorów |
| `w mikście` | mixed only | w mikście |
| `na rok YYYY` | always | na rok 2026 |

Returns `"i zdobywa tytuł …"` or `""` if `text` is empty or rank > 3.

Year is extracted from `PlDcDates` (first 4-digit year found via regex), falling back to `$_SESSION['TourWhenFrom']`.

Place infix forms (regulation §1.6, genitive, non-gendered):

| Rank | Infix |
|------|-------|
| 1 | Mistrza |
| 2 | Wicemistrza |
| 3 | II Wicemistrza |

## Default Values Per Category

Defaults returned by `pl_diploma_get_title_defaults($rawEventCode)` when no saved value exists. The function parses division (first char: R/C/B) and class (remainder).

| Event pattern | Default prefix | Default text |
|--------------|---------------|--------------|
| `*/M`, `*/W` | *(empty)* | `Polski Seniorów` |
| `*/U24M`, `*/U24W` | `Młodzieżowego` | `Polski` |
| `*/U21M`, `*/U21W` | *(empty)* | `Polski Juniorów` |
| `R/U18M`, `R/U18W` | *(empty)* | `Ogólnopolskiej Olimpiady Młodzieży` |
| `C,B/U18M`, `C,B/U18W` | *(empty)* | `Polski Juniorów Młodszych` |
| `*/50M`, `*/50W` | *(empty)* | *(empty — no title)* |
| `*/U15M`, `*/U15W` | `Międzywojewódzkiego` | `Młodzików` |
| `*/U12M`, `*/U12W` | *(empty)* | *(empty — no title)* |

Mixed event codes (`RX`, `RU18X`, etc.) follow the same age-class rules. `Zespołowego` and `w mikście` are appended by the construction function, not stored.

## Database Changes

### `PLDiplomaConfig` — one new column

```sql
PlDcTitlesEnabled  TINYINT(1) NOT NULL DEFAULT 0
```

### `PLDiplomaEventText` — two new columns

```sql
PlDeTitlePrefix  VARCHAR(100) NOT NULL DEFAULT ''
PlDeTitleText    VARCHAR(255) NOT NULL DEFAULT ''
```

Both added via `ALTER TABLE … ADD COLUMN IF NOT EXISTS` in `pl_diploma_ensure_tables()`.

## Files to Modify

| File | Change |
|------|--------|
| `Diplomas/DiplomaSetup.php` | Add migrations for new columns; add `pl_diploma_get_title_defaults()` and `pl_diploma_build_title()`; update `pl_diploma_get_config()` / `pl_diploma_save_config()` for `TitlesEnabled`; update `pl_diploma_get_event_texts()` / `pl_diploma_save_event_text()` for prefix+text |
| `Diplomas/DiplomaConfig.php` | Add "Tytuły na dyplomach" checkbox to main config table; add `Prefiks tytułu` + `Tekst tytułu` columns to event table; pre-fill from `pl_diploma_get_title_defaults()` when no saved value |
| `Diplomas/PLDiplomaPdf.php` | Add `$titleText = ''` param to `printDiploma()`; render `i zdobywa tytuł …` line after rank section if non-empty |
| `Diplomas/PrnIndividualDipl.php` | Load title config; call `pl_diploma_build_title()` per result row; pass to `printDiploma()` |
| `Diplomas/PrnTeamDipl.php` | Same as above; pass `isTeam=true` / `isMixed` from result `IsMixed` field |

No new files. No changes outside `Diplomas/`. No `menu.php` changes.

## Config UI Detail

`DiplomaConfig.php` event table gains two columns, displayed right of the existing "Tekst na dyplomie" column:

```
| Kod | Nazwa domyślna | Tekst na dyplomie | Prefiks tytułu | Tekst tytułu |
```

- Both title fields are pre-filled from `pl_diploma_get_title_defaults()` when the DB has no saved value for that event
- A small hint below each row (or as placeholder text) shows the constructed title for rank 1, e.g.:
  `→ "Mistrza Polski Juniorów na rok 2026"`
- The global toggle sits in the main config table: `Tytuły na dyplomach: [ ] Włącz`

## Integration Points

- `printDiploma()` already receives all data needed; `$titleText` is the only new parameter
- `pl_diploma_ensure_tables()` is called on every page load — safe place for migrations
- `PrnIndividualDipl.php` and `PrnTeamDipl.php` already load event texts; title fields come from the same `pl_diploma_get_event_texts()` call (extended to return them)
- Year extraction is a pure utility — no session writes, no side effects

## Verification

1. Enable titles in config; set prefix/text for a Senior Recurve event; generate individual diplomas for places 1–3 → verify each shows correct title line
2. Generate team diplomas → verify "Zespołowego" prefix present
3. Generate mixed team diplomas → verify "w mikście" appended, no "Zespołowego"
4. Disable global toggle → verify no title line on any diploma
5. Leave text empty for a Masters event → verify no title line
6. U18 Recurve → "Ogólnopolskiej Olimpiady Młodzieży"; U18 Compound → "Polski Juniorów Młodszych"
7. Place 4+ with title configured → no title line
