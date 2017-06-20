<?php

namespace Drupal\mailgun\Plugin\Mail;

use Drupal\Core\Mail\MailInterface;
use Drupal\Component\Utility\Html;
use Mailgun\Mailgun;

/**
 * Modify the Drupal mail system to use Mandrill when sending emails.
 *
 * @Mail(
 *   id = "mailgun_mail",
 *   label = @Translation("Mailgun mailer"),
 *   description = @Translation("Sends the message using Mailgun.")
 * )
 */
class MailgunMail implements MailInterface {

  /**
   * Configuration object
   * 
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $drupalConfig = null;

  /** @var LoggerInterface logger */
  private $logger = null;

  public function __construct() {
    $this->drupalConfig = \Drupal::config('mailgun.adminsettings');
    $this->logger = \Drupal::logger('mailgun');
  }

  /**
   * Concatenate and wrap the e-mail body for either plain-text or HTML e-mails.
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter().
   *
   * @return array
   *   The formatted $message.
   */
  public function format(array $message) {
    // Join the body array into one string.
    if (is_array($message['body'])) {
      $message['body'] = implode("\n\n", $message['body']);
    }

    // todo fix this after adding configuration page
//    // If a text format is specified in Mailgun settings, run the message through it.
//    $format = variable_get('mailgun_format', '_none');
//    if ($format != '_none') {
//      $message['body'] = check_markup($message['body'], $format);
//    }

    return $message;
  }

  /**
   * Send the e-mail message.
   *
   * @see drupal_mail()
   * @see https://documentation.mailgun.com/api-sending.html#sending
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter(). $message['params'] may contain additional parameters. See mailgun_send().
   *
   * @return bool
   *   TRUE if the mail was successfully accepted or queued, FALSE otherwise.
   */
  public function mail(array $message) {
    // Build the Mailgun message array.
    $mailgun_message = [
      'from' => $message['from'],
      'to' => $message['to'],
      'subject' => $message['subject'],
      'text' => Html::escape($message['body']),
      'html' => $message['body'],
    ];

    // Add the CC and BCC fields if not empty.
    if (!empty($message['params']['cc'])) {
      $mailgun_message['cc'] = $message['params']['cc'];
    }
    if (!empty($message['params']['bcc'])) {
      $mailgun_message['bcc'] = $message['params']['bcc'];
    }

    $params = [];

    // todo fix the following with configuration
//    // Populate default settings.
//    $variable = variable_get('mailgun_tracking', 'default')
//    if ($variable != 'default') {
//      $params['o:tracking'] = $variable;
//    }
//    $variable = variable_get('mailgun_tracking_clicks', 'default')
//    if ($variable != 'default') {
//      $params['o:tracking-clicks'] = $variable;
//    }
//    $variable = variable_get('mailgun_tracking_opens', 'default')
//    if ($variable != 'default') {
//      $params['o:tracking-opens'] = $variable;
//    }

    // For a full list of allowed parameters, see: https://documentation.mailgun.com/api-sending.html#sending.
    $allowed_params = [
      'o:tag',
      'o:campaign',
      'o:deliverytime',
      'o:dkim',
      'o:testmode',
      'o:tracking',
      'o:tracking-clicks',
      'o:tracking-opens'
    ];

    foreach ($message['params'] as $key => $value) {
      // Check if it's one of the known parameters.
      $allowed = (in_array($key, $allowed_params)) ? TRUE : FALSE;
      // If more options become available but are not yet supported by the module, uncomment the following line.
      //$allowed = (substr($key, 0, 2) == 'o:') ? TRUE : FALSE;
      if ($allowed) {
        $mailgun_message[$key] = $value;
      }
      // Check for custom MIME headers or custom JSON data.
      if (substr($key, 0, 2) == 'h:' || substr($key, 0, 2) == 'v:') {
        $mailgun_message[$key] = $value;
      }
    }

    // Make sure the files provided in the attachments array exist.
    if (!empty($message['params']['attachments'])) {
      $params['attachments'] = [];
      foreach ($message['params']['attachments'] as $attachment) {
        if (file_exists($attachment)) {
          $params['attachments'][] = $attachment;
        }
      }
    }

    $mailgun_message['params'] = $params;

    // todo enable queueing of message
//    // Queue the message if the setting is enabled.
//    if (variable_get('mailgun_queue', FALSE)) {
//      $queue = DrupalQueue::get('mailgun_queue', TRUE);
//      $queue->createItem($mailgun_message);
//      return TRUE;
//    }

    
    //$this->debugMode = $this->config->get('debug_mode');


    //$mailgun = new DrupalMailgun();

    $this->sendMail($mailgun_message);
    
    return FALSE;
  }

  private function sendMail($mailgun_message) {
    try {
      $mailgun = Mailgun::create($this->drupalConfig->get('api_key'));

      $response = $mg->messages()->send(
        $this->drupalConfig->get('working_domain'), 
        $mailgun_message
      );



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
