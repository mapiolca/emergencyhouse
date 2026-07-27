<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
}
if (!$res) {
	http_response_code(500);
	exit;
}

dol_include_once('/emergencyhouse/class/offer.class.php');
dol_include_once('/emergencyhouse/class/request.class.php');
dol_include_once('/emergencyhouse/lib/emergencyhouse_access.lib.php');

$langs->loadLangs(array('emergencyhouse@emergencyhouse'));
if (!isModEnabled('emergencyhouse') || !emergencyhouseCanDo($user, 'match', 'read')) {
	accessforbidden();
}
$id = GETPOSTINT('id');
$entities = array_filter(array_map('intval', explode(',', (string) getEntity('offer'))));
if ($id <= 0 || empty($entities)) {
	accessforbidden();
}
$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'emergencyhouse_match';
$sql .= ' WHERE rowid = '.$id.' AND entity IN ('.implode(',', $entities).')';
$resql = $db->query($sql);
$match = $resql ? $db->fetch_object($resql) : false;
if (!is_object($match)) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$offer = new EmergencyHouseOffer($db);
$request = new EmergencyHouseRequest($db);
$offerLoaded = $offer->fetch((int) $match->fk_offer) > 0;
$requestLoaded = $request->fetch((int) $match->fk_request) > 0;
$explanation = json_decode((string) $match->explanation_snapshot, true);
$warnings = json_decode((string) $match->warnings_snapshot, true);

llxHeader('', $langs->trans('Match'));
print load_fiche_titre(
	$langs->trans('Match').' #'.((int) $match->rowid),
	'<a href="'.dol_buildpath('/emergencyhouse/match/list.php', 1).'">'.$langs->trans('BackToList').'</a>',
	'compress'
);
print '<div class="fichecenter"><div class="fichehalfleft"><table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('Offer').'</td><td>'.($offerLoaded ? $offer->getNomUrl(1) : '<span class="opacitymedium">#'.((int) $match->fk_offer).'</span>').'</td></tr>';
print '<tr><td>'.$langs->trans('Request').'</td><td>'.($requestLoaded ? $request->getNomUrl(1) : '<span class="opacitymedium">#'.((int) $match->fk_request).'</span>').'</td></tr>';
print '<tr><td>'.$langs->trans('MatchScore').'</td><td>'.dol_escape_htmltag(number_format((float) $match->score_total, 2, '.', '')).'%</td></tr>';
print '<tr><td>'.$langs->trans('MatchClass').'</td><td>'.$langs->trans('MatchClass'.ucfirst((string) $match->score_class)).'</td></tr>';
print '<tr><td>'.$langs->trans('AlgorithmVersion').'</td><td>'.dol_escape_htmltag((string) $match->algorithm_version).'</td></tr>';
print '<tr><td>'.$langs->trans('ParametersVersion').'</td><td>'.dol_escape_htmltag((string) $match->parameters_version).'</td></tr>';
print '<tr><td>'.$langs->trans('DateCalculation').'</td><td>'.dol_print_date($db->jdate($match->date_calculation), 'dayhour').'</td></tr>';
print '</table></div><div class="fichehalfright">';
print '<table class="border centpercent">';
foreach (array(
	'score_distance' => 'DistanceScore',
	'score_capacity' => 'CapacityScore',
	'score_dates' => 'DateScore',
	'score_type' => 'HousingTypeScore',
	'score_features' => 'FeatureScore',
) as $property => $label) {
	print '<tr><td class="titlefield">'.$langs->trans($label).'</td><td>'.dol_escape_htmltag(number_format((float) $match->{$property}, 2, '.', '')).'%</td></tr>';
}
print '<tr><td>'.$langs->trans('CapacityEvaluated').'</td><td>'.((int) $match->capacity_evaluated).'</td></tr>';
print '<tr><td>'.$langs->trans('NightsRequested').'</td><td>'.(isset($match->nights_requested) ? (int) $match->nights_requested : $langs->trans('NotDefined')).'</td></tr>';
print '<tr><td>'.$langs->trans('NightsCovered').'</td><td>'.(isset($match->nights_covered) ? (int) $match->nights_covered : $langs->trans('NotDefined')).'</td></tr>';
print '</table></div></div>';
if (is_array($warnings) && !empty($warnings)) {
	print load_fiche_titre($langs->trans('Warnings'));
	print '<ul>';
	foreach ($warnings as $warning) {
		print '<li>'.$langs->trans((string) $warning).'</li>';
	}
	print '</ul>';
}
if (is_array($explanation)) {
	print load_fiche_titre($langs->trans('MatchExplanation'));
	print '<pre class="wordbreak">'.dol_escape_htmltag((string) json_encode($explanation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'</pre>';
}
if ((int) $match->status === 1 && emergencyhouseCanDo($user, 'allocation', 'write')) {
	print '<div class="tabsAction"><a class="butAction" href="'.dol_buildpath('/emergencyhouse/allocation/create.php', 1).'?source=m:'.((int) $match->rowid).'">'.$langs->trans('CreateAllocation').'</a></div>';
}
llxFooter();
$db->close();
