<?php
namespace Drupal\smfbridge\Smf;

class Member {

  use TConnection;

  public function __construct() {
    $this->setConnection();
  }

  /**
   *
   * @return bool
   */
  public function memberLogin(\Drupal\User\Entity\User $drupalUser, \stdClass $smfMember) {
    $this->syncSmfPassword($drupalUser, $smfMember);
    $config = \Drupal::config('smfbridge.settings');
    $this->memberSetCookie($smfMember, $config->get('cookiename'), 3600, $config->get('forum_uri'));
    //$_SESSION['admin_time'] = time();
    return TRUE;
  }

  /**
   * @return bool
   */
  public function memberLogout() {
    $config = \Drupal::config('smfbridge.settings');
    if (($smfCookieName = $config->get('cookiename')) && ($forumPath = $config->get('forum_uri'))) {
      $this->memberDeleteCookie($smfCookieName, $forumPath);
    }
    return TRUE;
  }

  public function getSmfMemberByName($memberName) {
    /**
     * @var \Drupal\Core\Database\Connection $this->smfConnection
     */
    $member = $this->smfConnection
      ->select('members', 'm')
      ->fields('m')
      ->condition('m.member_name', $memberName)
      ->execute()
      ->fetchAll();

    return !empty($member) ? reset($member) : NULL;
  }


  public function isMemberExists($memberName) {
    /**
     * @var \Drupal\Core\Database\Connection $this->smfConnection
     */
    $id_member = $this->smfConnection
      ->select('members', 'm')
      ->fields('m', ['id_member'])
      ->condition('m.member_name', $memberName)
      ->execute()
      ->fetchField();
    return $id_member;
  }

  public function memberRegister(\Drupal\User\Entity\User $user) {
    $defaultRegOptions = $this->getDefaultRegistrationOptions();
    $regOptions = [
      'member_name' => $user->getUsername(),
      'date_registered' => $user->get('created')->value,
      'real_name' => $user->get('name')->value,
      'passwd' => sha1($user->getPassword()),
      //'password_salt' => substr(md5(mt_rand()), 0, 4),//is taken from SMF sources
      'password_salt' => '',
      'email_address' => $user->get('mail')->value,
      'hide_email' => 1,
      'avatar' => ($user->get('user_picture')
        ->isEmpty()) ? '' : $user->get('user_picture')->entity->url(),
      'usertitle' => $user->getUsername(),
      'is_activated' => '1',
    ];
    $regOptions = array_merge($defaultRegOptions, $regOptions);
    \Drupal::moduleHandler()
      ->invokeAll('smf_member_register_options', $regOptions);
    $id_member = $this->smfConnection
      ->insert('members')
      ->fields($regOptions)
      ->execute();
    if ($id_member) {
      return $this->getSmfMemberByName($user->getUsername());
    }
    return NULL;
  }

  protected function memberSetCookie(\stdClass $member, $cookieName, $expire, $forumPath = '/forum') {
    setcookie($cookieName, serialize([
      $member->id_member,
      sha1($member->passwd . $member->password_salt),
      time() + $expire,
      0
    ]), time() + $expire, $forumPath, '', NULL);
  }

  protected function memberDeleteCookie($smfCookieName, $forumPath) {
    if (isset($_COOKIE[$smfCookieName])) {
      unset($_COOKIE[$smfCookieName]);
    }
    setcookie($smfCookieName, '', REQUEST_TIME - 3600, $forumPath);
  }

  /**
   * Sets new hash password in smf
   *
   * @param \Drupal\User\Entity\User $drupalUser
   * @param \stdClass $smfMember
   */
  protected function syncSmfPassword(\Drupal\User\Entity\User $drupalUser, \stdClass $smfMember) {
    /**
     * @var \Drupal\Core\Database\Connection $this ->smfConnection
     */
    $hashedPasswd = sha1($drupalUser->getPassword());
    $id_member = $this->smfConnection
      ->select('members', 'm')
      ->fields('m', ['id_member'])
      ->condition('m.member_name', $drupalUser->getUsername())
      ->condition('m.passwd', $hashedPasswd)
      ->execute()
      ->fetchField();
    if (!$id_member) {
      $updateResult = $this->smfConnection
        ->update('members')
        ->fields([
          'passwd' => $hashedPasswd,
          'password_salt' => ''
        ])
        ->condition('member_name', $drupalUser->getUsername())
        ->execute();
      if ($updateResult) {
        $smfMember->passwd = $hashedPasswd;
      }
    }
  }

  protected function getDefaultRegistrationOptions() {
    $regOptions = [];
    $rows = $this->smfConnection->query('DESCRIBE {members}')->fetchAll();
    if (!empty($rows)) {
    }
    foreach ($rows as $row) {
      if ($row->Extra == 'auto_increment') {
        continue;
      }
      if ($row->Null == 'NO') {
        if (!is_null($row->Default)) {
          $regOptions[$row->Field] = $row->Default;
        }
        elseif (preg_match('#^(text|varchar)#', $row->Type)) {
          $regOptions[$row->Field] = '';
        }
        else {
          $regOptions[$row->Field] = 0;
        }
      }
    }
    return $regOptions;
  }

  /**
   * Sets admin_time int member's session
   *
   * @return bool
   */
  public function setAdminTime($smfSessionId) {
    $smfSessionData = $this->smfConnection
      ->select('sessions', 's')
      ->fields('s', ['data'])
      ->condition('s.session_id', $smfSessionId)
      ->execute()
      ->fetchField();
    if (!empty($smfSessionData)) {
      $temp = $_SESSION;
      $_SESSION = array();
      session_decode($smfSessionData);
      $_SESSION['admin_time'] = time();
      $smfSessionDataEncoded = session_encode();
      $_SESSION = $temp;

      $updateResult = $this->smfConnection
        ->update('sessions')
        ->fields([
          'last_update' => time(),
          'data' => $smfSessionDataEncoded
        ])
        ->condition('session_id', $smfSessionId)
        ->execute();

      return $updateResult;
    }
  }
}
