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

class StockAlert extends CommonObject
{
    public $element = 'stockalert';
    public $table_element = '';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function checkProductStock($product_id)
    {
        global $conf;

        $config = new NotifierConfig($this->db);

        $sql = "SELECT p.rowid, p.ref, p.label, p.seuil_stock_alerte, p.tosell, p.tobuy,";
        $sql .= " COALESCE(ps.stock, 0) as stock_actuel";
        $sql .= " FROM ".MAIN_DB_PREFIX."product as p";
        $sql .= " LEFT JOIN (";
        $sql .= "   SELECT fk_product, SUM(reel) as stock";
        $sql .= "   FROM ".MAIN_DB_PREFIX."product_stock";
        $sql .= "   GROUP BY fk_product";
        $sql .= " ) as ps ON p.rowid = ps.fk_product";
        $sql .= " WHERE p.rowid = ".((int) $product_id);

        dol_syslog(get_class($this)."::checkProductStock", LOG_DEBUG);

        $resql = $this->db->query($sql);

        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $this->db->free($resql);

            if ($obj) {
                $excludeNoSell = $config->shouldExcludeNoSell();
                $excludeNoBuy = $config->shouldExcludeNoBuy();

                if ($excludeNoSell && $obj->tosell != 1) {
                    return null;
                }
                if ($excludeNoBuy && $obj->tobuy != 1) {
                    return null;
                }

                if (!empty($obj->seuil_stock_alerte) && $obj->seuil_stock_alerte > 0) {
                    if ($obj->stock_actuel <= $obj->seuil_stock_alerte) {
                        if (!$config->isAlertSent($obj->rowid)) {
                            return array(
                                'rowid' => $obj->rowid,
                                'ref' => $obj->ref,
                                'label' => $obj->label,
                                'stock_actuel' => $obj->stock_actuel,
                                'seuil_alerte' => $obj->seuil_stock_alerte,
                                'manquant' => max(0, $obj->seuil_stock_alerte - $obj->stock_actuel)
                            );
                        }
                    } else {
                        $config->resetAlertSent($obj->rowid);
                    }
                }
            }
            return null;
        } else {
            $this->error = $this->db->lasterror();
            dol_syslog(get_class($this)."::checkProductStock ERROR: ".$this->error, LOG_ERR);
            return -1;
        }
    }

    public function sendAlertEmail($product)
    {
        global $conf, $langs, $user;

        if (empty($product) || !is_array($product)) {
            return 0;
        }

        $config = new NotifierConfig($this->db);
        $to = $config->getAlertEmail();

        if (empty($to)) {
            $this->error = 'No email configured for alerts';
            dol_syslog(get_class($this)."::sendAlertEmail ERROR: ".$this->error, LOG_ERR);
            return -1;
        }

        $langs->load("stocknotifier@stocknotifier");

        $subject = $langs->trans("StockAlertEmailSubject", $product['ref']);
        $from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
        if (empty($from)) {
            $from = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
        }

        $message = $this->buildEmailBody($product);

        require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

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
            $config->markAlertSent($product['rowid']);
            return 1;
        } else {
            $this->error = $mailfile->error;
            dol_syslog(get_class($this)."::sendAlertEmail ERROR: ".$this->error, LOG_ERR);
            return -1;
        }
    }

    private function buildEmailBody($product)
    {
        global $langs, $conf;

        $langs->load("stocknotifier@stocknotifier");

        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="utf-8">';
        $html .= '<style>';
        $html .= 'body { font-family: Arial, sans-serif; color: #333; padding: 20px; }';
        $html .= 'table { border-collapse: collapse; width: 100%; margin: 20px 0; }';
        $html .= 'th { background-color: #f4f4f4; padding: 10px; text-align: left; border: 1px solid #ddd; }';
        $html .= 'td { padding: 8px; border: 1px solid #ddd; }';
        $html .= '.warning { color: #d9534f; font-weight: bold; }';
        $html .= '.footer { margin-top: 30px; font-size: 12px; color: #777; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        
        $html .= '<h2>'.$langs->trans("StockAlertEmailTitle").'</h2>';
        $html .= '<p>'.$langs->trans("StockAlertEmailIntro").'</p>';
        
        $html .= '<table>';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>'.$langs->trans("Ref").'</th>';
        $html .= '<th>'.$langs->trans("Label").'</th>';
        $html .= '<th>'.$langs->trans("CurrentStock").'</th>';
        $html .= '<th>'.$langs->trans("AlertThreshold").'</th>';
        $html .= '<th>'.$langs->trans("MissingQuantity").'</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $html .= '<tr>';
        $html .= '<td>'.dol_escape_htmltag($product['ref']).'</td>';
        $html .= '<td>'.dol_escape_htmltag($product['label']).'</td>';
        $html .= '<td class="warning">'.intval($product['stock_actuel']).'</td>';
        $html .= '<td>'.intval($product['seuil_alerte']).'</td>';
        $html .= '<td class="warning">'.intval($product['manquant']).'</td>';
        $html .= '</tr>';

        $html .= '</tbody>';
        $html .= '</table>';
        
        $html .= '<div class="footer">';
        $html .= '<p>'.$langs->trans("StockAlertEmailFooter").'</p>';
        $html .= '<p>'.getDolGlobalString('MAIN_INFO_SOCIETE_NOM').'</p>';
        $html .= '</div>';
        
        $html .= '</body>';
        $html .= '</html>';

        return $html;
    }
}
