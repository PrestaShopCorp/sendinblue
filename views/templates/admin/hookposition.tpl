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

		<table class="table tableblock hidetableblock form-data" style="margin-top:15px;"
		cellspacing="0" cellpadding="0" width="100%">
		<thead>
		<tr>
		<th colspan="4">{l s='Manage the position of our newsletter\'s subscription form in the front office' mod='sendinblue'}</th>
		</tr>
		</thead>

		<tr><td colspan="4"><form action="{$form_url|escape:'htmlall':'UTF-8'|replace:'&amp;':'&'}" method="POST">
		<div>
			<label>
			{l s='Right column block' mod='sendinblue'}
			</label>
			<span class="{$cl_version|escape:'htmlall':'UTF-8'|stripslashes}">
			<input type="radio" class="rightColumn radio_nospaceing" id="yesradio" name="rightColumn" value="1"
			{if isset($Sendin_RightColumn) && $Sendin_RightColumn == 1}checked="checked"{/if}/>
			{l s='Show' mod='sendinblue'}
			<input type="radio" class="rightColumn radio_spaceing2" id="noradio"
			name="rightColumn" value="2" {if isset($Sendin_RightColumn) && $Sendin_RightColumn == 2}checked="checked"{/if}/>{l s='Hide' mod='sendinblue'}
		</div>	
		<div>
			<label>
			{l s='Left column block' mod='sendinblue'}
			</label>
			<span class="{$cl_version|escape:'htmlall':'UTF-8'|stripslashes}">
			<input type="radio" class="leftColumn radio_nospaceing" id="yesradio" name="leftColumn" value="1"
			{if isset($Sendin_LeftColumn) && $Sendin_LeftColumn == 1}checked="checked"{/if}/>
			{l s='Show' mod='sendinblue'}
			<input type="radio" class="leftColumn radio_spaceing2" id="noradio"
			name="leftColumn" value="2" {if isset($Sendin_LeftColumn) && $Sendin_LeftColumn == 2}checked="checked"{/if}/>
			{l s='Hide' mod='sendinblue'}
		</div>
		<div>
			<label>
			{l s='Top block' mod='sendinblue'}
			</label>
			<span class="{$cl_version|escape:'htmlall':'UTF-8'|stripslashes}">
			<input type="radio" class="top radio_nospaceing" id="yesradio" name="top" value="1"
			{if isset($Sendin_Top) && $Sendin_Top == 1}checked="checked"{/if}/>
			{l s='Show' mod='sendinblue'}
			<input type="radio" class="top radio_spaceing2" id="noradio"
			name="top" value="2" {if isset($Sendin_Top) && $Sendin_Top == 2}checked="checked"{/if}/>
			{l s='Hide' mod='sendinblue'}
		</div>	
		<div>
			<label>
			{l s='Footer block' mod='sendinblue'}
			</label>
			<span class="{$cl_version|escape:'htmlall':'UTF-8'|stripslashes}">
			<input type="radio" class="footer radio_nospaceing" id="yesradio" name="footer" value="1"
			{if isset($Sendin_Footer) && $Sendin_Footer == 1}checked="checked"{/if}/>
			{l s='Show' mod='sendinblue'}
			<input type="radio" class="footer radio_spaceing2" id="noradio"
			name="footer" value="2" {if isset($Sendin_Footer) && $Sendin_Footer == 2}checked="checked"{/if}/>
			{l s='Hide' mod='sendinblue'}
		</div>
		</div>
		</td></tr></form>
			<form method="post" name="pageposition" id="pageposition" action="{$form_url|escape:'htmlall':'UTF-8'|replace:'&amp;':'&'}">
			<input type="hidden" name="hookerrormsg" value="{l s='Select hook and exception' mod='sendinblue'}" id="hookerrormsg">
			<tr id="position2" >
				<td style="float: left; margin-left:300px;">
					<label style="float: left; width: 150px">{l s='Select hook :' mod='sendinblue'}</label>
					<select name="hooksname" id="hooksname">{$option|escape:"htmlentity"}</select>
				</td>
			</tr>

			<tr id="position" >
				<td style="float: left; margin-left: 300px; height:auto;">
					<label style="float: left; width: 150px">{l s='Exceptions :' mod='sendinblue'}</label>
					<div style="float:left; width:240px;" class="{$cl_version|escape:'htmlall':'UTF-8'|stripslashes}">
						{l s='Please specify the files for which you don\'t want the newsletter\'s subscription form be displayed. Filename should be separated by a comma.' mod='sendinblue'}
						<br>
						<input id="em_text_val" type="text" value="auth" size="40" name="em_text_val">
						<br>
						<div id="div_position">
						<p style="padding-top:10px;">{l s='Select the pages where you want to hide our newsletter\'s subscription form' mod='sendinblue'}</p>
						<select id="oem_list" style="width:237px" multiple="multiple" size="10">
						<option value="address">address</option>
							<option value="addresses">addresses</option>
							<option value="attachment">attachment</option>
							<option value="auth">auth</option>
							<option value="bestsales">bestsales</option>
							<option value="cart">cart</option>
							<option value="category">category</option>
							<option value="changecurrency">changecurrency</option>
							<option value="cms">cms</option>
							<option value="compare">compare</option>
							<option value="contact">contact</option>
							<option value="discount">discount</option>
							<option value="getfile">getfile</option>
							<option value="guesttracking">guesttracking</option>
							<option value="history">history</option>
							<option value="identity">identity</option>
							<option value="index">index</option>
							<option value="manufacturer">manufacturer</option>
							<option value="myaccount">myaccount</option>
							<option value="newproducts">newproducts</option>
							<option value="order">order</option>
							<option value="orderconfirmation">orderconfirmation</option>
							<option value="orderdetail">orderdetail</option>
							<option value="orderfollow">orderfollow</option>
							<option value="orderopc">orderopc</option>
							<option value="orderreturn">orderreturn</option>
							<option value="orderslip">orderslip</option>
							<option value="pagenotfound">pagenotfound</option>
							<option value="parentorder">parentorder</option>
							<option value="password">password</option>
							<option value="pdfinvoice">pdfinvoice</option>
							<option value="pdforderreturn">pdforderreturn</option>
							<option value="pdforderslip">pdforderslip</option>
							<option value="pricesdrop">pricesdrop</option>
							<option value="product">product</option>
							<option value="search">search</option>
							<option value="sitemap">sitemap</option>
							<option value="statistics">statistics</option>
							<option value="stores">stores</option>
							<option value="supplier">supplier</option>
						</select>
					</div>	
				&nbsp;
					<input type="submit" style="float:left; margin-top:5px;"  class="button validationHook" value="{l s='Save' mod='sendinblue'}" name="pagepositionbtn" id="pagepositionbtn">
				</div>
				</td>
			</tr>			</form>
			</table>

