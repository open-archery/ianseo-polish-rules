<?php

declare(strict_types=1);

require_once('Common/Fun_Modules.php');

$version = date('Y-m-d H:i:s');

$AllowedTypes = [1, 3, 6];

$SetType['PL']['descr'] = get_text('Setup-PL', 'Install');
$SetType['PL']['types'] = [];
$SetType['PL']['rules'] = [];

foreach ($AllowedTypes as $val) {
    $SetType['PL']['types']["$val"] = $TourTypes[$val];
}

// One sub-rule per type: full PZŁucz configuration
foreach ($AllowedTypes as $val) {
    $SetType['PL']['rules']["$val"] = [
        'Poland-Full',
    ];
}
