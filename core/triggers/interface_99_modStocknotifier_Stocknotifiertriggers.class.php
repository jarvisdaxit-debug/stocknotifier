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
        $this->version = '1.0.4';
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
        
        dol_syslog("========== STOCKNOTIFIER TRIGGER START ==========");
        dol_syslog("Action: " . $action);
        dol_syslog("Object type: " . get_class($object));
        
        if (!isModEnabled('stocknotifier')) {
            dol_syslog("Module not enabled - exiting");
            return 0;
        }

        // Check for product-related actions (including stock movements)
        // Dolibarr uses these trigger names
        $stockActions = array(
            'STOCK_MOVEMENT',
            'PRODUCT_STOCK_UPDATE', 
            'STOCK_CORRECT',
            'STOCK_TRANSFER',
            'PRODUCT_CREATE',  // Check after creation too
        );
        
        if (!in_array($action, $stockActions, true)) {
            dol_syslog("Action not in stock actions list - exiting");
            return 0;
        }

        // Try to get product_id from different possible locations
        $product_id = 0;
        
        // Try object->product_id
        if (!empty($object->product_id)) {
            $product_id = $object->product_id;
        }
        // Try object->fk_product (some triggers use this)
        elseif (!empty($object->fk_product)) {
            $product_id = $object->fk_product;
        }
        // Try object->id (for product object itself)
        elseif (!empty($object->id) && !empty($object->element) && $object->element == 'product') {
            $product_id = $object->id;
        }

        if (empty($product_id) || $product_id <= 0) {
            dol_syslog("No product_id found - object: " . print_r($object, true));
            return 0;
        }

        dol_syslog("Processing product ID: " . $product_id);

        // Check if email is configured
        dol_include_once('/stocknotifier/class/notifierconfig.class.php');
        $config = new NotifierConfig($db);
        $email = $config->getAlertEmail();
        
        if (empty($email)) {
            dol_syslog("ERROR: No email configured for alerts!");
            return 0;
        }
        
        dol_syslog("Email configured: " . $email);

        // Check stock level
        try {
            $stockAlert = new StockAlert($db);
            $product = $stockAlert->checkProductStock($product_id);
            
            dol_syslog("Stock check result: " . ($product ? print_r($product, true) : 'null'));
            
            if (is_array($product)) {
                dol_syslog("Alert condition met - sending email...");
                $result = $stockAlert->sendAlertEmail($product);
                
                if ($result > 0) {
                    dol_syslog("SUCCESS: Stock alert sent for product ID: " . $product_id);
                } elseif ($result < 0) {
                    dol_syslog("ERROR: Failed to send stock alert: " . $stockAlert->error);
                } else {
                    dol_syslog("ERROR: sendAlertEmail returned 0");
                }
            } else {
                dol_syslog("No alert needed - stock is above threshold or alert already sent");
            }
        } catch (Exception $e) {
            dol_syslog("EXCEPTION: " . $e->getMessage());
        }

        dol_syslog("========== STOCKNOTIFIER TRIGGER END ==========");
        return 0;
    }
}
