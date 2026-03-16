# Feature Requirements: PZŁucz Sportzona Athlete Lookup Integration

**Feature name:** SportzonaLookup
**Author:** Advisor agent
**Status:** Ready for development

---

## 1. Competition Format Summary

This feature has no regulation citation — it is an **operational integration**
requirement, not a competition-rule requirement. The purpose is to connect ianseo's
athlete lookup/synchronisation system to the PZŁucz live athlete registry hosted at
`sportzona.pl`, so that tournament operators can import and validate registered
Polish archers without manually entering data.

---

## 2. Source: PZŁucz Sportzona Registry

### 2.1 Endpoint behaviour

The registry exposes a single endpoint that returns all registered archers in a
single JSON payload. The endpoint requires an HTTP **POST** request with a
JSON body specifying the archery discipline and pagination parameters. At time
of analysis (March 2026) the registry contained **5,853 athlete records**.

### 2.2 Response structure

The response is a JSON object with a top-level `players` array. Each element
represents one registered archer. The fields present across the full dataset are:

| Field                     | Type       | Always present | Description                                       |
| ------------------------- | ---------- | -------------- | ------------------------------------------------- |
| `id`                      | integer    | yes            | Internal sportzona database ID                    |
| `licence`                 | string     | yes\*          | PZŁucz federation licence number                  |
| `firstName`               | string     | yes            | Athlete's given name                              |
| `lastName`                | string     | yes            | Athlete's family name                             |
| `birthYear`               | integer    | no             | Year of birth only (no month/day)                 |
| `country` / `clubCountry` | string     | no             | Always `"POL"` for domestic athletes              |
| `clubName`                | string     | yes\*          | Full club name including city in parentheses      |
| `clubVoivodeship`         | string     | no             | Regional voivodeship of the club                  |
| `licenceDate`             | string     | no             | Licence expiry date in `YYYY-MM-DD` format        |
| `sportClass`              | string     | no             | Polish sports classification (e.g. "Mistrzowska") |
| `isArchived`              | boolean    | yes            | Whether the athlete is archived/inactive          |
| `coachFullName`           | string     | no             | Full name of assigned coach                       |
| `filename` / `fileId`     | string/int | no             | Reference to athlete photo                        |

\* Present on virtually all records but not guaranteed.

---

## 3. Integration Mechanism

### 3.1 Challenge

ianseo's synchronisation system fetches lookup data using a standard HTTP GET
request directed at a URL stored in its federation path configuration. The
Sportzona endpoint requires an HTTP **POST** with a JSON request body — it
cannot be called with a plain GET.

### 3.2 Solution: local adapter script

A **local adapter script** hosted on the ianseo server acts as a bridge:

1. ianseo fetches the adapter via GET (following its normal path convention for
   local scripts).
2. The adapter internally makes the POST call to Sportzona, receives the raw
   athlete list, transforms it into the JSON format ianseo expects, and outputs it.
3. ianseo processes the output as if it came directly from a federation API.

The adapter script must be placed inside the PL module directory
(`Modules/Sets/PL/`) to comply with the module isolation constraint. It must be
registered in ianseo's federation path configuration table with:

- A path pointing to the adapter script using the local-file convention
- A non-empty origin marker to signal the JSON/extranet processing branch

### 3.3 Pagination note

The Sportzona request uses `pageSize: 10000` and `page: 1`. With the current
registry size of ~5,900 athletes this retrieves all records in one call. If the
registry grows significantly, pagination handling may be needed in a future
revision.

---

## 4. Field Mapping Decisions

The following decisions were confirmed by the operator:

### 4.1 Athlete identifier

**Use `licence`** (federation licence number, e.g. `"4516"`) as the athlete's
unique code in ianseo. This is the human-readable federation identifier, preferred
over the internal Sportzona database ID.

> ⚠ Note: some athletes in the registry appear without a `licenceDate`, which
> may indicate legacy or provisional records. The adapter should still include
> these records using the `licence` field if present.

### 4.2 Name fields

- Given name → `firstName`
- Family name → `lastName`
- Name order → Western (given name first) — fixed, not per-athlete

### 4.3 Date of birth

Only `birthYear` is available. The adapter shall construct a synthetic full date
of `{birthYear}-01-01`. Downstream age-class calculations will use this date —
the operator accepts the resulting ±1 year imprecision for age-boundary athletes.

> ⚠ Athletes born near the class boundary (i.e. exactly at a cutoff year) may
> be placed in the wrong age class if their actual birthday is after January 1.
> Tournament operators should verify these edge cases manually at registration.

### 4.4 Gender

Gender is **not present** in the Sportzona data. The following heuristic applies:

- If the athlete's given name ends with the letter **"a"** (case-insensitive) →
  suggest **female**.
- Otherwise → suggest **male**.

This heuristic correctly identifies the majority of Polish female given names
(e.g. "Ewelina", "Patrycja", "Weronika"). It is a **suggestion only** — the
operator must verify and correct gender at athlete registration time. The adapter
should apply the heuristic and populate the gender field accordingly; it does not
prompt the operator during the sync itself.

> ⚠ Edge cases exist. Polish male names ending in "a" are uncommon but not
> absent (e.g. some diminutives). Names of foreign athletes registered with
> PZŁucz may not follow this pattern. Manual review is expected.

### 4.5 Club as country affiliation

Rather than the actual country code (`"POL"`, which would be identical for all
athletes and useless for grouping), the adapter shall derive three club-based
affiliation values from the raw `clubName` string:

- **Country code** — a short 2–4 character uppercase code derived from the
  club's distinctive name and city (e.g. `CSB`)
- **Short country name** — abbreviated form of the club name with city
  (e.g. `MLKS Czarna Strzała Bytom`)
- **Full country description** — raw `clubName` value as received from Sportzona
  (e.g. `Miejsko-Ludowy Klub Sportowy "Czarna Strzała" (Bytom)`)

---

#### 4.5.1 Club name structure

Polish sports club names follow a consistent structure:

```
[Organizational prefix] ["Proper name" | ProperName] (City)
```

- **Organizational prefix** — words describing the legal/organizational form
  (e.g. `Klub Sportowy`, `Uczniowski Klub Sportowy`, `Łuczniczy Klub Sportowy`)
- **Proper name** — the distinctive club name, typically in double quotes
  `"..."` but sometimes unquoted immediately after the prefix
- **City** — in parentheses at the end of the string

Before any processing, whitespace must be normalized (collapse multiple spaces
to a single space, trim leading/trailing whitespace). The registry contains
several names with irregular spacing.

---

#### 4.5.2 Short name derivation

Short name = `{PREFIX_ABBR} {ProperName} {City}`

1. Extract the city: everything inside the trailing `(...)`.
2. Identify the organizational prefix by matching the beginning of the name
   against the known abbreviation table below (longest match first).
3. Extract the proper name:
   - If quotes `"..."` are present after the prefix, use the content inside
     the quotes. If additional unquoted words appear between the closing quote
     and the opening parenthesis (e.g. `KS "Silesia" Miechowice (Bytom)`),
     include them as part of the proper name.
   - If no quotes, the proper name is everything remaining between the prefix
     and the city parenthesis.
4. If no prefix is recognized, omit the prefix abbreviation: short name is
   simply `{ProperName} {City}`.

**Known organizational prefix → abbreviation table** (match longest first):

| Full prefix | Abbreviation |
|---|---|
| Akademia Sportu | AS |
| Akademicki Klub Sportowy | AKS |
| Budowlany Klub Sportowy | BKS |
| Cywilno-Wojskowy Klub Sportowy | CWKS |
| Gminny Ludowy Klub Sportowy | GLKS |
| Gminny Ośrodek Kultury i Sportu | GOKiS |
| Górniczy Klub Sportowy | GKS |
| Integracyjne Centrum Sportu i Rehabilitacji | ICSiR |
| Klub Łuczniczy | KŁ |
| Klub Sportowy | KS |
| Kołobrzeskie Stowarzyszenie Łuczników | KSŁ |
| Ludowy Uczniowski Klub Sportowy | LUKS |
| Ludowy Klub Sportowy | LKS |
| Miejski Klub Sportowy | MKS |
| Miejsko-Ludowy Klub Sportowy | MLKS |
| Mokotowski Klub Łuczniczy | MKŁ |
| Morski Robotniczy Klub Sportowy | MRKS |
| Młodzieżowy Klub Sportowy | MłKS |
| Organizacja Środowiskowa Akademickiego Związku Sportowego | OŚAZS |
| Parafialny Klub Sportowy | PKS |
| Polski Związek Łuczniczy | PZŁ |
| Polskie Towarzystwo Gimnastyczne | PTG |
| Społeczne Towarzystwo Sportowe | STS |
| Społeczny Klub Sportowy | SKS |
| Stowarzyszenie Łucznicze | SŁ |
| Stowarzyszenie Sportowo-Rehabilitacyjne | SSR |
| Stowarzyszenie Sportowo-Rekreacyjne | SSRek |
| Stowarzyszenie | St. |
| Szkolny Klub Sportowy | SKS |
| Towarzystwo Sportowe | TS |
| Uczniowski Klub Łuczniczy | UKŁ |
| Uczniowski Klub Sportowy | UKS |
| Uczniowski Ludowy Klub Sportowy | ULKS |
| Warszawski Klub Łuczniczy | WKŁ |
| Zrzeszenie Sportu i Rehabilitacji | ZSiR |
| Łucznicze Towarzystwo Sportowe | ŁTS |
| Łuczniczy Klub Sportowy | ŁKS |
| Łuczniczy Ludowy Klub Sportowy | ŁLKS |
| Łuczniczy Uczniowski Klub Sportowy | ŁUKS |

> The table is non-exhaustive. For unrecognized prefixes the adapter should
> fall back to using the full name without abbreviation. Operators can extend
> the table without code changes if new organizational forms appear.

---

#### 4.5.3 Code derivation

The code is derived from the **proper name** (not the organizational prefix)
and the **city**. Target length: 2–4 uppercase characters.

Algorithm:

1. Split the proper name into words (on whitespace and hyphens).
2. Take the first letter of each word.
3. Append the first letter of the city.
4. If the result has fewer than 2 characters, extend by taking additional
   letters from the beginning of the city name until at least 2 characters
   are reached.
5. If the result exceeds 4 characters, truncate to the first 4.
6. Convert to uppercase.

**Examples:**

| Raw `clubName` | Proper name | City | Code |
|---|---|---|---|
| `Miejsko-Ludowy Klub Sportowy "Czarna Strzała" (Bytom)` | Czarna Strzała | Bytom | `CSB` |
| `Klub Sportowy "Piast" (Nowa Ruda)` | Piast | Nowa Ruda | `PNR` |
| `Bałtycka Strzała (Gdańsk)` | Bałtycka Strzała | Gdańsk | `BSG` |
| `Uczniowski Klub Sportowy "Talent" (Wrocław)` | Talent | Wrocław | `TW` → extend → `TWR` |
| `Klub Sportowy Wanda (Kraków)` | Wanda | Kraków | `W` → extend → `WKR` |
| `Łuczniczy Klub Sportowy "Sagit" (Humniska)` | Sagit | Humniska | `SH` |
| `Uczniowski Klub Sportowy "Diana" (Wolbrom)` | Diana | Wolbrom | `DW` |

---

#### 4.5.4 Special cases

- **`Niezrzeszony (Niezrzeszony)`** — means "unaffiliated". Treat as a fixed
  special case: short name = `Niezrzeszony`, code = `NIE`, full = `Niezrzeszony`.
- **All-caps names without quotes** (e.g. `KS LEW KRYNKI (Krynki)`,
  `KS ROKIS (Radzymin)`) — treat the all-caps words as the proper name.
- **Names with digits** (e.g. `UKS "11 Pobiedziska" (Pobiedziska)`,
  `UKS Piast 25 (Rzeszów)`) — digits count as word separators and do not
  contribute letters to the code.
- **Code collisions** — two different clubs may produce the same code. The
  developer must detect collisions within the transformed set and resolve them
  by appending a disambiguating digit (e.g. `TW` and `TW2`). Collision
  resolution must be deterministic (alphabetical ordering of full club name)
  so the same club always gets the same code across syncs.

---

#### 4.5.5 Summary table update

| ianseo field | Derived from | Example |
|---|---|---|
| Country code | §4.5.3 algorithm | `CSB` |
| Short country name | §4.5.2 algorithm | `MLKS Czarna Strzała Bytom` |
| Full country description | Raw `clubName` | `Miejsko-Ludowy Klub Sportowy "Czarna Strzała" (Bytom)` |

### 4.6 Paralympic classification

Always `false` for all athletes imported via this integration. Paralympic
athletes, if any, must be flagged manually.

### 4.7 Athlete status and archived records

- **Include archived athletes** (`isArchived: true`) in the lookup table. This
  allows historical matching of previously registered athletes.
- Status mapping:
  - `isArchived: false` + `licenceDate` is in the future (or missing) → **active**
  - `isArchived: false` + `licenceDate` is in the past → **licence expired**
  - `isArchived: true` → **archived/inactive**

The exact numeric status values to use in ianseo are the Developer's responsibility
to determine.

### 4.8 Sport class

The `sportClass` field (values such as `"Mistrzowska"`, `"I klasa"`, `"II klasa"`)
shall be **ignored**. It is not mapped to any ianseo field.

---

## 5. Summary of Field Mapping Table

| ianseo lookup field | Source                       | Transformation                               |
| ------------------- | ---------------------------- | -------------------------------------------- |
| Athlete code (ID)   | `licence`                    | Direct string                                |
| Family name         | `lastName`                   | Direct string                                |
| Given name          | `firstName`                  | Direct string                                |
| Gender              | `firstName`                  | Heuristic: ends with "a" → female, else male |
| Date of birth       | `birthYear`                  | `"{birthYear}-01-01"`                        |
| Country code        | `clubName`                   | 2–4 char code via §4.5.3 (e.g. `CSB`)       |
| Country description | `clubName`                   | Raw full club name string                    |
| Short country       | `clubName`                   | Abbreviated short name via §4.5.2            |
| Paralympic flag     | —                            | Always false                                 |
| Licence expiry      | `licenceDate`                | Direct date, used for status derivation      |
| Active status       | `isArchived` + `licenceDate` | See §4.7                                     |
| Name order          | —                            | Always Western (0)                           |
| Sport class         | —                            | Ignored                                      |

---

## 6. Known Gaps and Constraints

### ⚠ Gender heuristic is approximate

The name-ending heuristic will misclassify some athletes. Operators must plan
for a manual gender-review step at or after athlete registration. There is no
automated fallback.

### ⚠ No full date of birth

Year-only data means any age-class check at day/month granularity is impossible.
For PZŁucz, which uses calendar-year cutoffs for age classes, this is acceptable
in practice — but the operator must be aware the synthetic date `YYYY-01-01`
does not represent the athlete's actual birthday.

### ⚠ Code collision between clubs

Two different clubs may produce the same 2–4 character code (e.g. two clubs
whose proper name starts with the same letters in the same city). The
disambiguation algorithm (§4.5.4) must be implemented and verified against
the full 138-club list before deployment.

### ⚠ POST-only endpoint, no GET support

The adapter must handle the POST call internally. If the Sportzona endpoint
changes its authentication requirements (e.g. adds API keys), the adapter will
need to be updated. There is currently no authentication required.

### ⚠ No division or bow type in registry

Sportzona does not expose an athlete's bow type (Recurve, Compound, Barebow).
Division assignment happens at competition entry time, not during lookup import.

---

## 7. Open Questions

None — all business decisions have been confirmed by the operator.

---

## 8. Out of Scope

- Photo synchronisation (the `filename`/`fileId` fields exist in Sportzona but
  photo sync is not part of this feature)
- Ranking synchronisation
- Club names table synchronisation
- Automatic gender assignment without operator review
