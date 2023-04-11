<?php

declare(strict_types=1);

namespace Drupal\Tests\date_filter\Functional;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests date filters UI.
 *
 * @group date_filter
 */
final class DateFilterTest extends BrowserTestBase {

  use NodeCreationTrait;

  /**
   * The title used for checking if filter works.
   */
  private const TITLE = '1qaz@WSX3edc';

  private const NODE_TYPE = 'dates_test';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views_ui',
    'date_filter_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The date formatter service.
   */
  private DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->requestTime = $this->container->get('datetime.time')->getRequestTime();

    $this->dateFormatter = $this->container->get('date.formatter');

    $node_data = [
      'type' => self::NODE_TYPE,
      'title' => self::TITLE,
      'created' => $this->requestTime,
      'field_date' => $this->getDate($this->requestTime, TRUE, FALSE),
      'field_datetime' => $this->getDate($this->requestTime, FALSE, TRUE),
    ];
    $this->createNode($node_data);

    $this->adminUser = $this->drupalCreateUser(['administer views']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * The test method.
   *
   * It'd be a performance disaster with data provider so we'll do it
   * differently.
   */
  public function testDateFilters(): void {
    $this->drupalGet('date-filter-test');
    $this->assertSession()->pageTextContains(self::TITLE);

    foreach ($this->getTestData() as $case) {
      if ($case === 'reset') {
        $this->drupalGet('date-filter-test');
        continue;
      }

      $this->submitForm($case['form'], 'Apply');
      if ($case['show']) {
        $this->assertSession()->pageTextContains(self::TITLE);
      }
      else {
        $this->assertSession()->pageTextNotContains(self::TITLE);
      }
    }
  }

  /**
   * Data provider method.
   */
  private function getTestData(): array {
    $cases = [];

    // Created date ">" operator filter. First value represents minutes.
    foreach ([
      [-5, TRUE],
      [0, FALSE],
      [5, FALSE],
    ] as $case_data) {
      $datetime = $this->getDate($this->requestTime + $case_data[0] * 60);
      [$date, $time] = \explode('T', $datetime);
      $case = [
        'form' => [
          'created[date]' => $date,
          'created[time]' => $time,
        ],
        'show' => $case_data[1],
      ];
      $cases[] = $case;
    }

    // Reset after every filter change so we don't get previous filter results.
    $cases[] = 'reset';

    // Date field between filter. First 2 values represent days.
    foreach ([
      [-1, NULL, TRUE],
      [-1, 1, TRUE],
      [0, 0, TRUE],
      [1, 1, FALSE],
    ] as $case_data) {
      $case = ['form' => [], 'show' => $case_data[2]];
      foreach (['min', 'max'] as $i => $filter_key) {
        if ($case_data[$i] === NULL) {
          continue;
        }
        $date = $this->getDate($this->requestTime + $case_data[$i] * 86400, TRUE);
        $case['form']["date[$filter_key][date]"] = $date;
      }
      $cases[] = $case;
    }

    // Reset after every filter change so we don't get previous filter results.
    $cases[] = 'reset';

    // Datetime field between filter. First 2 values represent minutes.
    foreach ([
      [NULL, 5, TRUE],
      [-5, 5, TRUE],
      [0, 0, TRUE],
      [5, 10, FALSE],
    ] as $case_data) {
      $case = ['form' => [], 'show' => $case_data[2]];
      foreach (['min', 'max'] as $i => $filter_key) {
        if ($case_data[$i] === NULL) {
          continue;
        }
        $datetime = $this->getDate($this->requestTime + $case_data[$i] * 86400);
        [$date, $time] = \explode('T', $datetime);
        $case['form']["datetime[$filter_key][date]"] = $date;
        $case['form']["datetime[$filter_key][time]"] = $time;
      }
      $cases[] = $case;
    }

    // Reset after every filter change so we don't get previous filter results.
    $cases[] = 'reset';

    // Exact date filter. First value represents days.
    foreach ([
      [-1, FALSE],
      [0, TRUE],
      [1, FALSE],
    ] as $case_data) {
      $date = $this->getDate($this->requestTime + $case_data[0] * 86400, TRUE);
      $case = [
        'form' => [
          'specific_date[date]' => $date,
        ],
        'show' => $case_data[1],
      ];
      $cases[] = $case;
    }

    return $cases;
  }

  /**
   * Helper function to get date string.
   */
  private function getDate(int $timestamp, bool $date_only = FALSE, bool $storage = FALSE): string {
    $format = $date_only ? DateTimeItemInterface::DATE_STORAGE_FORMAT : DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $timezone = $storage ? DateTimeItemInterface::STORAGE_TIMEZONE : NULL;

    return $this->dateFormatter->format($timestamp, 'custom', $format, $timezone);
  }

}
