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
        // Same contract as Magento/OpenCart: Apurata-Auth Bearer must match the module token
        // before any cart/order mutation (DEV-1018 / CWE-306).
        if (!$this->isAuthorizedApurataWebhook()) {
            http_response_code(401);
            exit;
        }

        $id_cart = Tools::getValue('order_id');
        $event = Tools::getValue('event');
        $new_order_state = 0;
        $cart = new Cart($id_cart);
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
                Tools::redirect('index.php?controller=order&step=1');
        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $validation = null;
        switch ($event) {
            case 'onhold':
                $id_order = (int)Order::getIdByCartId($id_cart);
                // Check if the order has already been created
                if($id_order==0)
                    $validation=$this->module->validateOrder(
                        $id_cart,
                        Configuration::get('PS_OS_APURATA'),
                        $total,
                        $this->module->displayName,
                        NULL,
                        NULL,
                        (int)$currency->id,
                        false,
                        $customer->secure_key
                    );
                // Do not put secure_key in response headers — it is a customer credential.
                header('Apurata-Log: created=' .( $validation ? 'true':'false') .
                    ' PS_OS_APURATA=' . Configuration::get('PS_OS_APURATA') .
                    ' id_cart=' . $id_cart .
                    ' module->displayName= ' . $this->module->displayName
                );
                http_response_code(200);
                exit;
            case 'validated':
                $new_order_state = 2;
                break;
            case 'rejected':
                $new_order_state = 8;
                break;
            case 'canceled':
                $new_order_state = 6;
                break;
            //--states not implemented--
            case 'created':
                http_response_code(200);
                exit;
            case 'approved':
                http_response_code(200);
                exit;
            case 'funded':
                http_response_code(200);
                exit;
            //--------------------------
            default:
                return;
        }
        $id_order = (int)Order::getIdByCartId($id_cart);
        $history = new OrderHistory();
        $history->changeIdOrderState($new_order_state, $id_order);
        http_response_code(200);
        exit;
	}

    /**
     * True when Apurata-Auth Bearer matches APURATA_CLIENT_TOKEN (server-to-server webhook).
     */
    private function isAuthorizedApurataWebhook()
    {
        $expected = Configuration::get('APURATA_CLIENT_TOKEN');
        if (!$expected) {
            return false;
        }
        $auth = $this->getApurataAuthHeader();
        if (!$auth || stripos($auth, 'Bearer ') !== 0) {
            return false;
        }
        $token = trim(substr($auth, strlen('Bearer ')));
        return hash_equals((string) $expected, $token);
    }

    private function getApurataAuthHeader()
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp($name, 'Apurata-Auth') === 0) {
                        return $value;
                    }
                }
            }
        }
        return isset($_SERVER['HTTP_APURATA_AUTH']) ? $_SERVER['HTTP_APURATA_AUTH'] : '';
    }
}
