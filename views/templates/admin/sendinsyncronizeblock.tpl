{*
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
* @author PrestaShop SA <contact@prestashop.com>
* @copyright  2007-2015 PrestaShop SA
* @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
* International Registered Trademark & Property of PrestaShop SA
*}

<form method="post" action="{$form_url|escape:'htmlall':'UTF-8'|replace:'&amp;':'&'}">
<table cellspacing="0" cellpadding="0" width="100%" style="margin-top:15px;" class="table tableblock hidetableblock form-data">
        <thead>
        <tr>
        <th colspan="2">{l s='Activate SendinBlue to manage subscribers' mod='sendinblue'}</th>
        </tr>
        </thead>
        <tbody><tr>
        <td style="width:250px">
        <label> {l s='Activate SendinBlue to manage subscribers' mod='sendinblue'}
        </label>
        </td>
        <td class="{$cl_version|escape:'htmlall':'UTF-8'|stripslashes}"><input type="radio" {if isset($Sendin_Subscribe_Setting) && $Sendin_Subscribe_Setting == 1}checked="checked"{/if} value="1" name="managesubscribe" style="margin-right:10px;" id="managesubscribe" class="managesubscribe">{l s='Yes' mod='sendinblue'}
        <input type="radio" {if isset($Sendin_Subscribe_Setting) && $Sendin_Subscribe_Setting == 0}checked="checked"{/if} value="0" name="managesubscribe" style="margin-left:20px;margin-right:10px;" id="managesubscribe" class="managesubscribe">{l s='No' mod='sendinblue'}
        <span title="{l s='If you activate this feature, your new contacts will be automatically added to SendinBlue or unsubscribed from SendinBlue. To synchronize the other way (SendinBlue to PrestaShop), you should run the url (mentioned below) each day.' mod='sendinblue'}" class="toolTip">
        &nbsp;</span>
        </td>
        </tr><tr class="managesubscribeBlock">{$parselist|escape:'quotes':'UTF-8'}</tr>
        <tr class="managesubscribeBlock"><td>&nbsp;</td>
        <td>
        </td>
        </tr><tr class="managesubscribeBlock"><td></td><td>
        <div class="col-md-6 left-wrapper radio_group_option">
  <div class="form-group manage_subscribe_block">
    <div>
      <input type="radio" {if isset($radio_val_option) && $radio_val_option == "nocon"}checked="checked"{/if} value="nocon" name="subscribe_confirm_type" id="no_follow_email">
      <label for="no_follow_email" class="radio-label"> {l s='No confirmation' mod='sendinblue'}</label>
    </div>
    <div class="clearfix"></div>
    <div style="display:block;" class="inner_manage_box">
      <div class="{$cl_version|escape:'htmlall':'UTF-8'|stripslashes}" id="no-templates"> {l s='With this option, contacts are directly added to your list when they enter their email address. No confirmation email is sent.' mod='sendinblue'}</div>
    </div>
  </div>
  <div class="form-group manage_subscribe_block">
    <div class="col-md-10">
      <input type="radio" {if isset($radio_val_option) && $radio_val_option == "simplemail"}checked="checked"{/if} value="simplemail" id="follow_mail" name="subscribe_confirm_type">
      <label for="follow_mail" class="radio-label"> {l s='Simple confirmation' mod='sendinblue'}</label><span title="{l s='This confirmation email is one of your SMTP templates. By default, we have created a Default Template - Single Confirmation.' mod='sendinblue'}" class="toolTip"></span>
    </div>
    <div class="inner_manage_box" {if isset($radio_val_option) && $radio_val_option == "simplemail"} style="display:block;" {/if}> 
      <div class="clearfix"></div>
      <div class="{$cl_version|escape:'htmlall':'UTF-8'|stripslashes}" id="create-templates"> {l s='By selecting this option, contacts are directly added to your list when they enter their email address on the form. A confirmation email will automatically be sent following their subscription.' mod='sendinblue'}</div>
      <div class="clearfix"></div>
      <div id="mail-templates"><div style="text-align: left;" class="listData {$cl_version|escape:'htmlall':'UTF-8'|stripslashes} managesubscribeBlock">{$temp_data|escape:'quotes':'UTF-8'}</div></div>
      <div class="clearfix"></div>
      <div id="mail-templates-active-state">
        
      </div>
    </div>
    <div class="clearfix"></div>
  </div>
  <div class="clearfix"></div>
  <div class="form-group manage_subscribe_block">
    <div style="padding: 0px;" class="col-md-10">
      <input type="radio" {if isset($radio_val_option) && $radio_val_option == "doubleoptin"}checked="checked"{/if} value="doubleoptin" id="double_optin" name="subscribe_confirm_type">
      <label for="double_optin" class="radio-label"> {l s='Double opt-in confirmation' mod='sendinblue'}</label>
      <span title="{l s='If you select the Double Opt-in confirmation, subscribers will receive an email inviting them to confirm their subscription. Before confirmation, the contact will be saved in the \'FORM\' folder, on the \'Temp - DOUBLE OPT-IN\' list. After confirmation, the contact will be saved in the \'Corresponding List\' selected below.' mod='sendinblue'}" class="toolTip">
    </span></div>
    <div class="clearfix"></div>
    <div class="inner_manage_box" {if isset($radio_val_option) && $radio_val_option == "doubleoptin"} style="display:block;" {/if}> 
      <div class="clearfix"></div>
      <!-- Please select a template with the [DOUBLEOPTIN] link -->
      <div id="create-doubleoptin-templates">
        <p style="width: 90%;text-align: justify;text-justify: inter-word;">{l s='Once the form has been completed, your contact will receive an email with a link to confirm their subscription.' mod='sendinblue'}</p></div>
      <!-- Redirect URL after click on the validation email -->
      <div class="clearfix"></div>
      <div style="padding-top: 10px;" id="doubleoptin-redirect-url-area" class="form-group clearfix"> 
      <div style="float:left;">
        <input type="checkbox" class="openCollapse" {if isset($chkval_url) && $chkval_url == "yes"}checked="checked"{/if} value="yes" name="optin_redirect_url_check" id="doptin_redirect_span_icon">
        <a style="color: #555;font-weight: bold;" aria-controls="mail-doubleoptin-redirect" href="#mail-doubleoptin-redirect"> {l s='Redirect URL after clicking in the validation email' mod='sendinblue'} </a> 
        <!-- <label style="margin-bottom: 5px;"></label> -->
        </div>
        <div class="clearfix"></div>
        <div id="mail-doubleoptin-redirect" class="collapse">
          <p style="width: 90%;text-align: justify;text-justify: inter-word;">       
          {l s='Redirect your contacts to a landing page or to your website once they have clicked on the confirmation link in the email.' mod='sendinblue'}</p>
          <input type="url" style="margin-bottom:10px;width:370px" value="{$Sendin_doubleoptin_redirect|escape:'quotes':'UTF-8'}" placeholder="http://your-domain.com" class="form-control" name="doubleoptin-redirect-url" id="doubleoptin-redirect-url">
          <div class="clearfix"></div>
          <div class="pull-left" id="doubleoptin-redirect-message"> </div>
          <div class="clearfix"></div>
        </div>
      </div>
      <!-- Send a final confirmation email -->
      <div class="clearfix"></div>
      <div id="doubleoptin-final-confirmation-area" class="form-group clearfix"> 
      <div style="float:left;">
        <input type="checkbox" class="openCollapse" {if isset($chkval) && $chkval == "yes"}checked="checked"{/if} value="yes" name="final_confirm_email" id="doptin_final_confirm_email">
        <a style="color: #555;font-weight: bold;" aria-controls="doubleoptin-final-confirm" aria-expanded="false" data-toggle="collapse" href="#doubleoptin-final-confirm"> {l s='Send a final confirmation email' mod='sendinblue'} </a>
        </div>
        <div class="clearfix"></div>
        <div style="padding-left: 10px;" id="doubleoptin-final-confirm" class="collapse">
          <p>{l s='Once a contact has clicked in the double opt-in confirmation email, send them a final confirmation email' mod='sendinblue'}</p>
          <div style="text-align: left;" class="listData {$cl_version|escape:'htmlall':'UTF-8'|stripslashes} managesubscribeBlock">{$temp_confirm|escape:'quotes':'UTF-8'}</div>
          <div class="clearfix"></div>
          <div class="pull-left" id="final-mail-templates"></div>
         <div class="clearfix"></div> 
        </div>
          <div style="padding-left: 20px;" id="doubleoptin-templates-active-state">
        {$smtp_alert|escape:'quotes':'UTF-8'}
    </div>
    </div>

    </div>
    </div>
    <div class="clearfix"></div>
    </div>
        </td><td></td></tr><tr class="managesubscribeBlock"><td>&nbsp;</td>
        <td>
        <input type="submit" class="button" value="{l s='Update' mod='sendinblue'}" name="submitForm2">&nbsp;
        </td>
        </tr><tr class="managesubscribeBlock"><td>&nbsp;</td><td colspan="2">
        {if isset($Sendin_import_user_status) && $Sendin_import_user_status == 1}
        <input type="submit" class="button" value="{l s='Import Old Subscribers' mod='sendinblue'}" name="submitUpdateImport">&nbsp;
        {/if}
        </td>
        </form>
        </tr><tr class="managesubscribeBlock"><td class="{$cl_version|escape:'htmlall':'UTF-8'|stripslashes}" colspan="3">{l s='To synchronize the emails of your customers from SendinBlue platform to your e-commerce website, you should run' mod='sendinblue'}
        {$link|escape:'quotes':'UTF-8'} {l s='each day.' mod='sendinblue'}
        <span title="{l s='Note that if you change the name of your Shop (currently' mod='sendinblue'} {$site_name|escape:'htmlall':'UTF-8'}{l s=') the token value changes.' mod='sendinblue'}" class="toolTip">&nbsp;</span>
        </td>
        </tr>
        </tbody>
        </table>

