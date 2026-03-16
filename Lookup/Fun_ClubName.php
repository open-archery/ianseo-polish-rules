<?php
/**
 * Club name transformation functions for the PZŁucz Sportzona lookup adapter.
 *
 * Provides:
 *   pl_club_short_name(string $rawName): string  — abbreviated short name
 *   pl_club_code_base(string $rawName): string   — 2–4 char code (before collision resolution)
 *   pl_resolve_club_codes(array $clubs): array   — collision-resolved map
 *
 * No ianseo bootstrap required — this file is included by SportzonaProxy.php
 * which runs outside the ianseo session context.
 */

// ---------------------------------------------------------------------------
// Word-level map: each recognized organizational vocabulary word → its
// abbreviation token.  Empty string means the word is a connector (skip it
// but do not treat it as the start of the proper name).
//
// Matching is case-insensitive.  Hyphenated compound words (e.g.
// "Miejsko-Ludowy") are listed as single entries because they arrive as one
// whitespace-delimited token.
// ---------------------------------------------------------------------------
function pl_club_word_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [
        // Connectors — part of the prefix zone but contribute no abbreviation
        'i'                        => 'i',
        'i/lub'                    => '',
        'na'                       => '',
        'dla'                      => '',

        // Organizational vocabulary
        'Akademia'                 => 'A',
        'Akademicki'               => 'A',
        'Akademickie'              => 'A',
        'Akademickiego'              => 'A',
        'Budowlany'                => 'B',
        'Centrum'                  => 'C',
        'Cywilno-Wojskowy'         => 'CW',
        'Dzieci'                   => 'D',
        'Fundacja'                 => 'F',
        'Gimnastyczne'             => 'G',
        'Gminny'                   => 'G',
        'Górniczy'                 => 'G',
        'Inicjatyw'                => 'I',
        'Integracyjne'             => 'I',
        'Klub'                     => 'K',
        'Kołobrzeskie'             => 'K',
        'Koła'                     => 'K',
        'Krajowy'                  => 'K',
        'Kultury'                  => 'K',
        'Ligi'                     => 'L',
        'Ludowy'                   => 'L',
        'Łucznicze'                => 'Ł',
        'Łucznicza'                => 'Ł',
        'Łuczniczy'                => 'Ł',
        'Łuczników'                => 'Ł',
        'Miejski'                  => 'M',
        'Miejsko-Gminny'           => 'MG',
        'Miejsko-Ludowy'           => 'ML',
        'Międzyszkolny'            => 'M',
        'Młodzieżowy'              => 'Mł',
        'Mokotowski'               => 'Mo',
        'Morski'                   => 'M',
        'Niezależnych'             => 'N',
        'Niepełnosprawnych'        => 'N',
        'Obrony'                   => 'O',
        'Organizacja'              => 'O',
        'Ośrodek'                  => 'O',
        'Parafialny'               => 'P',
        'Polski'                   => 'P',
        'Polskie'                  => 'P',
        'Promocji'                 => 'P',
        'Rehabilitacji'            => 'R',
        'Rehabilitacyjne'          => 'R',
        'Rekreacyjne'              => 'R',
        'Robotniczy'               => 'R',
        'Rzeszowskie'              => 'R',
        'Sekcja'                   => 'S',
        'Społeczne'                => 'S',
        'Społeczno'                => 'S',
        'Społeczny'                => 'S',
        'Sportu'                   => 'S',
        'Sportowe'                 => 'S',
        'Sportowego'                 => 'S',
        'Sportowo'                 => 'S',
        'Sportowo-Rehabilitacyjne' => 'SR',
        'Sportowo-Rekreacyjne'     => 'SR',
        'Sportowy'                 => 'S',
        'Stowarzyszenie'           => 'S',
        'Szkolne'                  => 'S',
        'Szkolnego'                => 'S',
        'Szkolny'                  => 'S',
        'Środowiskowa'             => 'Ś',
        'Towarzystwo'              => 'T',
        'Uczniowski'               => 'U',
        'Uczniowskie'              => 'U',
        'Uczniowskiego'            => 'U',
        'Warszawski'               => 'W',
        'Wojskowy'                 => 'W',
        'Związek'                  => 'Z',
        'Związku'                  => 'Z',
        'Zrzeszenie'               => 'Z',
    ];

    return $map;
}

// ---------------------------------------------------------------------------
// Look up a single word in the word map (case-insensitive).
// Returns the abbreviation string (possibly '') if found, null if not in map.
// ---------------------------------------------------------------------------
function pl_word_map_lookup(string $word, array $wordMap): ?string
{
    $lower = mb_strtolower($word, 'UTF-8');
    foreach ($wordMap as $mapWord => $mapAbbr) {
        if (mb_strtolower($mapWord, 'UTF-8') === $lower) {
            return $mapAbbr;
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// Build a prefix abbreviation from a string of organizational words.
//
// Used for the quoted-name path, where everything before the first " is
// treated as the prefix zone.  Unknown words fall back to their first letter
// so that e.g. "Beskidzkie Zrzeszenie Sportowo-Rehabilitacyjne" → BZSR.
// ---------------------------------------------------------------------------
function pl_build_prefix_abbr(string $prefixText, array $wordMap): string
{
    $words = preg_split('/\s+/u', $prefixText, -1, PREG_SPLIT_NO_EMPTY);
    $parts = [];
    foreach ($words as $word) {
        $abbr = pl_word_map_lookup($word, $wordMap);
        if ($abbr !== null) {
            // Known word — use its abbreviation ('' for connectors)
            if ($abbr !== '') {
                $parts[] = $abbr;
            }
        } else {
            // Unknown word in prefix zone — fall back to first letter
            $parts[] = mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
        }
    }
    return implode('', $parts);
}

// ---------------------------------------------------------------------------
// Normalize whitespace: collapse multiple spaces, trim.
// ---------------------------------------------------------------------------
function pl_normalize_whitespace(string $s): string
{
    return trim(preg_replace('/\s+/u', ' ', $s));
}

// ---------------------------------------------------------------------------
// Parse a raw club name into its structural components.
//
// Two parsing paths:
//
//   QUOTED  — name contains "ProperName" in double quotes.
//             Everything before the first " is the prefix zone (all words
//             looked up; unknown words fall back to first letter).
//             Everything inside quotes (plus any trailing unquoted words
//             before the city) is the proper name.
//
//   UNQUOTED — no quotes.  Words are consumed left to right; each is looked
//              up in the word map.  The first word NOT in the map signals the
//              start of the proper name — all remaining words form it.
//
// Returns:
//   'city'       => string  (content of the trailing parentheses, or '')
//   'abbr'       => string  (concatenated prefix abbreviation, or '')
//   'properName' => string  (the distinctive club name)
//   'full'       => string  (normalized raw name)
// ---------------------------------------------------------------------------
function pl_parse_club_name(string $rawName): array
{
    $name = pl_normalize_whitespace($rawName);

    // Special case: Niezrzeszony
    if (mb_strtolower($name, 'UTF-8') === 'niezrzeszony (niezrzeszony)') {
        return [
            'city'       => 'Niezrzeszony',
            'abbr'       => '',
            'properName' => 'Niezrzeszony',
            'full'       => $name,
        ];
    }

    // Extract city from trailing (...)
    $city    = '';
    $working = $name;
    if (preg_match('/\(([^)]+)\)\s*$/u', $name, $m)) {
        $city    = trim($m[1]);
        $working = trim(preg_replace('/\s*\([^)]+\)\s*$/u', '', $name));
    }

    $wordMap    = pl_club_word_map();
    $abbr       = '';
    $properName = '';

    if (preg_match('/^(.*?)"([^"]+)"(.*)$/u', $working, $m)) {
        // QUOTED PATH
        $prefixText = trim($m[1]);
        $quotedName = trim($m[2]);
        $afterQuote = trim($m[3]);

        $abbr       = pl_build_prefix_abbr($prefixText, $wordMap);
        $properName = $afterQuote !== '' ? $quotedName . ' ' . $afterQuote : $quotedName;
    } else {
        // UNQUOTED PATH — stop at first unrecognized word
        $words       = preg_split('/\s+/u', $working, -1, PREG_SPLIT_NO_EMPTY);
        $abbrParts   = [];
        $properWords = [];
        $inProper    = false;

        foreach ($words as $word) {
            if ($inProper) {
                $properWords[] = $word;
                continue;
            }
            $lookup = pl_word_map_lookup($word, $wordMap);
            if ($lookup !== null) {
                // Known organizational word or connector
                if ($lookup !== '') {
                    $abbrParts[] = $lookup;
                }
            } else {
                // First unrecognized word = start of proper name
                $properWords[] = $word;
                $inProper      = true;
            }
        }

        $abbr       = implode('', $abbrParts);
        $properName = implode(' ', $properWords);
    }

    return [
        'city'       => $city,
        'abbr'       => $abbr,
        'properName' => trim($properName),
        'full'       => $name,
    ];
}

// ---------------------------------------------------------------------------
// Derive the short club name from a raw Sportzona clubName string.
// Format: "{ABBR} {ProperName} {City}"
// ---------------------------------------------------------------------------
function pl_club_short_name(string $rawName): string
{
    $p = pl_parse_club_name($rawName);

    if ($p['properName'] === 'Niezrzeszony' && $p['city'] === 'Niezrzeszony') {
        return 'Niezrzeszony';
    }

    $parts = [];
    if ($p['abbr'] !== '') {
        $parts[] = $p['abbr'];
    }
    if ($p['properName'] !== '') {
        $parts[] = $p['properName'];
    }
    if ($p['city'] !== '') {
        $parts[] = $p['city'];
    }

    return implode(' ', $parts);
}

// ---------------------------------------------------------------------------
// Derive the base club code (2–4 uppercase chars) from a raw clubName string.
// Collision resolution is NOT applied here — use pl_resolve_club_codes() for
// batch processing.
// ---------------------------------------------------------------------------
function pl_club_code_base(string $rawName): string
{
    $p = pl_parse_club_name($rawName);

    if ($p['properName'] === 'Niezrzeszony' && $p['city'] === 'Niezrzeszony') {
        return 'NIE';
    }

    $properName = $p['properName'];
    $city       = $p['city'];

    // Split proper name on whitespace and hyphens; skip purely numeric words.
    $words   = preg_split('/[\s\-]+/u', $properName, -1, PREG_SPLIT_NO_EMPTY);
    $letters = '';
    foreach ($words as $word) {
        if (preg_match('/^\d+$/u', $word)) {
            continue; // skip e.g. "11", "25"
        }
        $letters .= mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($letters, 'UTF-8') >= 4) {
            break;
        }
    }

    // Append first letter of city (if still room)
    if ($city !== '' && mb_strlen($letters, 'UTF-8') < 4) {
        $letters .= mb_strtoupper(mb_substr($city, 0, 1, 'UTF-8'), 'UTF-8');
    }

    // Extend with additional city letters until at least 2 chars
    if ($city !== '') {
        $cityUpper = mb_strtoupper($city, 'UTF-8');
        $i = 1;
        while (mb_strlen($letters, 'UTF-8') < 2 && $i < mb_strlen($cityUpper, 'UTF-8')) {
            $letters .= mb_substr($cityUpper, $i, 1, 'UTF-8');
            $i++;
        }
    }

    // Truncate to 4
    if (mb_strlen($letters, 'UTF-8') > 4) {
        $letters = mb_substr($letters, 0, 4, 'UTF-8');
    }

    return $letters;
}

// ---------------------------------------------------------------------------
// Resolve code collisions across a set of clubs.
//
// Input:  array of raw club name strings.
// Output: associative array keyed by normalized raw club name:
//   [
//     'Miejsko-Ludowy Klub Sportowy "Czarna Strzała" (Bytom)' => [
//         'code'      => 'CSB',
//         'shortName' => 'MLKS Czarna Strzała Bytom',
//         'fullName'  => 'Miejsko-Ludowy Klub Sportowy "Czarna Strzała" (Bytom)',
//     ],
//     ...
//   ]
//
// Collision rule: clubs sharing a base code are sorted alphabetically
// (case-insensitive) by normalized full name.  First keeps the base code;
// subsequent ones receive {CODE}2, {CODE}3, etc.
// ---------------------------------------------------------------------------
function pl_resolve_club_codes(array $clubNames): array
{
    // Deduplicate and compute base codes
    $distinct = [];
    foreach ($clubNames as $raw) {
        $normalized = pl_normalize_whitespace($raw);
        if (!isset($distinct[$normalized])) {
            $distinct[$normalized] = [
                'baseCode'  => pl_club_code_base($normalized),
                'shortName' => pl_club_short_name($normalized),
                'fullName'  => $normalized,
            ];
        }
    }

    // Group by base code
    $byCode = [];
    foreach ($distinct as $normalized => $info) {
        $byCode[$info['baseCode']][] = $normalized;
    }

    // Assign final codes
    $result = [];
    foreach ($byCode as $baseCode => $names) {
        if (count($names) === 1) {
            $n          = $names[0];
            $result[$n] = [
                'code'      => $baseCode,
                'shortName' => $distinct[$n]['shortName'],
                'fullName'  => $distinct[$n]['fullName'],
            ];
        } else {
            usort($names, function ($a, $b) {
                return mb_strtolower($a, 'UTF-8') <=> mb_strtolower($b, 'UTF-8');
            });
            foreach ($names as $idx => $n) {
                $code       = $idx === 0 ? $baseCode : $baseCode . ($idx + 1);
                $result[$n] = [
                    'code'      => $code,
                    'shortName' => $distinct[$n]['shortName'],
                    'fullName'  => $distinct[$n]['fullName'],
                ];
            }
        }
    }

    return $result;
}
