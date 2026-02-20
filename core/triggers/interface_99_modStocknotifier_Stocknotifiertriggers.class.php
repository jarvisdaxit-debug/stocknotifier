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
dol_include_once('/stocknotifier/class/notifierconfig.class.php');

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
        $this->version = '1.0.5';
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
        global $db;
        
        // Only process stock-related actions
        $stockActions = array(
            'STOCK_MOVEMENT',
            'STOCK_MOVEMENT_LINE',
            'PRODUCT_STOCK_UPDATE', 
            'STOCK_CORRECT',
            'STOCK_TRANSFER',
        );
        
        if (!in_array($action, $stockActions, true)) {
            return 0;
        }

        dol_syslog("===== STOCKNOTIFIER TRIGGER FIRED =====");
        dol_syslog("Action: " . $action);
        
        // Check module is enabled
        if (!isModEnabled('stocknotifier')) {
            dol_syslog("Module not enabled - skipping");
            return 0;
        }

        // Get product ID from various possible locations
        $product_id = 0;
        
        // For stock movement objects
        if (!empty($object) && is_object($object)) {
            // Common: fk_product (used in StockMovement)
            if (!empty($object->fk_product)) {
                $product_id = (int) $object->fk_product;
            }
            // Alternative: product_id
            elseif (!empty($object->product_id)) {
                $product_id = (int) $object->product_id;
            }
            // For product object itself
            elseif (!empty($object->id) && !empty($object->element) && $object->element == 'product') {
                $product_id = (int) $object->id;
            }
            // Alternative: rowid
            elseif (!empty($object->rowid)) {
                $product_id = (int) $object->rowid;
            }
        }
        
        dol_syslog("Product ID found: " . $product_id);

        if (empty($product_id) || $product_id <= 0) {
            dol_syslog("No valid product_id found - object: " . get_class($object));
            return 0;
        }

        // Get configuration
        $config = new NotifierConfig($db);
        $email = $config->getAlertEmail();
        
        dol_syslog("Configured email: " . ($email ? $email : 'NONE'));
        
        if (empty($email)) {
            dol_syslog("ERROR: No email configured - check module setup");
            return 0;
        }

        // Check stock and send alert
        try {
            $stockAlert = new StockAlert($db);
            $product = $stockAlert->checkProductStock($product_id);
            
            if (is_array($product) && !empty($product)) {
                dol_syslog("Stock alert condition met!");
                dol_syslog("Current stock: " . $product['stock_actuel'] . " / Threshold: " . $product['seuil_alerte']);
                
                $result = $stockAlert->sendAlertEmail($product);
                
                if ($result > 0) {
                    dol_syslog("SUCCESS: Email sent for product " . $product['ref']);
                } else {
                    dol_syslog("FAILURE: sendAlertEmail returned " . $result . " - Error: " . $stockAlert->error);
                }
            } else {
                dol_syslog("No alert needed - stock above threshold OR alert already sent");
            }
        } catch (Exception $e) {
            dol_syslog("EXCEPTION: " . $e->getMessage());
        }

        return 0;
    }
}
