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
        $this->version = '1.0.6';
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
        
        // Check module is enabled
        if (!isModEnabled('stocknotifier')) {
            return 0;
        }

        // Get product ID and warehouse ID from the stock movement
        $product_id = 0;
        $warehouse_id = 0;
        
        if (!empty($object) && is_object($object)) {
            // Product ID
            if (!empty($object->fk_product)) {
                $product_id = (int) $object->fk_product;
            } elseif (!empty($object->product_id)) {
                $product_id = (int) $object->product_id;
            } elseif (!empty($object->id) && !empty($object->element) && $object->element == 'product') {
                $product_id = (int) $object->id;
            } elseif (!empty($object->rowid)) {
                $product_id = (int) $object->rowid;
            }
            
            // Warehouse ID - check multiple possible fields
            if (!empty($object->fk_entrepot)) {
                $warehouse_id = (int) $object->fk_entrepot;
            } elseif (!empty($object->warehouse_id)) {
                $warehouse_id = (int) $object->warehouse_id;
            } elseif (!empty($object->fk_product)) {
                // For STOCK_MOVEMENT, warehouse might be in the movement lines
                // We'll check the main warehouse field
            }
        }

        if (empty($product_id) || $product_id <= 0) {
            dol_syslog("No valid product_id found");
            return 0;
        }

        dol_syslog("Product ID: " . $product_id . " | Warehouse ID: " . $warehouse_id);

        // Get configuration
        $config = new NotifierConfig($db);
        
        // Check if warehouse filtering is enabled
        $selectedWarehouses = $config->getSelectedWarehouses();
        
        if (!empty($selectedWarehouses)) {
            // If specific warehouses are selected, only process if movement is in one of them
            dol_syslog("Warehouse filtering enabled. Selected: " . implode(',', $selectedWarehouses));
            
            // If we have a warehouse ID, check if it's in the list
            if ($warehouse_id > 0) {
                if (!in_array($warehouse_id, $selectedWarehouses)) {
                    dol_syslog("Warehouse ".$warehouse_id." not in flagged list - skipping");
                    return 0;
                }
                dol_syslog("Warehouse ".$warehouse_id." is in flagged list - proceeding");
            } else {
                // No warehouse ID found in movement - can't verify, skip to be safe
                dol_syslog("No warehouse ID in movement - skipping for safety");
                return 0;
            }
        } else {
            dol_syslog("No warehouse filtering (all warehouses monitored)");
        }

        // Check if email is configured
        $email = $config->getAlertEmail();
        if (empty($email)) {
            dol_syslog("No email configured");
            return 0;
        }

        // Check stock and send alert
        try {
            $stockAlert = new StockAlert($db);
            $product = $stockAlert->checkProductStock($product_id);
            
            if (is_array($product) && !empty($product)) {
                dol_syslog("Stock below threshold! Current: " . $product['stock_actuel'] . " / Threshold: " . $product['seuil_alerte']);
                
                $result = $stockAlert->sendAlertEmail($product);
                
                if ($result > 0) {
                    dol_syslog("SUCCESS: Email sent for product " . $product['ref']);
                } else {
                    dol_syslog("FAILURE: " . $stockAlert->error);
                }
            } else {
                dol_syslog("No alert - stock is above threshold");
            }
        } catch (Exception $e) {
            dol_syslog("EXCEPTION: " . $e->getMessage());
        }

        return 0;
    }
}
