# Operator Guide: PZŁucz Sportzona Lookup Integration

## One-time setup

A system administrator registers the PZŁucz adapter in ianseo's federation path
configuration. This is a database-level operation done once. After it is done,
every tournament on that ianseo installation can use it — no per-tournament
configuration is needed.

---

## Per-tournament workflow

### Step 1 — Synchronise the athlete registry

Go to **Participants → Synchronise**.

You will see a table listing registered federation sources. There will be a row
for `POL` with a checkbox in the **"Net"** column and a date showing when it was
last downloaded.

1. Tick the checkbox on the `POL` row.
2. Click **Synchronise**.
3. ianseo downloads the full PZŁucz registry (~5,900 athletes) from Sportzona
   and stores it internally. Progress dots appear on screen.
4. When finished, the **Last Update** timestamp on that row updates to the
   current date/time.

> You only need to repeat this step if you want a fresher copy of the registry
> (e.g. new licence holders registered since the last sync). Once per tournament
> is normally sufficient.

---

### Step 2 — Register athletes using the lookup

When adding an athlete in **Participants → Entries**, look up athletes from the
downloaded registry by their **licence number** (the PZŁucz federation number,
e.g. `4516`).

Type the licence number in the athlete code field. ianseo pre-fills:
- First name and last name
- Club name (shown in the "country" field — short form like `MLKS Czarna Strzała Bytom`
  and 2–4 char code like `CSB`)
- Year of birth (as January 1 of that year)
- A suggested gender based on the first name

**You must verify gender manually.** The suggestion is based on the heuristic
that a given name ending in "a" = female. It is correct for most Polish names
but not guaranteed, especially for foreign athletes or unusual names.

---

### Step 3 — Match athletes entered without a licence number

If some athletes were already entered manually (e.g. imported from a registration
spreadsheet) without a licence number, the **Check WaIds** feature at the bottom
of the Synchronise page can help match them.

1. Tick the **Check WaIds** checkbox on the Synchronise page.
2. ianseo compares names of entries without a licence code against the downloaded
   registry using phonetic matching (soundex).
3. A table appears showing potential matches, colour-coded by confidence:
   - **Green** — name and date of birth match exactly
   - **Cyan** — name matches, date of birth differs
   - **Yellow** — name matches, one side has no date of birth
   - **White** — name is a phonetic near-match only
4. Click the licence number link on any row to apply that match to the entry.

> Use the **Filter on Div/Cl** field to narrow the matching table to a specific
> division/class combination, which is useful at large tournaments.

---

### Step 4 — Handle athletes not in the registry

Athletes not registered with PZŁucz (foreign visitors, newly licensed athletes
not yet in Sportzona) will not appear in the lookup. Register them manually as
normal. Their entries will simply not have a licence code pre-filled.

---

## What the lookup does NOT do automatically

| Task | Requires manual action |
|---|---|
| Assigning bow type (Recurve, Compound, Barebow) | Yes — always entered at registration |
| Verifying gender for edge-case names | Yes — heuristic only |
| Correcting age-class for athletes born late in the year | Yes — DOB is set to Jan 1 of birth year |
| Flagging Paralympic athletes | Yes — always false in the registry |
| Photo or flag synchronisation | Not part of this integration |
