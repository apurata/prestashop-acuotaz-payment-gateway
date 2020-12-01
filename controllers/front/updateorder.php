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
class Ps_ApurataUpdateOrderModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
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
class Ps_ApurataUpdateOrderModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
        $id_cart = Tools::getValue('order_id');
        $event = Tools::getValue('event');
        $new_order_state = 0;
        error_log("New event from Apurata");

        $cart = new Cart($id_cart);
        $customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');
        error_log("I have a cart");

		$currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        switch ($event) {
            case 'onhold':
                error_log("Creating order...");
                $this->module->validateOrder($id_cart, Configuration::get('PS_OS_APURATA'), $total, $this->module->displayName, NULL, NULL, (int)$currency->id, false, $customer->secure_key);
                //Tools::redirect('index.php?controller=order-confirmation&id_cart='.$id_cart.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
                error_log(http_response_code());
                header('HTTP/1.1 200 OK');
                error_log("------------------------------------------------------");
                exit;
            case 'validated':
                error_log("Validated");
                $new_order_state = 2;
                break;
            case 'rejected':
                error_log("Rejected");
                $new_order_state = 8;
                break;
            case 'canceled':
                error_log("Canceled");
                $new_order_state = 6;
                break;
            default:
                error_log($event);
                error_log("State no supported");
                die('Unsupported event');
        }
        $id_order = (int)Order::getIdByCartId($id_cart);
        error_log("Get Order ID");
        // $order = new Order($id_order);
        // $order->setCurrentState($new_order_state);   Doesn't work for me

        $history = new OrderHistory();
        error_log("Before change state");
        $history->changeIdOrderState($new_order_state, $id_order);
        // $history->save(); // It's found?
        error_log("After change state");
        http_response_code(200);
        echo http_response_code();
        error_log("------------------------------------------------------");
        exit;
	}
}
