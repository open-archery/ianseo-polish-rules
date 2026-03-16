<?php
/**
 * SportzonaProxy.php — PZŁucz Sportzona athlete lookup adapter.
 *
 * This script is fetched by ianseo via HTTP GET using the %-path convention
 * (LupPath = '%Modules/Sets/PL/Lookup/SportzonaProxy.php').
 *
 * It internally POSTs to the Sportzona registry, transforms the response into
 * the JSON array format expected by ianseo's extranet/JSON lookup branch, and
 * outputs it.
 *
 * IMPORTANT: This script must NOT require config.php or call CheckTourSession().
 * It runs outside the ianseo session context (called via file_get_contents from
 * the ianseo process itself).
 */

require_once __DIR__ . '/Fun_ClubName.php';

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

/** Sportzona endpoint URL */
define('SZ_ENDPOINT', 'https://sportzona.pl/wsx/players/list/discipline');

/** POST body sent to Sportzona — retrieves all Polish archery registrations */
define('SZ_REQUEST_BODY', json_encode([
    'mainList'     => true,
    'discipline'   => 'archery',
    'pageSize'     => 10000,
    'page'         => 1,
    'count'        => true,
    'fltLName'     => '',
    'fltBirth'     => '',
    'fltLicence'   => '',
    'fltClub'      => '',
    'fltRR'        => '',
    'fltRP'        => '',
    'fltRS'        => '',
    'isPublicList' => true,
]));

/** ianseo LueStatus values */
define('STATUS_ACTIVE',   1);
define('STATUS_EXPIRED',  5);
define('STATUS_ARCHIVED', 8);

// ---------------------------------------------------------------------------
// Fetch data from Sportzona via cURL POST
// ---------------------------------------------------------------------------

/**
 * Fetch the raw Sportzona response.
 *
 * Returns the decoded response object on success, or null on any failure.
 * Sets an appropriate HTTP status code header on failure.
 *
 * @return object|null
 */
function sz_fetch_players(): ?object
{
    $ch = curl_init(SZ_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => SZ_REQUEST_BODY,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json;charset=UTF-8',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== CURLE_OK || $body === false) {
        // Network or cURL error
        http_response_code(503);
        return null;
    }

    if ($httpCode !== 200) {
        http_response_code(502);
        return null;
    }

    $decoded = json_decode($body);
    if ($decoded === null || !isset($decoded->players)) {
        http_response_code(502);
        return null;
    }

    return $decoded;
}

// ---------------------------------------------------------------------------
// Status derivation
// ---------------------------------------------------------------------------

/**
 * Derive the ianseo LueStatus integer from Sportzona player fields.
 *
 * @param bool        $isArchived  Value of the isArchived field
 * @param string|null $licenceDate Licence expiry date (YYYY-MM-DD) or null/absent
 * @return int
 */
function sz_derive_status(bool $isArchived, ?string $licenceDate): int
{
    if ($isArchived) {
        return STATUS_ARCHIVED;
    }

    if (!empty($licenceDate)) {
        $today = date('Y-m-d');
        if ($licenceDate < $today) {
            return STATUS_EXPIRED;
        }
    }

    return STATUS_ACTIVE;
}

// ---------------------------------------------------------------------------
// Gender heuristic
// ---------------------------------------------------------------------------

/**
 * Derive gender from a given name.
 * Polish female names typically end with the letter "a".
 *
 * Returns 'M' (male) or 'W' (female).
 * ianseo checks $r->Gender == 'M' for male; anything else is treated as female.
 *
 * @param string $firstName
 * @return string 'M' or 'W'
 */
function sz_derive_gender(string $firstName): string
{
    $trimmed = mb_strtolower(trim($firstName), 'UTF-8');
    if ($trimmed === '') {
        return 'M';
    }
    $lastChar = mb_substr($trimmed, -1, 1, 'UTF-8');
    return ($lastChar === 'a') ? 'W' : 'M';
}

// ---------------------------------------------------------------------------
// Main transformation
// ---------------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');

$response = sz_fetch_players();

if ($response === null) {
    // Error headers already set by sz_fetch_players()
    echo '[]';
    exit;
}

$players = $response->players;

if (empty($players) || !is_array($players)) {
    echo '[]';
    exit;
}

// Collect all distinct club names first (needed for batch collision resolution)
$clubNames = [];
foreach ($players as $player) {
    $clubName = isset($player->clubName) ? (string)$player->clubName : '';
    if ($clubName !== '') {
        $clubNames[] = $clubName;
    }
}

// Resolve codes with collision detection across the full club set
$clubMap = pl_resolve_club_codes($clubNames);

// Transform each player record into the ianseo JSON format
$output = [];
foreach ($players as $player) {
    // Skip athletes without a licence number (cannot be matched in ianseo)
    $licence = isset($player->licence) ? trim((string)$player->licence) : '';
    if ($licence === '') {
        continue;
    }

    $firstName  = isset($player->firstName)  ? (string)$player->firstName  : '';
    $lastName   = isset($player->lastName)   ? (string)$player->lastName   : '';
    $birthYear  = isset($player->birthYear)  ? (int)$player->birthYear     : 0;
    $isArchived = isset($player->isArchived) ? (bool)$player->isArchived   : false;
    $licenceDate = isset($player->licenceDate) ? (string)$player->licenceDate : '';
    $rawClubName = isset($player->clubName) ? pl_normalize_whitespace((string)$player->clubName) : '';

    // Build the date-of-birth string
    $birthDate = ($birthYear > 0) ? "{$birthYear}-01-01" : '1900-01-01';

    // Derive club fields
    if ($rawClubName !== '' && isset($clubMap[$rawClubName])) {
        $clubEntry    = $clubMap[$rawClubName];
        $countryCode  = $clubEntry['code'];
        $countryName  = $clubEntry['fullName'];
        $shortCountry = $clubEntry['shortName'];
    } else {
        // Fallback for athletes with no club name
        $countryCode  = 'NIE';
        $countryName  = 'Niezrzeszony';
        $shortCountry = 'Niezrzeszony';
    }

    $output[] = (object)[
        'WaId'             => $licence,
        'FamilyName'       => $lastName,
        'GivenName'        => $firstName,
        'Gender'           => sz_derive_gender($firstName),
        'Para'             => false,
        'BirthDate'        => $birthDate,
        'CountryCode'      => $countryCode,
        'CountryName'      => $countryName,
        'ShortCountryName' => $shortCountry,
        'NameOrder'        => 0,
        'Status'           => sz_derive_status($isArchived, $licenceDate !== '' ? $licenceDate : null),
    ];
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
