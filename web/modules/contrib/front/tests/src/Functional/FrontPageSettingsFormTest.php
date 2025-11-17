<?php

namespace Drupal\Tests\front_page\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the FrontPageSettingsForm functionality.
 *
 * @group front
 */
class FrontPageSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'front_page',
    'test_page_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create a user with permission to administer front page.
    $admin_user = $this->drupalCreateUser(['administer front page']);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests disable_for_administrators checkbox is not saved as TRUE when hidden.
   */
  public function testDisableForAdministratorsHidden() {

    // Navigate to the form page.
    $this->drupalGet('/admin/config/system/front/settings');

    // Ensure the 'disable_for_administrators' checkbox is hidden.
    $this->assertSession()->elementNotExists('xpath', '//input[@name="disable_for_administrators" and @type="checkbox" and @checked="checked"]');

    // Submit the form with 'disable_for_administrators' hidden.
    $edit = [
      'front_page_enable' => TRUE,
      'roles[anonymous][enabled]' => TRUE,
      'roles[anonymous][path]' => '/test-page',
    ];
    $this->submitForm($edit, 'Save Settings');

    // Verify 'disable_for_administrators' setting is not saved as TRUE.
    $config = $this->config('front_page.settings');
    $this->assertFalse($config->get('disable_for_administrators'), 'The disable_for_administrators setting should not be TRUE when hidden.');
  }

}
