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

include (dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__).'/sendinblue.php');

if (Tools::getValue('token') != Tools::encrypt(Configuration::get('PS_SHOP_NAME'))) {
    die('Error: Invalid Token');
}

$sendin = new Sendinblue();

$data = Tools::getValue('resp_val');
$condition = $sendin->conditionalValue();
$customer_email = $sendin->encryptDecrypt('decrypt', $data);
$customer_data = Db::getInstance()->getRow('SELECT `email`, `newsletter` FROM ' . _DB_PREFIX_ . 'customer WHERE `email` = \'' . pSQL($customer_email) . '\''.$condition);

$newsletter_data = Db::getInstance()->getRow('SELECT `email`, `active` FROM ' . _DB_PREFIX_ . 'sendin_newsletter WHERE `email` = \'' . pSQL($customer_email) . '\''.$condition);
if ($customer_data['newsletter'] == 1 || $newsletter_data['active'] == 1) {
    $api_key = Configuration::get('Sendin_Api_Key', '', $sendin->id_shop_group, $sendin->id_shop);
    $Sendin_optin_list_id = Configuration::get('Sendin_optin_list_id', '', $sendin->id_shop_group, $sendin->id_shop);
    $Sendin_list_id = Configuration::get('Sendin_Selected_List_Data', '', $sendin->id_shop_group, $sendin->id_shop);

    $mailin = new Psmailin('https://api.sendinblue.com/v2.0', $api_key);

    $data = array( "email" => $customer_email,
            "attributes" => array(),
            "blacklisted" => 0,
            "listid" => array($Sendin_list_id),
            "listid_unlink" => array($Sendin_optin_list_id),
            "blacklisted_sms" => 0
        );
    $dd = $mailin->createUpdateUser($data);
    $confirm_email = Configuration::get('Sendin_final_confirm_email', '', $sendin->id_shop_group, $sendin->id_shop);
    if ($confirm_email === 'yes') {
        $final_id = Configuration::get('Sendin_Final_Template_Id', '', $sendin->id_shop_group, $sendin->id_shop);
        $sendin->sendWsTemplateMail($customer_email, $final_id);

    }
}

$Sendin_doubleoptin_redirect = Configuration::get('Sendin_doubleoptin_redirect', '', $sendin->id_shop_group, $sendin->id_shop);
if (!empty($Sendin_doubleoptin_redirect)) {
    Tools::redirectLink($Sendin_doubleoptin_redirect);
} else {
    $url = Tools::getHttpHost(true).__PS_BASE_URI__;
    Tools::redirectLink($url);
}
echo 'done';
exit;
