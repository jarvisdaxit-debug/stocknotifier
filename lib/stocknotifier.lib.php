<?php
/* Copyright (C) 2025 Daxit Solutions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

function stocknotifierAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("stocknotifier@stocknotifier");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/stocknotifier/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath("/stocknotifier/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'stocknotifier');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'stocknotifier', 'remove');

    return $head;
}
