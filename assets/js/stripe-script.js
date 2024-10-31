jQuery(document).ready(function ($) {
    $('body').on('updated_checkout', function(){
        var stripe = Stripe(JsStripeData.pk_key);

        var elements = stripe.elements();
        var cardElement = elements.create('card');
        cardElement.mount('#card-element');

        var cardholderName = document.getElementById('billing_first_name');
        var button = document.getElementById('get-plans-button');
        var stripeForm = document.getElementById('card-element');
        button.addEventListener('click', function(ev) {
            process_wait();
            clear_error();
            ev.preventDefault();
            stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {name: cardholderName.value}
            }).then(function(result) {
                if (result.error) {
                    process_unblock();
                    show_error(result.error.message);
                } else {
                    $.ajax({
                        url : JsStripeData.ajax_url,
                        type : 'POST',
                        data : {
                            action : 'collect_details',
                            payment_method_id : result.paymentMethod.id,
                        },
                        success : function (response) {
                             process_unblock();
                            if(response.success){
                                button.remove();
                                handleInstallmentPlans(response.data);
                            }else{
                                show_error(response.data.error_message);
                            }
                        },
                    });
                }
            });
        });

        const selectPlanForm = document.getElementById('installment-plan-form');
        let availablePlans = [];

        const handleInstallmentPlans = async (response) => {
            // Store the payment intent ID.
            document.getElementById('payment-intent-id').value = response.intent_id;
            availablePlans = response.available_plans;

            // Show available installment options
            availablePlans.forEach((plan, idx) => {
                const newInput = document.getElementById('immediate-plan').cloneNode();
                newInput.setAttribute('value', idx);
                newInput.setAttribute('id', '');
                const label = document.createElement('label');
                label.appendChild(newInput);
                label.appendChild(
                    document.createTextNode(`${plan.count} ${plan.interval}s`),
                );

                selectPlanForm.appendChild(label);
            });

            $('#details').hide();
            $('#plans').show();
        };



        function process_wait(){
            $("form.checkout").addClass("processing").block({
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            });
        }


        function process_unblock(){
            $("form.checkout").removeClass("processing").unblock();
        }

        function show_error(error){
            var alert = '<p id="stripe-alert" style="color: #e2401c">' + error + '</p>';
            stripeForm.insertAdjacentHTML('afterend', alert);
        }

        function clear_error(){
            var alertBox = document.getElementById('stripe-alert');

            if(alertBox){
                alertBox.remove();
            }
        }
    })



})