## Context

See `proposal.md` for motivation. This design supersedes the abandoned `class-presets` proposal (branch `feat/class-presets`, PR #44, closed unmerged) — that one scoped only the sub-rule selection mechanism; this folds in taxonomy changes discovered while re-scoping it.

**Prior art already in the codebase, load-bearing for this design:**
- `add-setup-types-16-37` (merged) added `CreateStandardClasses($TourId, $TourType, $KidsOnly = false)` / `InsertStandardEvents(..., $KidsOnly = false)` — a one-off boolean, not the reusable filter the abandoned proposal expected. This design replaces it rather than adding a second, parallel filter.
- The sub-rule `<select>` (`Tournament/index.php:454-471`) is a plain single-value dropdown — confirmed by reading the render code. No multiselect exists in ianseo core. Every combination we want to offer must be its own registered string.
- `get_text($v, 'Install')` (`Tournament/index.php:460/467`) renders each dropdown option's label. Traced into `Common/Globals.inc.php:128-222`: it looks up `$text` in a `static $_LANG` array populated only by `include()`ing `Common/Languages/<lang>/Install.php` — a real function-local `static`, not a true PHP global (no `global $_LANG;` anywhere in its own signature), so no injection point exists from our module. A cache miss renders `<b>[[$text]@[$lingua]@[Install]]</b>` regardless of what `$text` says. This almost certainly already affects the existing `Poland-Full` value today (no `Poland-*` key exists in either language file) — not verified live, flagged as a task-0 check. `Common/DebugOverrides.php`, the one update-exempt file that sounds like an override hook, does not exist in this checkout and has zero references anywhere in core — not a live extension point.
- Core does ship translated generic sub-rule keys reusable by any locale module (confirmed present in both `en/Install.php` and `pl/Install.php`, or English-only where noted): `SetAllClass` ("Wszystkie Kategorie"/"Every Classes"), `SetSeniorClass` (English-only: "Only senior classes"), `SetYouthClass` ("Kategorie młodzieżowe"/"Youth Categories"), `SetMasterClass` (English-only: "Only master classes"). ianseo's own official PL module (reference copy: `C:\Users\artik\Code\official-ianseo-pl-rules`) already reuses this exact vocabulary (`SetOneClass`/`SetAllClass`/`SetYouthClass`/`SetKidClass`) — precedent, not a hack.

## Goals / Non-Goals

**Goals:**
- One preset resolver in `lib.php`, consumed by every `Setup_*_PL.php`, replacing `$KidsOnly`.
- Ship the full concrete sub-rule list per TourType (not "gap: list TBD" — the list is finalized, see below).
- Land the Masters and PU12 taxonomy changes in the same change as the mechanism, since they're new inputs to the same functions.

**Non-Goals:**
- Fixing the `get_text()` translation gap upstream, or building a client-side relabeling workaround (no injection point exists; see Context).
- A preset-editing UI.
- Masters or PU12 on TourType 1 or 16.
- Retrofitting existing tournaments' `50M`/`50W` entries.

## Decisions

### Preset shape

Unchanged from the abandoned proposal's design — still the right shape given the single-select constraint:

```
'<sub-rule value>' => [
    'divisions' => ['R', 'C'],   // omit key = all divisions
    'classes'   => ['U21M', 'U21W', 'U18M', 'U18W'],  // omit key = all classes
]
```

`CreateStandardDivisions()`, `CreateStandardClasses()` and `InsertStandardEvents()` each take the resolved preset and skip anything outside it; the existing division-eligibility matrix still applies on top (a preset can only remove categories, never add one PZŁucz rules disallow). The unfiltered default resolves to a preset with neither key set.

`$KidsOnly` is retired. TourType 16's `pl_setup_kids_round()` keeps its own distinct distance/target body (it always has — its round shape, not just its class list, differs from the 70m-family), but it now resolves a fixed, non-selectable preset (`{classes: ['U12M','U12W'], divisions: ['R']}`) through the same `CreateStandardClasses($TourId, $TourType, $preset)` signature as every other TourType, instead of a bespoke boolean parameter.

### Sub-rule registration, per TourType (final list)

| TourType | Sub-rule value | Selects |
|---|---|---|
| 1 | `SetAllClass` | everything (R+C, all classes — no B) |
| 1 | `SetSeniorClass` | R+C, M/W |
| 1 | `Poland-RU24` | R only, U24 |
| 1 | `Poland-RU21` | R only, U21 |
| 1 | `Poland-RU18` | R only, U18 |
| 3 | `SetAllClass` | everything (R/C/B, all classes incl. U12/PU12) |
| 3 | `SetSeniorClass` | R/C/B, M/W |
| 3 | `Poland-RU24U21U18` | R only, U24+U21+U18 |
| 3 | `SetYouthClass` | R only, U24+U21 |
| 3 | `Poland-RU18Only` | R only, U18 |
| 3 | `Poland-RU15` | R only, U15 |
| 3 | `SetMasterClass` | Masters (R/C/B, 5 bands) — TourType 3 exclusive |
| 37 | `SetAllClass` | everything (R/C/B, all classes, no U12/PU12/Masters) |
| 37 | `SetSeniorClass` | R/C/B, M/W |
| 37 | `Poland-RU24U21U18` | R only, U24+U21+U18 |
| 37 | `SetYouthClass` | R only, U24+U21 |
| 37 | `Poland-RU18Only` | R only, U18 |
| 37 | `Poland-RU15` | R only, U15 |
| 6 | `SetAllClass` | everything (incl. PU12) |
| 6 | `SetSeniorClass` | R/C/B, M/W |
| 6 | `SetYouthClass` | R only, U24+U21 |
| 6 | `Poland-RU18Only` | R only, U18 |
| 6 | `Poland-RU15` | R only, U15 |
| 16 | `SetAllClass` | U12 only, fixed, not organiser-selectable (single entry) |

`Poland-RU18Only` (not `Poland-RU18`) avoids a value collision between TourType 1's per-class U24/U21/U18 trio and TourType 3/37/6's same-shaped-but-different-composition U18-only preset — both are "R only, U18", but registered under different `$SetType['PL']['rules'][ToType]` arrays, so a shared literal string would still resolve correctly per-TourType; the distinct names are for readability in `lib.php`'s preset table, not a functional requirement.

### Masters class codes

Not finalized by the domain owner — proposed here for design purposes, changeable without touching the mechanism: `40M`/`40W`, `50M`/`50W`, `60M`/`60W`, `70M`/`70W`, `80M`/`80W` (decade-start + gender, matching the existing `U15`/`U18`/`U21`/`U24` numeric-suffix convention). `ClValidClass` chains self-only per band (no upward eligibility into Senior — Masters is a parallel track, same pattern U12/PU12 already use).

### Blast radius beyond setup scripts

Removing `50M`/`50W` and adding the Masters band codes touches:
- `lib.php`: `$PL_CLASS_NAMES`, `$PL_MIXED_CLASS_NAMES` (mixed-team age key `'50'` needs replacing — Masters has no mixed-team requirement supplied, so Masters mixed teams are out of scope; the `'50'` key is simply removed, not replaced).
- `PointsRanking/CupCalc.php`: two ranking-label maps keyed on `'50'` (lines ~59, ~92 as of this writing).
- `Diplomas/DiplomaSetup.php`: the `case '50M': case '50W':` diploma-title branch (~line 266).
- All four files' `*Test.php` companions.

### Known follow-up, not blocking this change

The U15 `ClValidClass` chain bug (memory: U15 archers reportedly only get U15/U18 as assignable classes in the participant-entry UI, not the full U21/M chain the DB row grants) is unrelated to this change's mechanism — inherited pre-existing behavior, confirmed present on TourType 3 today. Investigate separately; do not assume this change's preset filter is the cause or the fix.

## Risks / Trade-offs

- **Sub-rule labels render as raw placeholders for ~20 of 24 entries.** [Risk] Organisers see `[[Poland-RU24U21U18]@[pl]@[Install]]` in the dropdown for anything without matching core vocabulary → Mitigation: accepted per proposal's Non-Goals; the 4 reused-vocabulary entries (`SetAllClass`, `SetSeniorClass`, `SetYouthClass`, `SetMasterClass`) at least cover the most commonly used presets (Full, Senior, broadest Youth, Masters) on every TourType that offers them.
- **1440 Round is a breaking change for existing tooling/expectations** (B removed, C distances change). [Risk] Anything downstream assuming TourType 1 always has a Barebow division or flat-50m Compound breaks → Mitigation: flagged **BREAKING** in proposal and spec; no known downstream consumer found in this module (`PointsRanking`/`Diplomas` don't special-case TourType 1's division set).
- **Masters elimination format is assumed, not independently re-verified per band.** [Risk] The domain owner confirmed "elimination for every class" but didn't re-confirm bracket size (top-104 like other adult classes, or something narrower given Masters fields are typically smaller) → Mitigation: task 0 re-confirms bracket size before implementing; default to the same top-104/top-24 cuts as other adult R/C/B classes if not corrected.

## Migration Plan

No DB migration — setup-time-only change, no schema impact. Deploy is a normal merge; existing tournaments (including any created earlier under `Poland-Full` TourType 1 with a Barebow division) are unaffected retroactively. Rollback is a normal revert.
