## Context

The PL module has two independent features: the combined ranking (merges two tournaments, computes a ranked table per div+class) and the diploma module (generates PDF diplomas from single-tournament results). The combined ranking currently only produces a printable table PDF. Organizers need to give diplomas to athletes placed in the combined ranking, using the same PDF style as individual diplomas.

The diploma module already provides everything needed: `PLDiplomaPdf::printDiploma()` (generic renderer), `pl_diploma_get_config()` (config reader), and `PLDiplomaConfig` (the per-tournament config table). The combined ranking already provides `pl_combined_ranking_compute()` which returns fully ranked rows per section.

## Goals / Non-Goals

**Goals:**
- Add a "Generuj dyplomy" button to `CombinedRanking.php` alongside the existing ranking PDF button
- Accept a date input on the same form (separate from `PLDiplomaConfig.Dates`)
- Generate one diploma page per athlete per div+class section, filtered to ranks within `PlaceFrom`..`PlaceTo` from diploma config
- Reuse `PLDiplomaPdf`, `pl_diploma_get_config()`, and the existing diploma layout without modification

**Non-Goals:**
- No new database tables or config screens
- No title/championship phrase ("Mistrza Polski...") on combined ranking diplomas
- No per-divClass text overrides
- No changes to existing individual/team diploma flow

## Decisions

### Decision: New printer file in `CombinedRanking/` rather than `Diplomas/`

The combined ranking printer lives next to the rest of the combined ranking code (`Fun_CombinedRanking.php`, `PrnCombinedRanking.php`). Adding it to `Diplomas/` would couple the combined ranking tightly to the diploma module. Cross-module `require_once` paths are acceptable and already used (e.g. `PrnCombinedRanking.php` includes `IanseoPdf.php` from `Common/`).

Alternatives considered:
- Add to `Diplomas/` — rejected: wrong conceptual home, diploma module knows nothing about combined ranking
- Inline in `CombinedRanking.php` — rejected: mixes PDF generation with UI page logic

### Decision: Date as a separate POST field, not from `PLDiplomaConfig`

The combined ranking spans two tournaments held on different dates. The diploma date should reflect the combined event context, not the active session tournament's configured date. A plain text input on the form gives the organizer full control with zero schema changes.

### Decision: Reuse `PLDiplomaConfig` for all other fields

Competition name, location, head judge, organizer, body text, and place range all come from the session tournament's diploma config. This avoids a new config table and keeps consistency — the organizer configures the diploma once in `DiplomaConfig.php` and it applies everywhere.

### Decision: No section-level text overrides

Individual diplomas use `PLDiplomaEventText` to allow per-event custom text. Combined ranking uses the section label from the ranking computation (e.g. "Recurve Mężczyźni") as the category text directly. Adding overrides would require a new config table and UI for marginal benefit.

## Files to Create / Modify

| File | Action |
|------|--------|
| `CombinedRanking/PrnCombinedRankingDipl.php` | **Create** — diploma printer |
| `CombinedRanking/CombinedRanking.php` | **Modify** — add date input + diploma button |

### `PrnCombinedRankingDipl.php` — structure

```
require config.php
CheckTourSession(true)
require Fun_CombinedRanking.php
require Diplomas/DiplomaSetup.php      ← pl_diploma_get_config()
require Diplomas/PLDiplomaPdf.php      ← PLDiplomaPdf class

read POST: tour1, tour2, diplDate
validate: tour1 required; diplDate required

pl_diploma_ensure_tables()
$config = pl_diploma_get_config($_SESSION['TourId'])

$data1 = pl_combined_ranking_load(tour1)
$data2 = tour2 ? pl_combined_ranking_load(tour2) : []
$merged = pl_combined_ranking_merge($data1, $data2)
$labels = pl_combined_ranking_get_div_labels(tour1)
$sections = pl_combined_ranking_compute($merged, $labels)

$pdf = PLDiplomaPdf::createInstance('Dyplomy rankingu łączonego')

foreach sections as section:
  foreach section.rows as row:
    if row.rank < PlaceFrom or row.rank > PlaceTo: skip
    $pdf->printDiploma(
      competitionName  ← $config['CompetitionName']
      dates            ← $diplDate  (POST input)
      location         ← $config['Location']
      classText        ← $section['label']
      rank             ← $row['rank']
      athleteName      ← $row['name']
      clubName         ← $row['club']
      teamMembers      ← []
      bodyText         ← $config['BodyText']
      headJudge        ← $config['HeadJudge']
      organizer        ← $config['Organizer']
      titleText        ← ''
    )

$pdf->Output('dyplomy_ranking_laczony.pdf', 'I')
```

### `CombinedRanking.php` — UI changes

Add below the existing "Generuj PDF" button:

```html
<hr>
<form method="POST" action="PrnCombinedRankingDipl.php">
  <input type="hidden" name="tour1" value="<?= selected tour1 ?>">
  <input type="hidden" name="tour2" value="<?= selected tour2 ?>">
  <label>Data na dyplomie: <input type="text" name="diplDate"></label>
  <input type="submit" value="Generuj dyplomy">
</form>
```

Note: tour1/tour2 are passed as hidden fields pre-filled from the current form state. Alternatively, the diploma form can duplicate the dropdowns — but hidden fields with JS sync are lighter. A simpler implementation may duplicate the dropdowns to avoid JS.

## Risks / Trade-offs

- **Missing diploma config** → `pl_diploma_get_config()` returns defaults (empty strings). The diploma will render but with blank competition name and signatories. Mitigation: show a warning if config is empty, consistent with how `Diplomas.php` handles it.
- **Empty combined ranking** → if no athletes qualify within the place range, the PDF will have no pages. Mitigation: `die()` with a Polish error message, same pattern as `PrnIndividualDipl.php`.
- **Cross-directory `require_once`** — `PrnCombinedRankingDipl.php` needs to reach `Diplomas/DiplomaSetup.php` and `Diplomas/PLDiplomaPdf.php`. Path: `dirname(__FILE__) . '/../Diplomas/DiplomaSetup.php'`. This is an established pattern in the codebase.

## Open Questions

None — all decisions resolved during exploration.
