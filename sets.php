<?php
require_once('Common/Fun_Modules.php');
$version = date('Y-m-d H:i:s');

$AllowedTypes = array(1, 3, 6, 16, 37);

$SetType['PL']['descr'] = 'Polski Związek Łuczniczy';
$SetType['PL']['noc'] = 'POL';
$SetType['PL']['types'] = array();
$SetType['PL']['rules'] = array();

foreach ($AllowedTypes as $val) {
    $SetType['PL']['types']["$val"] = $TourTypes[$val];
}

// TourTypes 16 and 37 have no Polish translation in ianseo core
// (Common/Languages/pl/Tournament.php) — get_text() would otherwise render a
// raw "<b>[[Type_...]@[pl]@[Tournament]]</b>" placeholder here.
$SetType['PL']['types']['16'] = 'Runda dziecięca';
$SetType['PL']['types']['37'] = 'Podwójna runda 70m/50m';

// Category presets per TourType, selected from ianseo's sub-rule dropdown.
// Abstract naming: reuses ianseo's own translated Install.php sub-rule
// vocabulary where its meaning fits (SetAllClass/SetSeniorClass/
// SetYouthClass/SetMasterClass — same convention the official ianseo PL
// module uses), module-specific keys elsewhere (rendered untranslated by
// ianseo core — see gotchas.md). Resolved against these exact strings by
// lib.php's pl_preset_table()/pl_resolve_preset().
$SetType['PL']['rules']['1'] = array(
    'SetAllClass', 'SetSeniorClass', 'Poland-RU24', 'Poland-RU21', 'Poland-RU18',
);
$SetType['PL']['rules']['3'] = array(
    'SetAllClass', 'SetSeniorClass', 'Poland-RU24U21U18', 'SetYouthClass',
    'Poland-RU18', 'Poland-RU15', 'SetMasterClass',
);
$SetType['PL']['rules']['37'] = array(
    'SetAllClass', 'SetSeniorClass', 'Poland-RU24U21U18', 'SetYouthClass',
    'Poland-RU18', 'Poland-RU15',
);
$SetType['PL']['rules']['6'] = array(
    'SetAllClass', 'SetSeniorClass', 'SetYouthClass', 'Poland-RU18', 'Poland-RU15',
);
// TourType 16 (Children's Round) has one fixed, non-selectable configuration
// — Setup_16_PL.php never resolves a preset from this value.
$SetType['PL']['rules']['16'] = array(
    'SetAllClass',
);
