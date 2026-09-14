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

// One sub-rule per type: full PZŁucz configuration
foreach ($AllowedTypes as $val) {
    $SetType['PL']['rules']["$val"] = array(
        'Poland-Full',
    );
}
