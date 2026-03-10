<?php
require_once('Common/Fun_Modules.php');
$version = date('Y-m-d H:i:s');

$AllowedTypes = array(1, 3, 6);

$SetType['PL']['descr'] = get_text('Setup-PL', 'Install');
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
