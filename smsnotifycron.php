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
include (dirname(__FILE__) . '/sendinblue.php');

if (Tools::getValue('token') != Tools::encrypt(Configuration::get('PS_SHOP_NAME'))) {
    die('Error: Invalid Token');
}

$id_shop_group = Tools::getValue('id_shop_group', 'NULL');
$id_shop = Tools::getValue('id_shop', 'NULL');
$sendin = new Sendinblue();
$credit_value = $sendin->getSmsCredit($id_shop_group, $id_shop);
if ($credit_value <= Configuration::get('Sendin_Notify_Value', '', $id_shop_group, $id_shop) && Configuration::get('Sendin_Api_Sms_Credit', '', $id_shop_group, $id_shop) == 1) {
    if (Configuration::get('Sendin_Notify_Cron_Executed', '', $id_shop_group, $id_shop) == 0) {
        $id_lang = Tools::getValue('lang');
        $notify_email = Configuration::get('Sendin_Notify_Email', '', $id_shop_group, $id_shop);
        $sendin->sendNotifySms($notify_email, $id_lang, $id_shop_group, $id_shop);
        Configuration::updateValue('Sendin_Notify_Cron_Executed', 1, '', $id_shop_group, $id_shop);
    }
} else {
    Configuration::updateValue('Sendin_Notify_Cron_Executed', 0, '', $id_shop_group, $id_shop_group);
}
echo 'Cron executed successfully';
