<?php

namespace Drupal\mailgun;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Mailgun\Mailgun;

/**
 * Mail handler to send out an email message array to the Mailgun API
 */
class MailHandler {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new \Drupal\mailgun\MailHandler object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * Connects to Mailgun API and sends out the email. 
   * 
   * @see https://documentation.mailgun.com/en/latest/api-sending.html#sending
   * 
   * @param array $mailgun_message
   *   A message array, as described in https://documentation.mailgun.com/en/latest/api-sending.html#sending
   *
   * @return bool
   *   TRUE if the mail was successfully accepted by the API, FALSE otherwise.
   */
  public function sendMail(array $mailgun_message) {
    try {
      $settings = $this->configFactory->get('mailgun.settings');
      $api_key = $settings->get('api_key');
      $working_domain = $settings->get('working_domain');

      if (empty($api_key) || empty($working_domain)) {
        $this->logger->error('Failed to send message from %from to %to. Please check the Mailgun settings.',
          [
            '%from' => $mailgun_message['from'],
            '%to' => $mailgun_message['to'],
          ]
        );

        return FALSE;
      }

      $mailgun = Mailgun::create($api_key);

      $response = $mailgun->messages()->send(
        $working_domain, 
        $mailgun_message
      );

      // Debug mode: log all messages.
      if ($settings->get('debug_mode')) {
        $this->logger->notice('Successfully sent message from %from to %to. %id %message.',
          [
            '%from' => $mailgun_message['from'],
            '%to' => $mailgun_message['to'],
            '%id' => $response->getId(),
            '%message' => $response->getMessage()
          ]
        );
      }

      return TRUE;

    } catch (\Mailgun\Exception $e) {
      $this->logger->error('Exception occurred while trying to send test email from %from to %to. @code: @message.',
        [
          '%from' => $mailgun_message['from'],
          '%to' => $mailgun_message['to'],
          '@code' => $e->getCode(),
          '@message' => $e->getMessage()
        ]
      );

      return FALSE;
    }
  }  
}