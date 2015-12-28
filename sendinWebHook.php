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

if (Tools::getValue('token') != Tools::encrypt(Configuration::get('PS_SHOP_NAME'))) {
    die('Error: Invalid Token');
}

$data = Tools::file_get_contents('php://input');
$res_value = Tools::jsonDecode($data, true);

if ($res_value['event'] == 'unsubscribe' || $res_value['event'] == 'hard_bounce' || $res_value['event'] == 'spam') {
    Db::getInstance()->Execute('UPDATE  `' . _DB_PREFIX_ . 'customer`
        SET newsletter="' . pSQL('0') . '",
        newsletter_date_add = "' . pSQL($res_value['date_event']) . '",
        date_upd = "' . pSQL(date('Y-m-d H:i:s')) . '"
        WHERE email = "' . pSQL($res_value['email']) . '" ');
    Db::getInstance()->Execute('UPDATE  `' . _DB_PREFIX_ . 'sendin_newsletter`
        SET active="' . pSQL('0') . '",
        newsletter_date_add = "' . pSQL($res_value['date_event']) . '"
        WHERE email = "' . pSQL($res_value['email']) . '" ');
}
