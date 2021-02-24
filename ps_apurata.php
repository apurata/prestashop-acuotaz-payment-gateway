<?php
/**
 * Version:           0.2.2
 * Plugin Name:       aCuotaz Apurata
 * Description:       Finance your purchases with a quick aCuotaz Apurata loan.
 * Requires PHP:      7.2
 * Author:            Apurata
 * Author URI:        https://apurata.com/app
 * License:           GPL3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       ps_apurata
 *
 * PRESTASHOP requires at least: 1.7.6.0
 * PRESTASHOP tested up to: 1.7.6.7
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Apurata extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;
    public $domain;

    public function __construct()
    {
        $this->name = 'ps_apurata';
        $this->tab = 'payments_gateways';
        $this->version = '0.2.2';
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
        $this->author = 'Apurata';
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('APURATA_CLIENT_TOKEN', 'APURATA_CLIENT_ID', 'APURATA_UPDATE_ORDER_URL','APURATA_ALLOW_HTTP', 'APURATA_DOMAIN'));
        if (!empty($config['APURATA_CLIENT_ID'])) {
            $this->owner = $config['APURATA_CLIENT_ID'];
        }
        if (!empty($config['APURATA_CLIENT_TOKEN'])) {
            $this->details = $config['APURATA_CLIENT_TOKEN'];
        }
        if (!empty($config['APURATA_UPDATE_ORDER_URL'])) {
            $this->details = $config['APURATA_UPDATE_ORDER_URL'];
        }
        if (!empty($config['APURATA_ALLOW_HTTP'])) {
            $this->details = $config['APURATA_ALLOW_HTTP'];
        }
        $domain = getenv('APURATA_API_DOMAIN') ?: 'https://apurata.com'; //'https://apurata.com'
        Configuration::updateValue('APURATA_DOMAIN', $domain);

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('aCuotaz Apurata');
        $this->description = $this->l('Evalúa a tus clientes y financia su compra con cuotas quincenales, sin tarjeta de crédito.');
        $this->confirmUninstall = $this->trans('Are you sure about removing these details?', array(), 'Modules.Wirepayment.Admin');
        if (!isset($this->owner) || !isset($this->details) || !isset($this->address)) {
            $this->warning = $this->trans('Account owner and account details must be configured before using this module.', array(), 'Modules.Wirepayment.Admin');
        }
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->trans('No currency has been set for this module.', array(), 'Modules.Wirepayment.Admin');
        }

        $this->extra_mail_vars = array(
            '{bankwire_owner}' => Configuration::get('APURATA_CLIENT_ID'),
            '{bankwire_details}' => nl2br(Configuration::get('APURATA_CLIENT_TOKEN')),
            '{bankwire_url}' => nl2br(Configuration::get('APURATA_UPDATE_ORDER_URL')),
            '{bankwire_url}' => nl2br(Configuration::get('APURATA_ALLOW_HTTP')),
        );

    }

    public function addOrderState($name)
    {
        $state_exist = false;
        $states = OrderState::getOrderStates((int)$this->context->language->id);

        // check if order state exist
        foreach ($states as $state) {
            if (in_array($name, $state)) {
                $state_exist = true;
                break;
            }
        }

        // If the state does not exist, we create it.
        if (!$state_exist) {
            // create new order state
            $order_state = new OrderState();
            $order_state->color = '#75c279';
            $order_state->send_email = false;
            $order_state->module_name = 'ps_apurata';
            $order_state->name = array();
            $languages = Language::getLanguages(false);
            foreach ($languages as $language)
                $order_state->name[ $language['id_lang'] ] = $name;

            // Update object
            $order_state->add();
            Configuration::updateValue("PS_OS_APURATA", (int) $order_state->id);
        }

        return true;
    }

    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('paymentReturn') ||
            !$this->registerHook('paymentOptions') ||
            !$this->registerHook('displayShoppingCartFooter') ||
            !$this->registerHook('displayAdminLogin')||
            !$this->registerHook('displayProductPriceBlock')||
            !$this->registerHook('displayHeader')){
            return false;
        }

        $this->addOrderState($this->l('Esperando validación de Apurata'));
        return true;
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('APURATA_CLIENT_TOKEN')
                || !Configuration::deleteByName('APURATA_CLIENT_ID')
                || !Configuration::deleteByName('APURATA_UPDATE_ORDER_URL')
                || !Configuration::deleteByName('APURATA_ALLOW_HTTP')
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            /* Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE)); */

            if (!Tools::getValue('APURATA_CLIENT_TOKEN')) {
                $this->_postErrors[] = $this->trans('Account details are required.', array(), 'Modules.Wirepayment.Admin');
            } elseif (!Tools::getValue('APURATA_CLIENT_ID')) {
                $this->_postErrors[] = $this->trans('Account owner is required.', array(), "Modules.Wirepayment.Admin");
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('APURATA_CLIENT_TOKEN', Tools::getValue('APURATA_CLIENT_TOKEN'));
            Configuration::updateValue('APURATA_CLIENT_ID', Tools::getValue('APURATA_CLIENT_ID'));
            Configuration::updateValue('APURATA_UPDATE_ORDER_URL', Tools::getValue('APURATA_UPDATE_ORDER_URL'));
            Configuration::updateValue('APURATA_ALLOW_HTTP', Tools::getValue('APURATA_ALLOW_HTTP'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    protected function _displayBankWire()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->_displayBankWire();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }

        if (!$this->checkCurrency($params['cart'])) {
            return [];
        }

        if (!$this->isCorrectAmount($params['cart'])) {
            return [];
        }

        if (!$this->ischeckHttp()) {
            return [];
        }

        $this->smarty->assign(
            $this->getTemplateVarInfos()
        );

        $newOption = new PaymentOption();
        $client_id = Configuration::get('APURATA_CLIENT_ID');
        $description = <<<EOF
                    <div id="apurata-pos-steps"></div>
                    <script style="display:none">
                        var r = new XMLHttpRequest();
                        r.open("GET", "https://apurata.com/pos/{$client_id}/info-steps", true);
                        r.onreadystatechange = function () {
                          if (r.readyState != 4 || r.status != 200) return;
                          var elem = document.getElementById("apurata-pos-steps");
                          elem.innerHTML = r.responseText;
                        };
                        r.send();
                    </script>
EOF;
        $newOption->setModuleName($this->name)
                ->setCallToActionText('Cuotas sin tarjeta de crédito - aCuotaz')
                ->setAdditionalInformation($description)
                ->setLogo('https://static.apurata.com/img/logo-dark-aCuotaz.svg')
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));

        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();
        if (
            in_array(
                $state,
                array(
                    Configuration::get('PS_OS_BANKWIRE'),
                    Configuration::get('PS_OS_OUTOFSTOCK'),
                    Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'),
                )
        )) {
            $bankwireOwner = $this->owner;
            if (!$bankwireOwner) {
                $bankwireOwner = '___________';
            }

            $bankwireDetails = Tools::nl2br($this->details);
            if (!$bankwireDetails) {
                $bankwireDetails = '___________';
            }

            $bankwireAddress = Tools::nl2br($this->address);
            if (!$bankwireAddress) {
                $bankwireAddress = '___________';
            }

            $totalToPaid = $params['order']->getOrdersTotalPaid() - $params['order']->getTotalPaid();
            $this->smarty->assign(array(
                'shop_name' => $this->context->shop->name,
                'total' => Tools::displayPrice(
                    $totalToPaid,
                    new Currency($params['order']->id_currency),
                    false
                ),
                'bankwireDetails' => $bankwireDetails,
                'bankwireAddress' => $bankwireAddress,
                'bankwireOwner' => $bankwireOwner,
                'status' => 'ok',
                'reference' => $params['order']->reference,
                'contact_url' => $this->context->link->getPageLink('contact', true)
            ));
        } else {
            $this->smarty->assign(
                array(
                    'status' => 'failed',
                    'contact_url' => $this->context->link->getPageLink('contact', true),
                )
            );
        }

        return $this->fetch('module:ps_apurata/views/templates/hook/payment_return.tpl');
    }

    public function ischeckHttp() {
		$isHttps =
			$_SERVER['HTTPS']
			?? $_SERVER['REQUEST_SCHEME']
			?? $_SERVER['HTTP_X_FORWARDED_PROTO']
			?? null;

		$isHttps = $isHttps && (
			strcasecmp('1', $isHttps) == 0
			|| strcasecmp('on', $isHttps) == 0
			|| strcasecmp('https', $isHttps) == 0
        );
        //error_log(Configuration::get('APURATA_ALLOW_HTTP'));

        if (Configuration::get('APURATA_ALLOW_HTTP') == false && !$isHttps) {
			return false;
        }
		return true;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency'] && $currency_order->iso_code == 'PEN') {
                    return true;
                }
            }
        }
        return false;
    }

    public function isCorrectAmount($cart)
    {
        $cart = new Cart($cart->id);
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $landing_config = $this->getLandingConfig();
        if (!is_object($landing_config)) {
            return false;
        }
        if (is_object($landing_config) && ($landing_config->min_amount > $total || $landing_config->max_amount < $total)) {
            return false;
		}
        return true;
    }

    public function sendWebhookUrl() {
        $url = "/pos/client/" . Configuration::get('APURATA_CLIENT_ID') . "/save_webhookurl";
        list ($httpCode, $ret) = $this->makeCurlToApurata("POST", $url, array(
            "pos_webhook_url"=> $this->context->link->getModuleLink($this->name, 'updateorder', array(), true)
        ));
        return $httpCode;
    }

    public function getLandingConfig() {
		list ($httpCode, $landing_config) = $this->makeCurlToApurata("GET", "/pos/client/landing_config");
		$landing_config = json_decode($landing_config);
		return $landing_config ?? null;
    }

    public function makeCurlToApurata($method, $path, $data = null, $fire_and_forget=FALSE, $domain = null) {
		// $method: "GET" or "POST"
        // $path: e.g. /pos/client/landing_config
        // If data is present, send it via JSON
		$ch = curl_init();
        if (!$domain) {
            $domain = Configuration::get('APURATA_DOMAIN');
        }
		$url = $domain . $path;
		curl_setopt($ch, CURLOPT_URL, $url);

		// Timeouts
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);    // seconds
		curl_setopt($ch, CURLOPT_TIMEOUT, 2); // seconds

		$headers = array("Authorization: Bearer " . Configuration::get('APURATA_CLIENT_TOKEN'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		if (strtoupper($method) == "GET") {
			curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
		} else if (strtoupper($method) == "POST") {
            curl_setopt($ch, CURLOPT_POST, TRUE);
		} else {
			throw new Exception("Method not supported: " . $method);
        }

        if ($data) {
            $payload = json_encode($data);

            // Attach encoded JSON string to the POST fields
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            // Set the content type to application/json
            array_push($headers, 'Content-Type:application/json');
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($fire_and_forget) {
            // From: https://www.xspdf.com/resolution/52447753.html
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            // We don't use CURLOPT_TIMEOUT_MS because the name resolution fails and the
            // whole request never goes out
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        }

		$ret = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($httpCode != 200) {
			error_log("Apurata responded with http_code ". $httpCode . " on " . $method . " to " . $url);
		}
		curl_close($ch);
		return array($httpCode, $ret);
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Account details', array(), 'Modules.Wirepayment.Admin'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Client ID', array(), 'Modules.Wirepayment.Admin'),
                        'name' => 'APURATA_CLIENT_ID',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Client Token', array(), 'Modules.Wirepayment.Admin'),
                        'name' => 'APURATA_CLIENT_TOKEN',
                        'required' => true
                    ),
                    array(
                        'type' => 'switch',
                        'label' => 'Permitir HTTP',
                        'name' => 'APURATA_ALLOW_HTTP',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global'),
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='
            .$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );
        $this->sendWebhookUrl();

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'APURATA_CLIENT_TOKEN' => Tools::getValue('APURATA_CLIENT_TOKEN', Configuration::get('APURATA_CLIENT_TOKEN')),
            'APURATA_CLIENT_ID' => Tools::getValue('APURATA_CLIENT_ID', Configuration::get('APURATA_CLIENT_ID')),
            'APURATA_UPDATE_ORDER_URL' => $this->context->link->getModuleLink($this->name, 'updateorder', array(), true),
            'APURATA_ALLOW_HTTP' => Tools::getValue('APURATA_ALLOW_HTTP', Configuration::get('APURATA_ALLOW_HTTP')),
            /*self::FLAG_DISPLAY_PAYMENT_INVITE => Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE)) */
        );
    }

    public function getTemplateVarInfos()
    {
        $cart = $this->context->cart;
        $total = sprintf(
            $this->trans('%1$s (tax incl.)', array(), 'Modules.Wirepayment.Shop'),
            Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH))
        );

         $bankwireOwner = $this->owner;
        if (!$bankwireOwner) {
            $bankwireOwner = '___________';
        }

        $bankwireDetails = Tools::nl2br($this->details);
        if (!$bankwireDetails) {
            $bankwireDetails = '___________';
        }

        $bankwireAddress = Tools::nl2br($this->address);
        if (!$bankwireAddress) {
            $bankwireAddress = '___________';
        }

        return array(
            'total' => $total,
            'bankwireDetails' => $bankwireDetails,
            'bankwireAddress' => $bankwireAddress,
            'bankwireOwner' => $bankwireOwner,
        );
    }

    public function generateApurataAddon($pageType, $params, $total, $variable_price=FALSE)
    {
        $url = '/pos/pay-with-apurata-add-on/' . $total . '?page='. $pageType;
        $customer = new Customer($params['cart']->id_customer);
        if ($customer) {
            $url .= '&user__id=' . urlencode((string) $params['cart']->id_customer) .
                '&user__email=' . urlencode((string) $customer->email) .
                '&user__first_name=' . urlencode((string) $customer->firstname) .
                '&user__last_name=' . urlencode((string) $customer->lastname);
        }
        if ($pageType == 'product') {
            $url .= '&variable_amount=' . urldecode((string) $variable_price);
        }
        $number_of_items = $params['cart']->nbProducts();
        if($pageType == 'cart' && $number_of_items > 1) {
            $url .= '&multiple_products=' . urldecode('TRUE');
        }
        list($resp_code, $this->pay_with_apurata_addon) = $this->makeCurlToApurata("GET", $url);
        if ($resp_code == 200) {
            $this->smarty->assign([
                'response' => $this->pay_with_apurata_addon,
            ]);
        } else {
            $this->smarty->assign([
                'response' => $resp_code,
            ]);
        }
        return $this->display(__FILE__, 'addon.tpl');
    }
    public function hookDisplayShoppingCartFooter($params)
    {
        $cart = new Cart($params['cart']->id);
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        return $this->generateApurataAddon('cart', $params, $total);
    }
    public function hookdisplayProductPriceBlock($params)
    {   
        if ((isset($params['type']) && $params['type'] == 'price')) {
            $variable_price = FALSE;
            $product = new Product($_GET['id_product']);
            $id_attributes = Context::getContext()->language->id;
            $combinations = $product->getAttributeCombinations($id_attributes);
            if (sizeof($combinations) > 1 ) {
                $variable_price = TRUE;
            }
            return $this->generateApurataAddon('product', $params, $product->price,$variable_price);
        }
        return;
    }

    public function hookDisplayAdminLogin()
    {
        $php_version = phpversion();
        $url = "/pos/client/" . Configuration::get('APURATA_CLIENT_ID') . "/context";
        $this->makeCurlToApurata("POST", $url, array(
            "php_version" => $php_version,
            "prestashop_version" => _PS_VERSION_,
            "ps_apurata_version" => $this->version,
        ), TRUE);
    }
    public function hookDisplayHeader(){
        $path = '/vendor/pixels/apurata-pixel.txt';
        $static_domain = 'https://static.apurata.com';
        list($httpCode, $response) = $this->makeCurlToApurata("GET", $path, null, false, $static_domain);
        if ($httpCode != 200) {
            $response = '';
        }
        $this->smarty->assign([
            'response' => $response,
        ]);
        return $this->display(__FILE__, 'apurata_pixel.tpl');
    }
}
