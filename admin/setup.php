<?php
/* Copyright (C) 2025 Daxit Solutions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/stocknotifier/lib/stocknotifier.lib.php');
dol_include_once('/stocknotifier/class/notifierconfig.class.php');
dol_include_once('/product/class/entrepot.class.php', 'Entrepot');

$langs->loadLangs(array("admin", "stocknotifier@stocknotifier"));

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action == 'setvar') {
    $alert_email = GETPOST('STOCKNOTIFIER_ALERT_EMAIL', 'email');
    $exclude_nosell = GETPOST('STOCKNOTIFIER_EXCLUDE_NOSELL', 'int') ? 1 : 0;
    $exclude_nobuy = GETPOST('STOCKNOTIFIER_EXCLUDE_NOBUY', 'int') ? 1 : 0;

    dolibarr_set_const($db, 'STOCKNOTIFIER_ALERT_EMAIL', $alert_email, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'STOCKNOTIFIER_EXCLUDE_NOSELL', $exclude_nosell, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'STOCKNOTIFIER_EXCLUDE_NOBUY', $exclude_nobuy, 'chaine', 0, '', $conf->entity);

    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

if ($action == 'setwarehouses') {
    $warehouses_selected = GETPOST('stocknotifier_warehouses', 'array:int');
    $warehouses_str = !empty($warehouses_selected) ? implode(',', $warehouses_selected) : '';
    dolibarr_set_const($db, 'STOCKNOTIFIER_WAREHOUSES', $warehouses_str, 'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

if ($action == 'resetalerts') {
    $config = new NotifierConfig($db);
    $config->resetAllAlertsSent();
    setEventMessages($langs->trans("AlertsResetSuccess"), null, 'mesgs');
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

llxHeader('', $langs->trans("StocknotifierSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("StocknotifierSetup"), $linkback, 'title_setup');

$head = stocknotifierAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("StocknotifierSetup"), -1, 'generic');

$self = dol_escape_htmltag($_SERVER["PHP_SELF"]);

/* ==============================
   FORM 1 - General parameters
   ============================== */

print '<form method="POST" action="'.$self.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="setvar">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("AlertEmailAddress").'</td>';
print '<td>';
print '<input type="email" name="STOCKNOTIFIER_ALERT_EMAIL" class="minwidth300" value="'.dol_escape_htmltag(getDolGlobalString('STOCKNOTIFIER_ALERT_EMAIL')).'">';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("ExcludeProductsNotForSale").'</td>';
print '<td>';
print '<input type="checkbox" name="STOCKNOTIFIER_EXCLUDE_NOSELL" value="1"'.(getDolGlobalInt('STOCKNOTIFIER_EXCLUDE_NOSELL', 1) ? ' checked="checked"' : '').'>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("ExcludeProductsNotForPurchase").'</td>';
print '<td>';
print '<input type="checkbox" name="STOCKNOTIFIER_EXCLUDE_NOBUY" value="1"'.(getDolGlobalInt('STOCKNOTIFIER_EXCLUDE_NOBUY', 0) ? ' checked="checked"' : '').'>';
print '</td>';
print '</tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print '<br>';

/* ==============================
   FORM 2 - Warehouses selection
   ============================== */

print '<form method="POST" action="'.$self.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="setwarehouses">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("WarehousesToMonitor").'</td>';
print '<td></td>';
print '</tr>';

$current_warehouses = getDolGlobalString('STOCKNOTIFIER_WAREHOUSES', '');
$current_array = !empty($current_warehouses) ? array_map('intval', explode(',', $current_warehouses)) : array();

$warehouse = new Entrepot($db);
$warehouses_list = $warehouse->list_array(1);

if (!empty($warehouses_list)) {
    foreach ($warehouses_list as $wh_id => $wh_label) {
        print '<tr class="oddeven">';
        print '<td>'.dol_escape_htmltag($wh_label).' (ID: '.(int) $wh_id.')</td>';
        print '<td class="right">';
        $checked = in_array((int) $wh_id, $current_array, true) ? ' checked="checked"' : '';
        print '<input type="checkbox" name="stocknotifier_warehouses[]" value="'.(int) $wh_id.'"'.$checked.'>';
        print '</td>';
        print '</tr>';
    }
} else {
    print '<tr class="oddeven">';
    print '<td colspan="2" class="opacitymedium">'.$langs->trans("NoWarehouseFound").'</td>';
    print '</tr>';
}

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

print '<br>';

print '<div class="tabsAction">';
print '<a class="butAction" href="'.$self.'?action=resetalerts&token='.newToken().'">'.$langs->trans("ResetAllAlerts").'</a>';
print '</div>';

print '<br>';

print '<div class="info">';
print '<p><strong>'.$langs->trans("TriggerInstructions").'</strong></p>';
print '<p>'.$langs->trans("TriggerDescription").'</p>';
print '</div>';

llxFooter();
$db->close();