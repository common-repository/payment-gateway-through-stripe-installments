<?php
namespace OnePix;
/*
 * Plugin Name: Payment Gateway through Stripe Installments
 * Description: Allow installments payments using stripe for woocommerce
 * Author: OnePix
 * Author URI: https://onepix.net/
 * Version: 1.0.1
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

class Stripe_Installments
{
    private static $instance;
    public static $plugin_url;
    public static $images_url;
    public static $gateway_id;
    public static $plugin_path;
    public static $version;

    private function __construct()
    {
        self::$gateway_id  = 'stripe_installments';
        self::$plugin_url  = plugin_dir_url(__FILE__);
        self::$images_url  = plugin_dir_url(__FILE__).'assets/img/';
        self::$plugin_path = plugin_dir_path(__FILE__);
        self::$version = time();
        // Check if WooCommerce is active
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if(is_plugin_active_for_network('woocommerce/woocommerce.php') or is_plugin_active('woocommerce/woocommerce.php')) {
            add_action('plugins_loaded', [$this, 'pluginsLoaded']);
            add_filter('woocommerce_payment_gateways', [$this, 'woocommercePaymentGateways']);
            add_action('wp_enqueue_scripts', [$this, 'frontend_enqueue_scripts'], 5);
            add_action( 'wp_ajax_nopriv_collect_details', array( $this, 'ajax_collect_details' ) );
            add_action( 'wp_ajax_collect_details', array( $this, 'ajax_collect_details' ) );
        }
    }

    public function ajax_collect_details(){
        $settings = get_option('woocommerce_stripe_installments_settings');

        if(empty($settings['sk_key'])){
            echo 'Secret key is missing';
        }


        try {
            # vendor using composer
            require_once('vendor/autoload.php');

            \Stripe\Stripe::setApiKey($settings['sk_key']);

            if(!empty($_POST['payment_method_id'])){

                $amount = floatval(WC()->cart->total);

                $intent = \Stripe\PaymentIntent::create([
                    'payment_method' => sanitize_text_field($_POST['payment_method_id']),
                    'amount' => $amount * 100, // * 100
                    'currency' => 'mxn',
                    'payment_method_options' => [
                        'card' => [
                            'installments' => [
                                'enabled' => true
                            ]
                        ]
                    ],
                ]);

                if(empty($intent->payment_method_options->card->installments->available_plans)){
                    if($amount < 300){
                        wp_send_json_error([
                            'error_message' => __('Minimal amount is 300 for installments', 'stripe-installments')
                        ]);
                    }else{
                        wp_send_json_error([
                            'error_message' => __('Plans list is empty', 'stripe-installments')
                        ]);
                    }
                }

                set_transient( 'stripe_avalible_plans', $intent->payment_method_options->card->installments->available_plans, DAY_IN_SECONDS );


                wp_send_json_success([
                    'intent_id' => $intent->id,
                    'available_plans' => $intent->payment_method_options->card->installments->available_plans
                ]);

            }
        }
        catch (\Stripe\Exception\CardException $e){
            # "e" contains a message explaining why the request failed

            wp_send_json_error([
                'error_message' => $e->getError()->message
            ]);
        }
        catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            wp_send_json_error([
                'error_message' => $e->getError()->message
            ]);
        }

        die();

    }



    public function frontend_enqueue_scripts()
    {
        if (is_checkout()) {
            $settings = get_option('woocommerce_stripe_installments_settings');
            wp_enqueue_script('stripe-cdn', "https://js.stripe.com/v3/", [], self::$version, true);
            wp_enqueue_script('stripe-script', self::$plugin_url . 'assets/js/stripe-script.js', ['jquery', 'stripe-cdn'], self::$version, true);
            wp_localize_script('stripe-script', 'JsStripeData', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'pk_key' => $settings['pk_key'],
            ]);
        }
    }

    public function pluginsLoaded()
    {
        require_once 'includes/class-wc-stripe-installments-gateway.php';
    }

    public function woocommercePaymentGateways($gateways)
    {
        $gateways[] = 'WC_Stripe_Installments';
        return $gateways;
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

Stripe_Installments::getInstance();
