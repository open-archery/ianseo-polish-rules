# 🏹 Open Archery \ IANSEO — Polish Archery Federation Rules

![Version](https://img.shields.io/github/v/release/open-archery/ianseo-polish-rules) ![License](https://img.shields.io/github/license/open-archery/ianseo-polish-rules)

## About

PZŁucz ruleset module for the [ianseo](https://www.ianseo.net/) tournament management system, conforming to [Polish Archery Federation (Polski Związek Łuczniczy)](https://archery.pl/) competition regulations.

Drop this module into an existing ianseo installation to enable Polish tournament types, age categories, distances, and scoring rules out of the box.

- UI in Polish; code comments in English
- No build step — pure PHP loaded directly by ianseo
- All code lives in `Modules/Sets/PL/`; the ianseo core is never modified

## Features

- **Tournament setup** — FITA 1440 (type 1), single-distance 70 m (type 3), and indoor 18 m (type 6) with correct divisions, age categories, distances, target faces, and elimination brackets per Polish Archery Federation rules
- **Post-elimination unique ranking** — losers of the same elimination round receive unique places (Regulation §2.6.5) rather than a shared rank; ties broken by match score then qualification rank
- **Sportzona athlete lookup** — syncs the Polish Archery Federation live athlete registry from [Sportzona.pl](https://sportzona.pl); maps divisions, derives gender, and generates club codes automatically
- **Batch import by licence number** — paste a list of licence numbers to register athletes in bulk; auto-resolves age class and detects duplicates
- **PDF diploma generation** — generates Polish-language diplomas in bulk for individual, team, and mixed events from qualification or finals results
- **Licence number on result PDFs** — shows each athlete's licence number as a "Nr lic." column in qualification and finals ranking printouts

## Quick Start

### Prerequisites

- Working [ianseo](https://www.ianseo.net/) installation
- PHP 8.0+

### Installation

Clone the repository directly into the `Modules/Sets/PL/` directory inside your ianseo root:

```bash
git clone https://github.com/open-archery/ianseo-polish-rules Modules/Sets/PL
```

Or download the ZIP from GitHub and extract its contents into `Modules/Sets/PL/`.

No database migration is needed — all tables are created automatically on first use.

### First Run

1. Log in to ianseo and create a new tournament.
2. Select ruleset **Poland (PZŁucz)** → sub-rule **Poland-Full**.
3. Choose a tournament type: **FITA 1440**, **Single distance 70 m**, or **Indoor 18 m**.
4. Complete the setup wizard — divisions, age classes, and schedule are pre-configured.
5. _(Optional)_ Install Sportzona sync: _Participants → Sync → Install Sportzona_.
6. _(Optional)_ Import athletes by licence: _Participants → Sync → Import by licence_.
7. Diplomas are available under _Printouts → Diplomas_.

## Roadmap & Feedback

Bug reports and feature requests are tracked on [GitHub Issues](https://github.com/open-archery/ianseo-polish-rules/issues). Contributions and proposals are welcome.

New features follow the [OpenSpec](https://openspec.dev) spec-driven workflow — see `.github/agents/workflow.md` for details.

## License

Released into the public domain under the [Unlicense](UNLICENSE). Free to use, modify, and distribute without restriction.
