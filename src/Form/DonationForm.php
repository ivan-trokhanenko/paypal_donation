<?php

namespace Drupal\paypal_donation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\paypal_donation\DonationType;

/**
 * Class DonationForm.
 *
 * @package Drupal\paypal_donation\Form
 */
class DonationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paypal_donation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $baseUrl = $this->getRequest()->getSchemeAndHttpHost();
    $config = $this->config('paypal_donation.settings');
    
    // Hidden values.
    $form['lc'] = [
      '#type' => 'hidden',
      '#default_value' => $config->get('locale_code'),
    ];
    if (!empty($config->get('return_path'))) {
      $form['return'] = [
        '#type' => 'hidden',
        '#default_value' => $baseUrl . $config->get('return_path'),
      ];
    }
    if (!empty($config->get('cancel_path'))) {
      $form['cancel_return'] = [
        '#type' => 'hidden',
        '#default_value' => $baseUrl . $config->get('cancel_path'),
      ];
    }
    $form['custom'] = [
      '#type' => 'hidden',
      '#default_value' => $config->get('variable'),
    ];
    $form['business'] = [
      '#type' => 'hidden',
      '#default_value' => $config->get('receiver'),
    ];
    $form['currency_code'] = [
      '#type' => 'hidden',
      '#default_value' => $config->get('currency_code'),
    ];
    $form['amount'] = [
      '#type' => 'hidden',
    ];
    if ($config->get('ipn.enabled') !== FALSE) {
      $ipnPath = $config->get('ipn.path');
      $notifyUrl = !empty($ipnPath) ? $baseUrl . $config->get('ipn.path') : Url::fromRoute('paypal_donation.ipn', [], ['absolute' => TRUE])->toString();
      $form['notify_url'] = [
        '#type' => 'hidden',
        '#default_value' => $notifyUrl,
      ];
    }

    // Generate amount options.
    $amounts = array_filter(explode(',', str_replace(' ', '', $config->get('options'))));
    $custom = $config->get('custom');
    if (!empty($amounts)) {
      $options = [];
      foreach ($amounts as $amount) {
        $options[$amount] = explode( '.', $amount )[0];
      }

      if ($custom) {
        $options['other'] = $config->get('custom_label') ?: $this->t('Custom amount');
      }

      $form['donate_amount'] = [
        '#title' => $config->get('amount_label') ?: $this->t('Select an amount'),
        '#type' => $config->get('options_style'),
        '#options' => $options,
        '#required' => TRUE,
        '#attributes' => [
          'class' => [
            // Add classes in favor of JS.
            'donation-amount-choice',
          ],
        ],
      ];
    }

    $form['custom_amount'] = [
      '#field_prefix' => $config->get('currency_sign'),
      '#type' => 'number',
      '#step' => 0.01,
      '#min' => $config->get('custom_min') ?: 0.01,
      '#max' => $config->get('custom_max') ?: NULL,
      '#states' => [
        'visible' => [
          ':input[name="donate_amount"]' => ['value' => 'other'],
        ],
        'required' => [
          ':input[name="donate_amount"]' => ['value' => 'other'],
        ],
      ],
      // '#required' => TRUE,
      '#attributes' => [
        'class' => [
          // Add classes in favor of JS.
          'donation-custom-amount',
        ],
      ],
    ];

    if ($config->get('description')) {
      $form['help'] = [
        '#type' => 'item',
        '#markup' => $config->get('description'),
      ];
    }

    if ($config->get('recurring.enabled')) {
      $form['donation_type'] = [
        '#type' => 'checkbox',
        '#title' => $config->get('recurring.label'),
        '#default_value' => 0,
        '#submit' => ['::submitDonationType'],
        '#executes_submit_callback' => TRUE,
        '#ajax' => [
          'callback' => '::ajaxUpdateForm',
          'wrapper' => 'recurring-donation',
          'event' => 'change',
          'method' => 'replace',
        ],
      ];
    }

    $form['recurring_container'] = [
      '#type' => 'container',
      '#prefix' => '<div id="recurring-donation">',
      '#suffix' => '</div>',
    ];

    if ($form_state->get('donation_type') == 'recurring') {
      $form['recurring_container']['cmd'] = [
        '#type' => 'hidden',
        '#default_value' => '_xclick-subscriptions',
      ];
      $form['recurring_container']['no_note'] = [
        '#type' => 'hidden',
        '#default_value' => 1,
      ];
      // Set subscriptions to recur.
      $form['recurring_container']['src'] = [
        '#type' => 'hidden',
        '#default_value' => 1,
      ];
      // Regular subscription price.
      $form['recurring_container']['a3'] = [
        '#type' => 'hidden',
        '#default_value' => $this->getAmount($form_state),
      ];
      // Subscription duration.
      $form['recurring_container']['p3'] = [
        '#type' => 'hidden',
        '#default_value' => $config->get('recurring.duration'),
      ];
      // Regular subscription units of duration.
      $form['recurring_container']['t3'] = [
        '#type' => 'hidden',
        '#default_value' => $config->get('recurring.unit'),
      ];
    }
    else {
      $form['recurring_container']['no_note'] = [
        '#type' => 'hidden',
        '#default_value' => 0,
      ];
      $form['recurring_container']['cmd'] = [
        '#type' => 'hidden',
        '#default_value' => '_donations',
      ];
    }


    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $config->get('button'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="donate_amount"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $mode = $config->get('mode') === 'live' ? '' : 'sandbox.';
    $url = 'https://www.' . $mode . 'paypal.com/cgi-bin/webscr';
    $form['#action'] = Url::fromUri($url, ['external' => TRUE])->toString();

    $form['#attached'] = [
      'library' => [
        'paypal_donation/paypal_donation',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This submit handler is not used.
  }

  /**
   * Helper update ajax callback.
   */
  public function ajaxUpdateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('donation_type') == 1) {
      $form['recurring_container']['cmd']['#value'] = '_xclick-subscriptions';
      $form['recurring_container']['no_note']['#value'] = 1;
    }
    else {
      $form['recurring_container']['cmd']['#value'] = '_donations';
      $form['recurring_container']['no_note']['#value'] = 0;
    }

    return $form['recurring_container'];
  }

  /**
   * Donation type submit callback.
   */
  public function submitDonationType(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('donation_type') == 1) {
      $form_state->set('donation_type', 'recurring');
    }
    else {
      $form_state->set('donation_type', 'single');
    }
    $form_state->setRebuild();
  }

  /**
   * Get amount field value.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed|string
   */
  public function getAmount(FormStateInterface $form_state) {
    $amount = $form_state->getValue('amount');
    if ($amount == 'other') {
      $amount = $form_state->getValue('custom_amount');
      if ($amount) {
        $amount = $amount . '.00';
      }
    }
    return $amount;
  }

}
