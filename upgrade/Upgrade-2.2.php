<?php
/**
 * 2007-2015 PrestaShop
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
 * @copyright 2007-2015 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_2($module)
{
    $upgrade_version = '2.2';
    $module->upgrade_detail[$upgrade_version] = array();
    
    Configuration::updateValue('Sendinblue_Version', $upgrade_version);
    
    //sql update
    if (Db::getInstance()->ExecuteS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'sendin_newsletter` LIKE \'id_shop\'') == false) {
        Db::getInstance()->Execute('ALTER TABLE `' . _DB_PREFIX_ . 'sendin_newsletter` ADD `id_shop` BOOLEAN NOT NULL DEFAULT 1 AFTER `id`');
    }
    
    if (Db::getInstance()->ExecuteS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'sendin_newsletter` LIKE \'id_shop_group\'') == false) {
        Db::getInstance()->Execute('ALTER TABLE `' . _DB_PREFIX_ . 'sendin_newsletter` ADD `id_shop_group` BOOLEAN NOT NULL DEFAULT 1 AFTER `id_shop`');
    }
    return $module;
}
