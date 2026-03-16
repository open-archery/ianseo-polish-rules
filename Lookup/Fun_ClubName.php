<?php
/**
 * Club name transformation functions for the PZŁucz Sportzona lookup adapter.
 *
 * Provides:
 *   pl_club_short_name(string $rawName): string  — abbreviated short name
 *   pl_club_code(string $rawName): string         — 2–4 char code (before collision resolution)
 *   pl_resolve_club_codes(array $clubs): array    — collision-resolved map
 *
 * No ianseo bootstrap required — this file is included by SportzonaProxy.php
 * which runs outside the ianseo session context.
 */

// ---------------------------------------------------------------------------
// Prefix → abbreviation table.
// Sorted longest-first so that more specific prefixes are matched before
// shorter ones that share a prefix string (e.g. "Łuczniczy Uczniowski Klub
// Sportowy" must be tried before "Uczniowski Klub Sportowy").
// ---------------------------------------------------------------------------
function pl_club_prefix_table(): array
{
    static $table = null;
    if ($table !== null) {
        return $table;
    }

    $raw = [
        'Organizacja Środowiskowa Akademickiego Związku Sportowego' => 'OŚAZS',
        'Integracyjne Centrum Sportu i Rehabilitacji'               => 'ICSiR',
        'Stowarzyszenie Sportowo-Rehabilitacyjne'                   => 'SSR',
        'Stowarzyszenie Sportowo-Rekreacyjne'                       => 'SSRek',
        'Kołobrzeskie Stowarzyszenie Łuczników'                     => 'KSŁ',
        'Zrzeszenie Sportu i Rehabilitacji'                         => 'ZSiR',
        'Łuczniczy Uczniowski Klub Sportowy'                        => 'ŁUKS',
        'Uczniowski Ludowy Klub Sportowy'                           => 'ULKS',
        'Łuczniczy Ludowy Klub Sportowy'                            => 'ŁLKS',
        'Morski Robotniczy Klub Sportowy'                           => 'MRKS',
        'Łucznicze Towarzystwo Sportowe'                            => 'ŁTS',
        'Gminny Ośrodek Kultury i Sportu'                           => 'GOKiS',
        'Cywilno-Wojskowy Klub Sportowy'                            => 'CWKS',
        'Ludowy Uczniowski Klub Sportowy'                           => 'LUKS',
        'Polskie Towarzystwo Gimnastyczne'                          => 'PTG',
        'Społeczne Towarzystwo Sportowe'                            => 'STS',
        'Akademicki Klub Sportowy'                                  => 'AKS',
        'Stowarzyszenie Łucznicze'                                  => 'SŁ',
        'Łuczniczy Klub Sportowy'                                   => 'ŁKS',
        'Gminny Ludowy Klub Sportowy'                               => 'GLKS',
        'Uczniowski Klub Łuczniczy'                                 => 'UKŁ',
        'Warszawski Klub Łuczniczy'                                 => 'WKŁ',
        'Mokotowski Klub Łuczniczy'                                 => 'MKŁ',
        'Uczniowski Klub Sportowy'                                  => 'UKS',
        'Miejsko-Ludowy Klub Sportowy'                              => 'MLKS',
        'Społeczny Klub Sportowy'                                   => 'SKS',
        'Szkolny Klub Sportowy'                                     => 'SKS',
        'Ludowy Klub Sportowy'                                      => 'LKS',
        'Budowlany Klub Sportowy'                                   => 'BKS',
        'Parafialny Klub Sportowy'                                  => 'PKS',
        'Górniczy Klub Sportowy'                                    => 'GKS',
        'Miejski Klub Sportowy'                                     => 'MKS',
        'Młodzieżowy Klub Sportowy'                                 => 'MłKS',
        'Polski Związek Łuczniczy'                                  => 'PZŁ',
        'Towarzystwo Sportowe'                                      => 'TS',
        'Akademia Sportu'                                           => 'AS',
        'Klub Łuczniczy'                                            => 'KŁ',
        'Klub Sportowy'                                             => 'KS',
        'Stowarzyszenie'                                            => 'St.',
    ];

    // Sort by key length descending so longest prefixes are matched first.
    uksort($raw, function ($a, $b) {
        return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
    });

    $table = $raw;
    return $table;
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
// Returns an associative array:
//   'city'       => string  (content of the trailing parentheses, or '')
//   'abbr'       => string  (matched prefix abbreviation, or '')
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
    $city = '';
    if (preg_match('/\(([^)]+)\)\s*$/u', $name, $m)) {
        $city = trim($m[1]);
        // Remove the trailing (city) from the working string
        $working = trim(preg_replace('/\s*\([^)]+\)\s*$/u', '', $name));
    } else {
        $working = $name;
    }

    // Match organizational prefix (longest first)
    $abbr = '';
    foreach (pl_club_prefix_table() as $prefix => $abbreviation) {
        // Case-insensitive prefix match at the start of the string
        $prefixLen = mb_strlen($prefix, 'UTF-8');
        if (mb_strtolower(mb_substr($working, 0, $prefixLen, 'UTF-8'), 'UTF-8')
            === mb_strtolower($prefix, 'UTF-8')
        ) {
            $abbr    = $abbreviation;
            $working = trim(mb_substr($working, $prefixLen, null, 'UTF-8'));
            break;
        }
    }

    // Extract proper name from the remaining working string.
    // If quoted content exists, use it (plus any trailing unquoted words).
    $properName = $working;
    if (preg_match('/"([^"]+)"/u', $working, $qm)) {
        $quoted = $qm[1];
        // Look for text after the closing quote before end of string
        $afterQuote = trim(preg_replace('/^.*"[^"]*"\s*/u', '', $working));
        $properName = $afterQuote !== '' ? $quoted . ' ' . $afterQuote : $quoted;
    }
    $properName = trim($properName);

    return [
        'city'       => $city,
        'abbr'       => $abbr,
        'properName' => $properName,
        'full'       => $name,
    ];
}

// ---------------------------------------------------------------------------
// Derive the short club name from a raw Sportzona clubName string.
// ---------------------------------------------------------------------------
function pl_club_short_name(string $rawName): string
{
    $p = pl_parse_club_name($rawName);

    // Special case: Niezrzeszony
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

    // Special case: Niezrzeszony
    if ($p['properName'] === 'Niezrzeszony' && $p['city'] === 'Niezrzeszony') {
        return 'NIE';
    }

    $properName = $p['properName'];
    $city       = $p['city'];

    // Split proper name on whitespace and hyphens; filter pure-digit words.
    $words = preg_split('/[\s\-]+/u', $properName, -1, PREG_SPLIT_NO_EMPTY);
    $letters = '';
    foreach ($words as $word) {
        if (preg_match('/^\d+$/u', $word)) {
            // Skip purely numeric words (e.g. "11", "25")
            continue;
        }
        // Take the first character (UTF-8 aware)
        $first = mb_substr($word, 0, 1, 'UTF-8');
        $letters .= mb_strtoupper($first, 'UTF-8');
        if (mb_strlen($letters, 'UTF-8') >= 4) {
            break;
        }
    }

    // Append first letter of city
    if ($city !== '' && mb_strlen($letters, 'UTF-8') < 4) {
        $letters .= mb_strtoupper(mb_substr($city, 0, 1, 'UTF-8'), 'UTF-8');
    }

    // If still < 2 chars, extend with additional letters from city
    if ($city !== '') {
        $cityUpper = mb_strtoupper($city, 'UTF-8');
        $cityLen   = mb_strlen($cityUpper, 'UTF-8');
        $i = 1; // already used index 0 above
        while (mb_strlen($letters, 'UTF-8') < 2 && $i < $cityLen) {
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
// Input:  array of raw club name strings (may contain duplicates — each
//         distinct string is processed once).
//
// Output: associative array keyed by raw club name (normalized):
//   [
//     'Miejsko-Ludowy Klub Sportowy "Czarna Strzała" (Bytom)' => [
//         'code'      => 'CSB',
//         'shortName' => 'MLKS Czarna Strzała Bytom',
//         'fullName'  => 'Miejsko-Ludowy Klub Sportowy "Czarna Strzała" (Bytom)',
//     ],
//     ...
//   ]
//
// Collision resolution rule: clubs sharing a base code are sorted
// alphabetically (case-insensitive) by their normalized full name.
// The first in that order keeps the base code; subsequent ones receive
// {CODE}2, {CODE}3, etc.
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
            // No collision
            $n = $names[0];
            $result[$n] = [
                'code'      => $baseCode,
                'shortName' => $distinct[$n]['shortName'],
                'fullName'  => $distinct[$n]['fullName'],
            ];
        } else {
            // Sort alphabetically by normalized full name (case-insensitive)
            usort($names, function ($a, $b) {
                return mb_strtolower($a, 'UTF-8') <=> mb_strtolower($b, 'UTF-8');
            });
            foreach ($names as $idx => $n) {
                $code = $idx === 0 ? $baseCode : $baseCode . ($idx + 1);
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
