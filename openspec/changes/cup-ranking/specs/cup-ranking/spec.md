## Purpose

Aggregates the points scored in the four separate Puchar Polski rounds — each hosted on a different ianseo installation — into one overall cup classification, its PDF report and its diplomas, using CSV as the only transport between rounds.

## ADDED Requirements

### Requirement: Availability gated by the Puchar Polski preset
Every cup page and every cup output SHALL be available only while the tournament's active points-ranking preset is `pp` (Puchar Polski — runda). With any other preset, or with no preset selected, the cup pages SHALL refuse to operate and SHALL explain that the cup classification exists only for Puchar Polski.

#### Scenario: Preset is Puchar Polski
- **WHEN** the operator opens the cup page and the active preset is `pp`
- **THEN** the cup configuration, import, export, snapshot and classification controls are shown

#### Scenario: Another preset active
- **WHEN** the active preset is `lzs` and the operator opens the cup page
- **THEN** no cup data is read or written and the page states that the feature applies to Puchar Polski only

---

### Requirement: Cup edition and round assignment
Cup data SHALL be scoped by a cup edition (a calendar year) so that rounds of different seasons never aggregate together. The operator SHALL select the edition and the round number (1–4) that the current tournament represents. The edition SHALL default to the year of the tournament's end date and the round assignment SHALL be persisted per tournament, so it is remembered on later visits.

#### Scenario: Round remembered
- **WHEN** the operator sets the current tournament as round 4 of edition 2026 and returns to the page in a later session
- **THEN** edition 2026 and round 4 are pre-selected

#### Scenario: Editions kept apart
- **WHEN** rounds exist for edition 2025 and edition 2026 and edition 2026 is selected
- **THEN** only the 2026 rounds are summed and displayed

---

### Requirement: Snapshot of the current round
The system SHALL store the current tournament's `pp` result as one round of the selected edition. The snapshot SHALL cover both `pp` classifications — individual and mixed — and SHALL record, per row, the category, the row identity, the display name, the club name, the place in that round, the points awarded and the qualification score. A row that placed but scored no points SHALL be stored too, because its place and qualification score can still decide a tie-break in a later round; only rows with no result at all (no place, or a DSQ/DNS/DNF) SHALL be left out. Re-running the snapshot SHALL replace that round's stored rows in full.

#### Scenario: Snapshot stored
- **WHEN** the operator snapshots the current tournament as round 4
- **THEN** every placed individual and mixed row of the `pp` calculation is stored under edition/round 4 with its place, points and qualification score

#### Scenario: Placed but unscored row kept
- **WHEN** an athlete finishes outside the points brackets in this round
- **THEN** the row is stored with 0 points, and its place and qualification score are available to the tie-break

#### Scenario: No result at all
- **WHEN** an athlete has no place, or was disqualified or did not start
- **THEN** no row is stored for them

#### Scenario: Re-snapshot replaces
- **WHEN** a result is corrected in ianseo and the operator snapshots round 4 again
- **THEN** the previously stored round 4 rows are discarded and replaced by the new set, with no leftovers

---

### Requirement: Snapshot staleness warning
While a snapshot exists for the current tournament's round, the system SHALL compare it against the live `pp` calculation on every view of the cup page and SHALL warn, in Polish, when the two differ — naming how many rows differ — so the operator knows to snapshot again. The stale snapshot SHALL still be used for the classification until it is replaced.

#### Scenario: Results changed after the snapshot
- **WHEN** an athlete's final rank changes in ianseo after round 4 was snapshotted
- **THEN** the cup page shows a warning that the snapshot is out of date and how many rows differ

#### Scenario: Snapshot current
- **WHEN** the stored round matches the live calculation exactly
- **THEN** no staleness warning is shown

---

### Requirement: Row identity
An individual row SHALL be identified by the athlete's licence number and its category; a mixed row SHALL be identified by the club code and its category, because a mixed pair's members may differ between rounds while the club does not (a club may enter only one mixed pair per Puchar Polski round). A snapshot or an import SHALL be rejected in full — with the offending rows named — when an individual row has no licence number, when a mixed row has no club code, or when two mixed rows of the same club occur in one category.

#### Scenario: Missing licence blocks the snapshot
- **WHEN** an athlete scored points but has an empty licence number
- **THEN** nothing is stored and the page names that athlete as the reason

#### Scenario: Mixed pair with changed members
- **WHEN** a club's mixed pair consists of different athletes in round 2 and in round 4
- **THEN** both rounds credit the same cup row, identified by the club

#### Scenario: Two pairs of one club
- **WHEN** a snapshot or import contains two mixed rows of the same club in one category
- **THEN** the operation is rejected and the conflict is reported

---

### Requirement: Round import from CSV
The system SHALL import one round of an edition from a CSV file, since earlier rounds' ianseo databases are not available. One row SHALL carry classification, category, identity, display name, club name, place, points and qualification score.

A row SHALL state its classification, category, identity, place and qualification score, and — for an individual row — the athlete's name, which identifies them against the rounds already stored. A qualification score of 0 SHALL be written explicitly, because an empty cell is far more often a forgotten column than a real zero. The club name is optional and is display data only.

Points SHALL always be computed from the place with this cup's own bracket table, never taken from the file: under the `pp` preset the points are a pure function of the place, so results scored under another annex — or not scored at all — import correctly. A points column that disagrees with the computed value SHALL be reported as a warning and the computed value stored, since such a disagreement usually means the place is wrong.

An import SHALL replace **only the categories the file contains**, leaving the round's other categories untouched: a round is routinely assembled from several sources — the recurve categories from an ianseo competition, the compound and barebow ones from files typed by their own hosts. An import SHALL be atomic: any rejected row aborts the whole import and nothing is written.

Category codes SHALL be validated against the categories of the current tournament, and an unknown code SHALL be reported with its line number.

#### Scenario: Earlier round imported
- **WHEN** the operator imports a CSV of round 2 for edition 2026
- **THEN** the round 2 rows are stored and appear in the classification's round 2 column

#### Scenario: Points computed from the place
- **WHEN** a file states place 3 for an individual row and leaves the points column empty
- **THEN** the row is stored with the 18 points the individual bracket table gives place 3

#### Scenario: Points column disagrees
- **WHEN** a file states place 1 and 10 points
- **THEN** the row is stored with 25 points and the operator is warned that the file's value differs

#### Scenario: Missing required value
- **WHEN** an individual row has no name, or any row has no place or an empty qualification score
- **THEN** the import is rejected with a message naming the line and the missing column

#### Scenario: Unknown category rejected
- **WHEN** an imported row names a category that does not exist in the current tournament
- **THEN** the import is aborted, nothing is written, and the offending line number and code are reported

#### Scenario: Re-import replaces
- **WHEN** a corrected CSV for round 2 is imported covering the same categories
- **THEN** the previous rows of those categories are removed and only the new rows remain

#### Scenario: Round assembled from several sources
- **WHEN** round 1 already holds the recurve categories imported from one competition and a CSV covering only the barebow and compound categories of round 1 is imported
- **THEN** both sets are stored side by side and the recurve rows are untouched

---

### Requirement: Round export to CSV
The system SHALL export the current tournament's round in exactly the format the import accepts, so that a later round's host can import it without retyping. The file SHALL name the competition its rows come from, as a comment line the import reads back — a file typed by hand simply has none.

#### Scenario: Export then import
- **WHEN** the round 1 host exports the round and the round 4 host imports that file
- **THEN** the round is stored unchanged, with the same identities, places, points and qualification scores

#### Scenario: Exported file names its competition
- **WHEN** a host exports its round and another host imports that file
- **THEN** the import records the exporting competition as the rows' source, without anyone typing it

---

### Requirement: Athlete identity consistency across rounds
Because a cup row is keyed by licence number, a licence that names two different athletes — or an athlete appearing under two licences — corrupts the classification silently. The system SHALL compare an import against the rows already stored for the edition and SHALL reject the whole file, storing nothing, when either contradiction is found, listing each one with the licence, both names and the round the stored row belongs to, so the CSV can be corrected.

Case, diacritics, extra spaces and word order SHALL be ignored on both checks. The two checks SHALL differ in how tolerant they are, because they ask different questions:

- **One licence, two names** — the licence already asserts the rows are one athlete, so the check looks for a spelling slip: a small number of character differences SHALL be tolerated ("Szkolnicki" / "Szkolnicky").
- **One name, two licences** — here the name decides identity, so the match SHALL be exact. Two athletes routinely differ by a single letter ("Zapała Daria" and "Zapała Maria" are two competitors, not a typo), and a tolerant comparison here would reject correct files.

Contradictions inside one file SHALL be reported the same way.

Club names SHALL NOT be compared: the same club is written differently by different hosts, and an athlete absent from the final round has no single spelling to fall back on — the club shown is the one from that athlete's most recent stored round.

Mixed rows SHALL NOT take part in this check: a mixed row is a club, and its pair may be two different athletes in every round.

A snapshot of the current tournament SHALL report the same contradictions as a warning rather than refusing to store: the snapshot is calculated, so a contradiction there means an imported round holds the wrong licence.

#### Scenario: One licence, two athletes
- **WHEN** a file gives licence `PL0001` to "Marek Wilk" and round 1 already has "Jan Kowalski" under that licence
- **THEN** the import is rejected, nothing is stored, and the message names the licence, both names and round 1

#### Scenario: One athlete, two licences
- **WHEN** a file gives "Jan Kowalsky" the licence `PL9999` and round 1 already has "Jan Kowalski" under `PL0001`
- **THEN** the import is rejected and the message names both licences

#### Scenario: Different club spelling accepted
- **WHEN** an athlete's club is written "KS Stella Kielce" in one round and "Stella Kielce" in another
- **THEN** the import is accepted and the classification shows the spelling from their most recent round

#### Scenario: Name typo tolerated
- **WHEN** the same licence carries "Szkolnicki Oskar" in one round and "Szkolnicky Oskar" in the file
- **THEN** the import is accepted

#### Scenario: Two athletes one letter apart
- **WHEN** "Zapała Daria" and "Zapała Maria" appear under their own licences
- **THEN** the import is accepted and they are classified as two athletes

#### Scenario: Contradiction inside one file
- **WHEN** one file uses the same licence for two different athletes
- **THEN** the import is rejected before anything is stored

---

### Requirement: Import history
The system SHALL show, on a page of its own reached from the cup page, what the edition is currently assembled from: one entry per import that still feeds the classification, with its round, the categories it owns, the number of rows, where the rows came from, the competition that was open when the import was performed, and when it happened.

The list SHALL be derived from the stored rows themselves, so that an import wholly replaced by a later one disappears and one partly replaced shrinks to the categories it still owns — the page shows what the ranking uses, not a log of everything that ever happened.

Unlike the classification, this page SHALL list every category of the edition, not only those the current competition runs, and SHALL name them in Polish from their category code.

The system SHALL let the operator delete one listed import — exactly its rows — or a whole round of the edition, each after a confirmation naming what will be removed.

#### Scenario: Two sources in one round
- **WHEN** round 1 holds recurve rows from a competition and barebow rows from a CSV
- **THEN** the page lists two entries for round 1, each with its own categories, source, competition and timestamp

#### Scenario: Superseded import disappears
- **WHEN** an import is replaced by a newer one covering the same categories
- **THEN** only the newer import is listed

#### Scenario: Import from before provenance was recorded
- **WHEN** rows were stored by an earlier version of the module and carry no source or timestamp
- **THEN** they are still listed, as one entry per round, and can be deleted

#### Scenario: Deleting one import
- **WHEN** the operator deletes the barebow import of round 1
- **THEN** those rows are removed, the round's other categories remain, and the classification is recomputed without them

#### Scenario: Deleting a whole round
- **WHEN** the operator deletes round 2
- **THEN** every row of round 2 is removed, whatever it was assembled from

---

### Requirement: Combined cup classification
The system SHALL present two cup classifications — individual and mixed — each sectioned per category in the same order as the round reports. Only the categories the current competition itself runs SHALL be shown: the stored rounds cover the whole cup, but a junior competition classifies juniors, not the barebow or compound categories it merely imported.

Every section SHALL be headed by its cup's own Polish name — "Klasyfikacja generalna Pucharu Polski Juniorek młodszych", "… łuków barebow - indywidualna mężczyzn", "… Seniorów - miksty" — never the raw category code.

An individual row SHALL show the licence number, the athlete, the club, the points scored in each of the four rounds, and their sum. In the page — not in the PDF, which stays a plain points table — each round SHALL additionally show that round's place and qualification score, and the sum SHALL be accompanied by the best place and the best qualification score of the whole cup, since those are what the tie-break reaches for next. Only the sum SHALL be emphasised; the per-round points are read together with their place and score, not picked out of the row. A mixed row SHALL show the club and its code in place of the athlete: the competitor is the club, and its pair may be two different athletes in every round, so no athlete names are stored or displayed. A round in which the row scored nothing SHALL be shown as empty and SHALL not reduce the sum. No minimum number of started rounds is required.

Rounds not yet imported or snapshotted SHALL simply be absent, so the classification is also usable as a standing after two or three rounds. A competitor whose stored rounds add up to zero points SHALL NOT be classified, even though their stored rows still feed the tie-break of the competitors who did score.

#### Scenario: Full cup
- **WHEN** all four rounds are present and an athlete scored 25, 21, 0 and 18
- **THEN** the row shows those per-round values and a sum of 64

#### Scenario: Round detail in the page
- **WHEN** an athlete placed 2nd in round 1 with a qualification score of 607
- **THEN** that round's cell shows 21 points, place 2 and 607, and the sum column also shows the athlete's best place and best qualification score

#### Scenario: One round only
- **WHEN** an athlete competed in round 1 only and scored 13
- **THEN** the athlete is classified with a sum of 13

#### Scenario: Standings after two rounds
- **WHEN** only rounds 1 and 2 are stored
- **THEN** the classification renders from those two rounds without an error

#### Scenario: Category outside this competition
- **WHEN** the stored rounds hold barebow rows and the current competition runs only `RU21` and `RU18` categories
- **THEN** the barebow sections are absent from the classification, its PDF and its diplomas

#### Scenario: Display data for an absent athlete
- **WHEN** an athlete is present in earlier rounds but not in the current tournament
- **THEN** the name and club stored with their most recent round are displayed

---

### Requirement: Tie-breaking
Rows SHALL be ordered by their sum descending. Further tie-break steps SHALL be applied only where the Puchar Polski regulation states them, per cup series:

| Series | Categories | Steps after the sum |
|---|---|---|
| Puchar Polski Juniorów | division `R`, classes `U21M`, `U21W` | best (lowest) place in any round, then highest qualification score in any round |
| Puchar Polski Juniorów Młodszych | division `R`, classes `U18M`, `U18W` | best place in any round, then highest qualification score in any round, then a baraż |
| Puchar Polski łuków barebow | division `B`, all classes | highest qualification score in any round, then a baraż |
| Puchar Polski Seniorów | division `R`, classes `M`, `W` | none stated |
| Puchar Polski łuków bloczkowych | division `C`, all classes | none stated |
| Puchar Polski mikstów | every mixed category | none stated |
| any other category present | — | none stated |

The system SHALL NOT invent a step the regulation does not state. Rows still equal after the steps stated for their series SHALL share a rank. Where the regulation prescribes a baraż, those rows SHALL be marked "baraż"; where it states no further tie-break, no mark SHALL be printed — the equal rank already says the place is shared.

#### Scenario: Junior series — best place decides
- **WHEN** two `RU21M` athletes both total 47 points and their best places in any round are 2 and 3
- **THEN** the athlete whose best place is 2 ranks higher

#### Scenario: Junior series — qualification score decides
- **WHEN** two `RU21M` athletes total the same points and share the same best place, and their highest qualification scores in any round are 645 and 638
- **THEN** the athlete with 645 ranks higher

#### Scenario: Barebow skips the place step
- **WHEN** two `BM` athletes total the same points, with best places 2 and 4 and highest qualification scores 520 and 533
- **THEN** the athlete with 533 ranks higher, the places being irrelevant

#### Scenario: Senior tie stands
- **WHEN** two `RM` athletes total the same points
- **THEN** they share a rank with no mark, regardless of their places or qualification scores

#### Scenario: Compound tie stands
- **WHEN** two `CU21W` athletes total the same points
- **THEN** they share a rank with no mark

#### Scenario: Mixed tie stands
- **WHEN** two clubs total the same points in a mixed category
- **THEN** they share a rank with no mark

#### Scenario: Baraż due
- **WHEN** two `RU18W` athletes are equal on sum, best place and highest qualification score
- **THEN** they share a rank and are marked "baraż"

---

### Requirement: Recording a baraż outcome
Where the regulation prescribes a baraż — the junior-młodszy and barebow series — the system SHALL let the operator record the shoot-off result for a tied group by placing its rows in the order decided on the shooting line. A recorded order SHALL be stored per edition, classification, category and row identity, and SHALL be applied as the final ordering step, giving the affected rows distinct ranks in the HTML view, the PDF and the diploma selection.

The recording control SHALL be offered only once the final round of the edition is stored, since a baraż settles the cup and not an intermediate standing. Categories whose series states no further tie-break SHALL NOT offer it — their tied rows stay a shared place.

Rows of a tied group left without a recorded position SHALL keep sharing a rank and SHALL keep the "baraż" mark. A recorded outcome SHALL survive a re-import or re-snapshot of any round, and SHALL be ignored when the group is no longer tied. Rows ranked by a recorded outcome SHALL be marked in the HTML view and the PDF as decided by a baraż, so the printed result is unambiguous about why two equal totals rank differently.

#### Scenario: Shoot-off recorded
- **WHEN** all four rounds are stored, two `RU18M` athletes are tied after every stated step, and the operator records that the first of them won the shoot-off
- **THEN** that athlete is ranked ahead, both rows carry distinct ranks, and the rows are marked as decided by a baraż

#### Scenario: Not the final round
- **WHEN** only rounds 1 and 2 are stored and a `BW` group is tied
- **THEN** no recording control is offered and the rows share a rank

#### Scenario: Series without a baraż
- **WHEN** two `RM` athletes share a rank after the full cup
- **THEN** no recording control is offered for them

#### Scenario: Outcome not yet recorded
- **WHEN** a tied group in a baraż series has no recorded outcome
- **THEN** its rows share a rank and are marked "baraż"

#### Scenario: Outcome survives a re-import
- **WHEN** a shoot-off outcome has been recorded and round 3 is re-imported without changing the tied rows' points
- **THEN** the recorded outcome still applies

#### Scenario: Tie disappears
- **WHEN** a correction to a round breaks the tie that a recorded outcome referred to
- **THEN** the regulation order decides and the recorded outcome has no effect

#### Scenario: Diplomas follow the recorded outcome
- **WHEN** the diploma place range is 1–3 and a baraż decided places 3 and 4
- **THEN** only the athlete placed 3rd by the shoot-off receives a diploma

---

### Requirement: Cup PDF report
The system SHALL generate a single A4 PDF containing the individual and mixed cup classifications, in that order, with the same sectioning as the HTML view, the per-round columns, the sum, the baraż marks and the ranks resulting from any recorded shoot-off. Each section's table SHALL be titled with its cup's Polish name followed by the edition, e.g. "Klasyfikacja generalna Pucharu Polski łuków barebow - indywidualna mężczyzn 2026".

#### Scenario: PDF produced
- **WHEN** the operator prints the cup classification
- **THEN** a PDF is streamed containing both classifications, sectioned per category, with columns for every round and the sum

---

### Requirement: Cup diplomas
The system SHALL produce cup diplomas with the same renderer and the same per-tournament diploma configuration as the round diplomas — dates, location, body text, head judge, organizer and place range — except the competition name, which SHALL come from a cup-specific name configured in the cup settings, so the diploma states that it is won in the whole Puchar Polski and not in a single round.

Only rows whose cup rank falls inside the configured place range SHALL be printed. An individual diploma SHALL name the athlete with their club; a mixed diploma SHALL name the club with no athlete line and no roster list. The diploma's category line SHALL be the row's category label.

#### Scenario: Cup name printed
- **WHEN** the cup name is set to "Puchar Polski 2026" and cup diplomas are printed
- **THEN** the diplomas carry that name while their dates, location and body text come from the tournament's diploma configuration

#### Scenario: Place range respected
- **WHEN** the diploma configuration covers places 1–3 and a category has 20 classified athletes
- **THEN** three diplomas are produced for that category

#### Scenario: Mixed diploma recipient
- **WHEN** a mixed cup diploma is printed for a club ranked 1st
- **THEN** the club name is the recipient, with no athlete name and no member list

#### Scenario: No rows in range
- **WHEN** no cup row falls inside the configured place range
- **THEN** the system reports that no diplomas can be generated instead of emitting an empty PDF
