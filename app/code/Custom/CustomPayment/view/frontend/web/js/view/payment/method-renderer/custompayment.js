define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/model/payment/additional-validators',
    'mage/url',
    'jquery',
    'https://js.stripe.com/v3/' // Carga el SDK de Stripe directamente
], function (
    Component,
    quote,
    placeOrderAction,
    additionalValidators,
    urlBuilder,
    $
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Custom_CustomPayment/payment/customtemplate'
        },

        initialize: function () {
            this._super();
			this.additionalData = {};

            this.stripePublicKey = window.checkoutConfig.payment.custompayment.publicKey;
            this.stripe = Stripe(this.stripePublicKey);
            this.elements = this.stripe.elements();

            let style = {
                base: {
                    color: '#32325d',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    fontSize: '16px',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            };

            this.card = this.elements.create('card', { style: style });
            setTimeout(() => {
                this.card.mount('#custom-stripe-card-element');
            }, 500);

            return this;
        },

        placeOrder: function (data, event) {
            event.preventDefault();

            if (!additionalValidators.validate()) {
                return false;
            }

            return this.stripe.createToken(this.card).then((result) => {
                if (result.error) {
                    alert(result.error.message);
                    return false;
                }

                this.messageContainer.clear();

                this.selectPaymentMethod();
                this.additionalData = {
                    source: result.token.id
                };
				console.log('Result:',result);
				console.log('Aditional data:',this.additionalData);
				console.log('getData:', this.getData());
                 return placeOrderAction(this.getData(), this.messageContainer).done(() => {
					window.location.replace(urlBuilder.build('checkout/onepage/success'));
				});
            });
        },

        getData: function () {
            return {
                method: this.item.method,
                additional_data: this.additionalData
            };
        }
    });
});
