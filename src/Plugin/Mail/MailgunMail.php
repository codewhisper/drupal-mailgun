<?php

namespace Drupal\mailgun\Plugin\Mail;

use Drupal\Core\Mail\MailInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
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
  protected $drupalConfig;

  /** @var LoggerInterface logger */
  protected $logger;

  public function __construct() {
    $this->drupalConfig = \Drupal::config('mailgun.settings');
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
    
    // If a text format is specified in Mailgun settings, run the message through it.
    $format = $this->drupalConfig->get('format_filter');
    
    if (!empty($format)) {
      $message['body'] = check_markup($message['body'], $format, $message['langcode']);
    }

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
      $attachments = [];
      foreach ($message['params']['attachments'] as $attachment) {
        if (file_exists($attachment)) {
          $attachments[] = $attachment;
        }
      }

      if (count($attachments) > 0) {
        $mailgun_message['attachments'] = $attachments;
      }
    }    

    if ($this->checkTracking($message)) {
      $track_opens = $this->drupalConfig->get('tracking_opens');

      if (!empty($track_opens)) {
        $mailgun_message['o:tracking-opens'] = $track_opens;
      }

      $track_clicks = $this->drupalConfig->get('tracking_clicks');

      if (!empty($track_clicks)) {
        $mailgun_message['o:tracking-clicks'] = $track_opens;
      }
    }
    else {
      $mailgun_message['o:tracking'] = 'no';
    }

    if ($this->drupalConfig->get('use_queue')) {
      /** @var QueueFactory $queue_factory */
      $queue_factory = \Drupal::service('queue');
  
      /** @var QueueInterface $queue */
      $queue = $queue_factory->get('mailgun_send_mail');
  
      $item = new \stdClass();
      $item->message = $mailgun_message;
      $queue->createItem($item);

      // Debug mode: log all messages.
      if ($this->drupalConfig->get('debug_mode')) {
        $this->logger->notice('Successfully queued message from %from to %to.',
          [
            '%from' => $mailgun_message['from'],
            '%to' => $mailgun_message['to']
          ]
        );
      }

      return TRUE;
    }

    return \Drupal::service('mailgun.mail_handler')->sendMail($mailgun_message);
  }  

  /**
   * Checks, if the mail key is excempted from tracking
   * 
   * @param array $message
   *  A message array
   * 
   * @return bool
   *  TRUE if the tracking is allowed, otherwise FALSE
   */
  protected function checkTracking(array $message) {
    $tracking = true;
    $tracking_exception = $this->drupalConfig->get('tracking_exception');

    if (!empty($tracking_exception)) {
      $tracking = !in_array($message['module'] . ':' . $message['key'], explode("\n", $tracking_exception));
    }

    return $tracking;
  }
}