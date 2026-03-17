## Why

Polish federation printouts currently omit the athlete's licence number (bib), making it impossible to cross-reference results with the PZŁucz registry or official start lists. Officials need the licence number visible on both qualification and finals ranking PDFs.

## What Changes

- Add a **"Nr lic."** column (licence number / bib) to the Individual Qualification ranking PDF (`DivClasIndividual` chunk).
- Add a **"Nr lic."** column to the Individual Finals ranking PDF (`RankIndividual` chunk).
- Both overrides are PL-only, implemented via the existing `PdfChunkLoader` module override mechanism — no core changes.

## Capabilities

### New Capabilities

- `bib-on-result-pdfs`: Displays athlete licence number as a new column on qualification and finals individual ranking PDFs for PL tournaments.

### Modified Capabilities

<!-- No existing spec-level requirements change — this is purely additive presentation. -->

## Non-goals

- No changes to team ranking PDFs.
- No changes to bracket/elimination PDFs.
- No changes to data layer — `bib` (`EnCode`) is already present in rank objects.
- No changes to any non-PL tournament types.

## Impact

- **New files**: `Modules/Sets/PL/pdf/chunks/RankIndividual.inc.php`, `Modules/Sets/PL/pdf/chunks/DivClasIndividual.inc.php`
- **Routing**: Automatic via `PdfChunkLoader()` — checks `Modules/Sets/<localRule>/pdf/chunks/` before falling back to core.
- **No menu changes**, no DB changes, no core modifications.
