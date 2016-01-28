<?php
namespace Drupal\smfbridge\Smf;

class Settings {

  use TConnection;

  public function __construct() {
    $this->setConnection();
  }

  public function installIntegration($forumDirectoryPath) {
    try {
      //Using SMF native functions
      $smfSSI = DRUPAL_ROOT . '/' . $forumDirectoryPath . '/SSI.php';
      if (file_exists($smfSSI)) {
        require_once($smfSSI);
        add_integration_function('integrate_pre_include', DRUPAL_ROOT . '/' . drupal_get_path('module', 'smfbridge') . '/src/Smf/Hooks.php');
        add_integration_function('integrate_actions', 'Drupal\smfbridge\Smf\Hooks::actions', TRUE);
        add_integration_function('integrate_menu_buttons', 'Drupal\smfbridge\Smf\Hooks::smfMenuButtons', TRUE);
        //Use database driven sessions - on
        $this->updateSmfSettingsValues('databaseSession_enable', '1');
        /**
         * @var \Drupal\Core\Session\SessionConfiguration $sessionConfiguration
         */
        $sessionConfiguration = \Drupal::service('session_configuration');
        $options = $sessionConfiguration->getOptions(\Drupal::request());
        $this->updateSmfSettingsValues('databaseSession_lifetime', $options['gc_maxlifetime']);
      }
      else {
        throw new \Exception(t('Can\'t forum/SSI.php. Please, place SMF sources into DRUPAL_ROOT/forum folder.'));
      }
      return TRUE;
    } catch (\Exception $e) {
      $errorMsg = t(
        'Error while enabling smfbridge. @message',
        ['@message' => $e->getMessage()]
      );
      \Drupal::logger('smfbridge')->error($errorMsg);
      drupal_set_message($errorMsg, 'error');
      return FALSE;
    }
  }

  public function removeIntegration($forumDirectoryPath) {
    try {
      $smfSSI = DRUPAL_ROOT . '/' . $forumDirectoryPath . '/SSI.php';
      if (file_exists($smfSSI)) {
        require_once($smfSSI);
        remove_integration_function('integrate_pre_include', DRUPAL_ROOT . '/' . drupal_get_path('module', 'smfbridge') . '/src/Smf/Hooks.php');
        remove_integration_function('integrate_actions', 'Drupal\smfbridge\Smf\Hooks::actions');
        remove_integration_function('integrate_menu_buttons', 'Drupal\smfbridge\Smf\Hooks::smfMenuButtons');

        //Using SMF native functions
        global $smcFunc;
        $smcFunc['db_query'](
          '',
          'DELETE FROM {db_prefix}settings WHERE variable = {string:variable}',
          ['variable' => 'drupalHomeLinkButton']
        );
      }
      else {
        throw new \Exception(t('Can\'t find forum/SSI.php. Please, place SMF sources into DRUPAL_ROOT/forum folder.'));
      }
    } catch (\Exception $e) {
      $errorMsg = t(
        'Error while disabling smfbridge. @message',
        ['@message' => $e->getMessage()]
      );
      \Drupal::logger('smfbridge')->error($errorMsg);
      drupal_set_message($errorMsg, 'error');
    }
  }

  public function saveSmfHomeLink($linkTitle, $linkUrl = '/') {
    if (!empty($linkTitle) && !empty($linkUrl)) {
      /*
       * Example button syntax
       * $button = [
       *  'title' => 'Home',
       *  'href' => '/',
       *  'show' =>TRUE,
       *  'sub_buttons' => [],
       *  'is_last' => FALSE,
       * ];
       */
      $drupalHomeLinkButton = [
        'title' => $linkTitle,
        'href' => $linkUrl,
        'show' => TRUE,
        'sub_buttons' => [],
        'is_last' => FALSE,
      ];
      $this->smfConnection
        ->merge('settings')
        ->key(array('variable' => 'drupalHomeLinkButton'))
        ->fields(array(
          'value' => serialize($drupalHomeLinkButton),
        ))
        ->execute();
    }
  }

  protected function updateSmfSettingsValues($settingName, $settingsValue = '') {
    //Use closure
    global $smcFunc;
    if (
      !empty($settingName)
      && !empty($smcFunc['db_query'])
      && is_callable($smcFunc['db_query'])
    ) {
      $smcFunc['db_query'](
        '',
        'UPDATE {db_prefix}settings SET value={string:val}  WHERE variable={string:col}',
        [
          'val' => $settingsValue,
          'col' => $settingName
        ]
      );
    }
  }
}