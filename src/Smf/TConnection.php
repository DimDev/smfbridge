<?php
namespace Drupal\smfbridge\Smf;

use Drupal\Core\Database\Database;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait TConnection {
  /**
   * @var \Drupal\Core\Database\Connection $this->smfConnection
   */
  protected $smfConnection;

  protected function setConnection() {
    try {
      /**
       * @var \Drupal\Core\Config\ImmutableConfig $config
       */
      $config = \Drupal::config('smfbridge.settings');
      Database::addConnectionInfo('smfbridge', 'smfbridge', $config->get());
      /**
       * @var \Drupal\Core\Database\Connection $this->smfConnection
       */
      $this->smfConnection = Database::getConnection('smfbridge', 'smfbridge');
    } catch (\Exception $e) {
      \Drupal::logger('smfbridge', $e->getMessage());
      throw new NotFoundHttpException();
    }
  }

}