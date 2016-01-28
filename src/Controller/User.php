<?php
namespace Drupal\smfbridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\smfbridge\Form\SmfbridgeAdminPassword;
use Drupal\user\Entity\User as DrupalEntityUser;

class User extends ControllerBase {

  public function login() {
    $currentUserAccount = \Drupal::currentUser();
    if ($currentUserAccount->isAuthenticated()) {
      $currentUser = DrupalEntityUser::load($currentUserAccount->id());
      call_user_func_array('smfbridge_user_login', [$currentUser]);
      return new RedirectResponse('/user/login');
    }
    else {
      /**
       * @var \Symfony\Component\HttpFoundation\Request $currentRequest
       */
      $currentRequest = \Drupal::request();
      $destination = $currentRequest->query->get('destination');

      //Remove "destination" to avoid redirecting by RedirectResponseSubscriber::checkRedirectUrl
      $currentRequest->query->remove('destination');
      /**
       * @var \Symfony\Component\HttpFoundation\Request $currentRequest
       */
      return $this->redirect('user.login', ['destination' => $destination]);
    }
  }

  public function redirectToEditProfile() {
    $currentUser = \Drupal::currentUser();
    if ($currentUser->isAuthenticated()) {
      $id = \Drupal::currentUser()->id();
      return new RedirectResponse("/user/{$id}/edit");
    }
    return new RedirectResponse('/');
  }

  public function adminPassword($smfSessionId, $encodedRedirectPath) {
    $form = \Drupal::formBuilder()->getForm(
      SmfbridgeAdminPassword::class,
      $smfSessionId,
      base64_decode($encodedRedirectPath)
    );
    return $form;
  }
}