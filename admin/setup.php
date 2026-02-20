<?php
/* Copyright (C) 2025 Daxit Solutions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

// ========== Robust main.inc.php includes ==========
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");
// ================================================

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/stocknotifier/lib/stocknotifier.lib.php');
dol_include_once('/stocknotifier/class/notifierconfig.class.php');

$langs->loadLangs(array("admin", "stocknotifier@stocknotifier"));

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action == 'setvar') {
    $alert_email = GETPOST('STOCKNOTIFIER_ALERT_EMAIL', 'email');
    $exclude_nosell = GETPOST('STOCKNOTIFIER_EXCLUDE_NOSELL', 'int') ? 1 : 0;
    $exclude_nobuy = GETPOST('STOCKNOTIFIER_EXCLUDE_NOBUY', 'int') ? 1 : 0;
    
    // Get selected warehouses
    $selected_warehouses = GETPOST('STOCKNOTIFIER_WAREHOUSES', 'array');
    $warehouses_value = !empty($selected_warehouses) ? implode(',', array_map('intval', $selected_warehouses)) : '';

    $res = dolibarr_set_const($db, 'STOCKNOTIFIER_ALERT_EMAIL', $alert_email, 'chaine', 0, '', $conf->entity);
    $res = dolibarr_set_const($db, 'STOCKNOTIFIER_EXCLUDE_NOSELL', $exclude_nosell, 'chaine', 0, '', $conf->entity);
    $res = dolibarr_set_const($db, 'STOCKNOTIFIER_EXCLUDE_NOBUY', $exclude_nobuy, 'chaine', 0, '', $conf->entity);
    $res = dolibarr_set_const($db, 'STOCKNOTIFIER_WAREHOUSES', $warehouses_value, 'chaine', 0, '', $conf->entity);

    if ($res > 0) {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }

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

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
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
print '<input type="checkbox" name="STOCKNOTIFIER_EXCLUDE_NOSELL" value="1"'.(getDolGlobalInt('STOCKNOTIFIER_EXCLUDE_NOSELL', 1) ? ' checked' : '').'>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("ExcludeProductsNotForPurchase").'</td>';
print '<td>';
print '<input type="checkbox" name="STOCKNOTIFIER_EXCLUDE_NOBUY" value="1"'.(getDolGlobalInt('STOCKNOTIFIER_EXCLUDE_NOBUY', 0) ? ' checked' : '').'>';
print '</td>';
print '</tr>';

// Warehouse selection
print '<tr class="oddeven">';
print '<td colspan="2">';
print '<strong>'.$langs->trans("WarehousesToMonitor").'</strong>';
print '<br>';
print '<span class="opacitymedium">'.$langs->trans("WarehousesToMonitorHelp").'</span>';
print '</td>';
print '</tr>';

// Fetch all active warehouses
$sql = "SELECT e.rowid, e.libelle as label, e.lieu as location";
$sql .= " FROM ".MAIN_DB_PREFIX."entrepot as e";
$sql .= " WHERE e.statut = 1";
$sql .= " ORDER BY e.libelle ASC";

dol_syslog("admin/setup.php::Fetch warehouses", LOG_DEBUG);
$resql = $db->query($sql);
$warehouses = array();
if ($resql) {
    $num = $db->num_rows($resql);
    dol_syslog("admin/setup.php::Found ".$num." active warehouses", LOG_DEBUG);
    while ($obj = $db->fetch_object($resql)) {
        $warehouses[] = $obj;
    }
    $db->free($resql);
} else {
    dol_syslog("admin/setup.php::Error fetching warehouses: ".$db->lasterror(), LOG_ERR);
}

// Get selected warehouses
$saved_warehouses = getDolGlobalString('STOCKNOTIFIER_WAREHOUSES', '');
$saved_warehouse_ids = !empty($saved_warehouses) ? explode(',', $saved_warehouses) : array();

if (!empty($warehouses)) {
    print '<tr class="oddeven">';
    print '<td colspan="2">';
    print '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; margin: 10px 0;">';
    
    $i = 0;
    foreach ($warehouses as $warehouse) {
        $checked = in_array($warehouse->rowid, $saved_warehouse_ids) ? ' checked' : '';
        $label = dol_escape_htmltag($warehouse->label);
        $location = !empty($warehouse->location) ? ' - '.dol_escape_htmltag($warehouse->location) : '';
        
        print '<div style="margin: 5px 0;">';
        print '<label style="display: inline-flex; align-items: center; cursor: pointer;">';
        print '<input type="checkbox" name="STOCKNOTIFIER_WAREHOUSES[]" value="'.intval($warehouse->rowid).'"'.$checked.' style="margin-right: 8px;">';
        print '<strong>'.$label.'</strong>'.$location;
        print '</label>';
        print '</div>';
        
        $i++;
    }
    
    print '</div>';
    print '</td>';
    print '</tr>';
} else {
    print '<tr class="oddeven">';
    print '<td colspan="2">';
    print '<span class="warning">'.$langs->trans("NoActiveWarehousesFound").'</span>';
    print '</td>';
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
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=resetalerts&token='.newToken().'">'.$langs->trans("ResetAllAlerts").'</a>';
print '</div>';

print '<br>';

print '<div class="info">';
print '<p><strong>'.$langs->trans("TriggerInstructions").'</strong></p>';
print '<p>'.$langs->trans("TriggerDescription").'</p>';
print '</div>';

llxFooter();
$db->close();
