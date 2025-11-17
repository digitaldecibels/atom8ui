<?php

namespace Drupal\Tests\rest_export_nested\Functional;

use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;

/**
 * Tests views REST export nested.
 *
 * @group rest_exoort_nested
 */
class RestExportNestedTest extends ViewTestBase {

  /**
   * View executable instance.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'rest_nested_test',
    'node',
    'rest',
    'serialization',
    'user',
    'views',
    'rest_export_nested',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = [
    'nested_data',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = []): void {
    parent::setUp($import_test_views);
    $this->createContentType([
      'type' => 'page',
    ]);
    $this->createNode([
      'status' => TRUE,
      'type' => 'page',
      'title' => 'test page',
    ]);

    ViewTestData::createTestViews(static::class, ['rest_nested_test']);

    $this->view = Views::getView('nested_data');
    $this->view->setDisplay('rest_export_nested_1');
  }

  /**
   * Test rest export nested json.
   */
  public function testViewExportNested() {
    $actual_json = $this->drupalGet('nested-data', ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeEquals(200);
    $expected = '[{"nothing":[{"title":"Article","body":"Body \u0026amp;"}]}]';
    $this->assertSame($actual_json, $expected);
  }

  /**
   * Test rest export json is different from nested json.
   */
  public function testViewExport() {
    $actual_json = $this->drupalGet('not-nested-data', ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeEquals(200);
    $expected = '[{"nothing":[{"title":"Article","body":"Body \u0026amp;"}]}]';
    $this->assertNotSame($actual_json, $expected);
  }

}
