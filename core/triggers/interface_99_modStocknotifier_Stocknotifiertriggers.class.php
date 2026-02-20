<?php
/* Copyright (C) 2025 Daxit Solutions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/stocknotifier/class/stockalert.class.php');

class InterfaceStocknotifiertriggers extends DolibarrTriggers
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "stock";
        $this->description = "Stock Notifier triggers for stock movements";
        $this->version = '1.0.0';
        $this->picto = 'generic';
    }

    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        if (!isModEnabled('stocknotifier')) {
            return 0;
        }

        dol_syslog("Trigger '".$this->name."' for action '".$action."' launched by ".__FILE__.". id=".(isset($object->id) ? $object->id : 'unknown'));

        $stockActions = array(
            'STOCK_MOVEMENT',
            'STOCK_CORRECT',
            'STOCK_TRANSFER'
        );

        if (in_array($action, $stockActions)) {
            if (isset($object->product_id) && $object->product_id > 0) {
                $stockAlert = new StockAlert($this->db);
                
                $product = $stockAlert->checkProductStock($object->product_id);
                
                if (is_array($product)) {
                    $result = $stockAlert->sendAlertEmail($product);
                    
                    if ($result > 0) {
                        dol_syslog("Stock alert sent for product ID: ".$object->product_id, LOG_INFO);
                    } elseif ($result < 0) {
                        dol_syslog("Failed to send stock alert: ".$stockAlert->error, LOG_ERR);
                    }
                }
            }
        }

        return 0;
    }
}
