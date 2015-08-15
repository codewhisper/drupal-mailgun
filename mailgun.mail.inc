<?php

/**
 * @file
 * Implements Mailgun as a Drupal MailSystemInterface
 */

/**
 * Modify the drupal mail system to use Mandrill when sending emails.
 */
class MailgunMailSystem implements MailSystemInterface {

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
    $format = variable_get('mailgun_format', '_none');
    if ($format != '_none') {
      $message['body'] = check_markup($message['body'], $format);
    }

    return $message;
  }

  /**
   * Send the e-mail message.
   *
   * @see drupal_mail()
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter().
   *
   * @return bool
   *   TRUE if the mail was successfully accepted, FALSE otherwise.
   */
  public function mail(array $message) {
/*    // Apply input format to body.
    $format = variable_get('mailgun_filter_format', '');
    if (!empty($format)) {
      $message['body'] = check_markup($message['body'], $format);
    }*/

    // Queue the message if the setting is enabled.
    if (variable_get('mailgun_queue', FALSE)) {
      $queue = DrupalQueue::get('mailgun_queue', TRUE);
      $queue->createItem($message);
      return TRUE;
    }

    return mailgun_send($message);
  }

}
