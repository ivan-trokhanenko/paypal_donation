(function ($) {
  // TODO. Use Drupal.behaviors.
  $(document).ready(function() {

    var $context = $('.block-paypal-donation');

    $('input.donation-amount-choice, select.donation-amount-choice, input.donation-custom-amount', $context).on('change', function() {
      var $parentForm = $(this).closest('form');
      var selectedVal;

      if ($(this).hasClass('donation-custom-amount')){
        selectedVal = $('input[name="custom_amount"]', $parentForm).val();
      }
      else if ($(this).is('select')) {
        selectedVal = $('select[name="donate_amount"]', $parentForm).val();
      }
      else if ($(this).is('input')) {
        selectedVal = $('input[name="donate_amount"]:checked', $parentForm).val();
      }

      if (selectedVal === 'other') {
        selectedVal = $('input[name="custom_amount"]', $parentForm).val();
      }
      $('input[name="amount"]', $parentForm).val(selectedVal);
      $('input[name="a3"]', $parentForm).val(selectedVal);
    });

  })
})(jQuery);
