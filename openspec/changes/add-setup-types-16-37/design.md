# Design — TourTypes 16 and 37

## Open gaps (decide before implementing)

| # | Gap | Effect if left open |
| - | --- | --- |
| ~~1~~ | ~~The four U12 children's-round distances.~~ **Answered — see below.** | — |
| ~~2~~ | ~~Whether U12 Compound exists in the youth round.~~ **Answered — see below.** | — |
| ~~3~~ | ~~Whether the youth round needs eliminations at all.~~ **Answered — see below.** | — |
| ~~4~~ | ~~Whether any live tournament still uses `Poland-4x70m`.~~ **Answered — see below.** | — |

### Gap 2, answered

Checked every provision in `regulamin-lucznictwa.md` that mentions U12 — outdoor target-face distances (§2.1.2.3.1-2), the children's round definition (§2.3.1.10.11), the indoor round (§2.3.2.7.4). Every single one qualifies U12 with *"w konkurencji łuków klasycznych"* (Recurve). None pairs U12 with *"łuków bloczkowych"* (Compound) — not once, anywhere in the document. U12 is Recurve-only, confirmed rather than assumed. Matches `lib.php`'s existing TourType-6 precedent (`CreateClass(..., 'U12M', ..., 'R')` — Recurve division only already).

### Gap 3, answered

Checked the entire elimination chapter both outdoor (§2.3.1.1-9) and indoor (§2.3.2.1-6) — U12 is never mentioned. No distance, no target face, no format defined for a U12 elimination round anywhere. There's nothing to configure even if it were wanted — qualification-only isn't a design choice for U12, it's the only thing the regulation supports.

(U15's elimination rules are more nuanced — §2.3.1.8 exempts them only *"na mistrzostwach Polski"*, and §2.3.1.5/§2.3.2.5 actually define a format for elsewhere — but that's moot for this file now that TourType 16 is U12-only; see "Scope change" below. `Setup_3_PL.php`/`Setup_37_PL.php` already ship `EvFinalFirstPhase=0` for U15 unconditionally, unaffected by this change.)

### Gap 1, answered

§2.3.1.10.11 itself only says "4 different distances, longest to shortest, two longer on 122cm, two shorter on 80cm" — it doesn't name meters. But `regulamin-lucznictwa.md` §2.1.2.3 (target-face-by-distance table, not the round-definition section) pins the actual numbers:

- §2.1.2.3.1 (122cm face): *"na odległości 25, 20 metrów dla kategorii U12M, U12W w konkurencji łuków klasycznych"* — 25m and 20m, Recurve.
- §2.1.2.3.2 (80cm face): *"Na odległości 15, 10 metrów kategorii U12 w konkurencji łuków klasycznych"* — 15m and 10m, Recurve.

That's **25m, 20m, 15m, 10m**, longest to shortest, 122cm at 25m/20m and 80cm at 15m/10m — matching §2.3.1.10.11's shape exactly. Both citations say "łuków klasycznych" (Recurve) only; §2.1.2.3 names no Compound distance for U12 anywhere, which corroborates gap 2's Recurve-only assumption without fully closing it (still worth an explicit confirmation, since absence of a citation isn't the same as an explicit exclusion).

`pzlucz-rules.md`'s existing target-face table (its §1.2/§4 area) only carried the indoor U12 entry (15m/80cm) and missed this outdoor cross-reference — worth back-filling that research doc separately so this doesn't get re-lost.

### Gap 4, answered (checked 2026-09-08, re-verified 2026-09-14 directly against the dev DB)

One tournament uses it: **ToId 129, "Mistrzostwa Krajowego Zrzeszenia LZS", TourType 3, `ToTypeSubRule = 'Poland-4x70m'`**, `ToWhenFrom = 2026-09-26` — in the future, with **`(select count(*) from Entries where EnTournament=129) = 0`**, so 0 scores follows directly. Re-run against `ianseo` on the dev MySQL container on 2026-09-14: same single row, unchanged. Every other PL tournament is `Poland-Full`.

So removal does affect a real competition, but one that has not started. It can simply be recreated as a TourType 37 tournament before the event, and no score data is at risk. **Waived by the competition owner (2026-09-14):** the production check (task 0.6) is skipped — removal proceeds regardless of what's live in production; any affected tournament there is handled by hand, outside this change.

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
- **TourType 16** doesn't need either gate touched at all. It's U12-only (see "Scope change" below), so it needs to *suppress* U15 and the base ten classes entirely and create *only* U12 — which is a different problem in kind, not a gate to extend.

Extending the gates for 37 is a one-line `in_array()` addition (task 2.3). TourType 16's "only U12" need is handled separately, below.

A `class-presets` change has been proposed on a separate branch that would eventually generalize class-set selection. It is not landed, not merged, and this change does not wait on it. `CreateStandardClasses()`/`InsertStandardEvents()` get a minimal, local `$KidsOnly` flag now; if `class-presets` lands later and wants to generalize both call sites onto a shared mechanism, that is its refactor to do against code that already exists and already has tests pinning the current behavior.

### `$KidsOnly` — concrete signature

```php
function CreateStandardClasses($TourId, $TourType, $KidsOnly = false) { ... }
function InsertStandardEvents($TourId, $TourType, $KidsOnly = false) { ... }
```

Default `false` preserves today's behavior byte-for-byte for TourTypes 1/3/6/37 — `LibTest.php`'s existing count assertions (10/12/14 `CreateClass` calls) stay valid unchanged.

`CreateStandardClasses`, `$KidsOnly = true`: skip the ten base `CreateClass()` calls and the U15 block entirely; create only U12M and U12W (indices 1-2), ignoring `$TourType`-based gating.

`InsertStandardEvents`, `$KidsOnly = true`: build the class-code arrays as
```php
$rClasses = array('U12M', 'U12W');
$cClasses = array();
$bClasses = array();
$rMixedAges = array();
$cMixedAges = array();
$bMixedAges = array();
```
instead of the normal 10-class base, then fall through to the *same* Individual/Team/Mixed loops already in the function — no loop logic is duplicated, only the seed arrays change. No mixed-team entry, matching the fact that U12 mixed teams don't exist anywhere in the module today (TourType 6 doesn't bind them either).

## ianseo hook points

Setup scripts are found by `GetSetupFile()` (`Common/Fun_ScriptsOnNewTour.inc.php:43`), which looks for `Modules/Sets/{LocRule}/Setup_{ToType}_{LocRule}.php` before falling back to `Modules/Sets/FITA/Setup_{ToType}.php`. Adding `Setup_16_PL.php` and `Setup_37_PL.php` is therefore enough for ianseo to use them — no core change, no registration beyond `sets.php` listing the type in `$SetType['PL']['types']`.

TourTypes 16 (`Type_GiochiGioventuW`) and 37 (`Type_2x70mRound`) already exist in the `TourTypes` table of every ianseo install; `$TourTypes[$val]` in `sets.php` picks up their labels.

Variables the setup script must set (`$tourDetTypeName`, `$tourDetNumDist`, `$tourDetNumEnds`, `$tourDetMaxDistScore`, `$tourDetCategory`, …) are the same contract `Setup_1/3/6_PL.php` already follow.

## Setup_37_PL.php

Copy of `Setup_3_PL.php` with the sub-rule branch resolved to the doubled path:

- `$tourDetTypeName = 'Type_2x70mRound'`, `$tourDetNumDist = 4`, `$tourDetMaxDistScore = 360`, **`$tourDetDouble = '1'`** (not `'0'`).

  `ToDouble` isn't cosmetic: `Common/Rank/Obj_Rank_*.php` read it into ranking metadata, and `Common/pdf/chunks/QualIndividual.inc.php:19-21` branches on `numDist>=4 && ToDouble` to decide whether the qualification results PDF pairs the 4 distance columns into two round-totals or prints them flat. FITA's reference `Setup_37.php` sets it to `1` for exactly this reason. Leaving it `0` (as `Setup_3_PL.php` does today, even under the current `Poland-4x70m` `$isDouble` path — a pre-existing latent bug, not introduced by this change) prints a double round as 4 flat columns instead of two paired totals.
- Distances: every `CreateDistanceNew()` call from `Setup_3_PL.php` with each session repeated twice — U15 becomes 40m, 40m, 20m, 20m.
- `CreateDistanceInformation()` with 4 sessions instead of 2.
- Everything else (divisions, classes, target faces, events, eliminations, finals, teams) identical to `Setup_3_PL.php`.

Rather than duplicating ~16KB of `Setup_3_PL.php`, the shared body moves into a helper in `lib.php` taking the session multiplier (1 or 2) as an argument, called by both scripts. This keeps the two formats from drifting apart, which was the original reason the double round was a sub-rule.

### Scope trap: this body is not a drop-in function body

`Setup_3_PL.php` currently relies on two things that only work because it's a *required script*, not a function:

- `$TourId` is never passed in — it's inherited from `GetSetupFile()`'s own local scope via `require_once` (see the comment at `Setup_3_PL.php:24-25`). A PHP function does **not** inherit the caller's scope this way; `$TourId` must become an explicit parameter.
- `$PL_CLASS_NAMES` / `$PL_MIXED_CLASS_NAMES` are read as same-scope globals — they're assigned at `lib.php`'s top level (`lib.php:16,34`), which works when both files are `require`d into the same script scope, but a function body sees neither unless it declares `global $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES;` at the top.

Concretely, the helper's signature is `pl_setup_70m_family($TourId, $TourType, $Multiplier)`, and its first line must be the `global` declaration above. `$DistanceInfoArray` should be computed inside the helper from `$Multiplier` (base `array(array(6,6), array(6,6))`, repeated `$Multiplier` times) rather than passed in, so the calling script doesn't need to build it.

`pl_double_legs()` (`Setup_3_PL.php:56-77`) moves into `lib.php` as its own top-level function alongside the helper — not nested inside it. If a copy is left behind in `Setup_3_PL.php`, it's a redeclaration fatal the moment both files load in the same request.

What stays in each thin `Setup_{3,37}_PL.php` wrapper: `$TourType`, the `$tourDetXxx` assignments (differ in `NumDist`/`MaxDistScore`/`TypeName` between the two), the `require_once` lines, the call to `pl_setup_70m_family(...)`, and the final `$tourDetails` / `UpdateTourDetails()` call. Everything between "Divisions & Classes" and "Tour Update" in today's `Setup_3_PL.php` (roughly lines 79-364) is what moves.

## Scope change: TourType 16 is U12-only, not "U15+U12 youth round"

The original idea put both U15 (2 real distance legs, §2.3.1.10.5) and U12 (4 legs, §2.3.1.10.11) in one TourType 16 tournament with `$tourDetNumDist=4` — U15's `Td3`/`Td4` would sit unset while U12's are populated. No setup in this module has ever mixed leg-counts like that: Type 1 fills all 4 slots for every class, Types 3/6 fill both slots for every class.

**Spike run, 2026-09-14 — fails.** Built the exact scenario on a throwaway TourType 16 tournament (dev install, IT ruleset — PL doesn't register 16 yet): one class at 2 legs (`Td1`/`Td2` only), one at 4 (`Td1`-`Td4`), `ToNumDist=4`, one athlete per class, same session. Posted `Qualification/index.php` directly for each of the 4 distance selections (no auth on this install).

Result: **both athletes appear under all four distance selections**, including the 2-leg athlete under Distance 3 and Distance 4 — an editable score box for `QuD3Score`/`QuD4Score` renders for a class that has no such leg. No PHP error or warning. Read why: `Qualification/index.php:174-186` selects by `QuSession = {x_Session}` and a target-number range only; it never joins or filters against `TournamentDistances`/`TdClasses` to check whether the selected distance number is meaningful for that athlete's class. It lists everyone assigned to the session and shows whatever `QuD{n}Score` currently holds (0 if unset), unconditionally editable. And `QuHits`'s recompute (`Qualification/index.php:100`) sums `QuD1Hits` through `QuD8Hits` blindly, so a stray entry in a non-existent leg silently pollutes that athlete's total. No per-class leg-count awareness exists anywhere in this screen to build on from within `Modules/Sets/PL/` — the fix would be core UI code.

Independent confirmation: the official ianseo-distributed PL module (`official-ianseo-pl-rules-copy` memory) solves this exact "youth-only tournament" need via generic sub-rules (`SetYouthClass`/`SetKidClass`) layered on TourTypes 1/3/6/37 — and in every one of its own combined-class branches, the two youth classes always get the *same* leg count as each other. It never mixes leg counts within a tournament either. (Its leftover `Setup_16_PL.php`, `$tourDetTypeName='U15/U12'`, isn't even reachable — `sets.php`'s `$AllowedTypes` doesn't include 16 — further evidence a dedicated TourType 16 for this was tried and abandoned upstream.)

**Decision: TourType 16 is U12-only.** Every class in that tournament uses the same 4 legs (25m/20m/15m/10m), so the mixed-arity problem doesn't arise. U15 keeps using TourType 3/37 exactly as today. A genuinely combined U12+U15 event needs two ianseo tournaments — nothing in `regulamin-lucznictwa.md` mandates one tournament per event, so this is a tooling boundary, not a rules violation. (If a single combined tournament is wanted later, the official module's own pattern — ride TourType 1's native 4 slots, give both classes real distinct 4-leg distances — is the precedent to reach for, not TourType 16.)

## Setup_16_PL.php

- `$tourDetTypeName = 'Type_GiochiGioventuW'`, `$tourDetCategory = 1` (outdoor), `$tourDetNumEnds` per 3-arrow ends, `$tourDetNumDist = 4` — one distance column per leg, all four populated for the tournament's only class group (U12M/U12W). `$tourDetMaxDistScore = 180` — 18 arrows per distance × max 10, not the 360 (36 arrows) that Types 3/37 use.
- Classes: `CreateStandardClasses($TourId, 16, true)` with the `$KidsOnly` flag — U12M/U12W only.
- Distances: `CreateDistanceNew($TourId, 16, 'RU12_', …)` — 25m, 20m, 15m, 10m (§2.1.2.3.1-2, see gap 1 above).
- Target faces via `CreateTargetFace()`: 122cm for 25m/20m, 80cm for 15m/10m.
- No elimination or finals configuration (gap #3).

## Missing Polish translations for the two new type names

`Common/Languages/pl/Tournament.php` has no `$lang['Type_2x70mRound']` or `$lang['Type_GiochiGioventuW']` entries (types 1/3/6 all do — most other maintained language files have both; Polish is one of the few missing them, exposed now because PL is the first to actually use these two types). `get_text()` (`Common/Globals.inc.php:217-219`) doesn't fall back to English on a miss with default params — it returns a literal debug placeholder: `<b>[[Type_2x70mRound]@[pl]@[Tournament]]</b>`.

Two separate places render this:

- The tournament-creation dropdown (`Tournament/index.php:807`) builds its labels via `get_text($r->TtType, 'Tournament')` into the core `$TourTypes[$val]` array, which `sets.php` then copies verbatim: `$SetType['PL']['types']["$val"] = $TourTypes[$val];`. **Fixable in-scope** — override the two entries with hardcoded Polish strings right after that loop.
- The public tournament homepage (`Main.php:70`) independently calls `get_text($MyRow->TtName, 'Tournament')` on the DB-stored `ToTypeName` for every visitor, every page load. No PL-module hook sits between that and core — the real fix is the two missing `$lang` entries in core, which is off-limits per this repo's conventions.

Decision: patch `sets.php`'s dropdown only; the public-homepage placeholder is an accepted, documented known limitation until ianseo core carries the Polish translations (not something this module can fix from `Modules/Sets/PL/`).

## Removing Poland-4x70m

- `sets.php`: drop the `$SetType['PL']['rules']['3'][] = 'Poland-4x70m';` line, add 16 and 37 to `$AllowedTypes`.
- `Setup_3_PL.php`: drop `$isDouble` and every branch on it.
- `openspec/specs/tournament-setup/spec.md` §2 Sub-Rules section rewritten at archive time.

## Files

- **New:** `Setup_16_PL.php`, `Setup_37_PL.php`
- **Modified:** `sets.php`, `Setup_3_PL.php`, `lib.php`, `LibTest.php`
- **Menu:** no additions — tournament types appear in ianseo's own creation form.
- **DB:** no new tables or columns.
