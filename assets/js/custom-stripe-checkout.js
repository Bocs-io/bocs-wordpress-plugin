jQuery(document).ready(function ($) {

    setTimeout(function () {

        if ($("div.wc-block-components-payment-methods__save-card-info input[type='checkbox']").length > 0) {
            $("div.wc-block-components-payment-methods__save-card-info input[type='checkbox']").prop('checked', true);
        }
    }, 5000);

    setTimeout(function () {
        if ($("input[name='radio-control-wc-payment-method-saved-tokens']").length == 0) {
            $("div.wc-block-components-payment-methods__save-card-info input[type='checkbox']").prop('checked', true);
            $("div.wc-block-components-payment-methods__save-card-info input[type='checkbox']").prop('disabled', true);
        } else {
            $("div.wc-block-components-payment-methods__save-card-info input[type='checkbox']").prop('disabled', false);
        }
    }, 5000);

});