<?php
/**
 * @file
 * Contains \Drupal\smfbridge\Form\SmfbridgeSettingsForm.
 */

namespace Drupal\smfbridge\Form;


use Behat\Mink\Exception\Exception;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SmfbridgeSettingsForm extends ConfigFormBase {

  public function getFormId() {
    return 'smfbridge_settings';
  }

  protected function getEditableConfigNames() {
    return [
      'smfbridge.settings',
    ];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('smfbridge.settings');
    $form['driver'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database type'),
      '#required' => TRUE,
      '#default_value' => $config->get('driver') ? $config->get('driver') : 'mysql',
    ];
    $form['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server name'),
      '#required' => TRUE,
      '#default_value' => $config->get('host') ? $config->get('host') : 'localhost',
    ];
    $form['database'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database name'),
      '#required' => TRUE,
      '#default_value' => $config->get('database'),
    ];
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database login'),
      '#required' => TRUE,
      '#default_value' => $config->get('username'),
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Database password'),
      '#default_value' => $config->get('password'),
    ];
    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database table prefix'),
      '#required' => TRUE,
      '#default_value' => $config->get('prefix'),
    ];
    $form['cookiename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SMF cookie name'),
      '#description' => t('This setting should be the same with "Cookie name" in forum settings(Admin &raquo; Configuration &raquo; Server settings &raquo; Cookies and Session)'),
      '#default_value' => $config->get('cookiename') ? $config->get('cookiename') : 'SMFCookie208',
    ];
    $form['forum_directory_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to forum'),
      '#description' => t('Path to folder with Simple Machines Forum sources(relative to Drupal\'s document root). Example: <b>forum</b>'),
      '#default_value' => $config->get('forum_directory_path') ? $config->get('forum_directory_path') : 'forum',
      '#required' => TRUE,
    ];

    $form['forum_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Forum URI'),
      '#description' => t('Relative, if Drupal and SMF are on the same domain, or absolute if Drupal and SMF has different domains'),
      '#default_value' => $config->get('forum_uri') ? $config->get('forum_uri') : '/forum',
      '#required' => TRUE,
    ];

    global $base_url;
    $form['forum_home_link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link title'),
      '#description' => $this->t(
        'Link which will be inserted into SMF main menu and leads to the <b>@path</b>',
        [
          '@path' => $base_url,
        ]
      ),
      '#default_value' => $config->get('forum_home_link_title') ? $config->get('forum_home_link_title') : '',
    ];
    //TODO: add cookie TTL
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    try {
      Database::addConnectionInfo('smfbridge', 'smfbridge', $form_state->getValues());
      /**
       * @var \Drupal\Core\Database\Connection $this ->smfConnection
       */
      $smfConnection = Database::getConnection('smfbridge', 'smfbridge');
      //Let's check if db_prefix is valid
      $smfTablePrefix = $form_state->getValues()['prefix'];
      if (!empty($smfTablePrefix) && ($result = $smfConnection->query('SHOW TABLES LIKE \'%settings\'')
          ->fetchCol())
      ) {
        $smfTableName = reset($result);
        if (empty($smfTableName) || ("{$smfTablePrefix}settings" !== $smfTableName)) {
          $form_state->setErrorByName('prefix', $this->t('Invalid table prefix'));
        }
      }

      $smfDirectoryFilePath = DRUPAL_ROOT . '/' . $form_state->getValues()['forum_directory_path'];
      $smfSsiFIlePath = $smfDirectoryFilePath . '/SSI.php';
      if (!file_exists($smfSsiFIlePath)) {
        $form_state->setErrorByName('forum_directory_path', $this->t('Invalid path to SMF sources'));
      }
    } catch (\PDOException $e) {
      $form_state->setErrorByName('smfdblogin', $this->t('Can\'t connect to SMF database with provided credentials'));
    } catch (\Exception $e) {
      $form_state->setErrorByName('smfdblogin', $this->t('Can\'t connect to SMF database with provided credentials'));
      \Drupal::logger('smfbridge')->error($e->getMessage());
    }
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('smfbridge.settings')
      ->set('driver', $values['driver'])
      ->set('host', $values['host'])
      ->set('database', $values['database'])
      ->set('username', $values['username'])
      ->set('password', $values['password'])
      ->set('prefix', $values['prefix'])
      ->set('cookiename', $values['cookiename'])
      ->set('forum_directory_path', $values['forum_directory_path'])
      ->set('forum_uri', $values['forum_uri'])
      ->set('forum_home_link_title', $values['forum_home_link_title'])
      ->set('conection_installed', TRUE)
      ->save();

    /**
     * @var \Drupal\smfbridge\Smf\Settings $smfSettingsService
     */
    $smfSettingsService = \Drupal::service('smfbridge.smfsettings');
    if ($smfSettingsService->installIntegration($values['forum_directory_path'])) {
      drupal_set_message(t('Integration with SMF is done successfully'));
      global $base_url;
      $smfSettingsService->saveSmfHomeLink($values['forum_home_link_title'], $base_url);
    }
    else {
      drupal_set_message(t('Integration with SMF is failed. Please check entered data.'), 'error');
    }
    parent::submitForm($form, $form_state);
  }
}