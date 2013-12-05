<?php
/**
* MOLPay Prestashop Plugin
*
* @package Payment Method
* @author MOLPay Technical Team <technical@molpay.com>
* @version 1.0.0
*
*/

if (!defined('_PS_VERSION_'))
    exit;

class MOLPay extends PaymentModule {
    private $_html = '';
    private $_postErrors = array();
    public $bout_valide;

    /**
     * Initialize the MOLPay payment method
     * 
     */
    public function __construct() {
        
        parent::__construct();
        
        $this->name = 'molpay';        
        $this->tab = 'payments_gateways';
        $this->version = 2.0;
        $this->bout_valide = $this->l('Validate');        
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';        
        $config = Configuration::getMultiple(array('MOLPAY_MERCHANT_VKEY', 'MOLPAY_MERCHANT_ID'));
		
        if(isset($config['MOLPAY_MERCHANT_VKEY']))
            $this->MOLPAY_MERCHANT_VKEY = $config['MOLPAY_MERCHANT_VKEY'];
        if(isset($config['MOLPAY_MERCHANT_ID']))
            $this->MOLPAY_MERCHANT_ID = $config['MOLPAY_MERCHANT_ID'];
               
        $this->page = basename(__FILE__, '.php');
        $this->displayName = 'MOLPay Malaysia Online Payment Gateway';
        $this->description = $this->l('Accept payments with MOLPay');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details ?');

        if(!count(Currency::checkPaymentCurrencies($this->id)))
            $this->warning = $this->l('No currency set for this module');
        if(!isset($this->MOLPAY_MERCHANT_VKEY) || !isset($this->MOLPAY_MERCHANT_ID))
            $this->warning = $this->l('Your MOLPay account must be set correctly');
    }        

    /**
     * Install the MOLPay module into prestashop
     * 
     * @return boolean
     */
    function install() {        
        if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn') || !$this->registerHook('header'))
            return false;
        else
            return true;
    }
    
    /**
     * Uninstall the MOLPay module from prestashop
     * 
     * @return boolean
     */
    function uninstall() {
        if (!Configuration::deleteByName('MOLPAY_MERCHANT_VKEY') || !Configuration::deleteByName('MOLPAY_MERCHANT_ID') || !parent::uninstall())
            return false;
        else
            return true;
    }

    /**
     * Validate the form submited by MOLPay configuration setting
     * 
     */
    private function _postValidation() {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('merchant_id'))
                $this->_postErrors[] = $this->l('Merchant ID is required');
            else if (!Tools::getValue('merchant_vkey'))
                $this->_postErrors[] = $this->l('Merchant VKey is required.');
        }
    }

    /**
     * Save/update the MOLPay configuration setting
     * 
     */
    private function _postProcess() {
        if (isset($_POST['btnSubmit'])) {
            Configuration::updateValue('MOLPAY_MERCHANT_ID', Tools::getValue('merchant_id'));
            Configuration::updateValue('MOLPAY_MERCHANT_VKEY', Tools::getValue('merchant_vkey'));
        }		
        $this->_html .= '<div class="conf confirm"> '.$this->l('Settings updated').'</div>';
    }
    
    /**
     * Display notification after saving the MOLPay configuration setting
     * 
     */
    private function _displayMOLPay() {
        $this->_html .= '<img src="../modules/molpay/img/molpay.gif" style="float:left; margin-right:15px;"><b>'.$this->l('This module allows you to accept payments by MOLPay.').'</b><br /><br />
        '.$this->l('You need to register on the site').' <a href="http://molpay.com" target="blank">Molpay.com</a> <br /><br /><br />';
    }
    
    /**
     * Display the form to provide the MOLPay configuration setting
     * 
     */
    private function _displayForm() {
        $this->_html .=
        '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
            <fieldset>
            <legend><img src="../img/admin/contact.gif" />'.$this->l('Contact details').'</legend>
                <table border="0" width="500" cellpadding="0" cellspacing="0" id="form">
                    <tr><td colspan="2">'.$this->l('Please specify the Merchant VKey and a unique MerchantID registered in the molpay system').'.<br /><br /></td></tr>
                    <tr><td width="140" style="height: 35px;">'.$this->l('Merchant ID').'</td><td><input type="text" name="merchant_id" value="'.htmlentities(Tools::getValue('merchant_id', $this->MOLPAY_MERCHANT_ID), ENT_COMPAT, 'UTF-8').'" style="width: 300px;" /></td></tr>
                    <tr><td width="140" style="height: 35px;">'.$this->l('Merchant VKey').'</td><td><input type="text" name="merchant_vkey" value="'.htmlentities(Tools::getValue('merchant_vkey', $this->MOLPAY_MERCHANT_VKEY), ENT_COMPAT, 'UTF-8').'" style="width: 300px;" /></td></tr>
                    <tr><td colspan="2" align="center"><br /><input class="button" name="btnSubmit" value="'.$this->l('Update settings').'" type="submit" /></td></tr>
                </table>
            </fieldset>
        </form>';
    }

    /**
     * Display the MOLPay configuration setting. <call private method>
     * 
     * @return string
     */
    function getContent() {
        $this->_html = '<h2>'.$this->displayName.'</h2>';
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors))
                $this->_postProcess();
            else
                foreach ($this->_postErrors as $err)
                    $this->_html .= '<div class="alert error">' . $err . '</div>';
        }
        else
            $this->_html .= '<br />';

        $this->_displayMOLPay();
        $this->_displayForm();
        return $this->_html;
    }

    /**
     * Hook MOLPay stylesheet to prestashop header method
     * 
     * @global array $smarty
     * @global array $cookie
     * @param mixed $params
     */
    public function hookHeader($params) {
        global $smarty, $cookie;
        $this->context->controller->addCSS(($this->_path) . 'css/molpay.css', 'all');
    }
    
    /**
     * Hook the payment form to the prestashop Payment method. Display in payment method selection
     * 
     * @param array $params
     * @return string
     */
    public function hookPayment($params) {
        if (!$this->active)
            return;
        if (!$this->checkCurrency($params['cart']))
            return;		
			
        $address     = new Address(intval($params['cart']->id_address_invoice));
        $customer    = new Customer((int)$this->context->cart->id_customer);
        $mp_merchant = Configuration::get('MOLPAY_MERCHANT_ID');
        $mp_vkey     = Configuration::get('MOLPAY_MERCHANT_VKEY');
        
        $currency_obj = $this->context->currency;
        $currency_code = $currency_obj->iso_code;
        $orderid = (int)$this->context->cart->id;
        $amount = $amount = $this->context->cart->getOrderTotal(true, Cart::BOTH);
        $bill_name = $customer->firstname." ".$customer->lastname;
        $bill_email = $customer->email;
        
        $country_obj =  new Country(intval($address->id_country));
        $country = $country_obj->iso_code;
        $country_name_obj = $country_obj->name;
        $country_name =  $country_name_obj[1];
        
        $add_obj = new Address(intval($params['cart']->id_address_invoice));
        $add = "";
        $add.="----------------------------------\nBilling Address\n----------------------------------\n";
        $add.= $add_obj->address1." ".$add_obj->address2." ".$add_obj->postcode." ".$add_obj->city." ".$country_name;
        
        $prod_obj = $params['cart']->getProducts();
        $size = sizeof($prod_obj);
        $prod = "";
        $prod.= "\n\n\n----------------------------------\nProduct(s) Info\n----------------------------------\n";
        for($i=0; $i<$size; $i++) {
            $prod.= $prod_obj[$i]['name']." x ".$prod_obj[$i]['cart_quantity'];
            $prod.= "\n";
        }

        //$cart = new Cart(intval($orderid));

        $bill_desc = $add.$prod;
        $vcode = md5($amount.$mp_merchant.$orderid.$mp_vkey);

        $this->smarty->assign(array(
            'amount' => $amount,
            'orderid' => $orderid,
            'mp_merchant' => $mp_merchant,
            'bill_name' => $bill_name,
            'bill_email' => $bill_email,
            'bill_desc' => $bill_desc,
            'molpayUrl' => 'https://www.onlinepayment.com.my/MOLPay/pay/'.$mp_merchant.'/',
            'currency' => $currency_code,
            'country' =>  $country,
            'vcode'   => $vcode,
            'returnurl' => 'http://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'index.php?fc=module&module=molpay&controller=validation',
            'this_path' => $this->_path
        ));

        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * Hook the payment return to the prestashop payment return method
     * 
     * @param array $params
     * @return string
     */
    public function hookPaymentReturn($params) {
        if (!$this->active)
            return;

        $state = $params['objOrder']->getCurrentState();
        if ($state == Configuration::get('PS_OS_PAYMENT')) {
            $this->smarty->assign(array(
                'status' => '00',
                'id_order' => $params['objOrder']->id
            ));
            if(isset($params['objOrder']->reference) && !empty($params['objOrder']->reference))
                $this->smarty->assign('reference', $params['objOrder']->reference);
        }		
        else if ($state == Configuration::get('PS_OS_ERROR')) {
            $this->smarty->assign(array(
                'status' => '11',
                'id_order' => $params['objOrder']->id
            ));
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference))
                $this->smarty->assign('reference', $params['objOrder']->reference);
        }
        else
            $this->smarty->assign('status', 'other');
			
        return $this->display(__FILE__, 'payment_return.tpl');
    }

    /**
     * Check the currency
     * 
     * @param object $cart
     * @return boolean
     */
    public function checkCurrency($cart) {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module))
            foreach ($currencies_module as $currency_module)
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;
        return false;
    }	
}

?>