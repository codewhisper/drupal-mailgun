<?php

namespace Drupal\mailgun;

use Mailgun\Mailgun;
use Mailgun\Connection\RestClient;
use Psr\Log\LoggerInterface;

/**
 * Overrides default Mailgun library to provide for API customization.
 */
class DrupalMailgun extends Mailgun {

  /**
   * @var string
   */
  protected $apiKey;

  /**
   * @var string
   */
  private $apiEndpoint;

  /**
   * @var string
   */
  private $apiVersion;

  /**
   * @var bool
   */
  private $ssl;

  /**
   * @var bool
   */
  private $debugMode;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var \Mailgun\Connection\RestClient
   */
  protected $restClient;
  
  private $config;

  
  public function __construct()
  {
    $this->config = \Drupal::config('mailgun.adminsettings');

    $this->apiKey = $this->config->get('api_key');
    $this->apiEndpoint = $this->config->get('api_endpoint');
    
    // todo Do we need apiVersion and ssl to be user editable?
    $this->apiVersion = 'v2';
    $this->ssl = true;
    
    $this->workingDomain = $this->config->get('working_domain');
    $this->debugMode = $this->config->get('debug_mode');


    /** @var LoggerInterface logger */
    $this->logger = \Drupal::logger('mailgun');

    $this->restClient = new RestClient($this->apiKey, $this->apiEndpoint, $this->apiVersion, $this->ssl);
  }

  /**
   * Send an e-mail using the Mailgun API.
   *
   * @param array $mailgun_message
   *   A Mailgun message array. Contains the following keys:
   *   - from: The e-mail addressthe message will be sent from.
   *   - to: The e-mail addressthe message will be sent to.
   *   - subject: The subject of the message.
   *   - text: The plain-text version of the message. Processed using check_plain().
   *   - html: The original message content. May contain HTML tags.
   *   - cc: One or more carbon copy recipients. If multiple, separate with commas.
   *   - bcc: One or more blind carbon copy recipients. If multiple, separate with commas.
   *   - o:tag: An array containing the tags to add to the message. See: https://documentation.mailgun.com/user_manual.html#tagging.
   *   - o:campaign: The campaign ID this message belongs to. See: https://documentation.mailgun.com/user_manual.html#um-campaign-analytics.
   *   - o:deliverytime: Desired time of delivery. Messages can be scheduled for a maximum of 3 days in the future. See: https://documentation.mailgun.com/api-intro.html#date-format.
   *   - o:dkim: Boolean indicating whether or not to enable DKIM signatures on per-message basis.
   *   - o:testmode: Boolean indicating whether or not to enable test mode. See: https://documentation.mailgun.com/user_manual.html#manual-testmode.
   *   - o:tracking: Boolean indicating whether or not to toggle tracking on a per-message basis. See: https://documentation.mailgun.com/user_manual.html#tracking-messages.
   *   - o:tracking-clicks: Boolean or string "htmlonly" indicating whether or not to toggle clicks tracking on a per-message basis. Has higher priority than domain-level setting.
   *   - o:tracking-opens: Boolean indicating whether or not to toggle clicks tracking on a per-message basis. Has higher priority than domain-level setting.
   *   - h:X-My-Header: h: prefix followed by an arbitrary value allows to append a custom MIME header to the message (X-My-Header in this case). For example, h:Reply-To to specify Reply-To address.
   *   - v:my-var: v: prefix followed by an arbitrary name allows to attach a custom JSON data to the message. See: https://documentation.mailgun.com/user_manual.html#manual-customdata.
   *
   * @return bool
   *   TRUE if the mail was successfully accepted, FALSE otherwise.
   */
  function send($mailgun_message) {

    try {
      if (!empty($mailgun_message['attachments'])) {
        // Send message with attachments.
        $attachments = $mailgun_message['attachments'];
        unset($mailgun_message['attachments']);
        $result = $this->sendMessage($this->workingDomain, $mailgun_message, ['attachment' => $attachments]);
      }
      else {
        // Just good old mail with no attachment.
        $result = $this->sendMessage($this->workingDomain, $mailgun_message);
      }

      // For a list of HTTP response codes, see: https://documentation.mailgun.com/api-intro.html#errors.
      if ($result->http_response_code == 200) {

        if ($this->debugMode) {
          // Debug mode: log all messages.
          $this->logger->notice('Successfully sent message from %from to %to. %code: %id %message.',
            [
              '%from' => $mailgun_message['from'],
              '%to' => $mailgun_message['to'],
              '%code' => $result->http_response_code,
              '%id' => $result->http_response_body->id,
              '%message' => $result->http_response_body->message
            ]
          );
        }
        return TRUE;
      }
      else {
        $this->logger->error('Failed to send message from %from to %to. %code: %message.',
          [
            '%from' => $mailgun_message['from'],
            '%to' => $mailgun_message['to'],
            '%code' => $result->http_response_code,
            '%message' => $result->http_response_body->message
          ]
        );
        return FALSE;
      }
    } catch (\Exception $e) {
      $this->logger->error('Exception occurred while trying to send test email from %from to %to. @code: @message.',
        [
          '%from' => $mailgun_message['from'],
          '%to' => $mailgun_message['to'],
          '@code' => $e->getCode(),
          '@message' => $e->getMessage()
        ]
      );
    }
  }
}
