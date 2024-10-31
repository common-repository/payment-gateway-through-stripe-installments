<?php

use OnePix\Stripe_Installments;

class WC_Stripe_Installments extends WC_Payment_Gateway {
	public function __construct() {
		$this->id                 = 'stripe_installments';
		$this->method_title       = __( 'Stripe Installments', 'stripe-installments' );
		$this->method_description = __( 'Stripe Installments for Mexico', 'stripe-installments' );
		$this->supports           = [ 'products', 'refunds' ];
		$this->key                = $this->get_option( 'key' );
		$this->logging            = 'yes' === $this->get_option( 'logging' );
		$this->order              = null;

		$this->init_form_fields();
		$this->init_settings();
		$this->sk_key      = $this->settings['sk_key'];
		$this->title       = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'wnd_checkout_code' ], 10 );
	}

	public function init_form_fields() {

		$fields = [
			'enabled'     => [
				'title'       => __( 'Enable/Disable', 'stripe-installments' ),
				'label'       => __( 'Enable Stripe Installments Gateway', 'stripe-installments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'title'       => [
				'title'       => __( 'Title', 'stripe-installments' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'stripe-installments' ),
				'default'     => __( 'Stripe Installments', 'stripe-installments' ),
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => __( 'Description', 'stripe-installments' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'stripe-installments' ),
				'default'     => __( 'Installments payment', 'stripe-installments' ),
			],
			'sk_key'      => [
				'title'       => __( 'Secret key', 'stripe-installments' ),
				'type'        => 'text',
				'description' => '',
				'default'     => ''
			],
			'pk_key'      => [
				'title'       => __( 'Publishable key', 'stripe-installments' ),
				'type'        => 'text',
				'description' => '',
				'default'     => ''
			],
			'logging'     => [
				'title'       => __( 'Enable logging', 'stripe-installments' ),
				'label'       => __( 'Enable/Disable', 'stripe-installments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
		];

		$this->form_fields = $fields;
	}

	/**
	 * If There are no payment fields show the description if set.
	 * Override this in your gateway if you have some.
	 */
	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo esc_html( wpautop( wptexturize( $description ) ) );
		}

		echo '<div id="card-element"></div>
        <button style="margin-top: 10px" id="get-plans-button">' . __( 'Get plans', 'stripe-installments' ) . '</button>';
		echo '<div id="plans" hidden>
          <div id="installment-plan-form" >
            <label><input id="immediate-plan" type="radio" name="installment_plan" value="-1" />' . __( 'Immediate', 'stripe-installments' ) . '</label>
            <input id="payment-intent-id" name="payment-intent-id" type="hidden" />
          </div>
        </div>
        
        <div id="result" hidden>
          <p id="status-message"></p>
        </div>';

		if ( $this->supports( 'default_credit_card_form' ) ) {
			$this->credit_card_form(); // Deprecated, will be removed in a future version.
		}
	}

	public function wnd_checkout_code() {
		if ( isset( $_GET['notice'] ) ) {
			echo '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">
                <ul class="woocommerce-error" role="alert">
                    <li>
                        ' . esc_html( $_GET['notice'] ) . '	
                    </li>
                </ul>
            </div>';
		}
	}

	public function process_payment( $order_id ) {
		if ( isset( $_POST['installment_plan'] ) ) {
			# vendor using composer
			require_once( Stripe_Installments::$plugin_path . 'vendor/autoload.php' );

			\Stripe\Stripe::setApiKey( $this->sk_key );

			$selected_plan = intval( $_POST['installment_plan'] );

			$plans_list = get_transient( 'stripe_avalible_plans' );

			$plan_data    = $plans_list[ $selected_plan ];
			$confirm_data = [
				'payment_method_options' =>
					[
						'card' => [
							'installments' => [
								'plan' => [ //$selected_plan$
								            'count'    => $plan_data->count,
								            'interval' => $plan_data->interval,
								            'type'     => $plan_data->type,
								]
							]
						]
					]
			];

			$intent = \Stripe\PaymentIntent::retrieve(
				sanitize_text_field( $_POST['payment-intent-id'] )
			);

			try {
				$params['description'] = $order_id . ' - ' . sanitize_text_field( $_POST['billing_first_name'] );
				$intent->update( sanitize_text_field( $_POST['payment-intent-id'] ), $params );
				$intent->confirm( $confirm_data );

			} catch ( Exception $e ) {
				wc_add_notice( __( $e->getMessage(), 'stripe-installments' ), 'error' );

				return false;
			}


			if ( $intent->status == 'succeeded' ) {
				$order = wc_get_order( $order_id );
				$order->payment_complete();

				return [ 'result' => 'success', 'redirect' => $order->get_checkout_order_received_url() ];
			} else {
				wc_add_notice( __( 'Error with processing payment', 'stripe-installments' ), 'error' );

				return false;
			}
		} else {
			wc_add_notice( __( 'Please choose the plan', 'stripe-installments' ), 'error' );

			return false;
		}
	}

	public function log( $data, $prefix = '' ) {
		if ( $this->logging ) {
			$context = [ 'source' => $this->id ];
			wc_get_logger()->debug( $prefix . "\n" . print_r( $data, 1 ), $context );
		}
	}
}
