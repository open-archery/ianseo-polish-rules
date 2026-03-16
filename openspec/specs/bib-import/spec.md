# Feature Requirements: Bib List Batch Import

**Feature name:** BibImport
**Author:** Advisor agent
**Status:** Ready for development

---

## 1. Purpose

Tournament operators are often provided a list of participant licence numbers
(referred to as "bibs") by the organising federation. The list frequently does
not match the exact format required by ianseo for manual entry. This feature
allows the operator to paste a raw list of PZŁucz licence numbers into a
textarea, select a single bow-type division, and have all recognised athletes
automatically registered as entries in the current tournament.

**Prerequisite:** The Sportzona athlete registry must have been synchronised
at least once before using this feature (i.e. the `LookUpEntries` table must
be populated with `LueIocCode = 'POL'`). If the table is empty the feature
should warn the operator rather than silently importing nothing.

---

## 2. User interface

A single page accessible from the ianseo menu under the PL module. It presents:

1. **Division selector** — a dropdown listing all divisions configured for the
   current tournament. The operator selects one division that applies to the
   entire batch being imported.

2. **Licence number textarea** — a plain text area where the operator pastes one
   licence number per line. Blank lines and leading/trailing whitespace on each
   line must be tolerated and ignored. Non-numeric or otherwise malformed tokens
   should be treated as unmatched (reported, not errored).

3. **Import button** — triggers processing. After submission the page reloads
   showing the result report (see §5) without redirecting away, so the operator
   can review what happened.

---

## 3. Processing logic

For each licence number in the input, the following steps are performed in
order:

### Step 1 — Lookup

Search `LookUpEntries` for a record where `LueCode` matches the licence number
and `LueIocCode = 'POL'`. If no match is found, add the licence to the
**unmatched list** and continue to the next line.

### Step 2 — Duplicate check

Search the current tournament's `Entries` for a record where `EnCode` matches
the licence number. If found, add the athlete to the **duplicate list** and
continue to the next line (do not update the existing entry).

### Step 3 — Age class resolution

Determine the appropriate age class for the athlete using:

- **Birth year** — from the lookup record's date-of-birth field (stored as
  `{year}-01-01`; extract the year component)
- **Tournament date** — the tournament's start date
- **Division** — the division selected by the operator
- **Sex** — from the lookup record

Search the tournament's class configuration for a class where:

- The athlete's birth year falls within the class's birth-year range (inclusive)
- The class's sex matches the athlete's sex
- The class permits the selected division

If multiple classes match, select the one with the narrowest birth-year range
(most specific). If no class matches, add the athlete to a **class-unresolved
list** (still import the entry, but leave the class field blank so the operator
can assign it manually).

### Step 4 — Country/club record

The athlete's club affiliation comes from the lookup record (club code, full
club name, short club name). Before creating the entry, ensure a country record
exists in the tournament's country list for this club code. If it does not yet
exist, create it using the club code, full name, and short name from the lookup.

### Step 5 — Entry creation

Create a new entry in the tournament with the following field mapping:

| Entry field            | Source                                    |
| ---------------------- | ----------------------------------------- |
| Licence / athlete code | Lookup: athlete code (`LueCode`)          |
| Family name            | Lookup: family name                       |
| Given name             | Lookup: given name                        |
| Sex                    | Lookup: sex                               |
| Date of birth          | Lookup: date of birth (`{year}-01-01`)    |
| Division               | Selected by operator in the UI            |
| Age class              | Resolved in Step 3 (blank if unresolved)  |
| Country / club         | Country record resolved/created in Step 4 |
| Federation code        | `'POL'`                                   |
| Status                 | Copied from lookup status                 |

---

## 4. Batch behaviour

- All lines are processed in the order they appear in the textarea.
- Processing does not stop on errors — every line is attempted.
- The entire batch is committed together (single transaction). If the database
  write fails partway through, no partial data is left.

---

## 5. Result report

After processing, the page displays a summary containing:

### 5.1 Success count

Number of athletes successfully imported.

### 5.2 Duplicate list

A table of athletes who were already present in the tournament and therefore
skipped. Columns: licence number, name (from lookup if available).

### 5.3 Unmatched list

A table of licence numbers that were not found in `LookUpEntries`. Columns:
licence number as entered. No name can be shown since the lookup failed.

### 5.4 Class-unresolved list

A table of athletes who were imported but whose age class could not be
automatically determined. Columns: licence number, name, birth year. The
operator must assign the class manually from the standard ianseo entry editor.

### 5.5 Sportzona sync warning

If `LookUpEntries` contains no records for `LueIocCode = 'POL'`, show a
prominent warning at the top of the page (before the form) explaining that the
Sportzona synchronisation must be run first, and link to the Synchronise page.

---

## 6. Known gaps and constraints

### ⚠ Division applies to the entire batch

A single import run covers one division only. If the operator has a mixed list
(e.g. Recurve and Compound archers together), they must run the import twice —
once per division — splitting the list themselves. This is an accepted
limitation of Option B.

### ⚠ Gender inherited from Sportzona heuristic

The imported athlete's sex is whatever was stored in `LookUpEntries` during the
Sportzona sync. If the heuristic assigned the wrong gender, the operator must
correct it in the entry editor after import.

### ⚠ Birth year only

Age class computation uses year precision only. Athletes born in the same year
as a class boundary may be placed in the wrong class if their actual birthday
is after January 1. The class-unresolved list provides the operator with
visibility over such edge cases only when no class can be found at all — it
does not flag boundary-year athletes who were assigned a class.

### ⚠ No division validation against lookup

The lookup table does not contain bow-type information. The feature cannot
warn if a licence number is valid but the selected division is wrong for that
athlete — the operator is responsible for consistency.

---

## 7. Out of scope

- Importing multiple divisions in a single run
- Uploading a file (textarea only for now)
- Editing or overwriting existing entries
- Assigning target or session numbers
- Photo import
