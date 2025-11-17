<?php

namespace Drupal\front_page\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Front page event subscriber for initData event.
 *
 * @package Drupal\front_page\EventSubscriber
 */
class FrontPageSubscriber implements EventSubscriberInterface {

  /**
   * The state key value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * An immutable config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * KillSwitch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $pageCacheKillSwitch;

  /**
   * The path matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Constructs the Event Subscriber object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $pageCacheKillSwitch
   *   The page cache kill switch.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(StateInterface $state, ConfigFactoryInterface $config, AccountInterface $current_user, KillSwitch $pageCacheKillSwitch, PathMatcherInterface $path_matcher, LanguageManagerInterface $language_manager) {
    $this->state = $state;
    $this->config = $config->get('front_page.settings');
    $this->currentUser = $current_user;
    $this->pageCacheKillSwitch = $pageCacheKillSwitch;
    $this->pathMatcher = $path_matcher;
    $this->languageManager = $language_manager;
  }

  /**
   * Manage the logic.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Managed event.
   */
  public function initData(RequestEvent $event) {
    // Make sure front page module is not run when using cli (drush).
    // Make sure front page module does not run when installing Drupal either.
    if (PHP_SAPI === 'cli' || InstallerKernel::installationAttempted()) {
      return;
    }

    // Don't run when site is in maintenance mode.
    if ($this->state->get('system.maintenance_mode')) {
      return;
    }

    // Ignore non index.php requests (like cron).
    if (!empty($_SERVER['SCRIPT_FILENAME']) && realpath(DRUPAL_ROOT . '/index.php') != realpath($_SERVER['SCRIPT_FILENAME'])) {
      return;
    }

    $front_page = NULL;
    $isFrontPage = $this->pathMatcher->isFrontPage();
    if ($this->config->get('enabled', '') && $isFrontPage) {

      $roles = $this->currentUser->getRoles();
      $current_weight = NULL;

      /** @var \Drupal\user\Entity\User $user */
      $user = User::load($this->currentUser->id());
      if ($user->hasRole('administrator') && $this->config->get('disable_for_administrators')) {
        return;
      }

      foreach ($roles as $role) {
        $role_config = $this->config->get('roles.' . $role);
        if ((isset($role_config['enabled']) && $role_config['enabled'] == TRUE)
          && (($role_config['weight'] < $current_weight) || $current_weight === NULL)) {

          // $base_path can contain a / at the end, strip to avoid double slash.
          $front_page = $role_config['path'];
          $current_weight = $role_config['weight'];
        }
      }
    }

    if ($front_page) {
      // Add '/' to the beginning of the URL.
      // This applies if it doesn't start with '/', '?', or '#'.
      if (!str_starts_with($front_page, '/') && !str_starts_with($front_page, '#') && !str_starts_with($front_page, '?')) {
        $front_page = '/' . $front_page;
      }
      $current_language = $this->languageManager->getCurrentLanguage();
      $request = $event->getRequest();
      $url = Url::fromUserInput($front_page, ['language' => $current_language, 'query' => $request->query->all()]);
      $event->setResponse(new RedirectResponse($url->toString()));

      // @todo Probably we must to remove this and manage cache by role.
      // Turn caching off for this page as it is dependant on role.
      $this->pageCacheKillSwitch->trigger();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['initData'];
    return $events;
  }

}
