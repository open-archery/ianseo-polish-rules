<?php
/**
 * Gender derivation for the PZŁucz Sportzona lookup adapter.
 *
 * Provides:
 *   pl_first_given_name(string $rawFirstName): string  — strip a comma-joined second given name
 *   pl_derive_gender(string $firstName): string         — 'M' or 'W'
 *
 * No ianseo bootstrap required — this file is included by SportzonaProxy.php
 * which runs outside the ianseo session context.
 */

// ---------------------------------------------------------------------------
// Given names that do NOT end in "a" but are nonetheless female.
// Lowercase, no diacritics needed — none of these names have any.
// ---------------------------------------------------------------------------
function pl_gender_female_exceptions(): array
{
    return [
        'abigail', 'angeliki', 'ariel', 'dinah', 'elizabeth', 'kendall',
        'madeleine', 'miriam', 'nelly', 'nicole', 'nikol', 'noemi',
        'sophie', 'vivienne', 'zerin',
    ];
}

// ---------------------------------------------------------------------------
// Given names that DO end in "a" but are nonetheless male.
// ---------------------------------------------------------------------------
function pl_gender_male_exceptions(): array
{
    return [
        'barnaba', 'bonawentura', 'ilia', 'illia', 'jarema', 'kosma',
        'kuba', 'mykyta', 'nikita',
    ];
}

// ---------------------------------------------------------------------------
// Reduce a raw Sportzona firstName to its primary given name.
//
// Sportzona sometimes stores two given names joined by a comma in one field
// (e.g. "Artur,  Damian", "Marcin,Artur", or even a bare trailing comma
// "Józef,"). Only the part before the first comma is the athlete's primary
// given name. A firstName with no comma — including a legitimate
// space-separated double given name like "Jan Maciej" — is returned
// unchanged.
// ---------------------------------------------------------------------------
function pl_first_given_name(string $rawFirstName): string
{
    $commaPos = strpos($rawFirstName, ',');
    if ($commaPos === false) {
        return $rawFirstName;
    }
    return trim(substr($rawFirstName, 0, $commaPos));
}

// ---------------------------------------------------------------------------
// Derive gender from a given name.
//
// Checks the static exception tables above first (known Polish/Ukrainian/
// foreign given names that break the "Polish female names end in 'a'"
// rule), then falls back to that rule.
//
// Returns 'M' (male) or 'W' (female).
// ianseo checks $r->Gender == 'M' for male; anything else is treated as female.
//
// @param string $firstName Given name, already normalized via
//                           pl_first_given_name() if it may contain a comma.
// ---------------------------------------------------------------------------
function pl_derive_gender(string $firstName): string
{
    $trimmed = mb_strtolower(trim($firstName), 'UTF-8');
    if ($trimmed === '') {
        return 'M';
    }

    if (in_array($trimmed, pl_gender_female_exceptions(), true)) {
        return 'W';
    }
    if (in_array($trimmed, pl_gender_male_exceptions(), true)) {
        return 'M';
    }

    $lastChar = mb_substr($trimmed, -1, 1, 'UTF-8');
    return ($lastChar === 'a') ? 'W' : 'M';
}
