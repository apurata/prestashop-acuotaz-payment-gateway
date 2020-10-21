<?php
/**
 * 2007-2020 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

/**
 * @since 1.5.0
 */
class Ps_ApurataValidationModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		$cart = $this->context->cart;
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirect('index.php?controller=order&step=1');

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
			if ($module['name'] == 'ps_apurata')
			{
				$authorized = true;
				break;
			}
		if (!$authorized)
			die($this->module->getTranslator()->trans('This payment method is not available.', array(), 'Modules.Wirepayment.Shop'));

		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer))
			Tools::redirect('index.php?controller=order&step=1');

		$currency = $this->context->currency;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);
		// ---------CREATE ORDER-------------------------
		//$this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $this->module->displayName, NULL, NULL, (int)$currency->id, false, $customer->secure_key);
		
		//Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
		
		// -----------------------------------------------------
		/* $id_order = Order::getOrderByCartId((int)($cart->id));
        $order = new Order((int) $id_order);
        $customer = new Customer($order->id_customer);
		$address = new Address ($order->id_address_delivery); */
		
		
		$cart = new Cart($cart->id);		
		$address =  new Address($cart->id_address_delivery);
		$description = '';
		foreach ($cart->getProducts() as $key => $product) {
			$description = $product['name'].' - '.$product['attributes'].', '. $description ;
		}
		
		Tools::redirect( Configuration::get('APURATA_DOMAIN').
						'/pos/crear-orden-y-continuar' .
						'?order_id=' . urlencode($cart->id).
						'&pos_client_id=' . urlencode(Configuration::get('APURATA_CLIENT_ID')) .
                        '&amount=' . urlencode($total).
                        '&url_redir_on_canceled=' . urlencode($this->context->link->getPageLink('order')) .
                        '&url_redir_on_rejected=' . urlencode($this->context->link->getPageLink('order')) .
                        '&url_redir_on_success=' . urlencode($this->context->link->getPageLink('order-confirmation')).
                        '&customer_data__customer_id=' . urlencode($cart->id_customer) .
                        '&customer_data__billing_company=' . urlencode($address->company) .
                        '&customer_data__shipping_company=' . urlencode('') .
                        '&customer_data__email=' . urlencode($customer->email) .
                        '&customer_data__phone=' . urlencode($address->phone) .
                        '&customer_data__billing_address_1=' . urlencode($address->address1) .
                        '&customer_data__billing_address_2=' . urlencode($address->address2) .
                        '&customer_data__billing_first_name=' . urlencode($customer->firstname) .
                        '&customer_data__billing_last_name=' . urlencode($customer->lastname) .
                        '&customer_data__billing_city=' .   urlencode($address->city) .
                        '&customer_data__shipping_address_1=' . urlencode($address->address1) .
                        '&customer_data__shipping_address_2=' . urlencode($address->address2) .
                        '&customer_data__shipping_first_name=' . urlencode($customer->firstname) .
                        '&customer_data__shipping_last_name=' . urlencode($customer->lastname) .
                        '&customer_data__shipping_city=' . urlencode($address->city) .
                        '&description=' . urlencode($description)
					);
	}
}
