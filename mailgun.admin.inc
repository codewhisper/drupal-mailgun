<?php

/**
 * @file
 * Administration page callbacks for Mailgun.
 */

/**
 * Menu callback: displays the Mailgun module settings page.
 *
 * @param array $form
 *   Form render array.
 * @param array $form_state
 *   Array containing form state values.
 *
 * @return array
 *   An array containing form items to place on the module settings page.
 */
function mailgun_admin_settings($form, &$form_state) {
  $library = libraries_detect('mailgun');

  if (!$library['installed']) {
    drupal_set_message(t('The Mailgun PHP library is not installed. Please see <a href="@url">documentation</a> for more information.', array('@url' => url('https://www.drupal.org/node/2547591'))), 'error');
  }

  $key = variable_get('mailgun_api_key', '');

  $form['mailgun_api_key'] = array(
    '#title' => t('Mailgun API key'),
    '#type' => 'textfield',
    '#description' => t('Get your Secret API key from the <a href="@url">Mailgun dashboard</a>.', array('@url' => url('https://mailgun.com/app/dashboard'))),
    '#default_value' => $key,
  );

  if ($key && $library['installed']) {
  	$form['mailgun_queue'] = array(
  	  '#title' => t('Queue e-mails'),
  	  '#type' => 'checkbox',
  	  '#description' => t('Mails will be queued and sent during cron runs. Useful for sending a large number of e-mails.'),
  	  '#default_value' => variable_get('mailgun_queue', FALSE),
  	);
  }

  $form['mailgun_log'] = array(
    '#title' => t('Log e-mails'),
    '#type' => 'checkbox',
    '#description' => t('Log e-mails sent through Mailgun. Should not be enabled on production sites. Messages fail to send will be logged regardless of this setting.'),
    '#default_value' => variable_get('mailgun_log', FALSE),
  );

  $options = array('_none' => t('- None -'));
  $formats = filter_formats();
  foreach ($formats as $format) {
    $options[$format->format] = t($format->name);
  }
  $form['mailgun_format'] = array(
    '#title' => t('Text format'),
    '#type' => 'select',
    '#description' => t('Specify an additional text format to filter the message through before sending the e-mail.'),
    '#options' => $options,
    '#default_value' => variable_get('mailgun_format', '_none'),
  );

  $form = system_settings_form($form);
  $form['#validate'][] = 'mailgun_admin_settings_validate';

  return $form;
}

/**
 * Form validation handler for mailgun_admin_settings().
 *
 * Perform additional validation to ensure the API key entered is valid.
 */
function mailgun_admin_settings_validate($form, &$form_state) {
  $form_state['values']['mailgun_api_key'] = trim($form_state['values']['mailgun_api_key']);
  $client = mailgun_get_client($form_state['values']['mailgun_api_key']);
  try {
    $result = $client->get('domains');
    drupal_set_message(t('Your API key has been successfully validated.'));
  } catch (Exception $e) {
    form_set_error('mailgun_api_key', t('An exception occurred. @code: @message', array('@code' => $e->getCode(), '@message' => $e->getMessage())));
  }
}

/**
 * Form builder. Display a form for sending a test e-mail.
 */
function mailgun_test_form($form, &$form_state) {
  drupal_set_title(t('Send test e-mail'));

  $form['mailgun_test_address'] = array(
    '#type' => 'textfield',
    '#title' => t('Send the test e-mail to'),
    '#default_value' => variable_get('site_mail', ''),
    '#description' => t('Type in an address to have a test e-mail sent there.'),
    '#required' => TRUE,
  );
  $form['mailgun_test_body'] = array(
    '#type' => 'textarea',
    '#title' => t('Body'),
    '#default_value' => t('If you receive this message it means your site is capable of using Mailgun to send e-mails. This URL is here to test HTML support: <a href="@url">visit your site</a>.', array('@url' => url($GLOBALS['base_url']))),
    '#required' => TRUE,
  );

  $form['test_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Send'),
  );
  $form['test_cancel'] = array(
    '#type' => 'link',
    '#href' => 'admin/config/system/mailgun',
    '#title' => t('Cancel'),
  );

  return $form;
}

/**
 * Form submission handler for mailgun_test_form().
 * Send the test e-mail.
 */
function mailgun_test_form_submit($form, &$form_state) {
  $test_address = $form_state['values']['mailgun_test_address'];
  $params = array(
    'body' => $form_state['values']['mailgun_test_body'],
  );
  $result = drupal_mail('mailgun', 'test', $test_address, $GLOBALS['language'], $params);
  drupal_set_message(t('Mailgun test e-mail sent from %from to %to. Check <a href="@url">database log</a> for details.', array('%from' => $result['from'], '%to' => $result['to'], '@url' => url('admin/reports/dblog'))), 'status');
}
