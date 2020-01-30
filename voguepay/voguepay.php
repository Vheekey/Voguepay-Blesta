<?php

/**
 * Voguepay Payment Gateway
 *
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.voguepay
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Voguepay extends NonmerchantGateway
{
    /**
     * @var string The version of this gateway
     */
    private static $version = '1.0.0';
    /**
     * @var string The authors of this gateway
     */
    private static $authors = [['name'=>'Vheekey','url'=>'vicformidable@gmail.com']];
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;
    /**
     * @var string The URL to post payments to
     */
   // private $pay_url = 'https://voguepay.com/pay/api';
   
    private $pay_url = "https://voguepay.com/";
  


    /**
     * Construct a new merchant gateway
     */
    public function __construct()
    {

       
        // Load components required by this gateway
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this gateway
        Language::loadLang('voguepay', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Returns the name of this gateway
     *
     * @return string The common name of this gateway
     */
    public function getName()
    {
        return Language::_('Voguepay.name', true);
    }

    /**
     * Returns the version of this gateway
     *
     * @return string The current version of this gateway
     */
    public function getVersion()
    {
        return self::$version;
    }

    /**
     * Returns the name and URL for the authors of this gateway
     *
     * @return array The name and URL of the authors of this gateway
     */
    public function getAuthors()
    {
        return self::$authors;
    }

    /**
     * Return all currencies supported by this gateway
     *
     * @return array A numerically indexed array containing all currency codes (ISO 4217 format) this gateway supports
     */
    public function getCurrencies()
    {
        return ['EUR', 'GBP', 'USD', 'NGN'];
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView('settings', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = [
            'account_id' => [
                'valid' => [
                    'rule' => ['isEmpty', false],
                    'message' => Language::_('Voguepay.!error.account_id.valid', true)
                ]
            ],
            'demo'=>[
                'valid'=>[
                    'if_set'=>true,
                    'rule'=>['in_array', ['true', 'false']],
                    'message'=>Language::_('Voguepay.!error.demo.valid', true)
                ]
            ]
        ];


        $rules = [];

        // Set checkbox if not set
        if (!isset($meta['demo'])) {
            $meta['demo'] = 'false';
        }

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);
        // Return the meta data, no changes required regardless of success or failure for this gateway

        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['account_id', 'api_token'];
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form
     *
     * @param array $contact_info An array of contact info including:
     *  - id The contact ID
     *  - client_id The ID of the client this contact belongs to
     *  - user_id The user ID this contact belongs to (if any)
     *  - contact_type The type of contact
     *  - contact_type_id The ID of the contact type
     *  - first_name The first name on the contact
     *  - last_name The last name on the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - address1 The address 1 line of the contact
     *  - address2 The address 2 line of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     *  - recur An array of recurring info including:
     *      - start_date The date/time in UTC that the recurring payment begins
     *      - amount The amount to recur
     *      - term The term to recur
     *      - period The recurring period (day, week, month, year, onetime) used in
     *          conjunction with term in order to determine the next recurring payment
     * @return mixed A string of HTML markup required to render an authorization and
     *  capture payment form, or an array of HTML markup
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {

        // Force 2-decimal places only
        $amount = round($amount, 2);
        // if (isset($options['recur']['amount'])) {
        //     $options['recur']['amount'] = round($options['recur']['amount'], 2);
        // }

        $post_to = $this->pay_url;
        $merchant_id=$this->ifSet($this->meta['account_id']);

        if ($this->ifSet($this->meta['demo']) == 'true') {
            $merchant_id = 'demo';
        }

        $ref=base64_encode(serialize($invoice_amounts)).":".$this->currency.":".$amount;
        $description=$this->ifSet($options['description']);
        $redirect_url_fail=$redirect_url=$this->ifSet($options['return_url']);
        $developer_code='5e2b0b61419a5';
        $callbackUrl=Configure::get('Blesta.gw_callback_url')
        . Configure::get('Blesta.company_id') . '/voguepay/?client_id='
        . $this->ifSet($contact_info['client_id']);

        $post_to= file_get_contents("https://voguepay.com/?p=linkToken&v_merchant_id=".strtolower($merchant_id)."&memo=".urlencode($description)."&total=".$amount."&merchant_ref=".$ref."&notify_url=".urlencode($callbackUrl)."&success_url=".urlencode($redirect_url)."&fail_url=".urlencode($redirect_url_fail)."&developer_code=".$developer_code."&cur=".$this->currency);
  

        // An array of key/value hidden fields to set for the payment form
        $fields = [];         
        

      
        return $this->buildForm($post_to, $fields, false);;
    }

    /**
     * Builds the HTML form
     *
     * @param string $post_to The URL to post to
     * @param array $fields An array of key/value input fields to set in the form
     * @param bool $recurring True if this is a recurring payment request, false otherwise
     * @return string The HTML form
     */
    private function buildForm($post_to, $fields, $recurring = false)
    {
        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('post_to', $post_to);
        $this->view->set('fields', $fields);
        //$this->view->set('recurring', $recurring);

        return $this->view->fetch();
    }

    /**
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's
     *      original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {
        $txId = $post['transaction_id'];
        
   
        $merchant_id=$this->ifSet($this->meta['account_id']);
        $merchant_id = strtolower($merchant_id);

        if($merchant_id == 'demo'){
            $response = "https://voguepay.com/?v_transaction_id=".$txId."&type=json&demo=true";
        }else{
            $response = "https://voguepay.com/?v_transaction_id=".$txId."&type=json";
        }
        $response = json_decode($response, true);

        if($response['status'] == "Approved"){
            $status = 'approved';
        }else{
            $status = 'declined';
        }
        $refer = $response['merchant_ref'];
        $refer = explode(':', $response);
        $refID = $refer[0]; 
        $cur = $refer[1]; 
        $amount = $refer[2]; 

        // Log post-back sent
        $this->log('validate', json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'output', true);

        return [
            'client_id' => $this->ifSet($merchant_id),
            'amount' => $this->ifSet($amount),
            'currency' => $this->ifSet($cur),
            'invoices' => unserialize(base64_decode($this->ifSet($post['invoices']))),
            'status' => $status,
            'reference_id' => $refID,
            'transaction_id' => $this->ifSet($txId),
            'parent_transaction_id' => null
        ];

    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
     */
    public function success(array $get, array $post)
    {
        // $client_id = $this->ifSet($get['client_id']);

        return [
            'client_id' => $get['client_id'],
            
        ];
    }

    /**
     * Refund a payment
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param float $amount The amount to refund this transaction
     * @param string $notes Notes about the refund that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    

    /**
     * Serializes an array of invoice info into a string
     *
     * @param array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
     */
    private function serializeInvoices(array $invoices)
    {
        $str = '';
        foreach ($invoices as $i => $invoice) {
            $str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }
        return $str;
    }

    /**
     * Unserializes a string of invoice info into an array
     *
     * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
     * @return array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     */
    private function unserializeInvoices($str)
    {
        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }
            $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
        }
        return $invoices;
    }

    /**
     *  Recursively convert all array values to a utf8-encoded string
     *
     * @param array $data The data to be encoded
     * @return array The encoded data
     */
    private function utf8EncodeArray(array $data) {
        foreach ($data as &$value) {
            if ($value !== null) {
                $value = (is_scalar($value) ? utf8_encode($value) : $this->utf8EncodeArray((array)$value));
            }
        }

        return $data;
    }
}
