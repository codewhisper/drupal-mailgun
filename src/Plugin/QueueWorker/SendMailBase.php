<?php 

/**
 * @file
 * Contains Drupal\mailgun\Plugin\QueueWorker\SendMailBase.php
 */

namespace Drupal\mailgun\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Provides base functionality for the SendMail Queue Workers.
 */
class SendMailBase extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $result = \Drupal::service('mailgun.mail_handler')->sendMail($data->message);

    if (\Drupal::config('mailgun.settings')->get('debug_mode')) {
      $logger = \Drupal::logger('mailgun');

      $logger->notice('Successfully sent message on CRON from %from to %to.',
          [
            '%from' => $data->message['from'],
            '%to' => $data->message['to']
          ]
        );
    }

    if (!$result) {
      throw new RequeueException('Mailgun: email did not pass through API.');
    }    
  }
}