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

/**
 * Class InterfaceStocknotifiertriggers
 * Trigger for stock movements - sends alerts when stock falls below threshold
 */
class InterfaceStocknotifiertriggers extends DolibarrTriggers
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "stock";
        $this->description = "Stock Notifier triggers for stock movements";
        $this->version = '1.0.2';
        $this->picto = 'stock';
    }

    /**
     * Function called when a stock event occurs
     *
     * @param string $action Action code
     * @param CommonObject $object Object triggering the action
     * @param User $user User
     * @param Translate $langs Language
     * @param Conf $conf Configuration
     * @return int 0 if no processing needed, 1 if OK
     */
    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        if (!isModEnabled('stocknotifier')) {
            return 0;
        }

        // Only process stock-related actions
        $stockActions = array('STOCK_MOVEMENT', 'STOCK_CORRECT', 'STOCK_TRANSFER');
        if (!in_array($action, $stockActions, true)) {
            return 0;
        }

        // Check if product_id is available
        if (empty($object->product_id) || $object->product_id <= 0) {
            dol_syslog("Trigger '".$this->name."' - No product_id in object", LOG_DEBUG);
            return 0;
        }

        dol_syslog("Trigger '".$this->name."' for action '".$action."' - Product ID: ".$object->product_id, LOG_INFO);

        try {
            $stockAlert = new StockAlert($this->db);
            $product = $stockAlert->checkProductStock($object->product_id);
            
            if (is_array($product)) {
                $result = $stockAlert->sendAlertEmail($product);
                
                if ($result > 0) {
                    dol_syslog("Stock alert sent for product ID: ".$object->product_id, LOG_INFO);
                } elseif ($result < 0) {
                    dol_syslog("Failed to send stock alert for product ID ".$object->product_id.": ".$stockAlert->error, LOG_ERR);
                    setEventMessages($langs->trans("StockAlertSendError", $product['ref']), null, 'warnings');
                }
            }
        } catch (Exception $e) {
            dol_syslog("Trigger '".$this->name."' ERROR: ".$e->getMessage(), LOG_ERR);
            // Don't block the stock operation, just log the error
        }

        return 0;
    }
}
