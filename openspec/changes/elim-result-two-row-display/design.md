# Elimination Results Two-Row Display — Architecture

## Context

`pdf/chunks/RankIndividual.inc.php` is the PL override of ianseo's core
results PDF chunk. It renders the post-elimination ranking table: one row per
archer, with fixed-width columns for rank, name, bib, birth year, country,
qualification score, optional elimination rounds, and then one 12px-wide cell
per finals phase.

Each finals cell currently renders as a **single 4px-high row** split into:
- Left 5px: tiebreak info (last phase only)
- Right 7px: `avgMatch` (arrow average) or `score` as fallback

The data passed to this template is the standard ianseo `$item['finals']`
array. Each phase entry already contains all the fields we need:
`setScore` (set-points total), `score` (cumulative arrow total),
`setPoints` (per-set arrow string — used to detect set-system), `avgMatch`,
`avgTie`, `tiebreak`, and `tie` (2 = bye).

ianseo's TCPDF wrapper (`OrisPDF.inc.php`) is used for all rendering.
The established pattern for stacking two 4px sub-rows within a taller cell
already exists in `DivClasIndividual.inc.php` (`$double` mode): save X/Y,
render top sub-row, `setXY(x, y+4)`, render bottom sub-row, restore Y.

## Goals / Non-Goals

**Goals:**
- Display match score (set points or compound total) in the top sub-row of each phase cell
- Display tiebreak annotation (T.X label + avgTie) in the right sub-cells of the last phase
- Keep all non-finals column widths unchanged
- Match the row height used by other cells (8px) so borders align

**Non-Goals:**
- No changes to ranking calculation, DB schema, or any other file
- No team result PDF changes
- No changes to the header row or page title

## Decisions

### Decision 1 — Two-pass absolute-positioning for finals cells

**Choice:** Render the data row in three passes:
1. Non-finals cells (rank through elim columns) at height 8 — one pass, straightforward.
2. Finals cells — save `$TmpX/$TmpY`, loop for top sub-row at `$TmpY`, move
   to `$TmpY+4`, loop for bottom sub-row, return X cursor to end of finals block.

**Why:** This is exactly how `DivClasIndividual.inc.php` handles its `$double`
mode, so there is an established pattern in this codebase. TCPDF's `Cell()`
does not natively support multi-line content with different font sizes, so
absolute positioning is the only clean option.

**Alternative considered:** `MultiCell()` with newline characters — rejected
because font size changes within a cell are not supported, and the fixed-width
column model breaks.

### Decision 2 — Set-system detection via `$v['setPoints']`

**Choice:** Use `!empty($v['setPoints'])` to determine whether the event uses
the set system. When true, display `$v['setScore']`; otherwise display `$v['score']`.

**Why:** `$v['setScore']` (= `FinSetScore`) can legitimately be `0` when an
archer loses all sets. Relying on `$v['setScore']` being truthy would incorrectly
fall back to `score` for a 0-set-points loser. The `setPoints` string
(per-set arrow breakdown) is always populated for set-system matches and always
empty for cumulative matches, making it a reliable discriminator.

**Alternative considered:** Checking `EvMatchMode` from section metadata —
that field is not currently surfaced in the template's `$section['meta']`
array, so `setPoints` string presence is cleaner.

### Decision 3 — Width split: 7px (score/avg) + 5px (tiebreak annotation)

**Choice:** Keep the same 12px total per phase, split 7+5 — same widths as the
current left/right sub-cells but now used for score/avg and tiebreak
annotation respectively.

**Why:** Preserves the total table width (no reflow of other columns).
The tiebreak label `T.X` is short (3–4 characters at font size 6), and 5px is
sufficient. The score/average in 7px matches current rendering.

### Decision 4 — Tiebreak label format: `T.` + stripped tiebreak string

**Choice:** Show `'T.' . str_replace('|', '', $v['tiebreak'])` in the
top-right sub-cell.

**Why:** `$v['tiebreak']` is a pipe-delimited concatenation of shoot-off arrow
values (e.g. `"6"` for a single-arrow shoot-off, `"X|9"` for two arrows).
Stripping pipes gives `"6"` or `"X9"`. Prefixing with `T.` produces `"T.6"`
or `"T.X9"` — standard notation that appears in ianseo's own HTML results.

### Decision 5 — Border scheme

**Choice:**
- Top sub-row cells: `'TLB'` (left sub-cell) + `'TRB'` (right sub-cell)
- Bottom sub-row cells: `'LB'` (left) + `'RB'` (right)

Top sub-row draws T, L/R, and B borders; bottom sub-row draws only L/R and B.
This produces one horizontal separator (from the top row's B) with no double-line.

**Why:** Matches the visual output in the user's reference screenshot —
a single divider line between score and average, with the outer box drawn
by the top row's T and the bottom row's B/L/R.

## Risks / Trade-offs

**Row height doubling increases page length** → For a 104-archer outdoor
bracket, rows grow from 4px to 8px each. At 270px usable height per A4 page,
a 104-row table (without headers) was ~416px (2 pages); at 8px it's ~832px
(~4 pages). This is acceptable and matches typical formatted results sheets.
The page-break check is updated from `SamePage(4)` to `SamePage(8)` to keep
header + first row together.

**`avgMatch` not yet computed** → If ranking hasn't been recalculated after
elimination, `avgMatch` is null/0 and the bottom sub-row is blank. This was
already the case with the single-row layout; no regression.

## Files to Modify

| File | Change |
|------|--------|
| `pdf/chunks/RankIndividual.inc.php` | Refactor data row and finals rendering as described |

No files to create. No other files affected.

## Migration Plan

Drop-in replacement: the file is loaded dynamically by ianseo's PDF engine
when `TourLocRule = PL`. Deploying updated file takes effect on the next
PDF generation. No DB migration required.

**Rollback:** `git checkout pdf/chunks/RankIndividual.inc.php`

## Open Questions

_(none — all decisions resolved during exploration)_
