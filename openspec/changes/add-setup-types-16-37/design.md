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

### Gap 3, answered — two different reasons, not one

**U15:** the regulation does *not* forbid U15 eliminations generally. §2.3.1.8 exempts them only *"na mistrzostwach Polski"* (at Polish Championships specifically); §2.3.1.5 (outdoor, 40m/122cm) and §2.3.2.5 (indoor, 18m/60cm) actually *define* a U15 elimination format for use elsewhere, and §2.3.1.9/§2.3.2.6 let non-championship organisers simply cap the field instead of skipping eliminations outright. But `Setup_3_PL.php` and `Setup_6_PL.php` **already ship** with `EvFinalFirstPhase=0` for U15 unconditionally — every competition, not just championships. That's existing module behavior this change inherits, not a new rule-driven decision; `Setup_16_PL.php` should just match it for consistency.

**U12:** checked the entire elimination chapter both outdoor (§2.3.1.1-9) and indoor (§2.3.2.1-6) — U12 is never mentioned. No distance, no target face, no format defined for a U12 elimination round anywhere. There's nothing to configure even if it were wanted — qualification-only isn't a design choice for U12, it's the only thing the regulation supports.

### Gap 1, answered

§2.3.1.10.11 itself only says "4 different distances, longest to shortest, two longer on 122cm, two shorter on 80cm" — it doesn't name meters. But `regulamin-lucznictwa.md` §2.1.2.3 (target-face-by-distance table, not the round-definition section) pins the actual numbers:

- §2.1.2.3.1 (122cm face): *"na odległości 25, 20 metrów dla kategorii U12M, U12W w konkurencji łuków klasycznych"* — 25m and 20m, Recurve.
- §2.1.2.3.2 (80cm face): *"Na odległości 15, 10 metrów kategorii U12 w konkurencji łuków klasycznych"* — 15m and 10m, Recurve.

That's **25m, 20m, 15m, 10m**, longest to shortest, 122cm at 25m/20m and 80cm at 15m/10m — matching §2.3.1.10.11's shape exactly. Both citations say "łuków klasycznych" (Recurve) only; §2.1.2.3 names no Compound distance for U12 anywhere, which corroborates gap 2's Recurve-only assumption without fully closing it (still worth an explicit confirmation, since absence of a citation isn't the same as an explicit exclusion).

`pzlucz-rules.md`'s existing target-face table (its §1.2/§4 area) only carried the indoor U12 entry (15m/80cm) and missed this outdoor cross-reference — worth back-filling that research doc separately so this doesn't get re-lost.

### Gap 4, answered (checked 2026-09-08, re-verified 2026-09-14 directly against the dev DB)

One tournament uses it: **ToId 129, "Mistrzostwa Krajowego Zrzeszenia LZS", TourType 3, `ToTypeSubRule = 'Poland-4x70m'`**, `ToWhenFrom = 2026-09-26` — in the future, with **`(select count(*) from Entries where EnTournament=129) = 0`**, so 0 scores follows directly. Re-run against `ianseo` on the dev MySQL container on 2026-09-14: same single row, unchanged. Every other PL tournament is `Poland-Full`.

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

Extending the gates for 37 is a one-line `in_array()` addition (task 2.3). TourType 16 is different in kind, not degree — it needs to *suppress* the base ten classes entirely, which no amount of `in_array()` extension gets you.

A `class-presets` change has been proposed on a separate branch that would eventually generalize class-set selection. It is not landed, not merged, and this change does not wait on it. `CreateStandardClasses()`/`InsertStandardEvents()` get a minimal, local `$YouthOnly` flag now; if `class-presets` lands later and wants to generalize both call sites onto a shared mechanism, that is its refactor to do against code that already exists and already has tests pinning the current behavior.

### `$YouthOnly` — concrete signature

```php
function CreateStandardClasses($TourId, $TourType, $YouthOnly = false) { ... }
function InsertStandardEvents($TourId, $TourType, $YouthOnly = false) { ... }
```

Default `false` preserves today's behavior byte-for-byte for TourTypes 1/3/6 — `LibTest.php`'s existing count assertions (10/12/14 `CreateClass` calls) stay valid unchanged.

`CreateStandardClasses`, `$YouthOnly = true`: skip the ten base `CreateClass()` calls entirely; run only the existing U15/U12 block (`lib.php:82-89` today) unconditionally, ignoring `$TourType`-based `$hasU15`/`$hasU12` gating. Four classes, indices 1-4: U15M, U15W, U12M, U12W.

`InsertStandardEvents`, `$YouthOnly = true`: build the class-code arrays as
```php
$rClasses = array('U15M', 'U15W');
$cClasses = array('U15M', 'U15W');
$bClasses = array();
$rMixedAges = array('U15');
$cMixedAges = array('U15');
$bMixedAges = array();
```
instead of the normal 10-class base, then fall through to the *same* Individual/Team/Mixed loops already in the function — no loop logic is duplicated, only the seed arrays change. U12 gets no mixed-team entry, matching the fact that U12 mixed teams don't exist anywhere in the module today (TourType 6 doesn't bind them either).

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

## Setup_16_PL.php

- `$tourDetTypeName = 'Type_GiochiGioventuW'`, `$tourDetCategory = 1` (outdoor), `$tourDetNumEnds` per 3-arrow ends, `$tourDetNumDist = 4` — the children's round needs four distance columns; U15 uses two of them and leaves the other two empty. `$tourDetMaxDistScore = 180` — 18 arrows per distance × max 10, not the 360 (36 arrows) that Types 3/37 use. Both U15 (18 arrows/end-group... actually 36 arrows over the full 40m or 20m leg, per §2.3.1.10.5) and U12 (18 arrows per leg) share this tournament, so this cap applies per-distance-column, consistent with how Setup_3_PL.php's 360 already means "per session," not "per tournament."
- Classes: `CreateStandardClasses()` gains a class-set filter so it can emit the U15/U12 subset. This is the same mechanism the `class-presets` change needs, so whichever lands first should build it and the other should reuse it.
- Distances: `CreateDistanceNew($TourId, 16, 'RU15_', …)` etc. per the spec table; U12 gets 25m, 20m, 15m, 10m (§2.1.2.3.1-2, see gap 1 above).
- Target faces via `CreateTargetFace()`: 122cm/80cm for R U15, 80cm/60cm for C U15, 122cm ×2 + 80cm ×2 for U12.
- No elimination or finals configuration (gap #3).

### Spike required: mixed-arity distances

U15 needs exactly 2 real `TournamentDistances` slots (`Td1`/`Td2`); U12 needs 4. `$tourDetNumDist` is a single tournament-wide value and must be `4` (U12 needs the columns to exist). This means, for the first time in this module, one class (U15) leaves `Td3`/`TdDist3`/`Td4`/`TdDist4` unset while a sibling class (U12) in the *same tournament* populates them.

Checked all three existing setups — none does this. Type 1 fills all 4 slots for every class. Type 3 and Type 6 fill both slots for every class. There's no precedent in this module for a class with fewer populated distance slots than the tournament's `ToNumDist`, and no research doc covers whether ianseo's session/score-entry screens key off `ToNumDist` globally or per-class-via-`TournamentDistances` when deciding which columns to render for a given athlete.

Before writing `Setup_16_PL.php` in full: create a throwaway TourType 16 tournament on the dev install, hand-craft `TournamentDistances` rows with this exact split (U15 = 2 slots, U12 = 4), enter a U15 and a U12 athlete, and confirm the qualification score-entry screen shows the right number of sessions for each and nothing errors on the empty columns. If it breaks, the fallback is to reconsider whether U15 and U12 can share one TourType at all versus U15 staying on TourType 3/37 and TourType 16 becoming U12-only — a bigger scope change than assumed here, so surface it before sinking time into the full script.

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
