<?php
/*
 * Plugin Name: Epay Payment Gateway
 * Plugin URI: https://epaygh.com
 * Description: Epay payment gateway plugin for WooCommerce. It allows you to accept Mobile Money payments in your shop
 * Version: 1.0.1
 * Author: AppGharage
 * Author URI: https://appgharage.com
*/

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'epay_add_gateway_class');
function epay_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Epay_Payment_Gateway';
    return $gateways;
}
 
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'epay_init_gateway_class');
function epay_init_gateway_class()
{
    class WC_Epay_Payment_Gateway extends WC_Payment_Gateway
    {
 
        /**
         * Class constructor
         */
        public function __construct()
        {
            $this->id = 'epay';
            $this->icon = plugins_url('banner.png', __FILE__);
            $this->has_fields = true;
            $this->method_title = 'Epay';
            $this->method_description = 'Recieve Mobile Money payments in your shop.';
 
            $this->supports = array(
                'products'
            );
 
            // Method with all the options fields
            $this->init_form_fields();
 
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->merchant_key =$this->get_option('merchant_key');
            $this->app_id =$this->get_option('app_id');
            $this->app_secret =$this->get_option('app_secret');
 
            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
 
            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
        }
 
        /**
         * Plugin options
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Epay Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Mobile Money (MTN, Vodafone, AirtelTigo)',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your mobile money via Epay payment gateway.',
                ),
                'merchant_key' => array(
                    'title'       => 'Account Merchant Key',
                    'type'        => 'text',
                    'description' => 'This is your Merchant Key as displayed under Account Settings on your Epay Dashboard',
                ),
                'app_id' => array(
                    'title'       => 'Integration App ID',
                    'type'        => 'text',
                    'description' => 'This is your Integration\'s App ID as displayed under Integrations on your Epay Dashboard',
                ),
                'app_secret' => array(
                    'title'       => 'Integration App Secret',
                    'type'        => 'text',
                    'description' => 'This is your Integration\'s App Secret as displayed under Integrations on your Epay Dashboard',
                )
            );
        }
 
        /**
         * Custom Mobile Money Payment Form
         */
        public function payment_fields()
        {
 
            // ok, let's display some description before the payment form
            if ($this->description) {
                // display the description with <p> tags etc.
                echo wpautop(wp_kses_post($this->description));
            }
            
            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
         
            // Add this action hook if you want your custom gateway to support it
            do_action('woocommerce_momo_form_start', $this->id);
         
            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '<div class="form-row form-row-wide"><label>Mobile Wallet Provider <span class="required">*</span></label>
				<select id="epaygh_mobile_wallet_network" name="epaygh_mobile_wallet_network">
					<option name="epaygh_mobile_wallet_network" value="mtn" selected>MTN</option>
					<option name="epaygh_mobile_wallet_network" value="airtel">Airtel</option>
					<option name="epaygh_mobile_wallet_network" value="tigo">Tigo</option>
					<option name="epaygh_mobile_wallet_network" value="vodafone">Vodafone</option>
				</select>
				</div>
				<div class="form-row form-row-wide"><label>Mobile Wallet Number <span class="required">*</span></label>
				<input id="epaygh_mobile_wallet_number" name="epaygh_mobile_wallet_number" type="tel">
				</div>

				<div class="form-row form-row-wide"><label>Voucher </label>
					<input id="epaygh_payment_voucher" name="epaygh_payment_voucher" type="text" autocomplete="off"><br>
					<span class="required">Leave empty if mobile network is not Vodafone</span>
				</div>

				<div class="clear"></div>';
         
            do_action('woocommerce_momo_form_end', $this->id);
         
            echo '<div class="clear"></div></fieldset>';
        }
 
        /*
         * Fields validation
         */
        public function validate_fields()
        {
            if (empty($_POST[ 'epaygh_mobile_wallet_network' ])) {
                wc_add_notice('Mobile Wallet Network Provider is required!', 'error');
                return false;
            }
            
            if (empty($_POST[ 'epaygh_mobile_wallet_number' ])) {
                wc_add_notice('Mobile Wallet Number is required!', 'error');
                return false;
            }
            
            if ($_POST[ 'epaygh_mobile_wallet_network' ] == 'vodafone' && empty($_POST[ 'epaygh_payment_voucher' ])) {
                wc_add_notice('A Voucher is required for Vodafone payments!', 'error');
                return false;
            }
            
            if (empty($_POST[ 'billing_first_name' ])) {
                wc_add_notice('First name is required!', 'error');
                return false;
            }

            if (empty($_POST[ 'billing_last_name' ])) {
                wc_add_notice('Last name is required!', 'error');
                return false;
            }

            return true;
        }
 
        /*
         * We're processing the payments here
         */
        public function process_payment($order_id)
        {
            global $woocommerce;
 
            // we need it to get any order detailes
            $order = wc_get_order($order_id);
            
            /**
             * Generate a random string, using a cryptographically secure
             * pseudorandom number generator (random_int)
             *
             * For PHP 7, random_int is a PHP core function
             * For PHP 5.x, depends on https://github.com/paragonie/random_compat
             *
             * @param int $length      How many characters do we want?
             * @param string $keyspace A string of all possible characters
             *                         to select from
             * @return string
             */
            function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
            {
                $pieces = [];
                $max = mb_strlen($keyspace, '8bit') - 1;
                for ($i = 0; $i < $length; ++$i) {
                    $pieces []= $keyspace[random_int(0, $max)];
                }
                return implode('', $pieces);
            }

            /*
            * Array with parameters for API interaction
            */
            $auth_api_args = array(
                "merchant_key" => $this->merchant_key,
                "app_id" => $this->app_id,
                "app_secret" => $this->app_secret
            );
            
            $auth_request_body = array(
                'body' => json_encode($auth_api_args),
                'headers' => array(
                    'Content-type' => '	application/json'
                )
            );
            
            /*
            * Generate Access Token
            */
            $response = wp_remote_post('https://epaygh.com/api/v1/token', $auth_request_body);
            
            if (!is_wp_error($response)) {
                $body = json_decode($response['body'], true);
        
                if (!$body['success']) {
                    wc_add_notice('Oops! Failed to generate Access Token. Please try again.', 'error');
                    return;
                }
                
                $access_token = $body['data']['access_token'];

                $charge_api_args = array(
                    "reference" => random_str(12),
                    "amount" => (float) $order->get_total(),
                    "payment_method" => "momo",
                    "customer_name"=> $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
                    "customer_email" => $order->get_billing_email(),
                    "customer_telephone" => $order->get_billing_phone(),
                    "mobile_wallet_number" => $_POST['epaygh_mobile_wallet_number'],
                    "mobile_wallet_network" => $_POST['epaygh_mobile_wallet_network'],
                    "voucher"=> $_POST['epaygh_payment_voucher'],
                    "payment_description" =>  'Purchase with Order ID of '.$order->get_order_number()
                );
               
                $charge_request_body = array(
                    'body' => json_encode($charge_api_args),
                    'headers' => array(
                        'Content-type' => '	application/json',
                        'Authorization' => 'Bearer '. $access_token
                    )
                );
     
                /*
                * Charge Customer
                */
                $charge_api_response = wp_remote_post('https://epaygh.com/api/v1/charge', $charge_request_body);
            
                if (is_wp_error($charge_api_response)) {
                    wc_add_notice('Connection Lost whilst trying to charge customer.', 'error');
                    return;
                }

            
                $charge_api_body = json_decode($charge_api_response['body'], true);


                if (!$charge_api_body['success']) {
                    wc_add_notice($charge_api_body['message'], 'error');
                    return;
                }
                
                wc_add_notice('A Payment Request has been sent to your Mobile Wallet, Kindly approve Payment.', 'success');
                return;
            } else {
                wc_add_notice('Connection error.', 'error');
                return;
            }
        }
 
        /*
         * In case you need a webhook
         */
        public function webhook()
        {
 
             // we received the payment
                //$order->payment_complete();
                //$order->reduce_order_stock();
        
                // some notes to customer (replace true with false to make it private)
                //$order->add_order_note('Hey, your order is paid! Thank you!', true);
        
                // Empty cart
                //$woocommerce->cart->empty_cart();
        }
    }
}
