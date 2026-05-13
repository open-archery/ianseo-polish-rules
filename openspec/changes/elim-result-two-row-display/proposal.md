## Why

The elimination results PDF currently shows only the arrow average per match phase — a number like `6.933`. This omits the set points (for recurve/barebow) or total match score (for compound), which coaches, athletes, and officials need to read the bracket at a glance. Both values together give the full picture of how a match was won or lost.

## What Changes

- Finals cells in `pdf/chunks/RankIndividual.inc.php` change from a single row (h=4) to two stacked sub-rows (h=4+4=8):
  - **Top-left** (7px): set points total for set-system events (R, B); cumulative match score for compound (C)
  - **Top-right** (5px): tiebreak arrow label `T.X` — shown only on the last phase when a shoot-off occurred
  - **Bottom-left** (7px): arrow average for the match (3 dp), as shown today
  - **Bottom-right** (5px): tiebreak arrow average (3 dp) — shown only on the last phase when a shoot-off occurred
- Bye cell (`-Wolne-`) spans the full 8px height
- All non-finals cells in each data row double in height from 4 to 8
- Page-break threshold increases from `SamePage(4)` to `SamePage(8)`

## Capabilities

### New Capabilities

- `elim-result-display`: Display requirements for the elimination results PDF — which values appear per phase cell and how they are laid out.

### Modified Capabilities

_(none — no existing spec covers the elimination results PDF layout)_

## Non-goals

- No changes to the ranking calculation logic
- No changes to team or diploma PDFs
- No changes to the qualification or distance score display
- No new database tables or menu items

## Impact

- **File changed**: `pdf/chunks/RankIndividual.inc.php` only
- **Rendered output**: all elimination result PDFs generated for PL tournaments — row height increases, denser bracket tables may flow to one more page for large brackets (104+ archers)
