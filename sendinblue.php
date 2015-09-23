<?php
/**
 * 2007-2014 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2014 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit();
}

if (!class_exists('Customer')) {
    include_once (_PS_CLASS_DIR_ . '/../classes/Customer.php');
}

include_once (dirname(__FILE__) . '/sendinblueResources.php');
if (version_compare(_PS_VERSION_, '1.4.5', '<')) {
    $sendinblue_resources = new SendinblueResources();
    if ($sendinblue_resources->checkConditionOlderVersion()) {
        include (dirname(__FILE__) . '/config.php');
    }
} else {
    include (dirname(__FILE__) . '/config.php');
}

class Sendinblue extends Module
{
    
    private $post_errors = array();
    private $html_code_tracking;
    private $_html_smtp_tracking;
    private $_second_block_code;
    private $tracking;
    private $email;
    private $newsletter;
    private $last_name;
    private $first_name;
    public $id_shop;
    public $id_shop_group;
    public $valid;
    
    public $error;
    public $_html = null;
    public $local_path;
    
    /**
     * class constructor
     */
    public function __construct()
    {
        $this->name = 'sendinblue';
        if (version_compare(_PS_VERSION_, '1.5', '>')) {
            $this->tab = 'emailing';
        } else {
            $this->tab = 'advertising_marketing';
        }
        $this->author = 'SendinBlue';
        $this->version = '2.5.2';
        
        parent::__construct();
        
        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('SendinBlue');
        $this->description = $this->l('Synchronize your PrestaShop contacts with SendinBlue platform & easily send your marketing and transactional emails and SMS');
        $this->confirmUninstall = $this->l('Are you sure you want to remove the SendinBlue module? N.B: we will enable php mail() send function (If you were using SMTP info before using SendinBlue SMTP, please update your configuration for the emails)');
        
        require (_PS_MODULE_DIR_ . $this->name . '/backward_compatibility/backward.php');
        
        $this->langid = !empty($this->context->language->id) ? $this->context->language->id : '';
        $this->lang_cookie = $this->context->cookie;
        
        $id_shop = null;
        if (version_compare(_PS_VERSION_, '1.5', '>')) {
            $id_shop_group = Shop::getContextShopGroupID(true);
            if (Shop::getContext() == Shop::CONTEXT_SHOP) {
                $id_shop = Shop::getContextShopID(true);
            }
        } else {
            $id_shop_group = null;
        }
        
        $this->id_shop_group = $id_shop_group;
        $this->id_shop = $id_shop;
        if (version_compare(_PS_VERSION_, '1.4.5', '<')) {
            $sendinblue_resources = new SendinblueResources();
            if ($sendinblue_resources->checkConditionOlderVersion() === true) {
                $pathconfig = new Pathfindsendinblue();
                $this->local_path = $pathconfig->pathdisp();
                
                //Call the callhookRegister method to send an email to the SendinBlue user
                //when someone registers.
                $this->callhookRegister();
            }
        } else {
            $pathconfig = new Pathfindsendinblue();
            $this->local_path = $pathconfig->pathdisp();
            
            //Call the callhookRegister method to send an email to the SendinBlue user
            //when someone registers.
            $this->callhookRegister();
        }
        
        // Checking Extension
        if (!extension_loaded('curl') || !ini_get('allow_url_fopen')) {
            if (!extension_loaded('curl') && !ini_get('allow_url_fopen')) {
                return $this->_html . $this->l('You must enable cURL extension and allow_url_fopen option on your server if you want to use this module.');
            } elseif (!extension_loaded('curl')) {
                return $this->_html . $this->l('You must enable cURL extension on your server if you want to use this module.');
            } elseif (!ini_get('allow_url_fopen')) {
                return $this->_html . $this->l('You must enable allow_url_fopen option on your server if you want to use this module.');
            }
        }
        if (version_compare(_PS_VERSION_, '1.5', '>')) {
            $this->cl_version = 'ver_5';
        } else {
            $this->cl_version = 'ver_4';
        }
        
        $this->checkForUpdates();
    }
    
    /**
     *  Function to set the SendinBlue SMTP and tracking code status to 0
     */
    public function checkSmtpStatus()
    {
        
        //If the SendinBlue tracking code status is empty we set the status to 0
        if (Configuration::get('Sendin_Tracking_Status', '', $this->id_shop_group, $this->id_shop) == '') {
            Configuration::updateValue('Sendin_Tracking_Status', 0, '', $this->id_shop_group, $this->id_shop);
        }
        
        //If the Sendin SMTP status is empty we set the status to 0
        if (Configuration::get('Sendin_Api_Smtp_Status', '', $this->id_shop_group, $this->id_shop) == '') {
            Configuration::updateValue('Sendin_Api_Smtp_Status', 0, '', $this->id_shop_group, $this->id_shop);
        }
        
        //If module is disabled, we set the default value for PrestaShop SMTP
        
    }
    
    /**
     * When a subscriber registers we send an email to the SendinBlue user informing
     * that a new registration has happened.
     */
    public function callhookRegister()
    {
        if (version_compare(_PS_VERSION_, '1.5', '>=') && Dispatcher::getInstance()->getController() == 'identity') {
            if (Module::getInstanceByName('blocknewsletter')->active == 0) {
                Module::getInstanceByName('blocknewsletter')->active = 1;
                echo '<script type="text/javascript">
window.onload=function(){
jQuery("#newsletter").closest("p.checkbox").hide();
jQuery("#optin").closest("p.checkbox").hide();
};
</script>';
            }
            
            $this->newsletter = Tools::getValue('newsletter');
            $this->email = Tools::getValue('email');
            $id_country = Tools::getValue('id_country');
            $phone_mobile = Tools::getValue('phone_mobile');
            $this->id_gender = Tools::getValue('id_gender');
            $this->first_name = Tools::getValue('firstname');
            $this->last_name = Tools::getValue('lastname');
            $this->id_lang = $this->context->cookie->id_lang;
            $this->days = Tools::getValue('days');
            $this->months = Tools::getValue('months');
            $this->years = Tools::getValue('years');
            $birthday = ($this->years . '-' . $this->months . '-' . $this->days);
            
            // Load customer data for logged in user so that we can register his/her with sendinblue
            
            $customer_data = $this->getCustomersByEmail($this->email);
            
            // Check if client have records in customer table
            if (isset($customer_data) && count($customer_data) > 0 && !empty($customer_data[0]['id_customer'])) {
                $newsletter_status = !empty($this->newsletter) ? $this->newsletter : $customer_data[0]['newsletter'];
                $this->email = !empty($this->email) ? $this->email : $customer_data[0]['email'];
                $this->id_gender = !empty($this->id_gender) ? $this->id_gender : $customer_data[0]['id_gender'];
                $this->first_name = !empty($this->first_name) ? $this->first_name : $customer_data[0]['firstname'];
                $this->last_name = !empty($this->last_name) ? $this->last_name : $customer_data[0]['lastname'];
                $this->birthday = (!empty($this->years) && !empty($this->months) && !empty($this->days)) ? $birthday : $customer_data[0]['birthday'];
                
                // If logged in user register with newsletter
                if (isset($newsletter_status) && $newsletter_status == 1) {
                    $id_customer = $customer_data[0]['id_customer'];
                    $customer = new CustomerCore((int)$id_customer);
                    
                    // Code to get address of logged in user
                    if (Validate::isLoadedObject($customer)) {
                        if (version_compare(_PS_VERSION_, '1.5.3.4', '>')) {
                            $customer_address = $customer->getAddresses((int)$customer_data[0]['id_lang']);
                        } else {
                            $customer_address = $this->getCustomerAddresses((int)$customer_data[0]['id_customer']);
                        }
                    }
                    $phone_mobile = '';
                    $id_country = '';
                    
                    // Check if user have address data
                    if ($customer_address && count($customer_address) > 0) {
                        
                        // Code to get latest phone number of logged in user
                        $count_address = count($customer_address);
                        for ($i = $count_address; $i >= 0; $i--) {
                            $temp = 0;
                            foreach ($customer_address as $select_address) {
                                if ($temp < $select_address['date_upd'] && !empty($select_address['phone_mobile'])) {
                                    $temp = $select_address['date_upd'];
                                    $phone_mobile = $select_address['phone_mobile'];
                                    $id_country = $select_address['id_country'];
                                }
                            }
                        }
                    }
                    
                    // Check if logged in user have phone number
                    if (!empty($phone_mobile)) {
                        
                        // Code to get country prefix
                        $result = Db::getInstance()->getRow('SELECT `call_prefix` FROM ' . _DB_PREFIX_ . 'country WHERE `id_country` = \'' . (int)$id_country . '\'');
                        
                        /**
                         * Code to validate phone number (if we have '00' or '+' then it'll add '00' without country prefix,
                         * if we have '0' then it'll add '00' with country prefix)
                         */
                        $phone_mobile = $this->checkMobileNumber($phone_mobile, (!empty($result['call_prefix']) ? $result['call_prefix'] : ''));
                        $phone_mobile = (!empty($phone_mobile)) ? $phone_mobile : '';
                    }
                    
                    // Code to update sendinblue with logged in user data.
                    $this->subscribeByruntimeRegister($this->email, $this->id_gender, $this->first_name, $this->last_name, $this->birthday, $this->id_lang, $phone_mobile, $this->newsletter, $this->id_shop_group, $this->id_shop);
                    $this->sendWsTemplateMail($this->email);
                }
            }
        } else {
            $this->newsletter = Tools::getValue('newsletter');
            $this->email = Tools::getValue('email');
            $id_country = Tools::getValue('id_country');
            $phone_mobile = Tools::getValue('phone_mobile');
            $this->id_gender = Tools::getValue('id_gender');
            $this->first_name = Tools::getValue('customer_firstname');
            $this->last_name = Tools::getValue('customer_lastname');
            $this->id_lang = !empty($this->context->cookie->id_lang) ? $this->context->cookie->id_lang : '';
            $this->days = Tools::getValue('days');
            $this->months = Tools::getValue('months');
            $this->years = Tools::getValue('years');
            $birthday = ($this->years . '-' . $this->months . '-' . $this->days);
            
            if (isset($this->newsletter) && $this->newsletter == 1 && $this->email != '') {
                $result = Db::getInstance()->getRow('SELECT `call_prefix` FROM ' . _DB_PREFIX_ . 'country WHERE `id_country` = \'' . (int)$id_country . '\'');
                $phone_mobile = $this->checkMobileNumber($phone_mobile, $result['call_prefix']);
                $phone_mobile = (!empty($phone_mobile)) ? $phone_mobile : '';
                
                if (Configuration::get('Sendin_Api_Key_Status', '', $this->id_shop_group, $this->id_shop) == 1) {
                    $result_id = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'sendin_newsletter WHERE `email` = \'' . pSQL($this->context->cookie->email) . '\'');
                }
                
                $email_id = (isset($result_id['id']) ? $result_id['id'] : '0');
                if ($email_id > 0) {
                    $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 1;
                    $condition = $this->checkVersionCondition($id_shop_group);
                    
                    Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'sendin_newsletter WHERE `email` = \'' . pSQL($this->context->cookie->email) . '\'' . $condition . '');
                    if ($this->newsletter == 0) {
                        $this->unsubscribeByruntime($this->context->cookie->email);
                    }
                }
                
                if (isset($this->newsletter) && $this->newsletter == 1) {
                    $this->subscribeByruntimeRegister($this->email, $this->id_gender, $this->first_name, $this->last_name, $birthday, $this->id_lang, $phone_mobile, $this->newsletter, $this->id_shop_group, $this->id_shop);
                    $this->sendWsTemplateMail($this->email);
                }
            } elseif (Tools::getValue('email') != '') {
                
                // Load customer data for logged in user so that we can register his/her with sendinblue
                if (!empty($this->context->cookie->email)) {
                    $customer_data = $this->getCustomersByEmail($this->context->cookie->email);
                }
                
                // Check if client have records in customer table
                if (!empty($customer_data[0]['id_customer']) && count($customer_data) > 0) {
                    $newsletter_status = !empty($this->newsletter) ? $this->newsletter : $customer_data[0]['newsletter'];
                    $this->email = !empty($this->email) ? $this->email : $customer_data[0]['email'];
                    $this->id_gender = !empty($this->id_gender) ? $this->id_gender : $customer_data[0]['id_gender'];
                    $this->first_name = !empty($this->first_name) ? $this->first_name : $customer_data[0]['firstname'];
                    $this->last_name = !empty($this->last_name) ? $this->last_name : $customer_data[0]['lastname'];
                    $this->id_lang = !empty($this->id_lang) ? $this->id_lang : $customer_data[0]['id_lang'];
                    $this->birthday = (!empty($this->years) && !empty($this->months) && !empty($this->days)) ? $birthday : $customer_data[0]['birthday'];
                    
                    if (Configuration::get('Sendin_Api_Key_Status', '', $this->id_shop_group, $this->id_shop) == 1) {
                        $result_id = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'sendin_newsletter WHERE `email` = \'' . pSQL($this->context->cookie->email) . '\'');
                    }
                    $email_id = (isset($result_id['id']) ? $result_id['id'] : '0');
                    if ($email_id > 0) {
                        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 1;
                        $condition = $this->checkVersionCondition($id_shop_group);
                        
                        Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'sendin_newsletter WHERE `email` = \'' . pSQL($this->context->cookie->email) . '\'' . $condition . '');
                        if ($this->newsletter == 0) {
                            $this->unsubscribeByruntime($this->context->cookie->email);
                        }
                    }
                    
                    // Code to update sendinblue with logged in user data.
                    if ($newsletter_status == 1) {
                        $this->subscribeByruntimeRegister($this->email, $this->id_gender, $this->first_name, $this->last_name, $this->birthday, $this->id_lang, $phone_mobile, $this->newsletter, $this->id_shop_group, $this->id_shop);
                    }
                }
            } elseif (Tools::getValue('phone_mobile') != '' || Tools::getValue('controller') == 'addresses' || strpos($_SERVER['REQUEST_URI'], 'addresses.php') !== false) {
                
                // Load customer data for logged in user so that we can register his/her with sendinblue
                if (!empty($this->context->cookie->email)) {
                    $customer_data = $this->getCustomersByEmail($this->context->cookie->email);
                }
                
                // Check if client have records in customer table
                if (!empty($customer_data[0]['id_customer']) && count($customer_data) > 0) {
                    $newsletter_status = !empty($customer_data[0]['newsletter']) ? $customer_data[0]['newsletter'] : '';
                    $this->email = !empty($customer_data[0]['email']) ? $customer_data[0]['email'] : '';
                    $this->id_gender = !empty($customer_data[0]['id_gender']) ? $customer_data[0]['id_gender'] : '';
                    $this->first_name = !empty($customer_data[0]['firstname']) ? $customer_data[0]['firstname'] : '';
                    $this->last_name = !empty($customer_data[0]['lastname']) ? $customer_data[0]['lastname'] : '';
                    $this->id_lang = !empty($customer_data[0]['id_lang']) ? $customer_data[0]['id_lang'] : $this->id_lang;
                    $this->birthday = !empty($customer_data[0]['birthday']) ? $customer_data[0]['birthday'] : '';
                    
                    // If logged in user register with newsletter
                    if (isset($newsletter_status) && $newsletter_status == 1) {
                        $id_customer = $customer_data[0]['id_customer'];
                        $customer = new CustomerCore((int)$id_customer);
                        
                        // Code to get address of logged in user
                        if (Validate::isLoadedObject($customer)) {
                            if (version_compare(_PS_VERSION_, '1.5.3.4', '>')) {
                                $customer_address = $customer->getAddresses((int)$this->context->language->id);
                            } else {
                                $customer_address = $this->getCustomerAddresses((int)$id_customer);
                            }
                        }
                        $phone_mobile = '';
                        $id_country = '';
                        
                        // Check if user have address data
                        if ($customer_address && count($customer_address) > 0) {
                            
                            // Code to get latest phone number of logged in user
                            $count_address = count($customer_address);
                            for ($i = $count_address; $i >= 0; $i--) {
                                $temp = 0;
                                foreach ($customer_address as $select_address) {
                                    if ($temp < $select_address['date_upd'] && !empty($select_address['phone_mobile'])) {
                                        $temp = $select_address['date_upd'];
                                        $phone_mobile = $select_address['phone_mobile'];
                                        $id_country = $select_address['id_country'];
                                    }
                                }
                            }
                        }

                        // Check if logged in user have phone number
                        if (!empty($phone_mobile)) {
                            
                            // Code to get country prefix
                            $result = Db::getInstance()->getRow('SELECT `call_prefix` FROM ' . _DB_PREFIX_ . 'country WHERE `id_country` = \'' . (int)$id_country . '\'');
                            
                            /**
                             * Code to validate phone number (if we have '00' or '+' then it'll add '00' without country prefix,
                             * if we have '0' then it'll add '00' with country prefix)
                             */
                            $phone_mobile = $this->checkMobileNumber($phone_mobile, (!empty($result['call_prefix']) ? $result['call_prefix'] : ''));
                            $phone_mobile = (!empty($phone_mobile)) ? $phone_mobile : '';
                        }
                        
                        // Code to update sendinblue with logged in user data.
                        $this->subscribeByruntimeRegister($this->email, $this->id_gender, $this->first_name, $this->last_name, $this->birthday, $this->id_lang, $phone_mobile, $this->newsletter, $this->id_shop_group, $this->id_shop);
                    }
                }
            }
        }
        if (!empty($this->context->language->id)) {
            $this->context->cookie->sms_message_land_id = $this->context->language->id;
        }
    }

    /**
     * To restore the default PrestaShop newsletter block.
     */
    public function restoreBlocknewsletterBlock()
    {
        if (version_compare(_PS_VERSION_, '1.4.1.0', '<=')) {
            Db::getInstance()->Execute('UPDATE `' . _DB_PREFIX_ . 'module` SET active = 1 WHERE name = "blocknewsletter"');
        } else {
            Module::enableByName('blocknewsletter');
        }
    }
    
    /**
     * This method is called when installing the SendinBlue plugin.
     */
    public function install()
    {
        if (parent::install() == false || $this->registerHook('OrderConfirmation') === false || $this->registerHook('leftColumn') === false || $this->registerHook('rightColumn') === false || $this->registerHook('top') === false || $this->registerHook('footer') === false || $this->registerHook('createAccount') === false || $this->registerHook('createAccountForm') === false || $this->registerHook('updateOrderStatus') === false) {
            return false;
        }
        
        Configuration::updateValue('Sendin_Newsletter_table', 1, '', $this->id_shop_group, $this->id_shop);
        
        if (Db::getInstance()->Execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sendin_newsletter`(
            `id` int(6) NOT NULL AUTO_INCREMENT,
            `id_shop` int(10) unsigned NOT NULL DEFAULT 1,
            `id_shop_group` int(10) unsigned NOT NULL DEFAULT 1,
            `email` varchar(255) NOT NULL,
            `newsletter_date_add` DATETIME NULL,
            `ip_registration_newsletter` varchar(15) NOT NULL,
            `http_referer` VARCHAR(255) NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY(`id`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' default CHARSET=utf8')) {
            return true;
        }
        
        return false;
    }
    
    /**
     *  This method is use for alter table structure below 1.5 version.
     */
    private function checkForUpdates()
    {
        
        // Used by PrestaShop 1.3 & 1.4
        if (version_compare(_PS_VERSION_, '1.5', '<') && self::isInstalled($this->name)) {
            foreach (array('2.2') as $version) {
                $file = dirname(__FILE__) . '/upgrade/Upgrade-' . $version . '.php';
                if (version_compare(Configuration::get('Sendinblue_Version'), $version, '<') && file_exists($file)) {
                    include_once ($file);
                    call_user_func('upgrade_module_' . str_replace('.', '_', $version), $this);
                }
            }
        }
        
        $version = '2.5.1';
        $file = dirname(__FILE__) . '/upgrade/Upgrade-' . $version . '.php';
        
        if (version_compare(Configuration::get('Sendinblue_Version'), $version, '<') && file_exists($file)) {
            include_once ($file);
            call_user_func('upgrade_module_' . str_replace('.', '_', $version), $this);
        }
    }
    
    /**
     *  We create our own table and import the unregisterd emails from the default
     *  newsletter table to the ps_sendin_newsletter table. This is used when you install
     * the SendinBlue PS plugin.
     */
    public function getOldNewsletterEmails()
    {
        Db::getInstance()->Execute('TRUNCATE table  ' . _DB_PREFIX_ . 'sendin_newsletter');
        
        if (version_compare(_PS_VERSION_, '1.5.3.0', '>=')) {
            Db::getInstance()->Execute('INSERT INTO  ' . _DB_PREFIX_ . 'sendin_newsletter
(id_shop, id_shop_group, email, newsletter_date_add, ip_registration_newsletter, http_referer, active)
SELECT id_shop, id_shop_group, email, newsletter_date_add, ip_registration_newsletter, http_referer, active FROM ' . _DB_PREFIX_ . 'newsletter');
        } else {
            Db::getInstance()->Execute('INSERT INTO  ' . _DB_PREFIX_ . 'sendin_newsletter
(email, newsletter_date_add, ip_registration_newsletter, http_referer)
SELECT email, newsletter_date_add, ip_registration_newsletter, http_referer FROM ' . _DB_PREFIX_ . 'newsletter');
        }
    }
    
    /**
     *  This method restores the subscribers from the ps_sendin_newsletter table to the default table.
     * This is used when you uninstall the SendinBlue PS Plugin.
     */
    public function getRestoreOldNewsletteremails()
    {
        if (Configuration::get('Sendin_Api_Key_Status', '', $this->id_shop_group, $this->id_shop)) {
            Db::getInstance()->Execute('TRUNCATE table  ' . _DB_PREFIX_ . 'newsletter');
        }
        
        if (version_compare(_PS_VERSION_, '1.5.3.0', '>=')) {
            Db::getInstance()->Execute('INSERT INTO  ' . _DB_PREFIX_ . 'newsletter
(id_shop, id_shop_group, email, newsletter_date_add, ip_registration_newsletter, http_referer, active)
SELECT id_shop, id_shop_group, email, newsletter_date_add, ip_registration_newsletter, http_referer, active FROM ' . _DB_PREFIX_ . 'sendin_newsletter');
        } else {
            Db::getInstance()->Execute('INSERT INTO  ' . _DB_PREFIX_ . 'newsletter
(email, newsletter_date_add, ip_registration_newsletter, http_referer)
SELECT email, newsletter_date_add, ip_registration_newsletter, http_referer FROM ' . _DB_PREFIX_ . 'sendin_newsletter');
        }
    }
    
    /**
     *  This method is used to fetch all users from the default customer table to list
     * them in the SendinBlue PS plugin.
     */
    public function getNewsletterEmails($start, $page, $id_shop_group, $id_shop)
    {
        $id_shop_group = !empty($id_shop_group) ? $id_shop_group : 'NULL';
        $id_shop = !empty($id_shop) ? $id_shop : 'NULL';
        if ($id_shop === 'NULL' && $id_shop_group === 'NULL') {
            $condition = '';
        } elseif ($id_shop_group != 'NULL' && $id_shop === 'NULL') {
            $condition = 'WHERE C.id_shop_group =' . $id_shop_group;
        } else {
            $condition = 'WHERE C.id_shop_group =' . $id_shop_group . ' AND C.id_shop =' . $id_shop;
        }
        
        if ($id_shop === 'NULL' && $id_shop_group === 'NULL') {
            $condition2 = '';
        } elseif ($id_shop_group != 'NULL' && $id_shop === 'NULL') {
            $condition2 = 'WHERE A.id_shop_group =' . $id_shop_group;
        } else {
            $condition2 = 'WHERE A.id_shop_group =' . $id_shop_group . ' AND A.id_shop =' . $id_shop;
        }
        
        return Db::getInstance()->ExecuteS('
SELECT LOWER(C.email) as email, C.newsletter AS newsletter, ' . _DB_PREFIX_ . 'country.call_prefix, PSA.phone_mobile, C.id_customer, PSA.date_upd
FROM ' . _DB_PREFIX_ . 'customer as C LEFT JOIN ' . _DB_PREFIX_ . 'address PSA ON (C.id_customer = PSA.id_customer and (PSA.id_customer, PSA.date_upd) IN
(SELECT id_customer, MAX(date_upd) upd  FROM ' . _DB_PREFIX_ . 'address GROUP BY ' . _DB_PREFIX_ . 'address.id_customer))
LEFT JOIN ' . _DB_PREFIX_ . 'country ON ' . _DB_PREFIX_ . 'country.id_country = PSA.id_country ' . $condition . '
GROUP BY C.id_customer
UNION
(SELECT LOWER(A.email) as email, A.active AS newsletter, NULL AS call_prefix,
NULL AS phone_mobile, "Nclient" AS id_customer, NULL AS date_upd
FROM ' . _DB_PREFIX_ . 'sendin_newsletter AS A ' . $condition2 . ')  LIMIT ' . (int)$start . ',' . (int)$page);
    }
    
    /**
     * Get the total count of the registered users including both subscribed
     * and unsubscribed in the default customer table.
     */
    public function getTotalEmail()
    {
        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 'NULL';
        $id_shop = !empty($this->id_shop) ? $this->id_shop : 'NULL';
        
        if ($id_shop === 'NULL' && $id_shop_group === 'NULL') {
            $condition = '';
        } elseif ($id_shop_group != 'NULL' && $id_shop === 'NULL') {
            $condition = 'WHERE id_shop_group =' . $id_shop_group;
        } else {
            $condition = 'WHERE id_shop_group =' . $id_shop_group . ' AND id_shop =' . $id_shop;
        }
        
        $customer_count = Db::getInstance()->getValue('SELECT count(*) AS Total FROM ' . _DB_PREFIX_ . 'customer ' . $condition);
        $newsletter_count = Db::getInstance()->getValue('SELECT count(A.email) AS Total FROM ' . _DB_PREFIX_ . 'sendin_newsletter AS A  ' . $condition);
        return ($customer_count + $newsletter_count);
    }
    
    /**
     *  Get the total count of the subscribed and unregistered users in the default customer table.
     */
    public function getTotalSubUnReg()
    {
        $condition = $this->conditionalValue();
        
        return Db::getInstance()->getValue('SELECT  count(*) as Total FROM ' . _DB_PREFIX_ . 'sendin_newsletter where active = 1 ' . $condition);
    }
    
    /**
     *  Get the total count of the unsubscribed and unregistered users in the default customer table.
     */
    public function getTotalUnSubUnReg()
    {
        $condition = $this->conditionalValue();
        
        return Db::getInstance()->getValue('SELECT  count(*) as Total FROM ' . _DB_PREFIX_ . 'sendin_newsletter where active = 0 ' . $condition);
    }
    
    /**
     *  Update a subscriber's status both on SendinBlue and PrestaShop.
     */
    public function updateNewsletterStatus($id_shop_group = '', $id_shop = '')
    {
        $id_shop_group = !empty($id_shop_group) ? $id_shop_group : 'NULL';
        $id_shop = !empty($id_shop) ? $id_shop : 'NULL';
        
        $this->newsletter = Tools::getValue('newsletter_value');
        $this->email = Tools::getValue('email_value');
        
        if (isset($this->newsletter) && $this->newsletter != '' && $this->email != '') {
            if ($this->newsletter == 0) {
                $this->unsubscribeByruntime($this->email, $id_shop_group, $id_shop);
                
                $status = 0;
            } elseif ($this->newsletter == 1) {
                $data = $this->getUpdateUserData($this->email, $id_shop_group, $id_shop);
                if (!empty($data['phone_mobile']) || $data['phone_mobile'] != '') {
                    $result = Db::getInstance()->getRow('SELECT `call_prefix` FROM ' . _DB_PREFIX_ . 'country WHERE `id_country` = \'' . (int)$data['id_country'] . '\'');
                    $mobile = $this->checkMobileNumber($data['phone_mobile'], $result['call_prefix']);
                } else {
                    $mobile = '';
                }
                $this->isEmailRegistered($this->email, $mobile, $this->newsletter, $id_shop_group, $id_shop);
                $status = 1;
            }
            
            Db::getInstance()->Execute('UPDATE `' . _DB_PREFIX_ . 'sendin_newsletter`
SET active="' . pSQL($status) . '",
newsletter_date_add = "' . pSQL(date('Y-m-d H:i:s')) . '"
WHERE email = "' . pSQL($this->email) . '"');
            Db::getInstance()->Execute('UPDATE `' . _DB_PREFIX_ . 'customer`
SET newsletter="' . pSQL($status) . '",
newsletter_date_add = "' . pSQL(date('Y-m-d H:i:s')) . '"
WHERE email = "' . pSQL($this->email) . '"');
        }
    }
    
    public function getUpdateUserData($email)
    {
        
        //Load customer data for logged in user so that we can register his/her with sendinblue
        $customer_data = $this->getCustomersByEmail($email);
        
        // Check if client have records in customer table
        if (count($customer_data) > 0 && !empty($customer_data[0]['id_customer'])) {
            $totalvalue = count($customer_data);
            $totalvalue = ($totalvalue - 1);
            for ($i = $totalvalue; $i >= 0; $i--) {
                $this->newsletter = !empty($customer_data[$i]['newsletter']) ? $customer_data[$i]['newsletter'] : '';
                $this->email = !empty($customer_data[$i]['email']) ? $customer_data[$i]['email'] : '';
                $this->first_name = !empty($customer_data[$i]['firstname']) ? $customer_data[$i]['firstname'] : '';
                $this->last_name = !empty($customer_data[$i]['lastname']) ? $customer_data[$i]['lastname'] : '';
                
                // If logged in user register with newsletter
                $id_customer = $customer_data[$i]['id_customer'];
                $customer = new CustomerCore((int)$id_customer);
                
                // Code to get address of logged in user
                if (Validate::isLoadedObject($customer)) {
                    if (version_compare(_PS_VERSION_, '1.5.3.4', '>')) {
                        $customer_address = $customer->getAddresses((int)$customer_data[$i]['id_lang']);
                    } else {
                        $customer_address = $this->getCustomerAddresses((int)$id_customer);
                    }
                }

                // Check if user have address data
                if ($customer_address && count($customer_address) > 0) {
                    
                    // Code to get latest phone number of logged in user
                    $count_address = count($customer_address);
                    for ($i = $count_address; $i >= 0; $i--) {
                        $temp = 0;
                        foreach ($customer_address as $select_address) {
                            if ($temp < $select_address['date_upd'] && !empty($select_address['phone_mobile'])) {
                                $temp = $select_address['date_upd'];
                            }
                        }
                        return $select_address;
                    }
                }
                if (!empty($select_address['phone_mobile'])) {
                    break;
                }
            }
        }
    }
    
    /**
     *   Display user's newsletter subscription
     *   This function displays both Sendin's and PrestaShop's newsletter subscription status.
     *   It also allows you to change the newsletter subscription status.
     */
    public function displayNewsletterEmail()
    {
        $sub_count = $this->totalsubscribedUser();
        $unsub_count = $this->totalUnsubscribedUser();
        $counter1 = $this->getTotalSubUnReg();
        $counter2 = $this->getTotalUnSubUnReg();
        $sub_count = $sub_count + $counter1;
        $unsub_count = $unsub_count + $counter2;
        
        $middlelabel = $this->l('You have ') . ' ' . $sub_count . ' ' . $this->l(' contacts subscribed and ') . ' ' . $unsub_count . ' ' . $this->l(' contacts unsubscribed from PrestaShop') . '<span id="Spantextmore">' . $this->l('. For more details,   ') . '</span><span id="Spantextless" style="display:none;">' . $this->l('. For less details,   ') . '</span>  <a href="javascript:void(0);" id="showUserlist">' . $this->l('click here') . '</a>';
        
        $this->context->smarty->assign('middlelable', $middlelabel);
        $this->context->smarty->assign('cl_version', $this->cl_version);
        
        return $this->display(__FILE__, 'views/templates/admin/userlist.tpl');
    }
    
    public function ajaxDisplayNewsletterEmail($id_shop_group, $id_shop)
    {
        $page = Tools::getValue('page');
        
        if (isset($page) && Configuration::get('Sendin_Api_Key_Status', '', $id_shop_group, $id_shop) == 1) {
            $page = (int)$page;
            
            $cur_page = $page;
            $page-= 1;
            $per_page = 20;
            $previous_btn = true;
            $next_btn = true;
            $first_btn = true;
            $last_btn = true;
            $start = $page * $per_page;
            $count = $this->getTotalEmail();
            $no_of_paginations = ceil($count / $per_page);
            
            if ($cur_page >= 7) {
                $start_loop = $cur_page - 3;
                if ($no_of_paginations > $cur_page + 3) {
                    $end_loop = $cur_page + 3;
                } elseif ($cur_page <= $no_of_paginations && $cur_page > $no_of_paginations - 6) {
                    $start_loop = $no_of_paginations - 6;
                    $end_loop = $no_of_paginations;
                } else {
                    $end_loop = $no_of_paginations;
                }
            } else {
                $start_loop = 1;
                if ($no_of_paginations > 7) {
                    $end_loop = 7;
                } else {
                    $end_loop = $no_of_paginations;
                }
            }
            
            $this->context->smarty->assign('previous_btn', $previous_btn);
            $this->context->smarty->assign('next_btn', $next_btn);
            $this->context->smarty->assign('cur_page', (int)$cur_page);
            $this->context->smarty->assign('first_btn', $first_btn);
            $this->context->smarty->assign('last_btn', $last_btn);
            $this->context->smarty->assign('start_loop', (int)$start_loop);
            $this->context->smarty->assign('end_loop', $end_loop);
            $this->context->smarty->assign('no_of_paginations', $no_of_paginations);
            $result = $this->getNewsletterEmails((int)$start, (int)$per_page, $id_shop_group, $id_shop);
            $data = $this->checkUserSendinStatus($result, $id_shop_group, $id_shop);
            $smsdata = $this->fixCountyCodeinSmsCol($result);
            $this->context->smarty->assign('smsdata', $smsdata);
            $this->context->smarty->assign('result', $result);
            $this->context->smarty->assign('data', (array_key_exists('result', $data) ? $data['result'] : ''));
            $this->context->smarty->assign('cl_version', $this->cl_version);
            
            echo $this->display(__FILE__, 'views/templates/admin/ajaxuserlist.tpl');
        }
    }
    
    /**
     * This method is used to fix country code in SendinBlue
     */
    public function fixCountyCodeinSmsCol($result)
    {
        $smsdetail = array();
        if (!empty($result) && is_array($result)) {
            foreach ($result as $detail) {
                if (isset($detail['phone_mobile']) && !empty($detail['phone_mobile'])) {
                    $smsdetail[$detail['phone_mobile']] = $this->checkMobileNumber($detail['phone_mobile'], $detail['call_prefix']);
                }
            }
        }
        return $smsdetail;
    }
    
    /**
     * This method is used to check the subscriber's newsletter subscription status in SendinBlue
     */
    public function checkUserSendinStatus($result, $id_shop_group, $id_shop)
    {
        $data = array();
        $userstatus = array();
        if (!empty($result) && is_array($result)) {
            foreach ($result as $value) {
                $userstatus[] = Tools::strtolower($value['email']);
            }
        }
        $email = implode(',', $userstatus);
        $data['key'] = trim(Configuration::get('Sendin_Api_Key', '', $id_shop_group, $id_shop));
        $data['webaction'] = 'USERS-STATUS-BLACKLIST';
        $data['email'] = $email;
        
        return Tools::jsonDecode($this->curlRequest($data), true);
    }
    
    /**
     *  Returns the list of active registered and unregistered user details
     * from both the default customer table and SendinBlue newsletter table.
     */
    public function getBothNewsletteremails()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT P.email, P.newsletter as newsletter,
        if (P.id_customer is null, 0, "customer_table") as table_type
        from ' . _DB_PREFIX_ . 'customer AS P UNION select Q.email,
        Q.active as newsletter,
        if (Q.newsletter_date_add is null, 0, "sendin_newsletter_table") as table_type
        from ' . _DB_PREFIX_ . 'sendin_newsletter AS Q');
    }
    
    /**
     * Fetches the subscriber's details viz email address, dateime of subscription, status and returns the same
     * in array format.
     */
    public function addNewUsersToDefaultList($id_shop_group = null, $id_shop = null)
    {
        $condition = $this->conditionalValueSecond($id_shop_group, $id_shop);
        $handle = fopen(_PS_MODULE_DIR_ . 'sendinblue/csv/SyncToSendinblue.csv', 'w+');
        $register_result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('SELECT C.email,
        C.newsletter as newsletter, C.date_upd as date_add
        from ' . _DB_PREFIX_ . 'customer AS C ' . $condition . '');
        
        if ($register_result) {
            foreach ($register_result as $register_row) {
                fwrite($handle, $register_row['email'] . ',' . $register_row['newsletter'] . ',' . $register_row['date_add'] . PHP_EOL);
            }
        }

        
        $unregister_result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('select C.email,
        C.active as newsletter, C.newsletter_date_add as date_add
        from ' . _DB_PREFIX_ . 'sendin_newsletter AS C ' . $condition . '');
        if ($unregister_result) {
            foreach ($unregister_result as $unregister_row) {
                fwrite($handle, $unregister_row['email'] . ',' . $unregister_row['newsletter'] . ',' . $unregister_row['date_add'] . PHP_EOL);
            }
        }
        fclose($handle);
        $value_total = (count($register_result) + count($unregister_result));
        return $value_total;
    }
    
    /**
     * We send an array of subscriber's email address along with the local timestamp to the SendinBlue API server
     * and based on the same the SendinBlue API server sends us a response with the current
     * status of each of the email address.
     */
    public function usersStatusTimeStamp($id_shop_group = null, $id_shop = null)
    {
        $result = $this->addNewUsersToDefaultList($id_shop_group, $id_shop);
        if ($result > 0) {
            $data = array();
            $data['webaction'] = 'UPDATE_USER_STATUS';
            $data['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
            $data['url'] = $this->local_path . $this->name . '/csv/SyncToSendinblue.csv';
            $data['timezone'] = date_default_timezone_get();
            $data['notify_url'] = $this->local_path . 'sendinblue/CronResponce.php?token=' . Tools::encrypt(Configuration::get('PS_SHOP_NAME'));
            
            // List id should be optional
            $this->curlRequest($data);
        }
    }
    
    /**
     * Method is used to check the current status of the module whether its active or not.
     */
    public function checkModuleStatus()
    {
        if (version_compare(_PS_VERSION_, '1.5.0.13', '<')) {
            return Db::getInstance()->getValue('SELECT `active` FROM `' . _DB_PREFIX_ . 'module`
            WHERE `name` = \'' . pSQL('sendinblue') . '\'');
        } elseif (!Module::isEnabled('sendinblue')) {
            return false;
        }
        return true;
    }
    
    /**
     * Checks whether the SendinBlue API key and the SendinBlue subscription form is enabled
     * and returns the true|false accordingly.
     */
    public function syncSetting($id_shop_group = null, $id_shop = null)
    {
        $id_shop = !empty($this->id_shop) ? $this->id_shop : $id_shop;
        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : $id_shop_group;
        
        if (Configuration::get('Sendin_Api_Key_Status', '', $id_shop_group, $id_shop) == 0 || Configuration::get('Sendin_Subscribe_Setting', '', $id_shop_group, $id_shop) == 0) {
            return false;
        }
        return $this->checkModuleStatus();
    }
    
    /**
     * This is an automated version of the usersStatusTimeStamp method but is called using a CRON.
     */
    public function userStatus($id_shop_group = null, $id_shop = null)
    {
        if (!$this->syncSetting($id_shop_group, $id_shop)) {
            return false;
        }
        $this->usersStatusTimeStamp($id_shop_group, $id_shop);
    }

    /**
     * Fetches all the subscribers of PrestaShop and adds them to the SendinBlue database.
     */
    private function autoSubscribeAfterInstallation()
    {
        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 'NULL';
        $id_shop = !empty($this->id_shop) ? $this->id_shop : 'NULL';
        
        $value_langauge = $this->getApiConfigValue();
        $handle = fopen(_PS_MODULE_DIR_ . 'sendinblue/csv/ImportSubUsersToSendinblue.csv', 'w+');
        $key_value = array();
        if ($value_langauge->language == 'fr') {
            $key_value[] = 'EMAIL,CIV,PRENOM,NOM,DDNAISSANCE,PS_LANG,CLIENT,SMS,GROUP_ID';
        } else {
            $key_value[] = 'EMAIL,CIV,NAME,SURNAME,BIRTHDAY,PS_LANG,CLIENT,SMS,GROUP_ID';
        }
        
        foreach ($key_value as $linedata) {
            fwrite($handle, $linedata . "\n");
        }
        
        if ($id_shop === 'NULL' && $id_shop_group === 'NULL') {
            $condition3 = '';
        } elseif ($id_shop_group != 'NULL' && $id_shop === 'NULL') {
            $condition3 = 'AND id_shop_group =' . $id_shop_group;
        } else {
            $condition3 = 'AND id_shop_group =' . $id_shop_group . ' AND id_shop =' . $id_shop;
        }
        
        $unregister_value = Db::getInstance()->ExecuteS('SELECT count(*) as count_value FROM ' . _DB_PREFIX_ . 'sendin_newsletter WHERE active = 1 ' . $condition3);
        
        // registered user store in array
        $start = 0;
        $end = 1000;
        $page = 1;
        $total_page = Tools::ceilf($unregister_value[0]['count_value'] / $end);
        while ($total_page > 0) {
            $register_email = array();
            $start = ($page - 1) * $end;
            $unregister_result = Db::getInstance()->ExecuteS('SELECT email FROM ' . _DB_PREFIX_ . 'sendin_newsletter WHERE active = 1 ' . $condition3 . ' limit ' . $start . ',' . $end . '');
            
            // unregistered user store in array
            if ($unregister_result) {
                foreach ($unregister_result as $unregister_row) {
                    if (version_compare(_PS_VERSION_, '1.5', '>=')) {
                        $all_group_unsubs = Db::getInstance()->getRow('SELECT GROUP_CONCAT(id_shop_group) as groupid FROM ' . _DB_PREFIX_ . 'sendin_newsletter  WHERE `active` = 1 AND email = "' . pSQL($unregister_row['email']) . '" GROUP BY email');
                        $data_group = $all_group_unsubs['groupid'];
                        if ($data_group === 'null') {
                            $data_group = 1;
                        }
                        if ($value_langauge->language == 'fr') {
                            $register_email[] = array('EMAIL' => $unregister_row['email'], 'CIV' => '', 'PRENOM' => '', 'NOM' => '', 'DDNAISSANCE' => '', 'PS_LANG' => '', 'CLIENT' => 0, 'SMS' => '', 'GROUP_ID' => $data_group);
                        } else {
                            $register_email[] = array('EMAIL' => $unregister_row['email'], 'CIV' => '', 'NAME' => '', 'SURNAME' => '', 'BIRTHDAY' => '', 'PS_LANG' => '', 'CLIENT' => 0, 'SMS' => '', 'GROUP_ID' => $data_group);
                        }
                    } else {
                        if ($value_langauge->language == 'fr') {
                            $register_email[] = array('EMAIL' => $unregister_row['email'], 'CIV' => '', 'PRENOM' => '', 'NOM' => '', 'DDNAISSANCE' => '', 'PS_LANG' => '', 'CLIENT' => 0, 'SMS' => '', 'GROUP_ID' => '');
                        } else {
                            $register_email[] = array('EMAIL' => $unregister_row['email'], 'CIV' => '', 'NAME' => '', 'SURNAME' => '', 'BIRTHDAY' => '', 'PS_LANG' => '', 'CLIENT' => 0, 'SMS' => '', 'GROUP_ID' => '');
                        }
                    }
                }
            }
            $page++;
            $total_page--;
            foreach ($register_email as $line) {
                fputcsv($handle, $line);
            }
        }
        $condition = $this->conditionalValue();
        
        $register_total = Db::getInstance()->ExecuteS('SELECT count(*) as total_val FROM ' . _DB_PREFIX_ . 'customer WHERE newsletter=1 ' . $condition);
        $start = 0;
        $end = 1000;
        $page = 1;
        $total_page = Tools::ceilf($register_total[0]['total_val'] / $end);
        while ($total_page > 0) {
            $register_email = array();
            $start = ($page - 1) * $end;
            
            if ($id_shop === 'NULL' && $id_shop_group === 'NULL') {
                $condition2 = '';
            } elseif ($id_shop_group != 'NULL' && $id_shop === 'NULL') {
                $condition2 = ' AND C.id_shop_group =' . $id_shop_group;
            } else {
                $condition2 = ' AND C.id_shop_group =' . $id_shop_group . ' AND C.id_shop =' . $id_shop;
            }
            
            if (version_compare(_PS_VERSION_, '1.5.3.4', '>')) {
                
                // select only newly added users and registered user
                
                $register_result = Db::getInstance()->ExecuteS('
                SELECT GROUP_CONCAT(C.id_customer) as cid, C.newsletter, C.email, C.firstname, C.lastname, C.birthday, C.id_gender, C.id_lang, GROUP_CONCAT(C.id_shop_group) as shopgroupid
                FROM ' . _DB_PREFIX_ . 'customer as C WHERE C.newsletter=1' . $condition2 . '
                GROUP BY C.email limit ' . $start . ',' . $end . '');
                if ($register_result) {
                    foreach ($register_result as $register_row) {
                        $data1 = explode(',', $register_row['cid']);
                        $total_ids = count($data1);
                        $total_ids = $total_ids - 1;
                        for ($i = $total_ids; $i >= 0; $i--) {
                            $sms_result = Db::getInstance()->ExecuteS('
                            SELECT  PSA.id_address, PSA.date_upd, PSA.phone_mobile, ' . _DB_PREFIX_ . 'country.call_prefix
                            FROM  ' . _DB_PREFIX_ . 'address PSA LEFT JOIN ' . _DB_PREFIX_ . 'country ON ' . _DB_PREFIX_ . 'country.id_country =  PSA.id_country WHERE PSA.id_customer = ' . $data1[$i] . ' and (PSA.id_customer, PSA.date_upd) IN
                            (SELECT id_customer, MAX(date_upd) upd  FROM ' . _DB_PREFIX_ . 'address GROUP BY ' . _DB_PREFIX_ . 'address.id_customer) ');
                            
                            if (!empty($sms_result[0]['phone_mobile'])) {
                                break;
                            }
                        }
                        
                        if (!empty($sms_result[0]['phone_mobile']) && $sms_result[0]['phone_mobile'] != '') {
                            $mobile = $this->checkMobileNumber($sms_result[0]['phone_mobile'], $sms_result[0]['call_prefix']);
                        } else {
                            $mobile = '';
                        }
                        
                        $birthday = (isset($register_row['birthday'])) ? $register_row['birthday'] : '';
                        if ($birthday > 0) {
                            if ($value_langauge->date_format == 'dd-mm-yyyy') {
                                $birthday = date('d-m-Y', strtotime($birthday));
                            } else {
                                $birthday = date('m-d-Y', strtotime($birthday));
                            }
                        }
                        
                        $civility_value = (isset($register_row['id_gender'])) ? $register_row['id_gender'] : '';
                        if ($civility_value == 1) {
                            $civility = 'Mr.';
                        } elseif ($civility_value == 2) {
                            $civility = 'Ms.';
                        } elseif ($civility_value == 3) {
                            $civility = 'Miss.';
                        } else {
                            $civility = '';
                        }
                        $all_group_subs = array();
                        $all_group_subs = Db::getInstance()->getRow('SELECT GROUP_CONCAT(id_shop_group) as groupid FROM ' . _DB_PREFIX_ . 'sendin_newsletter  WHERE `active` = 1 AND email = "' . pSQL($register_row['email']) . '" GROUP BY email');
                        if (!empty($all_group_subs['groupid'])) {
                            $data_second = explode(',', $all_group_subs['groupid']);
                            $data_first = explode(',', $register_row['shopgroupid']);
                            $uniqedata = array_merge($data_first, $data_second);
                            $value_merge = array_unique($uniqedata);
                            $value_group = implode(',', $value_merge);
                        } else {
                            $value_group = $register_row['shopgroupid'];
                        }
                        
                        $langisocode = Language::getIsoById((int)$register_row['id_lang']);
                        if ($value_langauge->language == 'fr') {
                            $register_email[] = array('EMAIL' => $register_row['email'], 'CIV' => $civility, 'PRENOM' => $register_row['firstname'], 'NOM' => $register_row['lastname'], 'DDNAISSANCE' => $birthday, 'PS_LANG' => $langisocode, 'CLIENT' => 1, 'SMS' => $mobile, 'GROUP_ID' => $value_group);
                        } else {
                            $register_email[] = array('EMAIL' => $register_row['email'], 'CIV' => $civility, 'NAME' => $register_row['firstname'], 'SURNAME' => $register_row['lastname'], 'BIRTHDAY' => $birthday, 'PS_LANG' => $langisocode, 'CLIENT' => 1, 'SMS' => $mobile, 'GROUP_ID' => $value_group);
                        }
                    }
                }
                $page++;
                $total_page--;
                foreach ($register_email as $line) {
                    fputcsv($handle, $line);
                }
            } else {
                
                // select only newly added users and registered user
                if (version_compare(_PS_VERSION_, '1.5.0.0', '>=')) {
                    
                    // select only newly added users and registered user
                    $register_result = Db::getInstance()->ExecuteS('
                    SELECT  GROUP_CONCAT(C.id_customer) as cid, C.newsletter, C.email, C.firstname, C.lastname, C.birthday, C.id_gender, GROUP_CONCAT(id_shop_group) as shopgroupid
                    FROM ' . _DB_PREFIX_ . 'customer as C WHERE C.newsletter=1' . $condition2 . '
                    GROUP BY C.email limit ' . $start . ',' . $end . '');
                } else {
                    $register_result = Db::getInstance()->ExecuteS('
                    SELECT  C.id_customer, C.newsletter, C.email, C.firstname, C.lastname, C.birthday, C.id_gender, PSA.id_address, PSA.date_upd, PSA.phone_mobile, ' . _DB_PREFIX_ . 'country.call_prefix
                    FROM ' . _DB_PREFIX_ . 'customer as C LEFT JOIN ' . _DB_PREFIX_ . 'address PSA ON (C.id_customer = PSA.id_customer and (PSA.id_customer, PSA.date_upd) IN
                    (SELECT id_customer, MAX(date_upd) upd  FROM ' . _DB_PREFIX_ . 'address GROUP BY ' . _DB_PREFIX_ . 'address.id_customer))
                    LEFT JOIN ' . _DB_PREFIX_ . 'country ON ' . _DB_PREFIX_ . 'country.id_country =  PSA.id_country
                    WHERE C.newsletter=1 ' . $condition2 . '
                    GROUP BY C.id_customer limit ' . $start . ',' . $end . '');
                }
                if ($register_result) {
                    foreach ($register_result as $register_row) {
                        if (version_compare(_PS_VERSION_, '1.5.0.0', '>=')) {
                            $data1 = explode(',', $register_row['cid']);
                            $total_ids = count($data1);
                            $total_ids = $total_ids - 1;
                            for ($i = $total_ids; $i >= 0; $i--) {
                                $sms_result = Db::getInstance()->ExecuteS('
                                SELECT  PSA.id_address, PSA.date_upd, PSA.phone_mobile, ' . _DB_PREFIX_ . 'country.call_prefix
                                FROM  ' . _DB_PREFIX_ . 'address PSA LEFT JOIN ' . _DB_PREFIX_ . 'country ON ' . _DB_PREFIX_ . 'country.id_country =  PSA.id_country WHERE PSA.id_customer = ' . $data1[$i] . ' and (PSA.id_customer, PSA.date_upd) IN
                                (SELECT id_customer, MAX(date_upd) upd  FROM ' . _DB_PREFIX_ . 'address GROUP BY ' . _DB_PREFIX_ . 'address.id_customer) ');
                                
                                if (!empty($sms_result[0]['phone_mobile'])) {
                                    break;
                                }
                            }
                            
                            if (!empty($sms_result[0]['phone_mobile']) && $sms_result[0]['phone_mobile'] != '') {
                                $mobile = $this->checkMobileNumber($sms_result[0]['phone_mobile'], $sms_result[0]['call_prefix']);
                            } else {
                                $mobile = '';
                            }
                        } else {
                            if (!empty($register_row['phone_mobile']) && $register_row['phone_mobile'] != '') {
                                $mobile = $this->checkMobileNumber($register_row['phone_mobile'], $register_row['call_prefix']);
                            } else {
                                $mobile = '';
                            }
                        }
                        
                        $birthday = (isset($register_row['birthday'])) ? $register_row['birthday'] : '';
                        if ($birthday > 0) {
                            if ($value_langauge->date_format == 'dd-mm-yyyy') {
                                $birthday = date('d-m-Y', strtotime($birthday));
                            } else {
                                $birthday = date('m-d-Y', strtotime($birthday));
                            }
                        }
                        
                        $civility_value = (isset($register_row['id_gender'])) ? $register_row['id_gender'] : '';
                        if ($civility_value == 1) {
                            $civility = 'Mr.';
                        } elseif ($civility_value == 2) {
                            $civility = 'Ms.';
                        } elseif ($civility_value == 3) {
                            $civility = 'Miss.';
                        } else {
                            $civility = '';
                        }
                        
                        if (version_compare(_PS_VERSION_, '1.5.0.0', '>=')) {
                            $all_group_subs = array();
                            $all_group_subs = Db::getInstance()->getRow('SELECT GROUP_CONCAT(id_shop_group) as groupid FROM ' . _DB_PREFIX_ . 'sendin_newsletter  WHERE `active` = 1 AND email = "' . pSQL($register_row['email']) . '" GROUP BY email');
                            if (!empty($all_group_subs['groupid'])) {
                                $data_second = explode(',', $all_group_subs['groupid']);
                                $data_first = explode(',', $register_row['shopgroupid']);
                                $uniqedata = array_merge($data_first, $data_second);
                                $value_merge = array_unique($uniqedata);
                                $value_group = implode(',', $value_merge);
                            } else {
                                $value_group = $register_row['shopgroupid'];
                            }
                        } else {
                            $value_group = '';
                        }
                        
                        $langisocode = '';
                        if ($value_langauge->language == 'fr') {
                            $register_email[] = array('EMAIL' => $register_row['email'], 'CIV' => $civility, 'PRENOM' => $register_row['firstname'], 'NOM' => $register_row['lastname'], 'DDNAISSANCE' => $birthday, 'PS_LANG' => $langisocode, 'CLIENT' => 1, 'SMS' => $mobile, 'GROUP_ID' => $value_group);
                        } else {
                            $register_email[] = array('EMAIL' => $register_row['email'], 'CIV' => $civility, 'NAME' => $register_row['firstname'], 'SURNAME' => $register_row['lastname'], 'BIRTHDAY' => $birthday, 'PS_LANG' => $langisocode, 'CLIENT' => 1, 'SMS' => $mobile, 'GROUP_ID' => $value_group);
                        }
                    }
                }
                $page++;
                $total_page--;
                foreach ($register_email as $line) {
                    fputcsv($handle, $line);
                }
            }
        }
        
        fclose($handle);
        $total_count = $register_total[0]['total_val'] + $unregister_value[0]['count_value'];
        return $total_count;
    }
    
    /**
     * Resets the default SMTP settings for PrestaShop.
     */
    public function resetConfigSendinSmtp($id_shop_group = null, $id_shop = null)
    {
        if ($id_shop === null) {
            $id_shop = $this->id_shop;
        }
        if ($id_shop_group === null) {
            $id_shop_group = $this->id_shop_group;
        }
        
        Configuration::updateValue('Sendin_Api_Smtp_Status', 0, '', $id_shop_group, $id_shop);
        Configuration::updateValue('PS_MAIL_METHOD', 1, '', $id_shop_group, $id_shop);
        Configuration::updateValue('PS_MAIL_SERVER', '', '', $id_shop_group, $id_shop);
        Configuration::updateValue('PS_MAIL_USER', '', '', $id_shop_group, $id_shop);
        Configuration::updateValue('PS_MAIL_PASSWD', '', '', $id_shop_group, $id_shop);
        Configuration::updateValue('PS_MAIL_SMTP_ENCRYPTION', '', '', $id_shop_group, $id_shop);
        Configuration::updateValue('PS_MAIL_SMTP_PORT', 25, '', $id_shop_group, $id_shop);
    }
    
    /**
     * This method is called when the user sets the API key and hits the submit button.
     * It adds the necessary configurations for SendinBlue in PrestaShop which allows
     * PrestaShop to use the SendinBlue settings.
     */
    public function postProcessConfiguration($id_shop_group, $id_shop)
    {
        $result_smtp = $this->trackingResult($id_shop_group, $id_shop);
        
        // If Sendinsmtp activation, let's configure
        if ($result_smtp->result->relay_data->status == 'enabled') {
            Configuration::updateValue('PS_MAIL_USER', $result_smtp->result->relay_data->data->username, '', $id_shop_group, $id_shop);
            Configuration::updateValue('PS_MAIL_PASSWD', $result_smtp->result->relay_data->data->password, '', $id_shop_group, $id_shop);
            
            // Test configuration
            $config = array('server' => $result_smtp->result->relay_data->data->relay, 'port' => $result_smtp->result->relay_data->data->port, 'protocol' => 'off');
            
            Configuration::updateValue('PS_MAIL_METHOD', 2, '', $id_shop_group, $id_shop);
            Configuration::updateValue('PS_MAIL_SERVER', $config['server'], '', $id_shop_group, $id_shop);
            Configuration::updateValue('PS_MAIL_SMTP_ENCRYPTION', $config['protocol'], '', $id_shop_group, $id_shop);
            Configuration::updateValue('PS_MAIL_SMTP_PORT', $config['port'], '', $id_shop_group, $id_shop);
            Configuration::updateValue('Sendin_Api_Smtp_Status', 1, '', $id_shop_group, $id_shop);
            
            return $this->l('Setting updated');
        } else {
            $this->resetConfigSendinSmtp();
            return $this->l('Your SMTP account is not activated and therefore you can\'t use SendinBlue SMTP. For more informations
, please contact our support to: contact@sendinblue.com');
        }
    }
    
    /**
     * This method is called when the user sets the OrderSms and hits the submit button.
     * It adds the necessary configurations for SendinBlue in PrestaShop which allows
     * PrestaShop to use sms service the SendinBlue settings.
     */
    public function saveSmsOrder()
    {
        
        // If Sendinsmtp activation, let's configure
        $sender_order = Tools::getValue('sender_order');
        $sender_order_message = Tools::getValue('sender_order_message');
        
        if ($sender_order != '' && $sender_order_message != '') {
            Configuration::updateValue('Sendin_Sender_Order', Tools::getValue('sender_order'), '', $this->id_shop_group, $this->id_shop);
            Configuration::updateValue('Sendin_Sender_Order_Message', Tools::getValue('sender_order_message'), '', $this->id_shop_group, $this->id_shop);
            return $this->redirectPage($this->l('Setting updated'), 'SUCCESS');
        }
    }
    
    /**
     * This method is called when the user want notification after having few credit.
     * It adds the necessary configurations for SendinBlue in PrestaShop which allows
     */
    public function sendSmsNotify()
    {
        Configuration::updateValue('Sendin_Notify_Value', Tools::getValue('sendin_notify_value'), '', $this->id_shop_group, $this->id_shop);
        Configuration::updateValue('Sendin_Notify_Email', Tools::getValue('sendin_notify_email'), '', $this->id_shop_group, $this->id_shop);
        return $this->redirectPage($this->l('Setting updated'), 'SUCCESS');
    }
    
    /**
     * This method is called when the user test order  Sms and hits the submit button.
     */
    public function sendOrderTestSms($sender, $message, $number, $iso_code)
    {
        $arr = array();
        $charone = Tools::substr($number, 0, 1);
        $chartwo = Tools::substr($number, 0, 2);
        if ($charone == '0' && $chartwo == '00') {
            $number = $number;
        }
        $result_code = Db::getInstance()->getRow('SELECT id_lang, lastname, firstname  FROM ' . _DB_PREFIX_ . 'employee');
        $civility = $this->l('Mr./Ms./Miss');
        $total_to_pay = rand(10, 1000);
        $total_pay = $total_to_pay . '.00 ' . $iso_code;
        $firstname = $result_code['firstname'];
        $lastname = $result_code['lastname'];
        if ($result_code['id_lang'] == 1) {
            $ord_date = date('m/d/Y');
        } else {
            $ord_date = date('d/m/Y');
        }
        
        if (version_compare(_PS_VERSION_, '1.5', '<')) {
            $characters = '1234567890';
        } else {
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        
        $ref_num = '';
        for ($i = 0; $i < 9; $i++) {
            $ref_num.= $characters[rand(0, Tools::strlen($characters) - 1) ];
        }
        
        $civility_data = str_replace('{civility}', $civility, $message);
        $fname = str_replace('{first_name}', $firstname, $civility_data);
        $lname = str_replace('{last_name}', $lastname . "\r\n", $fname);
        $product_price = str_replace('{order_price}', $total_pay, $lname);
        $order_date = str_replace('{order_date}', $ord_date . "\r\n", $product_price);
        $msgbody = str_replace('{order_reference}', $ref_num, $order_date);
        
        $arr['to'] = $number;
        $arr['from'] = $sender;
        $arr['text'] = $msgbody;
        
        $result = $this->sendSmsApi($arr);
        
        if (isset($result->status) && $result->status == 'OK') {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * This method is called when the user test Shipment  Sms and hits the submit button.
     */
    public function sendShipmentTestSms($sender, $message, $number, $iso_code)
    {
        $arr = array();
        $charone = Tools::substr($number, 0, 1);
        $chartwo = Tools::substr($number, 0, 2);
        
        if ($charone == '0' && $chartwo == '00') {
            $number = $number;
        }
        
        $result_code = Db::getInstance()->getRow('SELECT id_lang, lastname, firstname  FROM ' . _DB_PREFIX_ . 'employee');
        $civility = $this->l('Mr./Ms./Miss');
        $total_to_pay = rand(10, 1000);
        $total_pay = $total_to_pay . '.00 ' . $iso_code;
        $firstname = $result_code['firstname'];
        $lastname = $result_code['lastname'];
        if ($result_code['id_lang'] == 1) {
            $ord_date = date('m/d/Y');
        } else {
            $ord_date = date('d/m/Y');
        }
        if (version_compare(_PS_VERSION_, '1.5', '<')) {
            $characters = '1234567890';
        } else {
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        
        $ref_num = '';
        for ($i = 0; $i < 9; $i++) {
            $ref_num.= $characters[rand(0, Tools::strlen($characters) - 1) ];
        }
        
        $civility_data = str_replace('{civility}', $civility, $message);
        $fname = str_replace('{first_name}', $firstname, $civility_data);
        $lname = str_replace('{last_name}', $lastname . "\r\n", $fname);
        $product_price = str_replace('{order_price}', $total_pay, $lname);
        $order_date = str_replace('{order_date}', $ord_date . "\r\n", $product_price);
        $msgbody = str_replace('{order_reference}', $ref_num, $order_date);
        
        $arr['to'] = $number;
        $arr['from'] = $sender;
        $arr['text'] = $msgbody;
        $result = $this->sendSmsApi($arr);
        
        if (isset($result->status) && $result->status == 'OK') {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * This method is called when the user sets the Shiping Sms and hits the submit button.
     * It adds the necessary configurations for SendinBlue in PrestaShop which allows
     * PrestaShop to use sms service the SendinBlue settings.
     */
    public function saveSmsShiping()
    {
        $sender_shipment = Tools::getValue('sender_shipment');
        $sender_shipment_message = Tools::getValue('sender_shipment_message');
        
        if ($sender_shipment != '' && $sender_shipment_message != '') {
            Configuration::updateValue('Sendin_Sender_Shipment', $sender_shipment, '', $this->id_shop_group, $this->id_shop);
            Configuration::updateValue('Sendin_Sender_Shipment_Message', $sender_shipment_message, '', $this->id_shop_group, $this->id_shop);
            return $this->redirectPage($this->l('Setting updated'), 'SUCCESS');
        }
    }
    
    /**
     * This method is called when the user test Campaign  Sms and hits the submit button.
     */
    public function sendTestSmsCampaign($sender, $message, $number)
    {
        $charone = Tools::substr($number, 0, 1);
        $chartwo = Tools::substr($number, 0, 2);
        
        if ($charone == '0' && $chartwo == '00') {
            $number = $number;
        }
        
        $result_code = Db::getInstance()->getRow('SELECT id_lang, lastname, firstname  FROM ' . _DB_PREFIX_ . 'employee');
        $civility = $this->l('Mr./Ms./Miss');
        $firstname = $result_code['firstname'];
        $lastname = $result_code['lastname'];
        $civility_data = str_replace('{civility}', $civility, $message);
        $fname = str_replace('{first_name}', $firstname, $civility_data);
        $msgbody = str_replace('{last_name}', $lastname . "\r\n", $fname);
        $arr = array();
        $arr['to'] = $number;
        $arr['from'] = $sender;
        $arr['text'] = $msgbody;
        $result = $this->sendSmsApi($arr);
        
        if (isset($result->status) && $result->status == 'OK') {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * This method is called when the user sets the Campaign Sms and hits the submit button.
     * It adds the necessary configurations for Sendin in PrestaShop which allows
     * PrestaShop to use sms service the SendinBlue settings.
     */
    public function sendSmsCampaign()
    {
        $sendin_sms_choice = Tools::getValue('Sendin_Sms_Choice');
        
        if ($sendin_sms_choice == 1) {
            $this->singleChoiceCampaign();
        } elseif ($sendin_sms_choice == 0) {
            $this->multipleChoiceCampaign();
        } else {
            $this->multipleChoiceSubCampaign();
        }
    }
    
    /**
     * This method is called when the user sets the Campaign single Choic eCampaign and hits the submit button.
     */
    public function singleChoiceCampaign()
    {
        $sender_campaign = Tools::getValue('sender_campaign');
        $sender_campaign_number = Tools::getValue('singlechoice');
        $sender_campaign_message = Tools::getValue('sender_campaign_message');
        if (isset($sender_campaign) && $sender_campaign == '') {
            return $this->redirectPage($this->l('Please fill the sender field'), 'ERROR');
        } elseif ($sender_campaign_number == '') {
            return $this->redirectPage($this->l('Please fill the message field'), 'ERROR');
        } elseif ($sender_campaign_message == '') {
            return $this->redirectPage($this->l('Please fill the message field'), 'ERROR');
        } else {
            $arr = array();
            $arr['to'] = $sender_campaign_number;
            $arr['from'] = $sender_campaign;
            $arr['text'] = $sender_campaign_message;
            $result = $this->sendSmsApi($arr);
            if (isset($result->status) && $result->status == 'OK') {
                return $this->redirectPage($this->l('Message has been sent successfully'), 'SUCCESS');
            } else {
                return $this->redirectPage($this->l('Message has not been sent successfully'), 'ERROR');
            }
        }
    }
    
    /**
     * This method is called when the user sets the Campaign multiple Choic eCampaign and hits the submit button.
     */
    
    public function multipleChoiceCampaign()
    {
        $sender_campaign = Tools::getValue('sender_campaign');
        $sender_campaign_message = Tools::getValue('sender_campaign_message');
        
        if ($sender_campaign != '' && $sender_campaign_message != '') {
            $arr = array();
            $arr['from'] = $sender_campaign;
            
            $response = $this->getMobileNumber();
            foreach ($response as $value) {
                if (isset($value['phone_mobile']) && !empty($value['phone_mobile'])) {
                    $result = Db::getInstance()->getRow('SELECT `call_prefix` FROM ' . _DB_PREFIX_ . 'country WHERE `id_country` = \'' . (int)$value['id_country'] . '\'');
                    $number = $this->checkMobileNumber($value['phone_mobile'], (!empty($result['call_prefix']) ? $result['call_prefix'] : ''));
                    $first_name = (isset($value['firstname'])) ? $value['firstname'] : '';
                    $last_name = (isset($value['lastname'])) ? $value['lastname'] : '';
                    $customer_result = Db::getInstance()->ExecuteS('SELECT id_gender,firstname,lastname FROM ' . _DB_PREFIX_ . 'customer WHERE `id_customer` = ' . (int)$value['id_customer']);
                    
                    if (Tools::strtolower($first_name) === Tools::strtolower($customer_result[0]['firstname']) && Tools::strtolower($last_name) === Tools::strtolower($customer_result[0]['lastname'])) {
                        $civility_value = (isset($customer_result[0]['id_gender'])) ? $customer_result[0]['id_gender'] : '';
                    } else {
                        $civility_value = '';
                    }
                    
                    if ($civility_value == 1) {
                        $civility = 'Mr.';
                    } elseif ($civility_value == 2) {
                        $civility = 'Ms.';
                    } elseif ($civility_value == 3) {
                        $civility = 'Miss.';
                    } else {
                        $civility = '';
                    }
                    
                    $civility_data = str_replace('{civility}', $civility, $sender_campaign_message);
                    $fname = str_replace('{first_name}', $first_name, $civility_data);
                    $lname = str_replace('{last_name}', $last_name . "\r\n", $fname);
                    $arr['text'] = $lname;
                    $arr['to'] = $number;
                    $this->sendSmsApi($arr);
                }
            }
        }
        return $this->redirectPage($this->l('Message has been sent successfully'), 'SUCCESS');
    }
    
    /**
     * This method is called when the user sets the Campaign multiple Choic eCampaign and hits subscribed user the submit button.
     */
    public function multipleChoiceSubCampaign()
    {
        $sender_campaign = Tools::getValue('sender_campaign');
        $sender_campaign_message = Tools::getValue('sender_campaign_message');
        
        if ($sender_campaign != '' && $sender_campaign_message != '') {
            $camp_name = 'SMS_' . date('Ymd');
            $key = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
            if ($key == '') {
                return false;
            }
            $param = array();
            $param['key'] = $key;
            $param['listname'] = $camp_name;
            $param['webaction'] = 'NEWLIST';
            $param['list_parent'] = '1';
            
            //folder id
            $list_response = $this->curlRequest($param);
            $res = Tools::jsonDecode($list_response);
            $list_id = $res->result;
            
            // import old user to SendinBlue
            
            $iso_code = $this->context->language->iso_code;
            $allemail = $this->smsCampaignList();
            
            $data = array();
            $data['webaction'] = 'MULTI-USERCREADIT';
            $data['key'] = $key;
            $data['lang'] = $iso_code;
            $data['attributes'] = $allemail;
            $data['listid'] = $list_id;
            
            // List id should be optional
            
            $this->curlRequest($data);
            
            $arr = array();
            
            $first_name = '{NAME}';
            $last_name = '{SURNAME}';
            $civility = '{CIV}';
            
            $civility_data = str_replace('{civility}', $civility, $sender_campaign_message);
            $fname = str_replace('{first_name}', $first_name, $civility_data);
            $content = str_replace('{last_name}', $last_name . "\r\n", $fname);
            $arr['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
            $arr['webaction'] = 'SMSCAMPCREADIT';
            $arr['exclude_list'] = '';
            
            // mandatory
            $arr['camp_name'] = $camp_name;
            $arr['sender'] = $sender_campaign;
            $arr['content'] = $content;
            $arr['bat_sent'] = '';
            
            // mandatory if SMS campaign is scheduled
            $arr['listids'] = $list_id;
            $arr['schedule'] = date('Y-m-d H:i:s', time() + 300);
            $result = $this->curlRequest($arr);
            $data = Tools::jsondecode($result);
            if (!empty($data->errorMsg)) {
                return $this->redirectPage($this->l($data->errorMsg), 'ERROR');
            }
        }
        return $this->redirectPage($this->l('Message has been sent successfully'), 'SUCCESS');
    }
    
    /**
     *  This method is used to fetch all users from the default customer table to list
     * them in the SendinBlue PS plugin.
     */
    public function getMobileNumber()
    {
        $customer_data = $this->getAllCustomers();
        $address_mobilephone = array();
        foreach ($customer_data as $customer_detail) {
            $temp = 0;
            if (count($customer_detail) > 0 && !empty($customer_detail['id_customer'])) {
                $id_customer = $customer_detail['id_customer'];
                $customer = new CustomerCore((int)$id_customer);
                if (Validate::isLoadedObject($customer)) {
                    $customer_address = $customer->getAddresses((int)$this->context->language->id);
                }
                
                // Check if user have address data
                if ($customer_address && count($customer_address) > 0) {
                    
                    // Code to get latest phone number of logged in user
                    $count_address = count($customer_address);
                    for ($i = $count_address; $i >= 0; $i--) {
                        foreach ($customer_address as $select_address) {
                            if ($temp < $select_address['date_upd'] && !empty($select_address['phone_mobile'])) {
                                $temp = $select_address['date_upd'];
                                $address_mobilephone[$select_address['id_customer']] = $select_address;
                            }
                        }
                    }
                }
            }
        }
        return $address_mobilephone;
    }
    
    /**
     *  This method is used to fetch all subsribed users from the default customer table to list
     * them in the SendinBlue PS plugin.
     */
    public function geSubstMobileNumber()
    {
        $customer_data = $this->getAllCustomers();
        $address_mobilephone = array();
        foreach ($customer_data as $customer_detail) {
            $temp = 0;
            if (count($customer_detail) > 0 && !empty($customer_detail['id_customer']) && $customer_detail['newsletter_date_add'] > 0) {
                $id_customer = $customer_detail['id_customer'];
                $customer = new CustomerCore((int)$id_customer);
                if (Validate::isLoadedObject($customer)) {
                    $customer_address = $customer->getAddresses((int)$this->context->language->id);
                }
                
                // Check if user have address data
                if ($customer_address && count($customer_address) > 0) {
                    
                    // Code to get latest phone number of logged in user
                    $count_address = count($customer_address);
                    for ($i = $count_address; $i >= 0; $i--) {
                        foreach ($customer_address as $select_address) {
                            if ($temp < $select_address['date_upd'] && !empty($select_address['phone_mobile'])) {
                                $temp = $select_address['date_upd'];
                                $address_mobilephone[$select_address['id_customer']] = $select_address;
                            }
                        }
                    }
                }
            }
        }
        return $address_mobilephone;
    }
    
    /**
     * Send SMS from SendinBlue.
     */
    public function sendSmsApi($array)
    {
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
        $data['webaction'] = 'SENDSMS';
        $data['to'] = $array['to'];
        $data['from'] = $array['from'];
        $data['text'] = $array['text'];
        return Tools::jsonDecode($this->curlRequest($data));
    }
    
    /**
     * show  SMS  credit from SendinBlue.
     */
    public function getSmsCredit($id_shop_group = null, $id_shop = null)
    {
        if ($id_shop === null) {
            $id_shop = $this->id_shop;
        }
        
        if ($id_shop_group === null) {
            $id_shop_group = $this->id_shop_group;
        }
        
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $id_shop_group, $id_shop);
        $data['webaction'] = 'USER-CURRENT-PLAN';
        $sms_credit = $this->curlRequest($data);
        $result = Tools::jsonDecode($sms_credit);
        if (is_array($result)) {
            if ($result['1']->plan_type == 'SMS') {
                return $result['1']->credits;
            }
        }
    }
    
    /**
     * Method is called by PrestaShop by default everytime the module is loaded. It checks for some
     * basic settings and extensions like CURL and and allow_url_fopen to be enabled in the server.
     */
    public function getContent()
    {
        $this->_html.= $this->addCss();
        
        // send test mail to check if SMTP is working or not.
        if (Tools::isSubmit('sendTestMail')) {
            $this->sendMailProcess();
        }
        
        // send test sms to check if SMS is working or not.
        if (Tools::isSubmit('sender_order_submit')) {
            $this->sendOrderTestSms();
        }
        
        if (Tools::isSubmit('sender_order_save')) {
            $this->saveSmsOrder();
        }
        
        // send test sms to check if SMS is working or not.
        if (Tools::isSubmit('sender_shipment_submit')) {
            $this->sendShipmentTestSms();
        }
        
        if (Tools::isSubmit('sender_shipment_save')) {
            $this->saveSmsShiping();
        }
        
        // send test sms to check if SMS is working or not.
        if (Tools::isSubmit('sender_campaign_save')) {
            $this->sendSmsCampaign();
        }
        
        // send test sms to check if SMS is working or not.
        if (Tools::isSubmit('sender_campaign_test_submit')) {
            $this->sendTestSmsCampaign();
        }
        
        // send test sms to check if SMS is working or not.
        if (Tools::isSubmit('notify_sms_mail')) {
            $this->sendSmsNotify();
        }
        
        // update SMTP configuration in PrestaShop
        if (Tools::isSubmit('smtpupdate')) {
            Configuration::updateValue('Sendin_Smtp_Status', Tools::getValue('smtp'), '', $this->id_shop_group, $this->id_shop);
            $this->postProcessConfiguration();
        }
        
        // Import old user in sendinblue by csv
        if (Tools::isSubmit('submitUpdateImport')) {
            $email_value = $this->autoSubscribeAfterInstallation();
            if ($email_value > 0) {
                $data = array();
                $data['webaction'] = 'IMPORTUSERS';
                $data['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
                $data['url'] = $this->local_path . $this->name . '/csv/ImportSubUsersToSendinblue.csv';
                $data['listids'] = Configuration::get('Sendin_Selected_List_Data', '', $this->id_shop_group, $this->id_shop);
                $data['notify_url'] = $this->local_path . 'sendinblue/EmptyImportSubUsersFile.php?token=' . Tools::encrypt(Configuration::get('PS_SHOP_NAME'));
                
                // List id should be optional
                $responce_data = $this->curlRequestAsyc($data);
                $res_value = Tools::jsonDecode($responce_data);
                Configuration::updateValue('Sendin_import_user_status', 0, '', $this->id_shop_group, $this->id_shop);
                if (empty($res_value->process_id)) {
                    Configuration::updateValue('Sendin_import_user_status', 1, '', $this->id_shop_group, $this->id_shop);
                    $this->redirectPage($this->l('Old subscribers not imported successfully, please click on Import Old Subscribers button to import them again'), 'ERROR');
                }
            }
            return $this->redirectPage($this->l('Setting updated'), 'SUCCESS');
        }
        
        if (Tools::isSubmit('submitForm2')) {
            $this->saveTemplateValue();
            
            // update template id configuration in PrestaShop.
            $this->subscribeSettingPostProcess();
        }
        if (Tools::isSubmit('submitUpdate')) {
            $this->apiKeyPostProcessConfiguration();
        }
        
        if (!empty($this->context->cookie->display_message) && !empty($this->context->cookie->display_message_type)) {
            if ($this->context->cookie->display_message_type == 'ERROR') {
                $this->_html.= $this->displayError($this->l($this->context->cookie->display_message));
            } else {
                $this->_html.= $this->displayConfirmation($this->l($this->context->cookie->display_message));
            }
            
            unset($this->context->cookie->display_message, $this->context->cookie->display_message_type);
        }
        $this->displayForm();
        
        return $this->_html;
    }
    
    /**
     * This method is called when the user sets the subscribe setting and hits the submit button.
     * It adds the necessary configurations for SendinBlue in PrestaShop which allows
     * PrestaShop to use the SendinBlue settings.
     */
    public function subscribeSettingPostProcess()
    {
        $this->postValidationFormSync();
        
        if (!count($this->post_errors)) {
            if (Configuration::get('Sendin_Subscribe_Setting', '', $this->id_shop_group, $this->id_shop) == 1) {
                $display_list = Tools::getValue('display_list');
                if (!empty($display_list) && isset($display_list)) {
                    $display_list = implode('|', $display_list);
                    Configuration::updateValue('Sendin_Selected_List_Data', $display_list, '', $this->id_shop_group, $this->id_shop);
                }
            } else {
                Configuration::updateValue('Sendin_Subscribe_Setting', 0, '', $this->id_shop_group, $this->id_shop);
            }
        } else {
            $err_msg = '';
            
            foreach ($this->post_errors as $err) {
                $err_msg.= $err;
            }
            
            $this->redirectPage($this->l($err_msg), 'ERROR');
        }
        $this->redirectPage($this->l('Successfully updated'), 'SUCCESS');
    }
    
    /**
     * This method is called when the user send mail .
     */
    public function sendMailProcess()
    {
        $id_shop = !empty($this->id_shop) ? $this->id_shop : 'NULL';
        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 'NULL';
        
        $title = $this->l('[SendinBlue SMTP] test email');
        $smtp_result = Tools::jsonDecode(Configuration::get('Sendin_Smtp_Result', '', $id_shop_group, $id_shop));
        if ($id_shop_group = 'NULL' && $id_shop === 'NULL' && empty($smtp_result)) {
            $id_shop_group = 1;
            $id_shop = 1;
            $smtp_result = Tools::jsonDecode(Configuration::get('Sendin_Smtp_Result', '', $id_shop_group, $id_shop));
        }
        if ($smtp_result->result->relay_data->status == 'enabled') {
            $data_sendinblue_smtpstatus = $this->realTimeSmtpResult();
            if ($data_sendinblue_smtpstatus->result->relay_data->status == 'enabled') {
                $test_email = Tools::getValue('testEmail');
                if ($this->sendMail($test_email, $title)) {
                    $this->redirectPage($this->l('Mail sent'), 'SUCCESS');
                } else {
                    $this->redirectPage($this->l('Mail not sent'), 'ERROR');
                }
            } else {
                $this->redirectPage($this->l('Your SMTP account is not activated and therefore you can\'t use SendinBlue SMTP. For more informations, Please contact our support to: contact@sendinblue.com'), 'ERROR');
            }
        } else {
            $this->redirectPage($this->l('Your SMTP account is not activated and therefore you can\'t use SendinBlue SMTP. For more informations, Please contact our support to: contact@sendinblue.com'), 'ERROR');
        }
    }
    
    /**
     *This method is called when the user sets the API key and hits the submit button.
     *It adds the necessary configurations for SendinBlue in PrestaShop which allows
     *PrestaShop to use the SendinBlue settings.
     */
    public function apiKeyPostProcessConfiguration()
    {
        
        // endif User put new key after having old key
        $this->postValidation();
        
        if (!count($this->post_errors)) {
            
            //If the API key is valid, we activate the module, otherwise we deactivate it.
            $status = trim(Tools::getValue('status'));
            if (isset($status)) {
                Configuration::updateValue('Sendin_Api_Key_Status', $status, '', $this->id_shop_group, $this->id_shop);
            }
            
            if ($status == 1) {
                $apikey = trim(Tools::getValue('apikey'));
                $res = $this->getResultListValue($apikey);
                $row_list = Tools::jsonDecode($res);
                
                if (!empty($row_list->errorMsg) && $row_list->errorMsg == 'Key Not Found In Database') {
                    
                    //We reset all settings  in case the API key is invalid.
                    Configuration::updateValue('Sendin_Api_Key_Status', 0, '', $this->id_shop_group, $this->id_shop);
                    $this->resetDataBaseValue();
                    $this->resetConfigSendinSmtp($this->id_shop_group, $this->id_shop);
                    $this->redirectPage($this->l('API key is invalid.'), 'ERROR');
                } else {
                    
                    //If a user enters a new API key, we remove all records that belongs to the
                    //old API key.
                    $old_api_key = trim(Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop));
                    
                    // Old key
                    if ($apikey != $old_api_key) {
                        
                        // Reset data for old key
                        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 'NULL';
                        $id_shop = !empty($this->id_shop) ? $this->id_shop : 'NULL';
                        if ($id_shop_group === 'NULL' && $id_shop === 'NULL') {
                            Configuration::deleteByName('Sendin_First_Request');
                            Configuration::deleteByName('Sendin_Subscribe_Setting');
                            Configuration::deleteByName('Sendin_Tracking_Status');
                            Configuration::deleteByName('Sendin_Smtp_Result');
                            Configuration::deleteByName('Sendin_Api_Key');
                            Configuration::deleteByName('Sendin_Api_Key_Status');
                            Configuration::deleteByName('Sendin_Api_Smtp_Status');
                            Configuration::deleteByName('Sendin_Selected_List_Data');
                        } else {
                            Configuration::deleteFromContext('Sendin_First_Request');
                            Configuration::deleteFromContext('Sendin_Subscribe_Setting');
                            Configuration::deleteFromContext('Sendin_Tracking_Status');
                            Configuration::deleteFromContext('Sendin_Smtp_Result');
                            Configuration::deleteFromContext('Sendin_Api_Key');
                            Configuration::deleteFromContext('Sendin_Api_Key_Status');
                            Configuration::deleteFromContext('Sendin_Api_Smtp_Status');
                            Configuration::deleteFromContext('Sendin_Selected_List_Data');
                        }
                    }
                    
                    if (isset($apikey)) {
                        Configuration::updateValue('Sendin_Api_Key', $apikey, '', $this->id_shop_group, $this->id_shop);
                    }
                    
                    if (isset($status)) {
                        Configuration::updateValue('Sendin_Api_Key_Status', $status, '', $this->id_shop_group, $this->id_shop);
                    }
                    
                    $sendin_listdata = Configuration::get('Sendin_Selected_List_Data', '', $this->id_shop_group, $this->id_shop);
                    $sendin_firstrequest = Configuration::get('Sendin_First_Request', '', $this->id_shop_group, $this->id_shop);
                    
                    if (empty($sendin_listdata) && empty($sendin_firstrequest)) {
                        $this->getOldNewsletterEmails();
                        Configuration::updateValue('Sendin_First_Request', 1, '', $this->id_shop_group, $this->id_shop);
                        Configuration::updateValue('Sendin_Subscribe_Setting', 1, '', $this->id_shop_group, $this->id_shop);
                        Configuration::updateValue('Sendin_Notify_Cron_Executed', 0, '', $this->id_shop_group, $this->id_shop);
                        
                        //We remove the default newsletter block since we
                        //have to add the Sendin newsletter block.
                        $this->restoreBlocknewsletterBlock();
                        
                        if (empty($old_api_key)) {
                            $this->enableSendinblueBlock();
                        }
                        
                        $this->createFolderName();
                    }
                    
                    //We set the default status of SendinBlue SMTP and tracking code to 0
                    $this->checkSmtpStatus();
                    Configuration::updateValue('SENDINBLUE_CONFIGURATION_OK', true, '', $this->id_shop_group, $this->id_shop);
                    $this->redirectPage($this->l('Successfully updated'), 'SUCCESS');
                }
            }
        } else {
            $err_msg = '';
            
            foreach ($this->post_errors as $err) {
                $err_msg.= $err;
            }
            
            $this->redirectPage($this->l($err_msg), 'ERROR');
        }
    }
    
    /**
     * Redirect user to same page with message and message type (i.e. ERROR or SUCCESS)
     */
    private function redirectPage($msg = '', $type = 'SUCCESS')
    {
        $this->context->cookie->display_message = $msg;
        $this->context->cookie->display_message_type = $type;
        $this->context->cookie->write();
        
        $port = ($_SERVER['SERVER_PORT'] == '80') ? '' : (':' . $_SERVER['SERVER_PORT']);
        Tools::redirect(Tools::getShopDomainSsl(true) . $port . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    /**
     * Method to factory reset the database value
     */
    public function resetDataBaseValue()
    {
        Configuration::updateValue('Sendin_Tracking_Status', 0, '', $this->id_shop_group, $this->id_shop);
        Configuration::updateValue('Sendin_order_tracking_Status', 0, '', $this->id_shop_group, $this->id_shop);
        Configuration::updateValue('Sendin_Api_Smtp_Status', 0, '', $this->id_shop_group, $this->id_shop);
        Configuration::updateValue('Sendin_Selected_List_Data', '', '', $this->id_shop_group, $this->id_shop);
        Configuration::updateValue('Sendin_First_Request', '', '', $this->id_shop_group, $this->id_shop);
    }
    
    /**
     * Checks if API key is specified or not.
     */
    private function postValidation()
    {
        $apikey = trim(Tools::getValue('apikey'));
        $status = trim(Tools::getValue('status'));
        
        if (empty($apikey) && $status == 1) {
            $this->post_errors[] = $this->l('API key is invalid.');
        }
    }
    
    /**
     * Checks if the user has selected at least one list.
     */
    private function postValidationFormSync()
    {
        $display_list = Tools::getValue('display_list');
        
        if (isset($display_list) && empty($display_list)) {
            $this->post_errors[] = $this->l('Please choose atleast one list.');
        }
    }
    
    /**
     * Once we get all the list of the user from SendinBlue, we add them in
     * multi select dropdown box.
     */
    public function parselist()
    {
        $checkbox = '';
        $row = Tools::jsonDecode($this->getResultListValue());
        if (empty($row->result)) {
            return false;
        }
        
        $checkbox.= '<td><div class="listData"  style="text-align:left;">
        <select id="select" name="display_list[]" multiple="multiple">';
        
        foreach ($row->result as $valuearray) {
            $checkbox.= '<option value="' . (int)$valuearray->id . '" ' . $this->getSelectedvalue($valuearray->id) . ' >   
            <span style="margin-left:10px;" class="' . $this->cl_version . '"> ' . Tools::safeOutput($valuearray->name) . '</option>';
        }
        $checkbox.= '</select>
        <span class="toolTip listData"
        title="' . $this->l('Select the contact list where you want to save the contacts of your site PrestaShop. By default, we have created a list PrestaShop in your SendinBlue account and we have selected it') . '"  >
        &nbsp;</span></div></td>';
        
        return '<td><label>' . $this->l('Your lists') . '</label></td>' . $checkbox;
    }
    
    /**
     * Selects the list options that were already selected and saved by the user.
     */
    public function getSelectedvalue($value)
    {
        $result = explode('|', Configuration::get('Sendin_Selected_List_Data', '', $this->id_shop_group, $this->id_shop));
        if (in_array($value, $result)) {
            return 'selected="selected"';
        }
        return false;
    }
    
    /**
     * Fetches the SMTP and order tracking details
     */
    public function trackingResult($id_shop_group = '', $id_shop = '')
    {
        if ($id_shop == '') {
            $id_shop = $this->id_shop;
        }
        if ($id_shop_group == '') {
            $id_shop_group = $this->id_shop_group;
        }
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $id_shop_group, $id_shop);
        $data['webaction'] = 'TRACKINGDATA';
        $res = $this->curlRequest($data);
        Configuration::updateValue('Sendin_Smtp_Result', $res, '', $id_shop_group, $id_shop);
        return Tools::jsonDecode($res);
    }
    
    /**
     * Fetches the SMTP status details for send test mail
     */
    public function realTimeSmtpResult()
    {
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
        $data['webaction'] = 'TRACKINGDATA';
        $res = $this->curlRequest($data);
        return Tools::jsonDecode($res);
    }
    
    /**
     * CURL function to send request to the SendinBlue API server
     */
    public function curlRequest($data)
    {
        $url = 'http://ws.mailin.fr/';
        
        // WS URL
        $ch = curl_init();
        
        // prepate data for curl post
        $ndata = '';
        $data['source'] = 'PS';
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $ndata.= $key . '=' . urlencode($value) . '&';
            }
        } else {
            $ndata = $data;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $ndata);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        // Set curl to return the
        // data instead of
        // printing it to
        // the browser.
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    
    /**
     * CURL function to send request to the SendinBlue API server
     */
    public function curlRequestAsyc($data)
    {
        $url = 'http://ws.mailin.fr/';
        
        // WS URL
        $ch = curl_init();
        
        // prepate data for curl post
        $ndata = '';
        $data['source'] = 'PS';
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $ndata.= $key . '=' . urlencode($value) . '&';
            }
        } else {
            $ndata = $data;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $ndata);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        // Set curl to return the
        // data instead of
        // printing it to
        // the browser.
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    
    /**
     * Checks if a folder 'PrestaShop' and a list "PrestaShop" exits in the SendinBlue account.
     * If they do not exits, this method creates them.
     */
    public function createFolderCaseTwo()
    {
        $result = array();
        $result = $this->checkFolderList();
        $list_name = 'prestashop';
        $key = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
        if ($key == '') {
            return false;
        }
        $param = array();
        $data = array();
        $folder_id = $result['key'];
        $exist_list = $result['list_name'];
        
        if (!empty($key)) {
            $res = $this->getResultListValue();
            $rowlist = Tools::jsonDecode($res);
            if (!empty($rowlist->errorMsg) && $rowlist->errorMsg == 'Key Not Found In Database') {
                return false;
            }
        }
        
        if ($result === false) {
            
            // create folder
            $data['key'] = $key;
            $data['webaction'] = 'ADDFOLDER';
            $data['foldername'] = 'prestashop';
            $res = $this->curlRequest($data);
            $res = Tools::jsonDecode($res);
            $folder_id = $res->folder_id;
            
            // create list
            $param['key'] = $key;
            $param['listname'] = $list_name;
            $param['webaction'] = 'NEWLIST';
            $param['list_parent'] = $folder_id;
            
            //folder id
            $list_response = $this->curlRequest($param);
            $res = Tools::jsonDecode($list_response);
            $list_id = $res->result;
            
            // import old user to SendinBlue
            
            $emai_value = $this->autoSubscribeAfterInstallation();
            if ($emai_value > 0) {
                $data['webaction'] = 'IMPORTUSERS';
                $data['key'] = $key;
                $data['url'] = $this->local_path . $this->name . '/csv/ImportSubUsersToSendinblue.csv';
                $data['listids'] = $list_id;
                $data['notify_url'] = $this->local_path . 'sendinblue/EmptyImportSubUsersFile.php?token=' . Tools::encrypt(Configuration::get('PS_SHOP_NAME'));
                
                // List id should be optional
                Configuration::updateValue('Sendin_Selected_List_Data', trim($list_id), '', $this->id_shop_group, $this->id_shop);
                $response_data = $this->curlRequestAsyc($data);
                $res_value = Tools::jsonDecode($response_data);
                Configuration::updateValue('Sendin_import_user_status', 0, '', $this->id_shop_group, $this->id_shop);
                if (empty($res_value->process_id)) {
                    Configuration::updateValue('Sendin_import_user_status', 1, '', $this->id_shop_group, $this->id_shop);
                    $this->redirectPage($this->l('Old subscribers not imported successfully, please click on Import Old Subscribers button to import them again'), 'ERROR');
                }
            } else {
                Configuration::updateValue('Sendin_Selected_List_Data', trim($list_id), '', $this->id_shop_group, $this->id_shop);
            }
        } elseif (empty($exist_list)) {
            
            // create list
            $param['key'] = $key;
            $param['listname'] = $list_name;
            $param['webaction'] = 'NEWLIST';
            $param['list_parent'] = $folder_id;
            
            //folder id
            $list_response = $this->curlRequest($param);
            $res = Tools::jsonDecode($list_response);
            $list_id = $res->result;
            
            // import old user to SendinBlue
            
            $email_value = $this->autoSubscribeAfterInstallation();
            if ($email_value > 0) {
                $data['webaction'] = 'IMPORTUSERS';
                $data['key'] = $key;
                $data['url'] = $this->local_path . $this->name . '/csv/ImportSubUsersToSendinblue.csv';
                $data['listids'] = $list_id;
                
                // List id should be optional
                $data['notify_url'] = $this->local_path . 'sendinblue/EmptyImportSubUsersFile.php?token=' . Tools::encrypt(Configuration::get('PS_SHOP_NAME'));
                Configuration::updateValue('Sendin_Selected_List_Data', trim($list_id), '', $this->id_shop_group, $this->id_shop);
                $response_data = $this->curlRequestAsyc($data);
                $res_value = Tools::jsonDecode($response_data);
                Configuration::updateValue('Sendin_import_user_status', 0, '', $this->id_shop_group, $this->id_shop);
                if (empty($res_value->process_id)) {
                    Configuration::updateValue('Sendin_import_user_status', 1, '', $this->id_shop_group, $this->id_shop);
                    $this->redirectPage($this->l('Old subscribers not imported successfully, please click on Import Old Subscribers button to import them again'), 'ERROR');
                }
            } else {
                Configuration::updateValue('Sendin_Selected_List_Data', trim($list_id), '', $this->id_shop_group, $this->id_shop);
            }
        }
    }
    
    /**
     * Creates a folder with the name 'prestashop' after checking it on SendinBlue platform
     * and making sure the folder name does not exists.
     */
    public function createFolderName()
    {
        
        //Create the necessary attributes on the SendinBlue platform for PrestaShop
        $this->createAttributesName();
        
        //Check if the folder exists or not on SendinBlue platform.
        $result = $this->checkFolderList();
        if ($result === false) {
            $data = array();
            $data['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
            $data['webaction'] = 'ADDFOLDER';
            $data['foldername'] = 'prestashop';
            $res = $this->curlRequest($data);
            $res = Tools::jsonDecode($res);
            $folder_id = $res->folder_id;
            $exist_list = '';
        } else {
            $folder_id = $result['key'];
            $exist_list = $result['list_name'];
        }
        
        $this->createNewList($folder_id, $exist_list);
        
        // create list in SendinBlue
        //Create the partner's name i.e. PrestaShop on SendinBlue platform
        $this->partnerPrestashop();
    }
    
    /**
     * Creates a list by the name "prestashop" on user's SendinBlue account.
     */
    public function createNewList($response, $exist_list)
    {
        if ($exist_list != '') {
            $list_name = 'prestashop_' . date('dmY');
        } else {
            $list_name = 'prestashop';
        }
        
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
        $data['listname'] = $list_name;
        $data['webaction'] = 'NEWLIST';
        $data['list_parent'] = $response;
        
        //folder id
        $list_response = $this->curlRequest($data);
        $res = Tools::jsonDecode($list_response);
        $this->sendAllMailIDToSendin($res->result);
    }
    
    /**
     * Fetches all folders and all list within each folder of the user's SendinBlue
     * account and displays them to the user.
     */
    public function checkFolderList()
    {
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
        $data['webaction'] = 'DISPLAY-FOLDERS-LISTS';
        
        if ($data['key'] == '') {
            return false;
        }
        
        $data['ids'] = '';
        
        //folder id
        $s_array = array();
        $list_response = $this->curlRequest($data);
        $res = Tools::jsonDecode($list_response, true);
        $return = false;
        if (!empty($res)) {
            foreach ($res as $key => $value) {
                if (Tools::strtolower($value['name']) == 'prestashop') {
                    $s_array['key'] = $key;
                    $s_array['list_name'] = $value['name'];
                    if (!empty($value['lists'])) {
                        foreach ($value['lists'] as $val) {
                            if (Tools::strtolower($val['name']) == 'prestashop') {
                                $s_array['folder_name'] = $val['name'];
                            }
                        }
                    }
                }
            }
            if (count($s_array) > 0) {
                $return = $s_array;
            } else {
                $return = false;
            }
        }
        return $return;
    }
    
    /**
     * Method is used to add the partner's name in SendinBlue.
     * In this case its "PRESTASHOP".
     */
    public function partnerPrestashop()
    {
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
        $data['webaction'] = 'MAILIN-PARTNER';
        $data['partner'] = 'PRESTASHOP';
        $this->curlRequest($data);
    }
    
    /**
     * Method is used to send all the subscribers from PrestaShop to
     * SendinBlue for adding / updating purpose.
     */
    public function sendAllMailIDToSendin($list_id)
    {
        $email_value = $this->autoSubscribeAfterInstallation();
        if ($email_value > 0) {
            $key = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
            $data = array();
            $data['webaction'] = 'IMPORTUSERS';
            $data['key'] = $key;
            $data['url'] = $this->local_path . $this->name . '/csv/ImportSubUsersToSendinblue.csv';
            $data['listids'] = $list_id;
            
            // List id should be optional
            $data['notify_url'] = $this->local_path . 'sendinblue/EmptyImportSubUsersFile.php?token=' . Tools::encrypt(Configuration::get('PS_SHOP_NAME'));
            
            Configuration::updateValue('Sendin_Selected_List_Data', trim($list_id), '', $this->id_shop_group, $this->id_shop);
            $response_data = $this->curlRequestAsyc($data);
            $res_value = Tools::jsonDecode($response_data);
            Configuration::updateValue('Sendin_import_user_status', 0, '', $this->id_shop_group, $this->id_shop);
            if (empty($res_value->process_id)) {
                Configuration::updateValue('Sendin_import_user_status', 1, '', $this->id_shop_group, $this->id_shop);
                $this->redirectPage($this->l('Old subscribers not imported successfully, please click on Import Old Subscribers button to import them again'), 'ERROR');
            }
        } else {
            Configuration::updateValue('Sendin_Selected_List_Data', trim($list_id), '', $this->id_shop_group, $this->id_shop);
        }
    }
    
    /**
     * Create Normal, Transactional, Calculated and Global attributes and their values
     * on SendinBlue platform. This is necessary for the PrestaShop to add subscriber's details.
     */
    public function createAttributesName()
    {
        $data = array();
        
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
        $data['webaction'] = 'ATTRIBUTES_CREATION';
        $value_langauge = $this->getApiConfigValue();
        if ($value_langauge->language == 'fr') {
            $data['normal_attributes'] = 'CIV,text|PRENOM,text|NOM,text|DDNAISSANCE,date|PS_LANG,text|SMS,text|GROUP_ID,text|CLIENT,number';
        } else {
            $data['normal_attributes'] = 'CIV,text|NAME,text|SURNAME,text|BIRTHDAY,date|PS_LANG,text|SMS,text|GROUP_ID,text|CLIENT,number';
        }
        $data['transactional_attributes'] = 'ORDER_ID,id|ORDER_DATE,date|ORDER_PRICE,number';
        $data['calculated_value'] = 'PS_LAST_30_DAYS_CA,
        SUM[ORDER_PRICE,ORDER_DATE,>,NOW(-30)],true
        | PS_CA_USER, SUM[ORDER_PRICE],true | PS_ORDER_TOTAL, COUNT[ORDER_ID],true';
        $data['global_computation_value'] = 'PS_CA_LAST_30DAYS,
        SUM[PS_LAST_30_DAYS_CA]
        | PS_CA_TOTAL,
        SUM[PS_CA_USER]|
        PS_ORDERS_COUNT,
        SUM[PS_ORDER_TOTAL]';
        return $this->curlRequest($data);
    }
    
    /**
     * Unsubscribe a subscriber from SendinBlue.
     */
    public function unsubscribeByruntime($email, $id_shop_group = '', $id_shop = '')
    {
        if ($id_shop === null) {
            $id_shop = $this->id_shop;
        }
        if ($id_shop_group === null) {
            $id_shop_group = $this->id_shop_group;
        }
        
        if (!$this->syncSetting($id_shop_group, $id_shop)) {
            return false;
        }
        
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $id_shop_group, $id_shop);
        $data['webaction'] = 'EMAILBLACKLIST';
        $data['blacklisted'] = '0';
        $data['email'] = $email;
        return $this->curlRequest($data);
    }
    
    /**
     * Subscribe a subscriber from SendinBlue.
     */
    public function subscribeByruntime($email, $post_value = '')
    {
        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 'NULL';
        $id_shop = !empty($this->id_shop) ? $this->id_shop : 'NULL';
        
        if (!$this->syncSetting($id_shop_group, $id_shop)) {
            return false;
        }
        $value_langauge = $this->getApiConfigValue($id_shop_group, $id_shop);
        $civility = '';
        $fname = '';
        $lname = '';
        $mobile = '';
        $birthday = '';
        $client = 0;
        $customer_data = $this->getCustomersByEmail($email);
        $customer_value = count($customer_data);
        $customer_value = ($customer_value - 1);
        if (!empty($customer_data[0]['id_customer']) && count($customer_data) > 0) {
            for ($i = $customer_value; $i >= 0; $i--) {
                $fname = !empty($customer_data[$i]['firstname']) ? $customer_data[$i]['firstname'] : '';
                $lname = !empty($customer_data[$i]['lastname']) ? $customer_data[$i]['lastname'] : '';
                $birthday = (isset($customer_data[$i]['birthday'])) ? $customer_data[$i]['birthday'] : '';
                
                if ($birthday > 0) {
                    if ($value_langauge->date_format == 'dd-mm-yyyy') {
                        $birthday = date('d-m-Y', strtotime($birthday));
                    } else {
                        $birthday = date('m-d-Y', strtotime($birthday));
                    }
                }
                
                $civility_value = (isset($customer_data[$i]['id_gender'])) ? $customer_data[$i]['id_gender'] : '';
                if ($civility_value == 1) {
                    $civility = 'Mr.';
                } elseif ($civility_value == 2) {
                    $civility = 'Ms.';
                } elseif ($civility_value == 3) {
                    $civility = 'Miss.';
                } else {
                    $civility = '';
                }
                
                // Code to get address of logged in user
                
                if (version_compare(_PS_VERSION_, '1.5.3.4', '>')) {
                    $iso_code = Language::getIsoById((int)$customer_data[0]['id_lang']);
                } else {
                    $iso_code = '';
                }
                
                $id_customer = $customer_data[$i]['id_customer'];
                $customer = new CustomerCore((int)$id_customer);
                if (Validate::isLoadedObject($customer)) {
                    if (version_compare(_PS_VERSION_, '1.5.3.4', '>')) {
                        $customer_address = $customer->getAddresses((int)$customer_data[$i]['id_lang']);
                    } else {
                        $customer_address = $this->getCustomerAddresses((int)$id_customer);
                    }
                }
                if ($customer_address && count($customer_address) > 0) {
                    
                    // Code to get latest phone number of logged in user
                    $count_address = count($customer_address);
                    for ($i = $count_address; $i >= 0; $i--) {
                        $temp = 0;
                        foreach ($customer_address as $select_address) {
                            if ($temp < $select_address['date_upd'] && !empty($select_address['phone_mobile'])) {
                                $temp = $select_address['date_upd'];
                            }
                        }
                        if (!empty($select_address)) {
                            break;
                        }
                    }
                }
                if (!empty($select_address['phone_mobile'])) {
                    $result = Db::getInstance()->getRow('SELECT `call_prefix` FROM ' . _DB_PREFIX_ . 'country WHERE `id_country` = \'' . (int)$select_address['id_country'] . '\'');
                    $mobile = $this->checkMobileNumber($select_address['phone_mobile'], $result['call_prefix']);
                    break;
                }
            }
        }
        $attribute_data = array();
        $attribute_key = array();
        if (!empty($civility)) {
            $attribute_data[] = $civility;
            $attribute_key[] = 'CIV';
        }
        if (!empty($fname)) {
            $attribute_data[] = $fname;
            if ($value_langauge->language == 'fr') {
                $attribute_key[] = 'PRENOM';
            } else {
                $attribute_key[] = 'NAME';
            }
            
            $client = 1;
        }
        if (!empty($lname)) {
            $attribute_data[] = $lname;
            if ($value_langauge->language == 'fr') {
                $attribute_key[] = 'NOM';
            } else {
                $attribute_key[] = 'SURNAME';
            }
        }
        if (!empty($birthday) && $birthday > 0) {
            $attribute_data[] = $birthday;
            if ($value_langauge->language == 'fr') {
                $attribute_key[] = 'DDNAISSANCE';
            } else {
                $attribute_key[] = 'BIRTHDAY';
            }
        }
        if (!empty($iso_code)) {
            $attribute_data[] = $iso_code;
            $attribute_key[] = 'PS_LANG';
        }
        if (!empty($mobile) && $mobile > 0) {
            $attribute_data[] = $mobile;
            $attribute_key[] = 'SMS';
        }
        if (version_compare(_PS_VERSION_, '1.5.3.4', '>') && isset($id_shop_group) && !empty($email)) {
            if ($id_shop_group === null) {
                $id_shop_group = 1;
            }
            $all_group_reg = array();
            $all_group_unsubs = array();
            $all_group_reg = Db::getInstance()->getRow('SELECT GROUP_CONCAT(id_shop_group) as groupid FROM ' . _DB_PREFIX_ . 'customer  WHERE email = "' . pSQL($email) . '" GROUP BY email');
            
            $all_group_unsubs = Db::getInstance()->getRow('SELECT GROUP_CONCAT(id_shop_group) as groupid FROM ' . _DB_PREFIX_ . 'sendin_newsletter  WHERE email = "' . pSQL($email) . '" GROUP BY email');
            $data_first = explode(',', $all_group_reg['groupid']);
            $data_second = explode(',', $all_group_unsubs['groupid']);
            $value_merge = array_merge($data_first, $data_second);
            $value_group = implode(',', array_filter($value_merge));
            $attribute_data[] = $value_group;
            $attribute_key[] = 'GROUP_ID';
        }
        
        if ($client >= 0) {
            $attribute_data[] = $client;
            $attribute_key[] = 'CLIENT';
        }
        $attribute_data = implode('|', $attribute_data);
        $attribute = $attribute_data;
        
        $data = array();
        $data['key'] = trim(Configuration::get('Sendin_Api_Key', '', $id_shop_group, $id_shop));
        $data['webaction'] = 'USERCREADITM';
        if ($post_value == 0 || $post_value == 1) {
            $data['blacklisted'] = 0;
        }
        
        $attribute_key = implode('|', $attribute_key);
        $data['attributes_name'] = $attribute_key;
        $data['attributes_value'] = $attribute;
        $data['category'] = '';
        $data['email'] = $email;
        $data['listid'] = Configuration::get('Sendin_Selected_List_Data', '', $id_shop_group, $id_shop);
        return $this->curlRequest($data);
    }
    
    /**
     * Add / Modify subscribers with their full details like Firstname, Lastname etc.
     */
    public function subscribeByruntimeRegister($email, $id_gender, $fname, $lname, $birthday, $langisocode, $phone_mobile, $newsletter_status = '', $id_shop_group = 'NULL', $id_shop = 'NULL')
    {
        $id_shop_group = !empty($id_shop_group) ? $id_shop_group : 'NULL';
        $id_shop = !empty($id_shop) ? $id_shop : 'NULL';
        
        if (!$this->syncSetting($id_shop_group, $id_shop)) {
            return false;
        }
        
        $value_langauge = $this->getApiConfigValue($id_shop_group, $id_shop);
        $birthday = (isset($birthday)) ? $birthday : '';
        if ($birthday > 0) {
            if ($value_langauge->date_format == 'dd-mm-yyyy') {
                $birthday = date('d-m-Y', strtotime($birthday));
            } else {
                $birthday = date('m-d-Y', strtotime($birthday));
            }
        }
        
        $client = 1;
        $civility_value = (isset($id_gender)) ? $id_gender : '';
        if ($civility_value == 1) {
            $civility = 'Mr.';
        } elseif ($civility_value == 2) {
            $civility = 'Ms.';
        } elseif ($civility_value == 3) {
            $civility = 'Miss.';
        } else {
            $civility = '';
        }
        
        if ($langisocode != '') {
            if (version_compare(_PS_VERSION_, '1.5.3.4', '>')) {
                $langisocode = Language::getIsoById((int)$langisocode);
            } else {
                $langisocode = Db::getInstance()->getValue('SELECT `iso_code` FROM `' . _DB_PREFIX_ . 'lang`
                WHERE `id_lang` = \'' . (int)$langisocode . '\'');
            }
        }
        
        $attribute_data = array();
        $attribute_key = array();
        if ($civility != '') {
            $attribute_data[] = $civility;
            $attribute_key[] = 'CIV';
        }
        if (!empty($fname)) {
            $attribute_data[] = $fname;
            if ($value_langauge->language == 'fr') {
                $attribute_key[] = 'PRENOM';
            } else {
                $attribute_key[] = 'NAME';
            }
        }
        if (!empty($lname)) {
            $attribute_data[] = $lname;
            if ($value_langauge->language == 'fr') {
                $attribute_key[] = 'NOM';
            } else {
                $attribute_key[] = 'SURNAME';
            }
        }
        if (!empty($birthday) && $birthday > 0) {
            $attribute_data[] = $birthday;
            if ($value_langauge->language == 'fr') {
                $attribute_key[] = 'DDNAISSANCE';
            } else {
                $attribute_key[] = 'BIRTHDAY';
            }
        }
        if (!empty($langisocode)) {
            $attribute_data[] = $langisocode;
            $attribute_key[] = 'PS_LANG';
        }
        if ($client >= 0) {
            $attribute_data[] = $client;
            $attribute_key[] = 'CLIENT';
        }
        if (!empty($phone_mobile) && $phone_mobile > 0) {
            $attribute_data[] = $phone_mobile;
            $attribute_key[] = 'SMS';
        }
        if (version_compare(_PS_VERSION_, '1.5.3.4', '>') && isset($id_shop_group) && !empty($email)) {
            if ($id_shop_group === null) {
                $id_shop_group = 1;
            }
            
            $all_group_reg = array();
            $all_group_unsubs = array();
            $all_group_reg = Db::getInstance()->getRow('SELECT GROUP_CONCAT(id_shop_group) as groupid FROM ' . _DB_PREFIX_ . 'customer  WHERE email = "' . pSQL($email) . '" GROUP BY email');
            $all_group_unsubs = Db::getInstance()->getRow('SELECT GROUP_CONCAT(id_shop_group) as groupid FROM ' . _DB_PREFIX_ . 'sendin_newsletter  WHERE email = "' . pSQL($email) . '" GROUP BY email');
            
            $data_first = array();
            $data_second = array();
            if (!empty($all_group_reg['groupid'])) {
                $data_first = explode(',', $all_group_reg['groupid']);
            }
            
            if (!empty($all_group_unsubs['groupid'])) {
                $data_second = explode(',', $all_group_unsubs['groupid']);
            }
            
            $value_merge = array_merge($data_first, $data_second);
            $value_group = implode(',', $value_merge);
            $attribute_data[] = $value_group;
            $attribute_key[] = 'GROUP_ID';
        }
        
        $attribute_data = implode('|', $attribute_data);
        $attribute = $attribute_data;
        $data = array();
        $data['key'] = trim(Configuration::get('Sendin_Api_Key', '', $id_shop_group, $id_shop));
        $data['webaction'] = 'USERCREADITM';
        $data['email'] = $email;
        
        if ($newsletter_status == 0 || $newsletter_status == 1) {
            $data['blacklisted'] = 0;
        }
        
        $attribute_key = implode('|', $attribute_key);
        $data['attributes_name'] = $attribute_key;
        $data['attributes_value'] = $attribute;
        $data['category'] = '';
        $data['listid'] = Configuration::get('Sendin_Selected_List_Data', '', $id_shop_group, $id_shop);
        $this->curlRequest($data);
    }
    
    /**
     * Checks whether a subscriber is registered in the sendin_newsletter table.
     * If they are registered, we subscriber them on SendinBlue.
     */
    private function isEmailRegistered($customer_email, $mobile_number, $newsletter_status, $id_shop_group = '', $id_shop = '')
    {
        $id_shop_group = !empty($id_shop_group) ? $id_shop_group : 'NULL';
        $id_shop = !empty($id_shop) ? $id_shop : 'NULL';
        
        if (version_compare(_PS_VERSION_, '1.5.3.4', '>')) {
            if (Db::getInstance()->getRow('SELECT `email` FROM ' . _DB_PREFIX_ . 'sendin_newsletter WHERE `email` = \'' . pSQL($customer_email) . '\'')) {
                $this->subscribeByruntime($customer_email, $newsletter_status);
            } elseif ($registered = Db::getInstance()->getRow('SELECT id_gender, firstname, lastname, birthday, id_lang FROM ' . _DB_PREFIX_ . 'customer WHERE `email` = \'' . pSQL($customer_email) . '\'')) {
                $this->subscribeByruntimeRegister($customer_email, $registered['id_gender'], $registered['firstname'], $registered['lastname'], $registered['birthday'], $registered['id_lang'], $mobile_number, $newsletter_status, $id_shop_group, $id_shop);
            }
        } else {
            if (Db::getInstance()->getRow('SELECT `email` FROM ' . _DB_PREFIX_ . 'sendin_newsletter WHERE `email` = \'' . pSQL($customer_email) . '\'')) {
                $this->subscribeByruntime($customer_email, $newsletter_status);
            } elseif ($registered = Db::getInstance()->getRow('SELECT id_gender, firstname, lastname, birthday FROM ' . _DB_PREFIX_ . 'customer WHERE `email` = \'' . pSQL($customer_email) . '\'')) {
                $this->subscribeByruntimeRegister($customer_email, $registered['id_gender'], $registered['firstname'], $registered['lastname'], $registered['birthday'], '', $mobile_number, $newsletter_status, $id_shop_group, $id_shop);
            }
        }
    }
    
    /**
     * Displays the tracking code in the code block.
     */
    public function codeDeTracking()
    {
        $this->html_code_tracking.= '
        <table class="table tableblock hidetableblock form-data" style="margin-top:15px;" cellspacing="0" cellpadding="0" width="100%">
        <thead>
        <tr>
        <th colspan="2">' . $this->l('Code tracking') . '</th>
        </tr>
        </thead>';
        
        $this->html_code_tracking.= '
        <tr><td><label>
        ' . $this->l('Do you want to install a tracking code when validating an order') . '
        </label><span class="' . $this->cl_version . '">
        <input type="radio" class="ordertracking script radio_nospaceing" id="yesradio" name="script" value="1"
        ' . (Configuration::get('Sendin_Tracking_Status', '', $this->id_shop_group, $this->id_shop) ? 'checked="checked" ' : '') . '/>' . $this->l('Yes') . '
        <input type="radio" class="ordertracking script radio_spaceing2" id="noradio"
        name="script" value="0" ' . (!Configuration::get('Sendin_Tracking_Status', '', $this->id_shop_group, $this->id_shop) ? 'checked="checked" ' : '') . '/>' . $this->l('No') . '
        <span class="toolTip"
        title="' . $this->l('This feature will allow you to transfer all your customers orders from PrestaShop into SendinBlue to implement your email marketing strategy.') . '">
        &nbsp;</span>
        </td></tr>';
        if (Configuration::get('Sendin_Tracking_Status', '', $this->id_shop_group, $this->id_shop) && Configuration::get('Sendin_order_tracking_Status', '', $this->id_shop_group, $this->id_shop) != 1) {
            $st = '';
        } else {
            $st = 'style="display:none;"';
        }
        
        $this->html_code_tracking.= '
        <form method="post" name="smtp" id="smtp" action="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '">
        <tr id="ordertrack" ' . $st . ' ><td>
        <div id="div_order_track">
        <input type ="hidden" name="importmsg" id="importmsg" value="' . $this->l('Order history has been import successfully') . '">
        <p style="text-align:center">' . '<a href="javascript:void(0);" id="importOrderTrack" class="button">
        ' . $this->l('Import the data of previous orders') . '</a>                            
        </div>
        </td></tr></form></table>';
        return $this->html_code_tracking;
    }
    
    /**
     *This method is used to show options to the user whether the user wants the plugin to manage
     *their subscribers automatically.
     */
    public function syncronizeBlockCode()
    {
        $this->_second_block_code = '<style type="text/css">.tableblock tr td{padding:5px; border-bottom:0px;}</style>
        <form method="post" action="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '">
        <table class="table tableblock hidetableblock form-data" style="margin-top:15px;" cellspacing="0" cellpadding="0" width="100%">
        <thead>
        <tr>
        <th colspan="2">' . $this->l('Activate SendinBlue to manage subscribers') . '</th>
        </tr>
        </thead>
        <tr>
        <td style="width:250px">
        <label> ' . $this->l('Activate SendinBlue to manage subscribers') . '
        </label>
        </td>
        <td class="' . $this->cl_version . '"><input type="radio" class="managesubscribe" id="yessmtp"
        style="margin-right:10px;" name="managesubscribe" value="1"
        ' . (Configuration::get('Sendin_Subscribe_Setting', '', $this->id_shop_group, $this->id_shop) ? 'checked="checked" ' : '') . '/>' . $this->l('Yes') . '
        <input type="radio" class="managesubscribe"
        id="nosmtp" style="margin-left:20px;margin-right:10px;"
        name="managesubscribe" value="0" ' . (!Configuration::get('Sendin_Subscribe_Setting', '', $this->id_shop_group, $this->id_shop) ? 'checked="checked" ' : '') . '/>' . $this->l('No') . '
        <span class="toolTip"
        title="' . $this->l('If you activate this feature, your new contacts will be automatically added to SendinBlue or unsubscribed from SendinBlue. To synchronize the other way (SendinBlue to PrestaShop), you should run the url (mentioned below) each day.') . '">
        &nbsp;</span>
        </td>
        </tr>';
        $this->_second_block_code.= '<tr class="managesubscribeBlock">' . $this->parselist() . '</tr>';
        $this->_second_block_code.= '<tr class="managesubscribeBlock"><td>&nbsp;</td>
        <td>
        </td>
        </tr>';
        $this->_second_block_code.= '<td style="width:250px" class="managesubscribeBlock">
        <label> ' . $this->l('Manage follow up email for user subscription') . '
        </label>
        </td>
        <td class="' . $this->cl_version . ' managesubscribeBlock"><div class="listData" style="text-align:left;"><select name="template" class="ui-state-default" style="width: 225px; height:22px; border-radius:4px; margin-right:5px;"><option value="">' . $this->l('Select Template') . '</option>
        ';
        $options = '';
        $camp = $this->templateDisplay();
        if (!empty($camp->result->campaign_records)) {
            foreach ($camp->result->campaign_records as $template_data) {
                if ($template_data->templ_status === 'Active') {
                    $options.= '<option value="' . $template_data->id . '"';
                    if ($template_data->id == Configuration::get('Sendin_Template_Id', '', $this->id_shop_group, $this->id_shop)) {
                        $options.= 'selected="selected"';
                    }
                    
                    $options.= '>' . $template_data->campaign_name . '</option>';
                }
            }
        }
        $this->_second_block_code.= $options . '</select><span class="toolTip"
        title="' . $this->l('Select a SendinBlue template that will be sent personalized for each contact that subscribes to your newsletter') . '"
        &nbsp;</span></div>
        </td>
        </tr>';
        $this->_second_block_code.= '<tr class="managesubscribeBlock"><td>&nbsp;</td>
        <td>
        <input type="submit" name="submitForm2" value="' . $this->l('Update') . '" class="button" />&nbsp;
        </td>
        </tr><tr class="managesubscribeBlock"><td>&nbsp;</td><td colspan="2">';
        $data = '';
        if (Configuration::get('Sendin_import_user_status', '', $this->id_shop_group, $this->id_shop) == 1) {
            $data.= '<input type="submit" name="submitUpdateImport" value="' . $this->l('Import Old Subscribers') . '" class="button" />&nbsp;</form>';
        }
        $this->_second_block_code.= $data . '</td>
        </tr><tr class="managesubscribeBlock" ><td colspan="3" class="' . $this->cl_version . '">' . $this->l('To synchronize the emails of your customers from SendinBlue platform to your e-commerce website, you should run') . '
        <a target="_blank" href="' . $this->local_path . 'sendinblue/cron.php?token=' . Tools::encrypt(Configuration::get('PS_SHOP_NAME')) . '">
        ' . $this->l('this link') . '</a> ';
        $this->_second_block_code.= $this->l('each day.') . '
        <span class="toolTip" title="' . $this->l('Note that if you change the name of your Shop (currently ') . Configuration::get('PS_SHOP_NAME') . $this->l(') the token value changes.') . '">&nbsp;</span></td></tr></table>';
        
        return $this->_second_block_code;
    }
    
    /**
     * Displays the SMTP details in the SMTP block.
     */
    public function mailSendBySmtp()
    {
        $this->_html_smtp_tracking = '
        <table class="table tableblock hidetableblock form-data" style="margin-top:15px;"
        cellspacing="0" cellpadding="0" width="100%">
        <thead>
        <tr>
        <th colspan="2">' . $this->l('Activate SendinBlue SMTP for your transactional emails') . '</th>
        </tr>
        </thead>';
        
        $this->_html_smtp_tracking.= '
        <tr><td><form method="post" action="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '">
        <label>
        ' . $this->l('Activate SendinBlue SMTP for your transactional emails') . '
        </label><span class="' . $this->cl_version . '">
        <input type="radio" class="smtptestclick radio_nospaceing" id="yessmtp"
        name="smtp"
        value="1" ' . (Configuration::get('Sendin_Api_Smtp_Status', '', $this->id_shop_group, $this->id_shop) ? 'checked="checked" ' : '') . '/>' . $this->l('Yes') . '
        <input type="radio" class="smtptestclick radio_spaceing2" id="nosmtp"
        name="smtp" value="0"
        ' . (!Configuration::get('Sendin_Api_Smtp_Status', '', $this->id_shop_group, $this->id_shop) ? 'checked="checked" ' : '') . '/>' . $this->l('No') . '
        <span class="toolTip" title="' . $this->l('Transactional email is an expected email because it is triggered automatically after a transaction or a specific event. Common examples of transactional email are : account opening and welcome message, order shipment confirmation, shipment tracking and purchase order status, registration via a contact form, account termination, payment confirmation, invoice etc.') . '">&nbsp;</span>
        </form></td></tr>';
        
        if (Configuration::get('Sendin_Api_Smtp_Status', '', $this->id_shop_group, $this->id_shop)) {
            $st = '';
        } else {
            $st = 'style="display:none;"';
        }
        
        $this->_html_smtp_tracking.= '
        <form method="post" name="smtp" id="smtp" action="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '">
        <tr id="smtptest" ' . $st . ' ><td colspan="2">
        <div id="div_email_test">
        <p style="text-align:center">' . $this->l('Send email test From / To') . ' :&nbsp;
        <input type="text" size="40" name="testEmail" value="' . Configuration::get('PS_SHOP_EMAIL') . '" id="email_from">
        &nbsp;
        <input type="submit"  class="button" value="' . $this->l('Send') . '" name="sendTestMail" id="sendTestMail"></p>
        </div>
        </td></tr></form></table>';
        
        return $this->_html_smtp_tracking;
    }
    
    /**
     * Displays the SMS details in the SMS block.
     */
    public function mailSendBySms()
    {
        $this->context->smarty->assign('site_name', Configuration::get('PS_SHOP_NAME'));
        $this->context->smarty->assign('link', '<a target="_blank" href="' . $this->local_path . 'sendinblue/smsnotifycron.php?lang=' . $this->context->language->id . '&token=' . Tools::encrypt(Configuration::get('PS_SHOP_NAME')) . '&id_shop_group=' . $this->id_shop_group . '&id_shop=' . $this->id_shop . '">' . $this->l('this link') . '</a>');
        $this->context->smarty->assign('current_credits_sms', $this->getSmsCredit());
        $this->context->smarty->assign('sms_campaign_status', Configuration::get('Sendin_Api_Sms_Campaign_Status', '', $this->id_shop_group, $this->id_shop));
        $this->context->smarty->assign('Sendin_Notify_Email', Configuration::get('Sendin_Notify_Email', '', $this->id_shop_group, $this->id_shop));
        $this->context->smarty->assign('sms_shipment_status', Configuration::get('Sendin_Api_Sms_shipment_Status', '', $this->id_shop_group, $this->id_shop));
        $this->context->smarty->assign('sms_order_status', Configuration::get('Sendin_Api_Sms_Order_Status', '', $this->id_shop_group, $this->id_shop));
        $this->context->smarty->assign('sms_credit_status', Configuration::get('Sendin_Api_Sms_Credit', '', $this->id_shop_group, $this->id_shop));
        $this->context->smarty->assign('prs_version', _PS_VERSION_);
        $this->context->smarty->assign('Sendin_Notify_Value', Configuration::get('Sendin_Notify_Value', '', $this->id_shop_group, $this->id_shop));
        $this->context->smarty->assign('Sendin_Sender_Order', Configuration::get('Sendin_Sender_Order', '', $this->id_shop_group, $this->id_shop));
        $this->context->smarty->assign('Sendin_Sender_Order_Message', Configuration::get('Sendin_Sender_Order_Message', '', $this->id_shop_group, $this->id_shop));
        $this->context->smarty->assign('Sendin_Sender_Shipment', Configuration::get('Sendin_Sender_Shipment', '', $this->id_shop_group, $this->id_shop));
        $this->context->smarty->assign('Sendin_Sender_Shipment_Message', Configuration::get('Sendin_Sender_Shipment_Message', '', $this->id_shop_group, $this->id_shop));
        $this->context->smarty->assign('form_url', Tools::safeOutput($_SERVER['REQUEST_URI']));
        $this->context->smarty->assign('cl_version', $this->cl_version);
        return $this->display(__FILE__, 'views/templates/admin/smssetting.tpl');
    }
    
    /**
     * Fetches all the list of the user from the Sendin platform.
     */
    public function getResultListValue($key = false)
    {
        $data = array();
        
        if (!$key) {
            $key = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
        }
        $data['key'] = $key;
        $data['webaction'] = 'DISPLAYLISTDATA';
        return $this->curlRequest($data);
    }
    
    private function displayBankWire()
    {
        $this->_html.= '<img src="' . $this->local_path . 'sendinblue/views/img/' . $this->l('sendinblue.png') . '"
        style="float:left; margin:0px 15px 30px 0px;"><div style="float:left;
        font-weight:bold; padding:25px 0px 0px 0px; color:#268CCD;">' . $this->l('SendinBlue : THE all-in-one plugin for your marketing and transactional emails.') . '</div>
        <div class="clear"></div>';
    }
    
    private function displaySendin()
    {
        $this->_html.= $this->displayBankWire();
        if ($this->checkOlderVesion() == 1) {
            $this->_html.= '<div class="module_error alert error">' . $this->l('We notified that you are using the previous version of our plugin, please uninstall it / remove it and keep only the latest version of SendinBlue') . '</div>';
        }
        $this->_html.= '
        <fieldset>
        <legend><img src="' . $this->local_path . $this->name . '/logo.gif" alt="" /> ' . $this->l('SendinBlue') . '</legend>
        <div style="float: right; width: 340px; height: 205px; border: dashed 1px #666; padding: 8px; margin-left: 12px; margin-top:-15px;">
        <h2 style="color:#268CCD;">' . $this->l('Contact SendinBlue Team') . '</h2>
        <div style="clear: both;"></div>
        <p>' . $this->l(' Contact us :
        ') . '<br /><br />' . $this->l('Email : ') . '<a href="mailto:' . $this->l('contact@sendinblue.com') . '" style="color:#268CCD;">' . $this->l('contact@sendinblue.com') . '</a><br />' . $this->l('Phone : 0899 25 30 61') . '</p>
        <p style="padding-top:20px;"><b>' . $this->l('For further informations, please visit our website:') . '</b><br /><a href="' . $this->l('https://www.sendinblue.com?utm_source=prestashop_plugin&utm_medium=plugin&utm_campaign=module_link') . '" target="_blank"
        style="color:#268CCD;">' . $this->l('https://www.sendinblue.com') . '</a></p>
        </div>
        <p>' . $this->l('With the SendinBlue plugin, you can find everything you need to easily and efficiently send your email & SMS campaigns to your prospects and customers. ') . '</p>
        <ul class="listt">
        <li>' . $this->l(' Synchronize your subscribers with SendinBlue (subscribed and unsubscribed contacts)') . '</li>
        <li>' . $this->l(' Easily create good looking emailings') . '</li>
        <li>' . $this->l(' Schedule your campaigns') . '</li>
        <li>' . $this->l(' Track your results and optimize') . '</li>
        <li>' . $this->l(' Monitor your transactional emails (purchase confirmation, password reset, etc) with a better deliverability and real-time analytics') . '</li>
        </ul>
        <b>' . $this->l('Why should you use SendinBlue ?') . '</b>
        <ul class="listt">
        <li>' . $this->l(' Optimized deliverability') . '</li>
        <li>' . $this->l(' Unbeatable pricing – best value in the industry') . '</li>
        <li>' . $this->l(' Technical support, by phone or by email') . '</li>
        </ul><div style="clear:both;">&nbsp;</div>
        </fieldset>';
    }
    
    /**
     * PrestaShop's default method that gets called when page loads.
     */
    private function displayForm()
    {
        // checkFolderStatus after removing from SendinBlue
        $this->createFolderCaseTwo();
        
        if (Configuration::get('Sendin_Api_Key_Status', '', $this->id_shop_group, $this->id_shop)) {
            $str = '';
        } else {
            $str = 'style="display:none;"';
        }
        
        $this->_html.= '<p style="margin:1.5em 0;">' . $this->displaySendin() . '</p>';
        $this->_html.= '<style>.margin-form{padding: 0 0 2em 210px;}</style><fieldset style="margin-bottom:10px;">
        <legend><img src="' . $this->local_path . $this->name . '/logo.gif" alt="" />' . $this->l('Prerequisites') . '</legend>';
        $this->_html.= '<label">-
        ' . $this->l('You should have a SendinBlue account. You can create a free account here : ') . '<a href="' . $this->l('https://www.sendinblue.com?utm_source=prestashop_plugin&utm_medium=plugin&utm_campaign=module_link') . '" class="link_action" style="color:#268CCD;"  target="_blank">&nbsp;' . $this->l('https://www.sendinblue.com') . '</a></label><br />';
        
        if (!extension_loaded('curl') || !ini_get('allow_url_fopen')) {
            $this->_html.= '<label">-
                    ' . $this->l('You must enable CURL extension and allow_url_fopen option on your server if you want to use this module.') . '</label>';
        }
        $this->_html.= '</fieldset>
        <form method="post" action="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '">
        <input type ="hidden" name="customtoken" id="customtoken" value="' . Tools::encrypt(Configuration::get('PS_SHOP_NAME')) . '">
        <input type ="hidden" name="langvalue" id="langvalue" value="' . $this->context->language->id . '">
        <input type ="hidden" name="id_shop_group" id="id_shop_group" value="' . $this->id_shop_group . '">
        <input type ="hidden" name="id_shop" id="id_shop" value="' . $this->id_shop . '">
        <input type ="hidden" name="iso_code" id="iso_code" value="' . $this->context->currency->iso_code . '">
        <input type ="hidden" name="page_no" id="page_no" value="1"><fieldset style="position:relative;" class="form-display">';
        $this->_html.= '<legend>
        <img src="' . $this->local_path . $this->name . '/logo.gif" />' . $this->l('Settings') . '</legend>
        <label>' . $this->l('Activate the SendinBlue module') . '</label><div class="margin-form" style="padding-top:5px">
        <input type="radio" id="y" class="keyyes radio_spaceing"
        name="status" value="1"
        ' . (Configuration::get('Sendin_Api_Key_Status', '', $this->id_shop_group, $this->id_shop) ? 'checked="checked" ' : '') . '/>' . $this->l('Yes') . '
        <input type="radio"  id="n" class="keyyes radio_spaceing2"
        name="status" value="0"
        ' . (!Configuration::get('Sendin_Api_Key_Status', '', $this->id_shop_group, $this->id_shop) ? 'checked="checked" ' : '') . '/>' . $this->l('No') . '
        </div><div class="clear"></div>';
        $this->_html.= '<div id="apikeybox"  ' . $str . ' ><label class="key">' . $this->l('API key') . '</label>
        <div class="margin-form key">
        <input type="text" name="apikey" id="apikeys" value="' . Tools::safeOutput(Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop)) . '" />&nbsp;
        <span class="toolTip"
        title="' . $this->l('Please enter your API key from your SendinBlue account and if you don\'t have it yet, please go to www.sendinblue.com and subscribe. You can then get the API key from https://my.sendinblue.com/advanced/apikey') . '">
        &nbsp;</span>
        </div></div>';
        $this->_html.= '<div class="margin-form clear pspace">
        <input type="submit" name="submitUpdate" value="' . $this->l('Update') . '" class="button" />&nbsp;
        </div><div class="clear"></div></fieldset></form>';
        
        if (Configuration::get('Sendin_Api_Key_Status', '', $this->id_shop_group, $this->id_shop) == 1) {
            $this->_html.= $this->syncronizeBlockCode();
            $this->_html.= $this->mailSendBySmtp();
            $this->_html.= $this->codeDeTracking();
            $this->_html.= $this->mailSendBySms();
            $this->_html.= $this->displayNewsletterEmail();
        }
        
        return $this->_html;
    }
    
    /*
     * Get the count of total unsubcribed registered users.
    */
    public function totalUnsubscribedUser()
    {
        $condition = $this->conditionalValue();
        
        return Db::getInstance()->getValue('
        SELECT count(*) AS Total
        FROM `' . _DB_PREFIX_ . 'customer`
        WHERE  `newsletter` = 0 ' . $condition);
    }
    
    /*
     * Get the count of total subcribed registered users.
    */
    public function totalsubscribedUser()
    {
        $condition = $this->conditionalValue();
        return Db::getInstance()->getValue('
        SELECT count(*) AS Total
        FROM `' . _DB_PREFIX_ . 'customer`
        WHERE  `newsletter` = 1 ' . $condition);
    }
    
    /*
     * Checks if an email address already exists in the sendin_newsletter table
     * and returns a value accordingly.
    */
    private function isNewsletterRegistered($customer_email, $id_shop_group)
    {
        if (version_compare(_PS_VERSION_, '1.5', '>=')) {
            $condition = 'AND `id_shop_group` = ' . $id_shop_group . '';
        } else {
            $condition = '';
        }
        
        if (Db::getInstance()->getRow('SELECT `email` FROM ' . _DB_PREFIX_ . 'sendin_newsletter
        WHERE `email` = \'' . pSQL($customer_email) . '\'' . $condition . '')) {
            return 1;
        }
        
        if (!$registered = Db::getInstance()->getRow('SELECT `newsletter` FROM ' . _DB_PREFIX_ . 'customer
        WHERE `email` = \'' . pSQL($customer_email) . '\'' . $condition . '')) {
            return -1;
        }
        
        if ($registered['newsletter'] == '1') {
            return 2;
        }
        if ($registered['newsletter'] == '0') {
            return 3;
        }
        
        return 0;
    }
    
    /*
     * Checks if an email address is already subscribed in the sendin_newsletter table
     * and returns true, otherwise returns false.
    */
    private function isNewsletterRegisteredSub($customer_email, $id_shop_group)
    {
        $condition = $this->checkVersionCondition($id_shop_group);
        if (Db::getInstance()->getRow('SELECT `email` FROM ' . _DB_PREFIX_ . 'sendin_newsletter
        WHERE `email` = \'' . pSQL($customer_email) . '\' and active=1 ' . $condition . '')) {
            return true;
        }
        
        if (Db::getInstance()->getRow('SELECT `newsletter` FROM ' . _DB_PREFIX_ . 'customer
        WHERE `email` = \'' . pSQL($customer_email) . '\' and newsletter=1 ' . $condition . '')) {
            return true;
        }
        
        return false;
    }
    
    /*
     * Checks if an email address is already unsubscribed in the sendin_newsletter table
     * and returns true, otherwise returns false.
    */
    private function isNewsletterRegisteredUnsub($customer_email)
    {
        if (Db::getInstance()->getRow('SELECT `email` FROM ' . _DB_PREFIX_ . 'sendin_newsletter
        WHERE `email` = \'' . pSQL($customer_email) . '\' and active=0')) {
            return true;
        }
        
        if (Db::getInstance()->getRow('SELECT `newsletter` FROM ' . _DB_PREFIX_ . 'customer
        WHERE `email` = \'' . pSQL($customer_email) . '\' and newsletter=0')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * This method is being called when a subsriber subscribes from the front end of PrestaShop.
     */
    private function newsletterRegistration()
    {
        $post_action = Tools::getValue('action');
        $s_new_timestamp = date('Y-m-d H:m:s');
        
        // get post email value
        $this->email = Tools::getValue('email');
        $id_shop = !empty($this->id_shop) ? $this->id_shop : 1;
        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 1;
        if (version_compare(_PS_VERSION_, '1.5', '>=')) {
            $condition = 'AND `id_shop_group` = ' . pSQL($id_shop_group) . '';
        } else {
            $condition = '';
        }
        
        if (empty($this->email) || !Validate::isEmail($this->email)) {
            return;
        } elseif ($post_action == '1') {
            $register_status = $this->isNewsletterRegistered($this->email, $id_shop_group);
            $register_status_unsub = $this->isNewsletterRegisteredUnsub($this->email, $id_shop_group);
            
            if ($register_status == - 1) {
                return;
            } elseif ($register_status_unsub == - 1) {
                return;
            }
            
            // update unsubscribe unregister
            if ($register_status == 1) {
                
                // email status send to remote server
                $this->unsubscribeByruntime($this->email);
                if (!Db::getInstance()->Execute('UPDATE ' . _DB_PREFIX_ . 'sendin_newsletter
                SET `active` = 0,
                newsletter_date_add = \'' . $s_new_timestamp . '\'
                WHERE `email` = \'' . pSQL($this->email) . '\'' . $condition . '')) {
                    return;
                }
                return $this->valid = $this->l('Unsubscription successful');
            } elseif ($register_status == 2) {
                
                // email status send to remote server
                $this->unsubscribeByruntime($this->email);
                if (!Db::getInstance()->Execute('UPDATE ' . _DB_PREFIX_ . 'customer
                    SET `newsletter` = 0,
                    newsletter_date_add = \'' . $s_new_timestamp . '\',
                    `ip_registration_newsletter` = \'' . pSQL(Tools::getRemoteAddr()) . '\'
                    WHERE `email` = \'' . pSQL($this->email) . '\'' . $condition . '')) {
                    return;
                }
                return $this->valid = $this->l('Unsubscription successful');
            }
        } elseif ($post_action == '0') {
            $register_status = $this->isNewsletterRegistered($this->email, $id_shop_group);
            $register_status_sub = $this->isNewsletterRegisteredSub($this->email, $id_shop_group);
            
            if ($register_status_sub) {
                return;
            }
            $switchQuery = false;
            switch ($register_status) {
                // email status send to remote server
                case -1:
                    $switchQuery = Db::getInstance()->Execute('INSERT INTO ' . _DB_PREFIX_ . 'sendin_newsletter
                        (id_shop, id_shop_group, email, newsletter_date_add, ip_registration_newsletter, http_referer)
                        VALUES (\'' . pSQL($id_shop) . '\',\'' . pSQL($id_shop_group) . '\',\'' . pSQL($this->email) . '\', \'' . $s_new_timestamp . '\', \'' . pSQL(Tools::getRemoteAddr()) . '\',
                        (SELECT c.http_referer FROM ' . _DB_PREFIX_ . 'connections c WHERE c.id_guest = ' . (int)$this->context->cookie->id_guest . ' ORDER BY c.date_add DESC LIMIT 1))');
                    break;
                // email status send to remote server
                case 0:
                    $switchQuery = Db::getInstance()->Execute('UPDATE ' . _DB_PREFIX_ . 'sendin_newsletter
                        SET `active` = 1,
                        newsletter_date_add = \'' . $s_new_timestamp . '\'
                        WHERE `email` = \'' . pSQL($this->email) . '\'' . $condition . '');
                    break;
                // email status send to remote server
                case 1:
                    $switchQuery = Db::getInstance()->Execute('UPDATE ' . _DB_PREFIX_ . 'sendin_newsletter
                        SET `active` = 1,
                        newsletter_date_add = \'' . $s_new_timestamp . '\'
                        WHERE `email` = \'' . pSQL($this->email) . '\'' . $condition . '');
                    break;
                // email status send to remote server
                case 3:
                    $switchQuery = Db::getInstance()->Execute('UPDATE ' . _DB_PREFIX_ . 'customer
                        SET `newsletter` = 1,
                        newsletter_date_add = \'' . $s_new_timestamp . '\',
                        `ip_registration_newsletter` = \'' . pSQL(Tools::getRemoteAddr()) . '\'
                        WHERE `email` = \'' . pSQL($this->email) . '\'' . $condition . '');
                    break;
            }
            if (!$switchQuery) {
                return;
            }
            $this->subscribeByruntime($this->email);
            $this->sendWsTemplateMail($this->email);
            
            return $this->valid = $this->l('Subscription successful');
        }
    }
    
    /**
     * Method is being called at the time of uninstalling the SendinBlue module.
     */
    public function uninstall()
    {
        $this->unregisterHook('leftColumn');
        $this->unregisterHook('rightColumn');
        $this->unregisterHook('top');
        $this->unregisterHook('footer');
        $this->unregisterHook('createAccount');
        $this->unregisterHook('createAccountForm');
        $this->unregisterHook('OrderConfirmation');
        Configuration::deleteByName('Sendin_Api_Sms_Order_Status');
        Configuration::deleteByName('Sendin_Api_Sms_shipment_Status');
        Configuration::deleteByName('Sendin_Api_Sms_Campaign_Status');
        Configuration::deleteByName('Sendin_Sender_Shipment_Message');
        Configuration::deleteByName('Sendin_Sender_Shipment');
        Configuration::deleteByName('Sendin_Sender_Order');
        Configuration::deleteByName('Sendin_Sender_Order_Message');
        Configuration::deleteByName('Sendin_Notify_Value');
        Configuration::deleteByName('Sendin_Notify_Email');
        Configuration::deleteByName('Sendin_Api_Sms_Credit');
        Configuration::deleteByName('Sendin_Notify_Cron_Executed');
        Configuration::deleteByName('Sendin_Template_Id');
        Configuration::deleteByName('Sendin_Rightblock');
        Configuration::deleteByName('Sendin_Leftblock');
        Configuration::deleteByName('Sendin_Top');
        Configuration::deleteByName('Sendin_Footer');
        
        if (Configuration::get('Sendin_Api_Smtp_Status')) {
            Configuration::updateValue('Sendin_Api_Smtp_Status', 0);
            Configuration::updateValue('PS_MAIL_METHOD', 1);
            Configuration::updateValue('PS_MAIL_SERVER', '');
            Configuration::updateValue('PS_MAIL_USER', '');
            Configuration::updateValue('PS_MAIL_PASSWD', '');
            Configuration::updateValue('PS_MAIL_SMTP_ENCRYPTION', '');
            Configuration::updateValue('PS_MAIL_SMTP_PORT', 25);
        }
        
        // Uninstall module
        Configuration::deleteByName('Sendin_First_Request');
        Configuration::deleteByName('Sendin_Subscribe_Setting');
        Configuration::deleteByName('Sendin_dropdown');
        Configuration::deleteByName('Sendin_Tracking_Status');
        Configuration::deleteByName('Sendin_order_tracking_Status');
        Configuration::deleteByName('Sendin_Smtp_Result');
        Configuration::deleteByName('Sendin_Api_Key');
        Configuration::deleteByName('Sendin_Api_Smtp_Status');
        Configuration::deleteByName('Sendin_import_user_status');
        Configuration::deleteByName('Sendin_Selected_List_Data');
        Configuration::deleteByName('SENDINBLUE_CONFIGURATION_OK');
        
        if (Configuration::get('Sendin_Newsletter_table', '', $this->id_shop_group, $this->id_shop)) {
            $this->getRestoreOldNewsletteremails();
            
            Db::getInstance()->Execute('DROP TABLE  ' . _DB_PREFIX_ . 'sendin_newsletter');
            
            Configuration::deleteByName('Sendin_Newsletter_table');
            Configuration::deleteByName('Sendin_Api_Key_Status');
        }
        
        return parent::uninstall();
    }
    public function hookupdateOrderStatus()
    {
        $id_order_state = Tools::getValue('id_order_state');
        $id_order = Tools::getValue('id_order');
        if ($id_order_state == 4 && Configuration::get('Sendin_Api_Sms_shipment_Status', '', $this->id_shop_group, $this->id_shop) == 1 && Configuration::get('Sendin_Sender_Shipment_Message', '', $this->id_shop_group, $this->id_shop) != '' && is_numeric($id_order) == true) {
            $order = new Order($id_order);
            $address = new Address((int)$order->id_address_delivery);
            $customer_civility_result = Db::getInstance()->ExecuteS('SELECT id_gender,firstname,lastname FROM ' . _DB_PREFIX_ . 'customer WHERE `id_customer` = ' . (int)$order->id_customer);
            $firstname = (isset($address->firstname)) ? $address->firstname : '';
            $lastname = (isset($address->lastname)) ? $address->lastname : '';
            
            if (Tools::strtolower($firstname) === Tools::strtolower($customer_civility_result[0]['firstname']) && Tools::strtolower($lastname) === Tools::strtolower($customer_civility_result[0]['lastname'])) {
                $civility_value = (isset($customer_civility_result['0']['id_gender'])) ? $customer_civility_result['0']['id_gender'] : '';
            } else {
                $civility_value = '';
            }
            
            if ($civility_value == 1) {
                $civility = 'Mr.';
            } elseif ($civility_value == 2) {
                $civility = 'Ms.';
            } elseif ($civility_value == 3) {
                $civility = 'Miss.';
            } else {
                $civility = '';
            }
            
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
                SELECT `call_prefix`
                FROM `' . _DB_PREFIX_ . 'country`
                WHERE `id_country` = ' . (int)$address->id_country);
            
            if (isset($address->phone_mobile) && !empty($address->phone_mobile)) {
                $order_date = (isset($order->date_upd)) ? $order->date_upd : 0;
                if ($this->context->language->id == 1) {
                    $ord_date = date('m/d/Y', strtotime($order_date));
                } else {
                    $ord_date = date('d/m/Y', strtotime($order_date));
                }
                
                $msgbody = Configuration::get('Sendin_Sender_Shipment_Message', '', $this->id_shop_group, $this->id_shop);
                $total_pay = (isset($order->total_paid)) ? $order->total_paid : 0;
                $total_pay = $total_pay . '' . $this->context->currency->iso_code;
                if (version_compare(_PS_VERSION_, '1.5.0.0', '<')) {
                    $ref_num = (isset($order->id)) ? $order->id : '';
                } else {
                    $ref_num = (isset($order->reference)) ? $order->reference : '';
                }
                
                $civility_data = str_replace('{civility}', $civility, $msgbody);
                $fname = str_replace('{first_name}', $firstname, $civility_data);
                $lname = str_replace('{last_name}', $lastname . "\r\n", $fname);
                $product_price = str_replace('{order_price}', $total_pay, $lname);
                $order_date = str_replace('{order_date}', $ord_date . "\r\n", $product_price);
                $msgbody = str_replace('{order_reference}', $ref_num, $order_date);
                $arr = array();
                $arr['to'] = $this->checkMobileNumber($address->phone_mobile, $result['call_prefix']);
                $arr['from'] = Configuration::get('Sendin_Sender_Shipment', '', $this->id_shop_group, $this->id_shop);
                $arr['text'] = $msgbody;
                
                $this->sendSmsApi($arr);
            }
        }
    }
    
    /**
     * Displays the newsletter on the front page in Left column of PrestaShop
     */
    public function hookLeftColumn($params)
    {
        if (!$this->syncSetting()) {
            return false;
        }
        
        if (Tools::isSubmit('submitNewsletter')) {
            $this->newsletterRegistration();
            $this->email = Tools::safeOutput(Tools::getValue('email'));
            
            if ($this->valid) {
                if (Configuration::get('NW_CONFIRMATION_EMAIL', '', $this->id_shop_group, $this->id_shop)) {
                    Mail::Send((int)$params['cookie']->id_lang, 'newsletter_conf', Mail::l('Newsletter confirmation', (int)$params['cookie']->id_lang), array(), $this->email, null, null, null, null, null, dirname(__FILE__) . '/mails/');
                }
            }
        }
    }
    
    /**
     * Displays the newsletter on the front page in Right column of PrestaShop
     */
    public function hookRightColumn($params)
    {
        if (!$this->syncSetting()) {
            return false;
        }
        
        if (Tools::isSubmit('submitNewsletter')) {
            $this->newsletterRegistration();
            $this->email = Tools::safeOutput(Tools::getValue('email'));
            if ($this->valid) {
                if (Configuration::get('NW_CONFIRMATION_EMAIL', '', $this->id_shop_group, $this->id_shop)) {
                    Mail::Send((int)$params['cookie']->id_lang, 'newsletter_conf', Mail::l('Newsletter confirmation', (int)$params['cookie']->id_lang), array(), $this->email, null, null, null, null, null, dirname(__FILE__) . '/mails/');
                }
            }
        }
    }
    
    /**
     * Displays the newsletter on the front page in Footer of PrestaShop
     */
    public function hookFooter($params)
    {
        if (!$this->syncSetting()) {
            return false;
        }
        
        if (Tools::isSubmit('submitNewsletter')) {
            $this->newsletterRegistration();
            $this->email = Tools::safeOutput(Tools::getValue('email'));
            
            if ($this->valid) {
                if (Configuration::get('NW_CONFIRMATION_EMAIL', '', $this->id_shop_group, $this->id_shop)) {
                    Mail::Send((int)$params['cookie']->id_lang, 'newsletter_conf', Mail::l('Newsletter confirmation', (int)$params['cookie']->id_lang), array(), $this->email, null, null, null, null, null, dirname(__FILE__) . '/mails/');
                }
            }
        }
        
        $this->context->smarty->assign('this_path', $this->local_path);
    }
    
    /**
     * Displays the newsletter on the front page in Top of PrestaShop
     */
    public function hookTop($params)
    {
        if (!$this->syncSetting()) {
            return false;
        }
        
        if (Tools::isSubmit('submitNewsletter')) {
            $this->newsletterRegistration();
            $this->email = Tools::safeOutput(Tools::getValue('email'));
            
            if ($this->valid) {
                if (Configuration::get('NW_CONFIRMATION_EMAIL', '', $this->id_shop_group, $this->id_shop)) {
                    Mail::Send((int)$params['cookie']->id_lang, 'newsletter_conf', Mail::l('Newsletter confirmation', (int)$params['cookie']->id_lang), array(), $this->email, null, null, null, null, null, dirname(__FILE__) . '/mails/');
                }
            }
        }
        
        $this->context->smarty->assign('this_path', $this->local_path);
    }
    
    /**
     * Displays the newsletter option on registration page of PrestaShop
     */
    public function hookcreateAccountForm($params)
    {
        if (!$this->syncSetting()) {
            return false;
        }
        
        $this->context->smarty->assign('params', $params);
    }
    
    /*
     * Displays the CSS for the SendinBlue module.
    */
    public function addCss()
    {
        $min = $_SERVER['HTTP_HOST'] == 'localhost' ? '' : '.min';
        $so = $this->l('Select option');
        $selected = $this->l('selected');
        $html = '<script>  var selectoption = "' . $so . '"; </script>';
        $html.= '<script>  var base_url = "' . str_replace('modules/', '', $this->local_path) . '"; </script>';
        $html.= '<script>  var selected = "' . $selected . '"; </script>';
        $sendin_js_path = $this->local_path . $this->name . '/views/js/' . $this->name . $min . '.js?_=' . time();
        $js_ddl_list = $this->local_path . $this->name . '/views/js/jquery.multiselect.min.js';
        $liveclickquery = $this->local_path . $this->name . '/views/js/jquery.livequery.min.js';
        $s_css = $this->local_path . $this->name . '/views/css/' . $this->name . '.css?_=' . time();
        
        $base = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://');
        $html.= '<link rel="stylesheet" type="text/css" href="' . $base . 'ajax.googleapis.com/ajax/libs/jqueryui/1/themes/ui-lightness/jquery-ui.css" />
            <link "text/css" href="' . $s_css . '" rel="stylesheet" />
            <script type="text/javascript" src="' . $base . 'ajax.googleapis.com/ajax/libs/jqueryui/1/jquery-ui.min.js"></script>
            <script type="text/javascript" src="' . $js_ddl_list . '"></script>
            <script type="text/javascript" src="' . $liveclickquery . '"></script>
            <script type="text/javascript" src="' . $sendin_js_path . '"></script>';
        
        return $html;
    }
    
    /**
     * When a user places an order, the tracking code integrates in the order confirmation page.
     */
    public function hookOrderConfirmation($params)
    {
        if (!$this->checkModuleStatus()) {
            return false;
        }
        
        $customerid = (isset($params['objOrder']->id_customer)) ? $params['objOrder']->id_customer : '';
        $customer_result = Db::getInstance()->ExecuteS('SELECT id_gender, firstname, lastname, newsletter  FROM ' . _DB_PREFIX_ . 'customer WHERE `id_customer` = ' . (int)$customerid);
        $id_delivery = (isset($params['objOrder']->id_address_delivery)) ? $params['objOrder']->id_address_delivery : 0;
        $address_delivery = Db::getInstance()->ExecuteS('SELECT * FROM ' . _DB_PREFIX_ . 'address WHERE `id_address` = ' . (int)$id_delivery);
        if (version_compare(_PS_VERSION_, '1.5.0.0', '<')) {
            $ref_num = (isset($params['objOrder']->id)) ? $params['objOrder']->id : 0;
        } else {
            $ref_num = (isset($params['objOrder']->reference)) ? $params['objOrder']->reference : 0;
        }
        $total_to_pay = (isset($params['total_to_pay'])) ? $params['total_to_pay'] : 0;
        
        if (Configuration::get('Sendin_Api_Sms_Order_Status', '', $this->id_shop_group, $this->id_shop) && Configuration::get('Sendin_Sender_Order', '', $this->id_shop_group, $this->id_shop) && Configuration::get('Sendin_Sender_Order_Message', '', $this->id_shop_group, $this->id_shop)) {
            $data = array();
            
            if (isset($address_delivery[0]['phone_mobile']) && !empty($address_delivery[0]['phone_mobile'])) {
                $result_code = Db::getInstance()->getRow('SELECT `call_prefix` FROM ' . _DB_PREFIX_ . 'country
WHERE               `id_country` = \'' . pSQL($address_delivery[0]['id_country']) . '\'');
                $number = $this->checkMobileNumber($address_delivery[0]['phone_mobile'], $result_code['call_prefix']);
                
                $order_date = (isset($params['objOrder']->date_upd)) ? $params['objOrder']->date_upd : 0;
                if ($this->context->language->id == 1) {
                    $ord_date = date('m/d/Y', strtotime($order_date));
                } else {
                    $ord_date = date('d/m/Y', strtotime($order_date));
                }
                $firstname = (isset($address_delivery[0]['firstname'])) ? $address_delivery[0]['firstname'] : '';
                $lastname = (isset($address_delivery[0]['lastname'])) ? $address_delivery[0]['lastname'] : '';
                
                if ((Tools::strtolower($firstname) === Tools::strtolower($customer_result[0]['firstname'])) && (Tools::strtolower($lastname) === Tools::strtolower($customer_result[0]['lastname']))) {
                    $civility_value = (isset($this->context->customer->id_gender)) ? $this->context->customer->id_gender : '';
                } else {
                    $civility_value = '';
                }
                
                if ($civility_value == 1) {
                    $civility = 'Mr.';
                } elseif ($civility_value == 2) {
                    $civility = 'Ms.';
                } elseif ($civility_value == 3) {
                    $civility = 'Miss.';
                } else {
                    $civility = '';
                }
                
                $total_pay = $total_to_pay . '' . $this->context->currency->iso_code;
                $msgbody = Configuration::get('Sendin_Sender_Order_Message', '', $this->id_shop_group, $this->id_shop);
                $civility_data = str_replace('{civility}', $civility, $msgbody);
                $fname = str_replace('{first_name}', $firstname, $civility_data);
                $lname = str_replace('{last_name}', $lastname . "\r\n", $fname);
                $product_price = str_replace('{order_price}', $total_pay, $lname);
                $order_date = str_replace('{order_date}', $ord_date . "\r\n", $product_price);
                $msgbody = str_replace('{order_reference}', $ref_num, $order_date);
                $data['from'] = Configuration::get('Sendin_Sender_Order', '', $this->id_shop_group, $this->id_shop);
                $data['text'] = $msgbody;
                $data['to'] = $number;
                $this->sendSmsApi($data);
            }
        }
        
        if (Configuration::get('Sendin_Api_Key_Status', '', $this->id_shop_group, $this->id_shop) == 1 && Configuration::get('Sendin_Tracking_Status', '', $this->id_shop_group, $this->id_shop) == 1 && $customer_result[0]['newsletter'] == 1) {
            $this->tracking = $this->trackingResult();
            $date_value = $this->getApiConfigValue();
            
            if ($date_value->date_format == 'dd-mm-yyyy') {
                $date = date('d-m-Y');
            } else {
                $date = date('m-d-Y');
            }
            
            $list = str_replace('|', ',', Configuration::get('Sendin_Selected_List_Data', '', $this->id_shop_group, $this->id_shop));
            if (preg_match('/^[0-9,]+$/', $list)) {
                $list = $list;
            } else {
                $list = '';
            }
            $code = '<script type="text/javascript">
            /**Code for NB tracking*/
            function loadScript(url,callback){var script=document.createElement("script");script.type="text/javascript";if(script.readyState){script.onreadystatechange=function(){
            if(script.readyState=="loaded"||script.readyState=="complete"){script.onreadystatechange=null;callback(url)}}}else{
            script.onload=function(){callback(url)}}script.src=url;if(document.body){document.body.appendChild(script)}else{
            document.head.appendChild(script)}}
            var nbJsURL = (("https:" == document.location.protocol) ? "https://my-tracking-orders.googlecode.com/files" : "http://my-tracking-orders.googlecode.com/files");
            var nbBaseURL = "http://tracking.mailin.fr/";
            loadScript(nbJsURL+"/nbv2.js",
            function(){
            /*You can put your custom variables here as shown in example.*/
            try {
            var nbTracker = nb.getTracker(nbBaseURL , "' . Tools::safeOutput($this->tracking->result->tracking_data->site_id) . '");
            var list = [' . $list . '];
            var attributes = ["EMAIL","PRENOM","NOM","ORDER_ID","ORDER_DATE","ORDER_PRICE"];
            var values = ["' . $this->context->customer->email . '",
            "' . $this->context->customer->firstname . '",
            "' . $this->context->customer->lastname . '",
            "' . $ref_num . '",
            "' . $date . '",
            "' . Tools::safeOutput($total_to_pay) . '"];
            nbTracker.setListData(list);
            nbTracker.setTrackingData(attributes,values);
            nbTracker.trackPageView();
            } catch( err ) {}
            });
            </script>';
            
            echo html_entity_decode($code, ENT_COMPAT, 'UTF-8');
        }
    }
    
    /**
     * Method is used to send test email to the user.
     */
    private function sendMail($email, $title)
    {
        $country_iso = Tools::strtolower($this->context->language->iso_code);
        if (is_dir(dirname(__FILE__) . '/mails/' . $country_iso) != true) {
            $result = Db::getInstance()->getRow('SELECT `id_lang` FROM ' . _DB_PREFIX_ . 'lang WHERE `iso_code` = \'en\'');
            $this->context->language->id = $result['id_lang'];
        }
        
        $toname = explode('@', $email);
        $toname = preg_replace('/[^a-zA-Z0-9]+/', ' ', $toname[0]);
        return Mail::Send((int)$this->context->language->id, 'sendinsmtp_conf', Mail::l($title, (int)$this->context->language->id), array('{title}' => $title), $email, $toname, $this->l('contact@sendinblue.com'), $this->l('SendinBlue'), null, null, dirname(__FILE__) . '/mails/');
    }
    
    public function sendNotifySms($email, $id_lang, $id_shop_group, $id_shop)
    {
        $country_iso = Db::getInstance()->getRow('SELECT `iso_code` FROM ' . _DB_PREFIX_ . 'lang WHERE  `id_lang` = \'' . pSQL($id_lang) . '\'');
        $iso_code = Tools::strtolower($country_iso['iso_code']);
        if (is_dir(dirname(__FILE__) . '/mails/' . $iso_code) != true) {
            $result = Db::getInstance()->getRow('SELECT `id_lang` FROM ' . _DB_PREFIX_ . 'lang WHERE `iso_code` = \'en\'');
            $id_lang = $result['id_lang'];
        }
        $title = '[SendinBlue] Notification : Credits SMS';
        $site_name = Configuration::get('PS_SHOP_NAME');
        $present_credit = $this->getSmsCredit($id_shop_group, $id_shop);
        $toname = explode('@', $email);
        $toname = preg_replace('/[^a-zA-Z0-9]+/', ' ', $toname[0]);
        
        return Mail::Send((int)$id_lang, 'sendinsms_notify', Mail::l($title, (int)$id_lang), array('{title}' => $title, '{present_credit}' => $present_credit, '{site_name}' => $site_name), $email, $toname, $this->l('contact@sendinblue.com'), $this->l('SendinBlue'), null, null, dirname(__FILE__) . '/mails/');
    }
    
    public function checkMobileNumber($number, $call_prefix)
    {
        $number = preg_replace('/\s+/', '', $number);
        $charone = Tools::substr($number, 0, 1);
        $chartwo = Tools::substr($number, 0, 2);
        
        if (preg_match('/^' . $call_prefix . '/', $number)) {
            return '00' . $number;
        } elseif ($charone == '0' && $chartwo != '00') {
            if (preg_match('/^0' . $call_prefix . '/', $number)) {
                return '00' . Tools::substr($number, 1);
            } else {
                return '00' . $call_prefix . Tools::substr($number, 1);
            }
        } elseif ($chartwo == '00') {
            if (preg_match('/^00' . $call_prefix . '/', $number)) {
                return $number;
            } else {
                return '00' . $call_prefix . Tools::substr($number, 2);
            }
        } elseif ($charone == '+') {
            if (preg_match('/^\+' . $call_prefix . '/', $number)) {
                return '00' . Tools::substr($number, 1);
            } else {
                return '00' . $call_prefix . Tools::substr($number, 1);
            }
        } elseif ($charone != '0') {
            return '00' . $call_prefix . $number;
        }
    }
    
    /**
     * Retrieve customers by email address
     *
     * @static
     * @param $email
     * @return array
     */
    public function getCustomersByEmail($email)
    {
        $sql = 'SELECT *
            FROM `' . _DB_PREFIX_ . 'customer`
            WHERE `email` = \'' . pSQL($email) . '\'';
        return Db::getInstance()->ExecuteS($sql);
    }
    
    public function getAllCustomers()
    {
        $id_shop = !empty($this->id_shop) ? $this->id_shop : 'NULL';
        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 'NULL';
        $condition = $this->conditionalValueSecond($id_shop_group, $id_shop);
        $sql = 'SELECT C.id_customer, C.firstname, C.lastname, C.email, C.id_gender, C.newsletter, C.newsletter_date_add FROM ' . _DB_PREFIX_ . 'customer as C ' . $condition;
        return Db::getInstance()->ExecuteS($sql);
    }
    
    /**
     * API config value from SendinBlue.
     */
    public function getApiConfigValue($id_shop_group = null, $id_shop = null)
    {
        if ($id_shop === null) {
            $id_shop = $this->id_shop;
        }
        if ($id_shop_group === null) {
            $id_shop_group = $this->id_shop_group;
        }
        
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $id_shop_group, $id_shop);
        $data['webaction'] = 'PLUGIN-CONFIG';
        $value_config = $this->curlRequest($data);
        $result = Tools::jsonDecode($value_config);
        return $result;
    }
    
    public function checkOlderVesion()
    {
        if (version_compare(_PS_VERSION_, '1.4.1.0', '<=')) {
            $module_status = Db::getInstance()->getRow('select COUNT(*) totalvalue FROM `' . _DB_PREFIX_ . 'module`  WHERE name ="mailin" || name ="mailinblue"');
            if ($module_status['totalvalue'] > 0) {
                return 1;
            } else {
                return 0;
            }
        } else {
            $module_newsletter = Module::getInstanceByName('mailin');
            $module_newsletter_second = Module::getInstanceByName('mailinblue');
            $data_first = !empty($module_newsletter->active) ? $module_newsletter->active : '0';
            $data_second = !empty($module_newsletter_second->active) ? $module_newsletter_second->active : '0';
            if ($data_first == 1 || $data_second == 1) {
                return 1;
            } else {
                return 0;
            }
        }
    }
    
    public function updateSmsSendinStatus($email, $id_shop_group, $id_shop)
    {
        if (!$this->syncSetting($id_shop_group, $id_shop)) {
            return false;
        }
        
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $id_shop_group, $id_shop);
        $data['webaction'] = 'USERUNSUBSCRIBEDSMS';
        $data['email'] = $email;
        $this->curlRequest($data);
    }
    
    /**
     * Fetches all the subscribers of PrestaShop and adds them to the SendinBlue database for SMS campaign.
     */
    private function smsCampaignList()
    {
        $id_shop = !empty($this->id_shop) ? $this->id_shop : 'NULL';
        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 'NULL';
        if ($id_shop === 'NULL' && $id_shop_group === 'NULL') {
            $condition = '';
        } elseif ($id_shop_group != 'NULL' && $id_shop === 'NULL') {
            $condition = 'AND C.id_shop_group =' . $id_shop_group;
        } else {
            $condition = 'AND C.id_shop_group =' . $id_shop_group . ' AND C.id_shop =' . $id_shop;
        }
        
        // select only newly added users and registered user
        $register_result = Db::getInstance()->ExecuteS('
            SELECT  C.id_customer, C.newsletter, C.newsletter_date_add, C.email, C.firstname, C.lastname, C.birthday, C.id_gender, PSA.id_address, PSA.date_upd, PSA.phone_mobile, ' . _DB_PREFIX_ . 'country.call_prefix
            FROM ' . _DB_PREFIX_ . 'customer as C LEFT JOIN ' . _DB_PREFIX_ . 'address PSA ON (C.id_customer = PSA.id_customer and (PSA.id_customer, PSA.date_upd) IN
            (SELECT id_customer, MAX(date_upd) upd  FROM ' . _DB_PREFIX_ . 'address GROUP BY ' . _DB_PREFIX_ . 'address.id_customer))
            LEFT JOIN ' . _DB_PREFIX_ . 'country ON ' . _DB_PREFIX_ . 'country.id_country =  PSA.id_country
            WHERE C.newsletter_date_add > 0 ' . $condition . '
            GROUP BY C.id_customer');
        
        $value_langauge = $this->getApiConfigValue();
        $register_email = array();
        
        // registered user store in array
        if ($register_result) {
            foreach ($register_result as $register_row) {
                if (!empty($register_row['phone_mobile']) && $register_row['phone_mobile'] != '') {
                    $mobile = $this->checkMobileNumber($register_row['phone_mobile'], $register_row['call_prefix']);
                    $birthday = (isset($register_row['birthday'])) ? $register_row['birthday'] : '';
                    
                    if ($value_langauge->date_format == 'dd-mm-yyyy') {
                        $birthday = date('d-m-Y', strtotime($birthday));
                    } else {
                        $birthday = date('m-d-Y', strtotime($birthday));
                    }
                    
                    $civility_value = (isset($register_row['id_gender'])) ? $register_row['id_gender'] : '';
                    if ($civility_value == 1) {
                        $civility = 'Mr.';
                    } elseif ($civility_value == 2) {
                        $civility = 'Ms.';
                    } elseif ($civility_value == 3) {
                        $civility = 'Miss.';
                    } else {
                        $civility = '';
                    }
                    
                    if ($value_langauge->language == 'fr') {
                        $register_email[] = array('EMAIL' => $register_row['email'], 'CIV' => $civility, 'PRENOM' => $register_row['firstname'], 'NOM' => $register_row['lastname'], 'DDNAISSANCE' => $birthday, 'CLIENT' => 1, 'SMS' => $mobile);
                    } else {
                        $register_email[] = array('EMAIL' => $register_row['email'], 'CIV' => $civility, 'NAME' => $register_row['firstname'], 'SURNAME' => $register_row['lastname'], 'BIRTHDAY' => $birthday, 'CLIENT' => 1, 'SMS' => $mobile);
                    }
                }
            }
        }
        return Tools::jsonEncode($register_email);
    }
    
    /**
     * Send template email by sendinblue for newsletter subscriber user  .
     */
    public function sendWsTemplateMail($to)
    {
        $id_shop = !empty($this->id_shop) ? $this->id_shop : 'NULL';
        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 'NULL';
        
        $mail_url = 'http://mysmtp.mailin.fr/ws/template/';
        
        //Curl url
        
        $key = Configuration::get('Sendin_Api_Key', '', $id_shop_group, $id_shop);
        if (empty($key)) {
            $id_shop = 'NULL';
            $id_shop_group = 'NULL';
            $key = Configuration::get('Sendin_Api_Key', '', $id_shop_group, $id_shop);
        }
        $smtp_data = Tools::jsondecode(Configuration::get('Sendin_Smtp_Result', '', $id_shop_group, $id_shop));
        $user = !empty($smtp_data->result->relay_data->data->username) ? $smtp_data->result->relay_data->data->username : '';
        
        $temp_id_value = Configuration::get('Sendin_Template_Id', '', $id_shop_group, $id_shop);
        $templateid = !empty($temp_id_value) ? $temp_id_value : '';
        
        // should be the campaign id of template created on mailin. Please remember this template should be active than only it will be sent, otherwise it will return error.
        $post_data = array();
        $post_data['to'] = $to;
        $post_data['key'] = $key;
        $post_data['user'] = $user;
        $post_data['templateid'] = $templateid;
        $post_data = http_build_query($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $mail_url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $return_data = curl_exec($ch);
        
        curl_close($ch);
        
        $res = Tools::jsondecode($return_data, true);
        return $res;
    }
    
    /**
     * Get all temlpate list id by sendinblue.
     */
    public function templateDisplay()
    {
        $data = array();
        $data['key'] = Configuration::get('Sendin_Api_Key', '', $this->id_shop_group, $this->id_shop);
        $data['webaction'] = 'CAMPAIGNDETAIL';
        $data['show'] = 'ALL';
        $data['messageType'] = 'template';
        $temp_result = $this->curlRequest($data);
        return Tools::jsonDecode($temp_result);
    }
    
    /**
     * Return customer addresses
     * @return array Addresses
     */
    public function getCustomerAddresses($id)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
            SELECT a.*, cl.`name` AS country, s.name AS state, s.iso_code AS state_iso
            FROM `' . _DB_PREFIX_ . 'address` a
            LEFT JOIN `' . _DB_PREFIX_ . 'country` c ON (a.`id_country` = c.`id_country`)
            LEFT JOIN `' . _DB_PREFIX_ . 'country_lang` cl ON (c.`id_country` = cl.`id_country`)
            LEFT JOIN `' . _DB_PREFIX_ . 'state` s ON (s.`id_state` = a.`id_state`)
            WHERE `id_customer` = ' . (int)$id . ' AND a.`deleted` = 0');
    }
    
    /**
     * Update temlpate id in prestashop configuration.
     */
    public function saveTemplateValue()
    {
        $value_template_id = Tools::getValue('template');
        Configuration::updateValue('Sendin_Template_Id', $value_template_id, '', $this->id_shop_group, $this->id_shop);
    }
    
    /**
     * Check and Update Sendinblue status.
     */
    public function enableSendinblueBlock()
    {
        if (version_compare(_PS_VERSION_, '1.5.3.0', '<')) {
            $sendin_status = Db::getInstance()->getRow('SELECT `active` FROM `' . _DB_PREFIX_ . 'module`
                WHERE `name` = \'' . pSQL('sendinblue') . '\'');
        } else {
            $sendin_status = Module::isEnabled('sendinblue');
        }
        if (empty($sendin_status) || $sendin_status == 0) {
            if (version_compare(_PS_VERSION_, '1.5.3.0', '>=')) {
                Module::enableByName('sendinblue');
            }
        }
    }
    
    /**
     * Make a condition for query.
     */
    public function conditionalValue()
    {
        $id_shop_group = !empty($this->id_shop_group) ? $this->id_shop_group : 'NULL';
        $id_shop = !empty($this->id_shop) ? $this->id_shop : 'NULL';
        
        if ($id_shop === 'NULL' && $id_shop_group === 'NULL') {
            $condition = '';
        } elseif ($id_shop_group != 'NULL' && $id_shop === 'NULL') {
            $condition = 'AND id_shop_group =' . $id_shop_group;
        } else {
            $condition = 'AND id_shop_group =' . $id_shop_group . ' AND id_shop =' . $id_shop;
        }
        return $condition;
    }
    public function conditionalValueSecond($id_shop_group = null, $id_shop = null)
    {
        $id_shop_group = !empty($id_shop_group) ? $id_shop_group : 'NULL';
        $id_shop = !empty($id_shop) ? $id_shop : 'NULL';
        if ($id_shop === 'NULL' && $id_shop_group === 'NULL') {
            $condition = '';
        } elseif ($id_shop_group != 'NULL' && $id_shop === 'NULL') {
            $condition = 'WHERE C.id_shop_group =' . $id_shop_group;
        } else {
            $condition = 'WHERE C.id_shop_group =' . $id_shop_group . ' AND C.id_shop =' . $id_shop;
        }
        
        return $condition;
    }
    
    /**
     * Make a condition after check ps version.
     */
    public function checkVersionCondition($id_shop_group)
    {
        if (version_compare(_PS_VERSION_, '1.5', '>=')) {
            $condition = 'and `id_shop_group` = ' . (int)$id_shop_group . '';
        } else {
            $condition = '';
        }
        
        return $condition;
    }
}
