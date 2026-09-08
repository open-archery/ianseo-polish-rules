# Design — TourTypes 16 and 37

## Open gaps (decide before implementing)

| # | Gap | Effect if left open |
| - | --- | --- |
| 1 | The four U12 children's-round distances (§2.3.1.10.11 leaves them to the organiser). | `Setup_16_PL.php` cannot be written; everything else in this change can. |
| 2 | Whether U12 Compound exists in the youth round. §1.2 of the rules research says C excludes U12, so the design assumes U12 is Recurve-only. | Wrong class/division matrix for one class. |
| 3 | Whether the youth round needs eliminations at all, or is qualification-only. Assumed qualification-only, following §2.3.1.8 (no elimination for U15 at Polish Championships). | Finals structure may need adding later. |
| ~~4~~ | ~~Whether any live tournament still uses `Poland-4x70m`.~~ **Answered — see below.** | — |

### Gap 4, answered (checked 2026-09-08 on the dev install)

One tournament uses it: **ToId 129, `KZLZS26`, "Mistrzostwa Krajowego Zrzeszenia LZS", TourType 3, `ToTypeSubRule = 'Poland-4x70m'`**, dated 2026-09-26 — in the future, with **0 entries and 0 scores**. Every other PL tournament is `Poland-Full`.

So removal does affect a real competition, but one that has not started. It can simply be recreated as a TourType 37 tournament before the event, and no score data is at risk. The check must be repeated on the production install, which this dev database does not necessarily mirror — task 0.5 stays, narrowed to that.

### Class and event type gates in `lib.php`

`CreateStandardClasses()` and `InsertStandardEvents()` both gate the youth categories on the TourType number:

```php
$hasU15 = in_array($TourType, array(3, 6));   // CreateStandardClasses
$hasU12 = ($TourType == 6);
if (in_array($TourType, array(3, 6))) { ... } // InsertStandardEvents, U15 bindings
if ($TourType == 6) { ... }                   // U12 bindings
```

Neither new type passes these gates, which breaks both halves of this change if the gates are left alone:

- **TourType 37** would silently lose U15 entirely — no classes, no events, no bindings — even though the double round is supposed to be TourType 3 with doubled sessions. The gate must include 37 wherever it includes 3.
- **TourType 16** would create no youth classes at all, or (if the class gate alone is extended) classes with no event bindings.

Extending the gates is part of this change, not an implementation detail: the class-set filter from `class-presets` is the general form, and the two changes should converge on it rather than adding a third TourType number to each `in_array()`.

## ianseo hook points

Setup scripts are found by `GetSetupFile()` (`Common/Fun_ScriptsOnNewTour.inc.php:43`), which looks for `Modules/Sets/{LocRule}/Setup_{ToType}_{LocRule}.php` before falling back to `Modules/Sets/FITA/Setup_{ToType}.php`. Adding `Setup_16_PL.php` and `Setup_37_PL.php` is therefore enough for ianseo to use them — no core change, no registration beyond `sets.php` listing the type in `$SetType['PL']['types']`.

TourTypes 16 (`Type_GiochiGioventuW`) and 37 (`Type_2x70mRound`) already exist in the `TourTypes` table of every ianseo install; `$TourTypes[$val]` in `sets.php` picks up their labels.

Variables the setup script must set (`$tourDetTypeName`, `$tourDetNumDist`, `$tourDetNumEnds`, `$tourDetMaxDistScore`, `$tourDetCategory`, …) are the same contract `Setup_1/3/6_PL.php` already follow.

## Setup_37_PL.php

Copy of `Setup_3_PL.php` with the sub-rule branch resolved to the doubled path:

- `$tourDetTypeName = 'Type_2x70mRound'`, `$tourDetNumDist = 4`, `$tourDetMaxDistScore = 360`.
- Distances: every `CreateDistanceNew()` call from `Setup_3_PL.php` with each session repeated twice — U15 becomes 40m, 40m, 20m, 20m.
- `CreateDistanceInformation()` with 4 sessions instead of 2.
- Everything else (divisions, classes, target faces, events, eliminations, finals, teams) identical to `Setup_3_PL.php`.

Rather than duplicating ~16KB of `Setup_3_PL.php`, the shared body moves into a helper in `lib.php` taking the session multiplier (1 or 2) as an argument, called by both scripts. This keeps the two formats from drifting apart, which was the original reason the double round was a sub-rule.

## Setup_16_PL.php

- `$tourDetTypeName = 'Type_GiochiGioventuW'`, `$tourDetCategory = 1` (outdoor), `$tourDetNumEnds` per 3-arrow ends, `$tourDetNumDist = 4` — the children's round needs four distance columns; U15 uses two of them and leaves the other two empty.
- Classes: `CreateStandardClasses()` gains a class-set filter so it can emit the U15/U12 subset. This is the same mechanism the `class-presets` change needs, so whichever lands first should build it and the other should reuse it.
- Distances: `CreateDistanceNew($TourId, 16, 'RU15_', …)` etc. per the spec table; U12 gets its four distances once gap #1 is closed.
- Target faces via `CreateTargetFace()`: 122cm/80cm for R U15, 80cm/60cm for C U15, 122cm ×2 + 80cm ×2 for U12.
- No elimination or finals configuration (gap #3).

## Removing Poland-4x70m

- `sets.php`: drop the `$SetType['PL']['rules']['3'][] = 'Poland-4x70m';` line, add 16 and 37 to `$AllowedTypes`.
- `Setup_3_PL.php`: drop `$isDouble` and every branch on it.
- `openspec/specs/tournament-setup/spec.md` §2 Sub-Rules section rewritten at archive time.

## Files

- **New:** `Setup_16_PL.php`, `Setup_37_PL.php`
- **Modified:** `sets.php`, `Setup_3_PL.php`, `lib.php`, `LibTest.php`
- **Menu:** no additions — tournament types appear in ianseo's own creation form.
- **DB:** no new tables or columns.
