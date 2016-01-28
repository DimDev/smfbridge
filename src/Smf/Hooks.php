<?php
/**
 * SMF hooks implementation.
 */
namespace Drupal\smfbridge\Smf;

class Hooks {
  /**
   * Implements integrate_actions hook.
   * http://wiki.simplemachines.org/smf/Integration_hooks#integrate_actions
   *
   * @param $smfActions array
   */
  public static function actions(&$smfActions) {
    $smfActions['login'][1] = $smfActions['login2'][1] = __CLASS__ . '::' . 'loginAction';
    $smfActions['logout'][1] = __CLASS__ . '::' . 'logoutAction';
    $smfActions['register'][1] = __CLASS__ . '::' . 'registerAction';
    $smfActions['reminder'][1] = __CLASS__ . '::' . 'reminderAction';
    if (isset($_GET['action']) && ($_GET['action'] == 'profile') && isset($_GET['area']) && ($_GET['area'] == 'account')) {
      $smfActions['profile'][1] = __CLASS__ . '::' . 'profileAction';
    }
    global $modSettings;
    if (
      empty($modSettings['securityDisable'])
      && (
        empty($_SESSION['admin_time'])
        || $_SESSION['admin_time'] + 3600 < time()
      )
    ) {
      $smfActions['admin'][1] = __CLASS__ . '::' . 'adminAction';
    }
  }

  public static function smfMenuButtons(&$smfMenuButtons) {
    global $modSettings;
    if (!empty($modSettings['drupalHomeLinkButton'])) {
      $drupalHomeLink = unserialize($modSettings['drupalHomeLinkButton']);
      array_unshift($smfMenuButtons, $drupalHomeLink);
    }
  }

  /**
   * Overridden SMF Login action.
   *
   * Redirects user from smf to Drupal login page.
   */
  public function loginAction() {
    header('HTTP/1.1 301 Moved Permanently');
    $location = 'Location: /smfbridge/user/login';
    if (!empty($GLOBALS['scripturl'])) {
      $parsed_url = parse_url($GLOBALS['scripturl']);
      if (!empty($parsed_url['path'])) {
        $location .= '?destination=' . urlencode($parsed_url['path']);
      }
    }
    header($location);
    exit;
  }

  /**
   * Overridden SMF Logout action.
   * Redirects user from smf to Drupal logout page.
   */
  public function logoutAction() {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: /user/logout');
    exit;
  }

  /**
   * Overridden SMF register action.
   * Redirects user from smf to Drupal register page.
   */
  public function registerAction() {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: /user/register');
    exit;
  }

  /**
   * Overridden SMF remnder action.
   * Redirects user from smf to Drupal password page.
   */
  public function reminderAction() {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: /user/password');
    exit;
  }

  /**
   * Overridden SMF Profile action to prevent members chanfing password in SMF
   * Redirects user from smf to Drupal logout page.
   */
  public function profileAction() {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: /smfbridge/profile/edit');
    exit;
  }

  /**
   * Overridden SMF admin action to redirect admin to check pass via Drupal
   * Redirects user from smf to Drupal logout page.
   */
  public function adminAction() {
    if (isset($_REQUEST['REQUEST_URI'])) {
      $redirectPath = $_REQUEST['REQUEST_URI'];
    }
    else {
      $redirectPath = '/forum/index.php?action=admin';
    }
    $redirectPath = base64_encode($redirectPath);
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: /smfbridge/adminpassword/' . session_id() . '/' . $redirectPath);
    exit;
  }

}
