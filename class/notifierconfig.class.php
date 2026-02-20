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

/**
 * Class NotifierConfig
 * Handles configuration and alert state management
 */
class NotifierConfig extends CommonObject
{
    public $element = 'notifierconfig';
    public $table_element = 'stocknotifier_config';
    public $picto = 'generic';

    /** @var DoliDB Database handler */
    public $db;
    
    /** @var array Cache for configuration values */
    private $cache = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get alert email address
     * @return string Email address
     */
    public function getAlertEmail(): string
    {
        if (!isset($this->cache['email'])) {
            $email = getDolGlobalString('STOCKNOTIFIER_ALERT_EMAIL');
            if (empty($email)) {
                $email = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
            }
            $this->cache['email'] = $email;
        }
        return $this->cache['email'];
    }

    /**
     * Check if products not for sale should be excluded
     * @return bool True if exclude
     */
    public function shouldExcludeNoSell(): bool
    {
        if (!isset($this->cache['exclude_nosell'])) {
            $this->cache['exclude_nosell'] = getDolGlobalInt('STOCKNOTIFIER_EXCLUDE_NOSELL', 1);
        }
        return (bool) $this->cache['exclude_nosell'];
    }

    /**
     * Check if products not for purchase should be excluded
     * @return bool True if exclude
     */
    public function shouldExcludeNoBuy(): bool
    {
        if (!isset($this->cache['exclude_nobuy'])) {
            $this->cache['exclude_nobuy'] = getDolGlobalInt('STOCKNOTIFIER_EXCLUDE_NOBUY', 0);
        }
        return (bool) $this->cache['exclude_nobuy'];
    }

    /**
     * Get selected warehouse IDs
     * @return int[] Array of warehouse IDs (empty = all warehouses)
     */
    public function getSelectedWarehouses(): array
    {
        if (!isset($this->cache['warehouses'])) {
            $warehouses = getDolGlobalString('STOCKNOTIFIER_WAREHOUSES');
            if (empty($warehouses)) {
                $this->cache['warehouses'] = array();
            } else {
                $this->cache['warehouses'] = array_map('intval', explode(',', $warehouses));
            }
        }
        return $this->cache['warehouses'];
    }

    /**
     * Check if warehouse selection is configured
     * @return bool True if selection exists
     */
    public function hasWarehouseSelection(): bool
    {
        $warehouses = $this->getSelectedWarehouses();
        return !empty($warehouses);
    }

    /**
     * Get alert sent product IDs
     * @return int[] Array of product IDs
     */
    private function getAlertSentArray(): array
    {
        $alerted = getDolGlobalString('STOCKNOTIFIER_ALERT_SENT');
        return !empty($alerted) ? array_map('intval', explode(',', $alerted)) : array();
    }

    /**
     * Check if alert was already sent for product
     * @param int $product_id Product ID
     * @return bool True if alert sent
     */
    public function isAlertSent(int $product_id): bool
    {
        $alertedArray = $this->getAlertSentArray();
        return in_array($product_id, $alertedArray, true);
    }

    /**
     * Mark alert as sent for product
     * @param int $product_id Product ID
     * @return int Result
     */
    public function markAlertSent(int $product_id): int
    {
        $alertedArray = $this->getAlertSentArray();
        
        if (!in_array($product_id, $alertedArray, true)) {
            $alertedArray[] = $product_id;
            $newValue = implode(',', $alertedArray);
            return dolibarr_set_const($this->db, 'STOCKNOTIFIER_ALERT_SENT', $newValue, 'chaine', 0, '', $this->db->entity);
        }
        return 0;
    }

    /**
     * Reset alert sent flag for product
     * @param int $product_id Product ID
     * @return int Result
     */
    public function resetAlertSent(int $product_id): int
    {
        $alertedArray = $this->getAlertSentArray();
        
        $key = array_search($product_id, $alertedArray, true);
        if ($key !== false) {
            unset($alertedArray[$key]);
            $newValue = implode(',', array_values($alertedArray));
            return dolibarr_set_const($this->db, 'STOCKNOTIFIER_ALERT_SENT', $newValue, 'chaine', 0, '', $this->db->entity);
        }
        return 0;
    }

    /**
     * Reset all alert sent flags
     * @return int Result
     */
    public function resetAllAlertsSent(): int
    {
        $this->cache = array(); // Clear cache
        return dolibarr_set_const($this->db, 'STOCKNOTIFIER_ALERT_SENT', '', 'chaine', 0, '', $this->db->entity);
    }
}
