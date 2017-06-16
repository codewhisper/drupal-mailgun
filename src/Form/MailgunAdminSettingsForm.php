<?php

namespace Drupal\mailgun\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class MailgunAdminSettingsForm.
 *
 * @package Drupal\mailgun\Form
 */
class MailgunAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mailgun.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailgun_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $config = $this->config('mailgun.adminsettings');

    $url = Url::fromUri('https://mailgun.com/app/domains');
    $link = \Drupal::l($this->t('mailgun.com/app/domains'), $url);
    
    $form['description'] =
      [
        '#markup' => "Please refer to $link for your settings."
      ];

    $form['api_key'] = array(
      '#title' => $this->t('Mailgun API Key'),
      '#type' => 'textfield',
      '#description' => $this->t('Enter your API key.'),
      '#default_value' => $config->get('api_key'),
    );

    $form['api_endpoint'] = array(
      '#title' => $this->t('Mailgun API Endpoint'),
      '#type' => 'textfield',
      '#description' => $this->t('Enter your API endpoint.'),
      '#default_value' => $config->get('api_endpoint'),
    );

    $form['working_domain'] = array(
      '#title' => $this->t('Mailgun API Working Domain'),
      '#type' => 'textfield',
      '#description' => $this->t('Enter your API working domain.'),
      '#default_value' => $config->get('working_domain'),
    );

    $form['debug_mode'] = array(
      '#title' => $this->t('Enable Debug Mode'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('debug_mode'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('mailgun.adminsettings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_endpoint', $form_state->getValue('api_endpoint'))
      ->set('working_domain', $form_state->getValue('working_domain'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->save();

    drupal_set_message($this->t('The configuration options have been saved.'));
  }

}
