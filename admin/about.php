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
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
dol_include_once('/stocknotifier/lib/stocknotifier.lib.php');
dol_include_once('/stocknotifier/core/modules/modStocknotifier.class.php');

$langs->loadLangs(array("admin", "stocknotifier@stocknotifier"));

if (!$user->admin) {
    accessforbidden();
}

// Get module version dynamically
$module = new modStocknotifier($db);
$moduleVersion = $module->version ?: '1.0.0';

llxHeader('', $langs->trans("About"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("StocknotifierSetup"), $linkback, 'title_setup');

$head = stocknotifierAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans("StocknotifierSetup"), -1, 'generic');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("ModuleName").'</td>';
print '<td>Stock Notifier</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("Version").'</td>';
print '<td><strong>'.$moduleVersion.'</strong></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("Publisher").'</td>';
print '<td>Daxit Solutions</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("URL").'</td>';
print '<td><a href="https://daxit.be" target="_blank" rel="noopener noreferrer">https://daxit.be</a></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("License").'</td>';
print '<td>GPL v3+</td>';
print '</tr>';

print '</table>';

print dol_get_fiche_end();

print '<br><br>';

print '<div class="info">';
print '<p><strong>'.$langs->trans("Features").'</strong></p>';
print '<ul>';
print '<li>'.$langs->trans("FeatureRealTimeAlerts").'</li>';
print '<li>'.$langs->trans("FeatureMultiWarehouse").'</li>';
print '<li>'.$langs->trans("FeatureEmailNotifications").'</li>';
print '<li>'.$langs->trans("FeatureAntiSpam").'</li>';
print '</ul>';
print '</div>';

llxFooter();
$db->close();
