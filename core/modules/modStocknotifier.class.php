<?php
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

class modStocknotifier extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 136406;
        $this->rights_class = 'stocknotifier';

        $this->family = "products";
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Alertes e-mail temps réel sur seuils de stock";
        $this->descriptionlong = "Envoi automatique d’alertes e-mail lors de mouvements de stock critiques";
        $this->editor_name = 'Daxit Solutions';
        $this->editor_url = '';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'stock';

        $this->module_parts = array(
            'triggers' => 1,
            'hooks' => array()
        );

        $this->dirs = array();
        $this->config_page_url = array("setup.php@stocknotifier");

        $this->hidden = false;
        $this->depends = array('modProduct', 'modStock');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->phpmin = array(7, 4);
        $this->need_dolibarr_version = array(14, 0);
        $this->langfiles = array("stocknotifier@stocknotifier");

        $this->const = array(
            0 => array(
                'STOCKNOTIFIER_ALERT_EMAIL',
                'chaine',
                '',
                'Destinataires alertes stock',
                0
            ),
            1 => array(
                'STOCKNOTIFIER_EXCLUDE_NOSELL',
                'yesno',
                '1',
                'Exclure produits non vendables',
                0
            ),
            2 => array(
                'STOCKNOTIFIER_EXCLUDE_NOBUY',
                'yesno',
                '0',
                'Exclure produits non achetables',
                0
            ),
            3 => array(
                'STOCKNOTIFIER_ALERT_SENT',
                'chaine',
                '',
                'Produits déjà alertés (anti-spam)',
                0
            )
        );

        $this->rights = array();
        $r = 0;

        $this->rights[$r][0] = $this->numero + 1;
        $this->rights[$r][1] = 'Lire configuration stocknotifier';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'config';
        $this->rights[$r][5] = 'read';
        $r++;

        $this->rights[$r][0] = $this->numero + 2;
        $this->rights[$r][1] = 'Gérer configuration stocknotifier';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'config';
        $this->rights[$r][5] = 'write';
        $r++;

        $this->menus = array();
    }

    public function init($options = '')
    {
        $sql = array();
        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
