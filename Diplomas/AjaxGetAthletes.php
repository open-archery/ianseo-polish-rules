<?php
/**
 * AjaxGetAthletes.php - AJAX endpoint for athlete search (custom diploma picker).
 *
 * GET parameters:
 *   q - Search term (name fragment)
 *
 * Returns JSON array of matching athletes.
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('Fun_Diploma.php');

header('Content-Type: application/json; charset=utf-8');

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($search) < 2) {
	echo json_encode(array());
	exit;
}

$athletes = pl_diploma_get_all_athletes($search);

// Limit to 20 results for performance
$athletes = array_slice($athletes, 0, 20);

echo json_encode($athletes);
