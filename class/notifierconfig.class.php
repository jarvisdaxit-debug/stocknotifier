<?php
/* Copyright (C) 2025 Daxit Solutions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

class NotifierConfig extends CommonObject
{
    public $element = 'notifierconfig';
    public $table_element = 'stocknotifier_config';
    public $picto = 'generic';

    public $alert_email;
    public $exclude_nosell;
    public $exclude_nobuy;
    public $alert_sent;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getAlertEmail()
    {
        global $conf;
        $email = getDolGlobalString('STOCKNOTIFIER_ALERT_EMAIL');
        if (empty($email)) {
            $email = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
        }
        return $email;
    }

    public function shouldExcludeNoSell()
    {
        global $conf;
        return getDolGlobalInt('STOCKNOTIFIER_EXCLUDE_NOSELL', 1);
    }

    public function shouldExcludeNoBuy()
    {
        global $conf;
        return getDolGlobalInt('STOCKNOTIFIER_EXCLUDE_NOBUY', 0);
    }

    public function isAlertSent($product_id)
    {
        global $conf;
        $alerted = getDolGlobalString('STOCKNOTIFIER_ALERT_SENT');
        $alertedArray = !empty($alerted) ? explode(',', $alerted) : array();
        return in_array($product_id, $alertedArray);
    }

    public function markAlertSent($product_id)
    {
        global $conf;
        $alerted = getDolGlobalString('STOCKNOTIFIER_ALERT_SENT');
        $alertedArray = !empty($alerted) ? explode(',', $alerted) : array();
        
        if (!in_array($product_id, $alertedArray)) {
            $alertedArray[] = $product_id;
            $newValue = implode(',', $alertedArray);
            dolibarr_set_const($this->db, 'STOCKNOTIFIER_ALERT_SENT', $newValue, 'chaine', 0, '', $conf->entity);
        }
    }

    public function resetAlertSent($product_id)
    {
        global $conf;
        $alerted = getDolGlobalString('STOCKNOTIFIER_ALERT_SENT');
        $alertedArray = !empty($alerted) ? explode(',', $alerted) : array();
        
        $key = array_search($product_id, $alertedArray);
        if ($key !== false) {
            unset($alertedArray[$key]);
            $newValue = implode(',', array_values($alertedArray));
            dolibarr_set_const($this->db, 'STOCKNOTIFIER_ALERT_SENT', $newValue, 'chaine', 0, '', $conf->entity);
        }
    }

    public function resetAllAlertsSent()
    {
        global $conf;
        dolibarr_set_const($this->db, 'STOCKNOTIFIER_ALERT_SENT', '', 'chaine', 0, '', $conf->entity);
    }
}
