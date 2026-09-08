<?php
require_once('Common/Fun_Modules.php');
$version = date('Y-m-d H:i:s');

$AllowedTypes = array(1, 3, 6);

// A literal, not get_text('Setup-PL', 'Install'): that key exists in none of
// ianseo's language packs, and get_text() renders a missing key as the marker
// "<b>[[Setup-PL]@[en]@[Install]]</b>" rather than falling back to the key. The
// packs are re-downloaded on every ianseo update, so adding the key upstream
// would not survive either. ianseo's own PL set does the same thing.
$SetType['PL']['descr'] = 'Polski Związek Łuczniczy';
// Prefills the competition's country on the new-tournament form
// (Tournament/Fun_Index.js), only when it is new or still empty.
$SetType['PL']['noc'] = 'POL';
$SetType['PL']['types'] = array();
$SetType['PL']['rules'] = array();

foreach ($AllowedTypes as $val) {
    $SetType['PL']['types']["$val"] = $TourTypes[$val];
}

// One sub-rule per type: full PZŁucz configuration
foreach ($AllowedTypes as $val) {
    $SetType['PL']['rules']["$val"] = array(
        'Poland-Full',
    );
}

// Type 3 (70m Round) only: Podwójna runda — every class shoots double the
// qualification sessions (§2.11.1.1 / §2.11.1.2).
$SetType['PL']['rules']['3'][] = 'Poland-4x70m';
