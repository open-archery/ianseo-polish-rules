<?php
require_once('Common/Fun_Modules.php');
$version = date('Y-m-d H:i:s');

// Minimal registration for the Poland ruleset so it appears in Setup
// Allowing a common target type (e.g., 70m Round = 3) by default.
$AllowedTypes = array(1,3,6);

$SetType['PL']['descr'] = get_text('Setup-PL', 'Install');
$SetType['PL']['types'] = array();
$SetType['PL']['rules'] = array();

foreach ($AllowedTypes as $val) {
    $SetType['PL']['types']["$val"] = $TourTypes[$val];
}

// No special preset rules; the key registers the set.
$SetType['PL']['rules']['3'] = array(
    'Poland-TeamsTop3of4',
);
