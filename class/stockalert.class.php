<?php
/* Copyright (C) 2025 Daxit Solutions
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
dol_include_once('/stocknotifier/class/notifierconfig.class.php');

/**
 * Class StockAlert
 * Handles stock level checking and alert notifications
 */
class StockAlert extends CommonObject
{
    public $element = 'stockalert';
    public $table_element = '';

    /** @var NotifierConfig Configuration cache */
    private $config;

    public function __construct($db)
    {
        $this->db = $db;
        $this->config = new NotifierConfig($this->db);
    }

    /**
     * Check product stock level against threshold
     * 
     * @param int $product_id Product ID to check
     * @return array|null Return alert data if below threshold, null otherwise
     */
    public function checkProductStock(int $product_id): ?array
    {
        $selectedWarehouses = $this->config->getSelectedWarehouses();
        
        // Build warehouse filter safely
        $warehouseFilter = '';
        if (!empty($selectedWarehouses)) {
            $warehouseIds = array_map('intval', $selectedWarehouses);
            $warehouseFilter = ' AND ps.fk_entrepot IN ('.implode(',', $warehouseIds).')';
        }

        $sql = "SELECT p.rowid, p.ref, p.label, p.seuil_stock_alerte, p.tosell, p.tobuy,";
        $sql .= " COALESCE(ps.stock, 0) as stock_actuel";
        $sql .= " FROM ".MAIN_DB_PREFIX."product as p";
        $sql .= " LEFT JOIN (";
        $sql .= "   SELECT fk_product, SUM(reel) as stock";
        $sql .= "   FROM ".MAIN_DB_PREFIX."product_stock as ps";
        $sql .= "   WHERE 1=1";
        $sql .= $warehouseFilter;
        $sql .= "   GROUP BY fk_product";
        $sql .= " ) as ps ON p.rowid = ps.fk_product";
        $sql .= " WHERE p.rowid = ".((int) $product_id);

        dol_syslog(get_class($this)."::checkProductStock", LOG_DEBUG);

        $resql = $this->db->query($sql);

        if (!$resql) {
            $this->error = $this->db->lasterror();
            dol_syslog(get_class($this)."::checkProductStock ERROR: ".$this->error, LOG_ERR);
            return null;
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        if (!$obj) {
            return null;
        }

        // Apply exclusion filters
        if ($this->config->shouldExcludeNoSell() && $obj->tosell != 1) {
            return null;
        }
        if ($this->config->shouldExcludeNoBuy() && $obj->tobuy != 1) {
            return null;
        }

        // Check stock threshold
        if (empty($obj->seuil_stock_alerte) || $obj->seuil_stock_alerte <= 0) {
            return null;
        }

        if ($obj->stock_actuel <= $obj->seuil_stock_alerte) {
            // Always send alert when stock is below threshold (no anti-spam)
            return array(
                'rowid' => (int) $obj->rowid,
                'ref' => $obj->ref,
                'label' => $obj->label,
                'stock_actuel' => (int) $obj->stock_actuel,
                'seuil_alerte' => (int) $obj->seuil_alerte,
                'manquant' => max(0, $obj->seuil_alerte - $obj->stock_actuel)
            );
        }

        return null;
    }

    /**
     * Send alert email for product
     * 
     * @param array $product Product data
     * @return int 1 on success, -1 on error, 0 on invalid input
     */
    public function sendAlertEmail(array $product): int
    {
        if (empty($product) || !is_array($product)) {
            return 0;
        }

        $to = $this->config->getAlertEmail();
        if (empty($to)) {
            $this->error = 'No email configured for alerts';
            dol_syslog(get_class($this)."::sendAlertEmail ERROR: ".$this->error, LOG_ERR);
            return -1;
        }

        global $langs;
        $langs->load("stocknotifier@stocknotifier");

        $subject = $langs->trans("StockAlertEmailSubject", $product['ref']);
        
        $from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
        if (empty($from)) {
            $from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
        }

        $message = $this->buildEmailBody($product);

        $mailfile = new CMailFile(
            $subject,
            $to,
            $from,
            $message,
            array(),
            array(),
            array(),
            '',
            '',
            0,
            -1,
            '',
            '',
            '',
            '',
            'mail'
        );

        if ($mailfile->sendfile()) {
            dol_syslog(get_class($this)."::sendAlertEmail Email sent successfully to ".$to, LOG_INFO);
            $this->config->markAlertSent($product['rowid']);
            return 1;
        }

        $this->error = $mailfile->error;
        dol_syslog(get_class($this)."::sendAlertEmail ERROR: ".$this->error, LOG_ERR);
        return -1;
    }

    /**
     * Build HTML email body
     * 
     * @param array $product Product data
     * @return string HTML email content
     */
    private function buildEmailBody(array $product): string
    {
        global $langs;

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>';
        $html .= 'body{font-family:Arial,sans-serif;color:#333;padding:20px}';
        $html .= 'table{border-collapse:collapse;width:100%;margin:20px 0}';
        $html .= 'th{background-color:#f4f4f4;padding:10px;text-align:left;border:1px solid #ddd}';
        $html .= 'td{padding:8px;border:1px solid #ddd}';
        $html .= '.warning{color:#d9534f;font-weight:bold}';
        $html .= '.footer{margin-top:30px;font-size:12px;color:#777}';
        $html .= '</style></head><body>';
        
        $html .= '<h2>'.$langs->trans("StockAlertEmailTitle").'</h2>';
        $html .= '<p>'.$langs->trans("StockAlertEmailIntro").'</p>';
        
        $html .= '<table><thead><tr>';
        $html .= '<th>'.$langs->trans("Ref").'</th>';
        $html .= '<th>'.$langs->trans("Label").'</th>';
        $html .= '<th>'.$langs->trans("CurrentStock").'</th>';
        $html .= '<th>'.$langs->trans("AlertThreshold").'</th>';
        $html .= '<th>'.$langs->trans("MissingQuantity").'</th>';
        $html .= '</tr></thead><tbody><tr>';
        $html .= '<td>'.dol_escape_htmltag($product['ref']).'</td>';
        $html .= '<td>'.dol_escape_htmltag($product['label']).'</td>';
        $html .= '<td class="warning">'.(int) $product['stock_actuel'].'</td>';
        $html .= '<td>'.(int) $product['seuil_alerte'].'</td>';
        $html .= '<td class="warning">'.(int) $product['manquant'].'</td>';
        $html .= '</tr></tbody></table>';
        
        $html .= '<div class="footer">';
        $html .= '<p>'.$langs->trans("StockAlertEmailFooter").'</p>';
        $html .= '<p>'.dol_escape_htmltag(getDolGlobalString('MAIN_INFO_SOCIETE_NOM')).'</p>';
        $html .= '</div></body></html>';

        return $html;
    }
}
