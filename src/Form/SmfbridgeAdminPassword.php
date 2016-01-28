<?php
namespace Drupal\smfbridge\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

class SmfbridgeAdminPassword extends FormBase implements FormInterface {

  public function getFormId() {
    return 'smfbridge_admin_password';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $args = $form_state->getBuildInfo()['args'];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (!empty($values['password'])) {
      /**
       * @var \Drupal\Core\Use
       */
      $account = User::load(\Drupal::currentUser()->id());
      /**
       * @var \Drupal\user\MigratePassword $passwordService
       */
      $passwordService = \Drupal::service('password');
      if (!$passwordService->check(trim($values['password']), $account->getPassword())) {
        $form_state->setErrorByName('password', $this->t('Password is invalid'));
      }


    }
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      if (!empty($form_state->getBuildInfo()['args'][0]) && !empty($form_state->getBuildInfo()['args'][1])) {

        $smfSessionId = $form_state->getBuildInfo()['args'][0];
        /**
         * @var \Drupal\smfbridge\Smf\Member $smfMember
         */
        $smfMember = \Drupal::service('smfbridge.smfmember');
        if ($smfMember->setAdminTime($smfSessionId)) {
          $form_state->setRedirectUrl(Url::fromUserInput($form_state->getBuildInfo()['args'][1]));
        }
        else {
          throw new \Exception($this->t('Failed to get access to SMF admin area.'));
        }
      }
      else {
        throw new \Exception($this->t('Failed to get access to SMF admin area.'));
      }
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }
}