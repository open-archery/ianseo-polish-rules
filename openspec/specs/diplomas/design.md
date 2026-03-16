# Plan: PL Diplomas Module Rewrite

**TL;DR**: Replace the Swiss Archery Federation diplomas clone with a Polish-language, text-only diploma generator supporting individual, team, and mixed events from both qualification and finals results. Configuration is stored in a custom DB table auto-created by the module. The admin selects events from a unified list, configures a place range (from-to), customizes all diploma texts via a settings page, and can also generate a single custom diploma by picking an athlete. All changes stay within `Modules/Sets/PL/`.

## Steps

### 1. Delete Swiss-specific files

Remove `SaaDiplomaPdf.php` (Swiss-branded PDF class), `Minimas.php` (score thresholds), and all Swiss logo images (`swissarchery-*.png`, `swissolympic-*.jpg`, `j-s-logo.png`, `worldarchery-logo.jpg`) from `Modules/Sets/PL/Diplomas/`.

### 2. Create `DiplomaSetup.php`

Auto-install module that checks for and creates two DB tables on first use:

- **`PLDiplomaConfig`** — per-tournament settings:
  - `PlDcTournament` (PK, FK→Tournament)
  - `PlDcCompetitionName` (varchar 255)
  - `PlDcDates` (varchar 100)
  - `PlDcLocation` (varchar 255)
  - `PlDcPlaceFrom` (int, default 1)
  - `PlDcPlaceTo` (int, default 3)
  - `PlDcBodyText` (text — optional extra text on diploma body)
  - `PlDcHeadJudge` (varchar 255 — name of head of judges)
  - `PlDcOrganizer` (varchar 255 — name of the organizer)

- **`PLDiplomaEventText`** — per-event text overrides:
  - `PlDeTournament` + `PlDeEventCode` (composite PK)
  - `PlDeCustomText` (varchar 255 — custom event/class label to show on diploma instead of the default `EvEventName`)

- Uses `SHOW TABLES LIKE 'PLDiplomaConfig'` pattern for auto-create check.
- Provides CRUD functions:
  - `pl_diploma_ensure_tables()`
  - `pl_diploma_get_config($tourId)`
  - `pl_diploma_save_config($tourId, $data)`
  - `pl_diploma_get_event_texts($tourId)`
  - `pl_diploma_save_event_text($tourId, $eventCode, $text)`

### 3. Create `DiplomaConfig.php`

Settings UI page (included via `Common/Templates/head.php` / `tail.php`). Form with fields for:

- Competition name (pre-filled from `$_SESSION['TourName']`)
- Dates (pre-filled from `$_SESSION['TourWhenFrom']` / `TourWhenTo`)
- Location (from `$_SESSION['TourWhere']`)
- Place range (from/to)
- Body text
- Head of Judge
- Organizer

Below the tournament-level config, a table listing all events (individual + team + mixed) with editable custom text fields. Saves via POST to itself.

Polish labels throughout (e.g., "Nazwa zawodów", "Data", "Miejsce", "Dyplomy od miejsca", "Dyplomy do miejsca", "Tekst dyplomu", "Sędzia główny", "Organizator").

### 4. Rewrite `Fun_Diploma.php`

Replace Swiss-specific data functions with generic ones:

- `pl_diploma_get_events($type=null)` — Returns events for the current tournament.
  - `$type`: `'individual'` (EvTeamEvent=0), `'team'` (EvTeamEvent=1, EvMixedTeam=0), `'mixed'` (EvTeamEvent=1, EvMixedTeam=1), or `null` for all.
  - Returns array of `[EvCode => EvEventName]`.

- `pl_diploma_get_ind_qual_results($events, $placeFrom, $placeTo)` — Fetches individual qualification results ranked by `QuClRank` within the specified place range. Returns array of `[EnFullName, CoName, IndEvent, EvEventName, QuScore, QuClRank]`.

- `pl_diploma_get_ind_final_results($events, $placeFrom, $placeTo)` — Fetches individual finals results using `IF(EvFinalFirstPhase=0, IndRank, ABS(IndRankFinal))` as rank. Only returns results for events where `EvFinalFirstPhase > 0` (finals exist). Same return structure plus `FinalRank`.

- `pl_diploma_get_team_qual_results($events, $placeFrom, $placeTo)` — Fetches team qualification results ranked by `TeRank`. Returns grouped structure: `[EventId, EventName, Rank, Club, Score, IsMixed, Athletes[EnFullName, QuScore]]`.

- `pl_diploma_get_team_final_results($events, $placeFrom, $placeTo)` — Fetches team finals results using `IF(EvFinalFirstPhase=0, TeRank, TeRankFinal)`. Uses `UNION ALL` pattern from `Obj_Rank_FinalTeam`: teams in finals (via `TeamFinComponent`) + teams not in finals (via `TeamComponent`). Returns same grouped structure.

- `pl_diploma_get_all_athletes()` — Returns all athletes in the tournament for the custom diploma picker. Joins `Entries`, `Countries`, `Individuals`, `Events`. Returns `[EnId, EnFullName, CoName, IndEvent, EvEventName]`.

- Remove all references to `$SAADiplMinima` and the minimas concept.

### 5. Create `PLDiplomaPdf.php`

Minimal TCPDF subclass replacing `SaaDiplomaPdf`. Portrait A4, no graphics.

- `Header()` and `Footer()` are empty (text-only).
- Provides a helper method `printDiploma($competitionName, $dates, $location, $classText, $rank, $athleteName, $clubName, $teamMembers=[], $bodyText='', $headJudge='', $organizer='')` that renders a centered, clean text diploma layout:
  - Top: competition name (bold, 22pt), dates (14pt), location (14pt)
  - Center: "DYPLOM" (bold, 36pt)
  - Diploma body following the template: `"{NameSurname} {Club} za zajęcie {Place} w {TourName} w kategorii {ClassText}"`, rendered as:
    - Athlete name (bold, 20pt, centered)
    - Club name below in smaller font (14pt, centered)
    - "za zajęcie" (14pt)
    - Rank as Polish ordinal + "miejsca" (bold, 24pt)
    - "w {CompetitionName}" (14pt)
    - "w kategorii {ClassText}" (18pt) — where ClassText is the per-event custom text (or default EvEventName)
    - For teams: club name bold (20pt) + individual athlete names listed below (14pt)
  - Body text if configured — additional free-form text (12pt)
  - Bottom: two signature lines side by side — "Sędzia główny" (head of judges) on the left, "Organizator" (organizer) on the right, with names below each label

### 6. Rewrite `PrnIndividualDipl.php`

Accepts GET parameters: `Button` (action), `Event[]` (selected events), `Source` ('qualification' or 'finals').

- Loads config from `DiplomaSetup.php`
- Fetches results via `pl_diploma_get_ind_qual_results()` or `pl_diploma_get_ind_final_results()` based on `Source`
- Uses `PLDiplomaPdf` to generate one page per athlete
- Applies per-event custom text overrides from `PLDiplomaEventText`

### 7. Rewrite `PrnTeamDipl.php`

Same structure as individual but for team/mixed events.

- Uses `pl_diploma_get_team_qual_results()` or `pl_diploma_get_team_final_results()`
- For each team diploma: shows club name, lists all athlete names from the `Athletes` array, and the rank

### 8. Create `PrnCustomDipl.php`

Generates a single diploma from POST/GET parameters: `athleteId` (or manual name), `eventCode`, `rank`, `customText`.

- If `athleteId` is provided, loads the athlete name and club from DB
- Uses `PLDiplomaPdf` to render
- The custom text replaces the default body text

### 9. Create `AjaxGetAthletes.php`

AJAX endpoint for the custom diploma athlete picker.

- Returns JSON array of athletes matching a search term (GET `q` parameter)
- Queries `Entries` joined with `Countries`, filtered by `EnTournament` and `LIKE` on name
- Used by a JavaScript autocomplete on the Diplomas page

### 10. Rewrite `Diplomas.php`

Complete UI rewrite. Structure:

- Calls `pl_diploma_ensure_tables()` from `DiplomaSetup.php` on load (auto-install)
- Loads current config from DB

**Top section**: Link to configuration page (`DiplomaConfig.php`). If not configured, show a warning.

**Main section**: Unified event list as a multi-select with type indicators (`[I]` individual, `[T]` team, `[M]` mixed). Two source radio buttons: "Kwalifikacje" / "Finały". Place range display (from config, read-only on this page).

**Action buttons**: "Generuj dyplomy" (generates diplomas for selected events and source).

**Custom diploma section** at bottom: Athlete picker (autocomplete field + hidden ID), event selector, rank input, custom text textarea, "Generuj dyplom" button. Posts to `PrnCustomDipl.php`.

All labels in Polish.

### 11. Update `menu.php`

Keep a single menu entry for "Dyplomy" pointing to `Diplomas.php`. The configuration page is accessible via a link on the Diplomas page itself (simpler, less menu clutter).

```php
$ret['PRNT'][] = get_text('PLDiplomas','Install') . '|' . $CFG->ROOT_DIR . 'Modules/Sets/PL/Diplomas/Diplomas.php';
```

## Verification

- Open a tournament with `TourLocRule='PL'`, verify "Dyplomy" appears under Printouts menu
- Navigate to Diplomas page, verify auto-table creation (check DB for `PLDiplomaConfig` and `PLDiplomaEventText` tables)
- Configure diploma settings (competition name, dates, place range 4-8), save, verify persistence
- Select individual events + "Kwalifikacje" source, generate → verify PDF with correct athletes in rank range, Polish text, no graphics
- Select individual events + "Finały" source on an event with finals data → verify final ranks are used
- Select team events, generate → verify club name + athlete list per diploma
- Select mixed events, generate → verify mixed team diplomas
- Generate a custom diploma by picking an athlete → verify single-page PDF with custom text
- Test edge cases: no finals data → "Finały" button should produce empty result or warning; no events selected → validation message

## Decisions

- No minimas concept: diplomas generated for all athletes in the configured from-to place range
- DB storage via custom `PLDiplomaConfig` / `PLDiplomaEventText` tables (auto-created, no install script changes)
- Strict separation: admin explicitly chooses qualification or finals as data source (no auto-fallback)
- Text-only PDF: Portrait A4, no logos or images, clean centered typography
- Unified event list with type indicators rather than separate sections
- Single menu entry; config page linked from within the Diplomas page
- Custom diploma uses athlete picker (DB lookup) with custom text override
- All UI and diploma content in Polish
