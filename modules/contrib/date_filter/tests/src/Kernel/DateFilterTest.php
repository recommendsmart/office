<?php

declare(strict_types=1);

namespace Drupal\Tests\date_filter\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Views;

/**
 * Tests date and datetime fields filtering.
 *
 * @group date_filter
 */
final class DateFilterTest extends KernelTestBase {

  use NodeCreationTrait;

  // Time interval for created nodes. Since the initial value of one of the
  // filters is > 1 month (see views.view.date_filter_test), one week will
  // be appropriate.
  private const TIME_INTERVAL = 604_800;

  private const NODE_TYPE = 'dates_test';

  private const TEST_VIEW = 'date_filter_test';
  private const TEST_VIEW_DISPLAY = 'page_1';

  private const TEST_DATA = [
    [
      'created' => '2022-01-01T10:00:00',
      'field_date' => '2022-01-01',
      'field_datetime' => '2022-01-01T10:00:00',
    ],
    [
      'created' => '2022-01-02T11:00:00',
      'field_date' => '2022-01-02',
      'field_datetime' => '2022-01-02T11:00:00',
    ],
    [
      'created' => '2022-02-10T08:00:00',
      'field_date' => '2022-02-10',
      'field_datetime' => '2022-02-10T08:00:00',
    ],
    [
      'created' => '2022-02-11T08:30:00',
      'field_date' => '2022-02-11',
      'field_datetime' => '2022-02-11T08:30:00',
    ],
    [
      'created' => '2022-02-12T08:45:00',
      'field_date' => '2022-02-12',
      'field_datetime' => '2022-02-12T08:45:00',
    ],
  ];

  private const DATETIME_FORMAT = 'Y-m-d\TH:i:s';
  private const DATE_FORMAT = 'Y-m-d';

  /**
   * The timezone where test content was created.
   *
   * This needs to be UTC, otherwise we'd have to do a lot
   * of additional timezone conversions.
   */
  private const INITIAL_TIMEZONE = 'UTC';

  /**
   * The time service.
   */
  private TimeInterface $time;

  /**
   * The date formatter service.
   */
  private DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'field',
    'views',
    // The datetime module is not a general dependency but should be included
    // for a comprehensive test of all supported field types.
    'datetime',
    'date_filter',
    'date_filter_test',
    'system',
  ];

  /**
   * Test nodes data.
   *
   * Includes titles, created timestamps and field_date values.
   *
   * @var mixed[]|null
   */
  protected ?array $testNodesData;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');
    $this->installSchema('system', 'sequences');

    $this->installConfig([
      'system',
      'date_filter_test',
    ]);

    $this->setTimezone(self::INITIAL_TIMEZONE);

    // Get time and VBO view data services.
    $this->time = $this->container->get('datetime.time');
    $this->dateFormatter = $this->container->get('date.formatter');

    // Create test nodes with different created timestamps and
    // field_date values.
    foreach (self::TEST_DATA as $delta => $node_data) {
      $node_data['created_ts'] = $this->getTimestamp($node_data['created']);
      $node_data['field_date_ts'] = $this->getTimestamp($node_data['field_date']);
      $node_data['field_datetime_ts'] = $this->getTimestamp($node_data['field_datetime']);

      $node = $this->createNode([
        'type' => self::NODE_TYPE,
        'title' => \sprintf('test node "%d"', $delta),
        'created' => $node_data['created_ts'],
        'field_date' => $node_data['field_date'],
        'field_datetime' => $node_data['field_datetime'],
      ]);
      $this->testNodesData[$node->id()] = $node_data;
    }
  }

  /**
   * Tests the initial value of the filter.
   */
  public function testDateFilterInitial(): void {
    $view = Views::getView(self::TEST_VIEW);
    $view->setDisplay(self::TEST_VIEW_DISPLAY);
    $view->execute();

    // Check if the view filters were set to correct dates.
    // Allow 5 second threshold for view execution time.
    $actual_created = \strtotime(implode('T', $view->exposed_data['created']));
    $expected = \strtotime('-1 month', $this->time->getCurrentTime());
    $condition =
      $expected >= $actual_created &&
      $expected <= $actual_created + 5;
    $this->assertTrue($condition, \sprintf('Created filter actual %d not equal to expected %d', $actual_created, $expected));
  }

  /**
   * Tests a timestamp storage field.
   *
   * @param string|null $filter_value
   *   Values of the date_field filter.
   * @param string $timezone
   *   The test case time zone.
   *
   * @dataProvider filterSingleDataProvider
   */
  public function testDateFilterTimestamp(?string $filter_value, string $timezone) {
    // Set timezone.
    $this->setTimezone($timezone);
    $exposed_input = [];

    // Default value from test view config.
    if ($filter_value === NULL) {
      $filter_value_timestamp = \strtotime('-1 month', $this->time->getCurrentTime());
      $exposed_input['created']['date'] = $this->dateFormatter->format($filter_value_timestamp, 'custom', 'Y-m-d');
      $exposed_input['created']['time'] = $this->dateFormatter->format($filter_value_timestamp, 'custom', 'H:i:s');
    }
    else {
      $filter_value_timestamp = $this->getTimestamp($filter_value);
      $parts = \explode('T', $filter_value);
      $exposed_input['created']['date'] = $parts[0];
      $exposed_input['created']['time'] = $parts[1];
    }

    $should_show = [];
    foreach ($this->testNodesData as $node_id => $data) {
      $should_show[$node_id] = TRUE;
      if ($data['created_ts'] <= $filter_value_timestamp) {
        $should_show[$node_id] = FALSE;
      }
    }

    $this->assertViewResults($exposed_input, $should_show);
  }

  /**
   * Tests the date range filter.
   *
   * @param string[] $filter_values
   *   Values of the date_field filter.
   * @param string $timezone
   *   The test case time zone.
   *
   * @dataProvider filterRangeDataProvider
   */
  public function testDateFilterDateRange(array $filter_values, string $timezone): void {

    // Set timezone.
    $this->setTimezone($timezone);

    $filter_values_timestamp = [];
    $exposed_input = [];
    // This is a date-only filter so we need to convert times.
    foreach ([
      'min' => '00:00:00',
      'max' => '23:59:59',
    ] as $filter_key => $filter_time) {
      if ($filter_values[$filter_key] !== NULL) {
        $parts = \explode('T', $filter_values[$filter_key]);
        // Date only fields are not subject to any time zone conversion so
        // we must use the initial one for assertions.
        $filter_values_timestamp[$filter_key] = $this->getTimestamp($parts[0] . 'T' . $filter_time, self::INITIAL_TIMEZONE);
        $exposed_input['date'][$filter_key] = [
          'date' => $parts[0],
        ];
      }
    }

    $should_show = [];
    foreach ($this->testNodesData as $node_id => $data) {
      $should_show[$node_id] = TRUE;

      // Check all conditions.
      if (
        \array_key_exists('min', $filter_values_timestamp) &&
        $data['field_date_ts'] <= $filter_values_timestamp['min']
      ) {
        $should_show[$node_id] = FALSE;
        continue;
      }
      if (
        \array_key_exists('max', $filter_values_timestamp) &&
        $data['field_date_ts'] >= $filter_values_timestamp['max']
      ) {
        $should_show[$node_id] = FALSE;
        continue;
      }
    }

    $this->assertViewResults($exposed_input, $should_show);
  }

  /**
   * Tests the datetime range filter.
   *
   * @param string[] $filter_values
   *   Values of the date_field filter.
   * @param string $timezone
   *   The test case time zone.
   *
   * @dataProvider filterRangeDataProvider
   */
  public function testDateTimeFilterDateRange(array $filter_values, string $timezone): void {

    // Set timezone.
    $this->setTimezone($timezone);

    $filter_values_timestamp = [];
    $exposed_input = [];
    foreach (['min', 'max'] as $filter_key) {
      if ($filter_values[$filter_key] !== NULL) {
        $filter_values_timestamp[$filter_key] = $this->getTimestamp($filter_values[$filter_key]);
        $parts = \explode('T', $filter_values[$filter_key]);
        $exposed_input['datetime'][$filter_key] = [
          'date' => $parts[0],
          'time' => $parts[1],
        ];
      }
    }

    $should_show = [];
    foreach ($this->testNodesData as $node_id => $data) {
      $should_show[$node_id] = TRUE;
      // Check all conditions.
      if (
        \array_key_exists('min', $filter_values_timestamp) &&
        $data['field_datetime_ts'] <= $filter_values_timestamp['min']
      ) {
        $should_show[$node_id] = FALSE;
        continue;
      }
      if (
        \array_key_exists('max', $filter_values_timestamp) &&
        $data['field_datetime_ts'] >= $filter_values_timestamp['max']
      ) {
        $should_show[$node_id] = FALSE;
        continue;
      }
    }

    $this->assertViewResults($exposed_input, $should_show);
  }

  /**
   * Tests the date range filter.
   *
   * @param string|null $filter_value
   *   Value of the specific_date filter.
   * @param string $timezone
   *   The test case time zone.
   *
   * @dataProvider filterSingleDataProvider
   */
  public function testDateFilterSpecificDate(?string $filter_value, string $timezone): void {

    // Set timezone.
    $this->setTimezone($timezone);

    $filter_values_timestamp = [];
    $exposed_input = [];
    if ($filter_value !== NULL) {
      $parts = \explode('T', $filter_value);

      $filter_values_timestamp['min'] = $this->getTimestamp($parts[0] . 'T00:00:00');
      $filter_values_timestamp['max'] = $this->getTimestamp($parts[0] . 'T23:59:59');
      $exposed_input['specific_date'] = [
        'date' => $parts[0],
      ];
    }

    $should_show = [];
    foreach ($this->testNodesData as $node_id => $data) {
      $should_show[$node_id] = TRUE;
      if (\count($filter_values_timestamp) === 0) {
        continue;
      }

      if ($data['field_datetime_ts'] <= $filter_values_timestamp['min']) {
        $should_show[$node_id] = FALSE;
        continue;
      }
      if ($data['field_datetime_ts'] >= $filter_values_timestamp['max']) {
        $should_show[$node_id] = FALSE;
        continue;
      }
    }

    $this->assertViewResults($exposed_input, $should_show);
  }

  /**
   * Get timestamp from date for test comparisons.
   */
  private function getTimestamp(string $date, ?string $timezone = NULL) {
    if (\strpos($date, 'T') !== FALSE) {
      return DrupalDateTime::createFromFormat(self::DATETIME_FORMAT, $date, $timezone)->format('U');
    }
    return DrupalDateTime::createFromFormat(self::DATE_FORMAT, $date, $timezone)->format('U');
  }

  /**
   * Set time zone.
   */
  private function setTimezone(string $timezone) {
    \date_default_timezone_set($timezone);
    $this->config('system.date')
      ->set('timezone.user.configurable', 0)
      ->set('timezone.default', $timezone)
      ->save();
  }

  /**
   * Helper function that executes the view and gets results.
   */
  private function assertViewResults(array $exposed_input, array $should_show): void {
    $view = Views::getView(self::TEST_VIEW);
    $view->setDisplay(self::TEST_VIEW_DISPLAY);

    // If created filter value is not explicitly set, set it to a date earlier
    // than any of the test nodes created dates so it doesn't break the results
    // of individual filters testing.
    if (!\array_key_exists('created', $exposed_input)) {
      $exposed_input['created'] = [
        'date' => '2020-01-01',
        'time' => '00:00:00',
      ];
    }

    $view->setExposedInput($exposed_input);
    $view->setItemsPerPage(0);
    $view->setCurrentPage(0);
    $view->setOffset(0);
    $view->execute();

    $result_ids = [];
    foreach ($view->result as $row) {
      $node_id = $row->_entity->id();
      $result_ids[$node_id] = $node_id;
    }

    foreach ($should_show as $node_id => $result) {
      if ($result) {
        $this->assertArrayHasKey($node_id, $result_ids, \sprintf(
          'Node %d (created on %s, date field %s, datetime field %s) should show in results.',
          $node_id,
          $this->testNodesData[$node_id]['created'],
          $this->testNodesData[$node_id]['field_date'],
          $this->testNodesData[$node_id]['field_datetime']
        ));
      }
      else {
        $this->assertArrayNotHasKey($node_id, $result_ids, \sprintf(
          'Node %d (created on %s, date field %s, datetime field %s) should not show in results.',
          $node_id,
          $this->testNodesData[$node_id]['created'],
          $this->testNodesData[$node_id]['field_date'],
          $this->testNodesData[$node_id]['field_datetime']
        ));
      }
    }
  }

  /**
   * Data provider method for date range tests.
   *
   * @return mixed[]
   *   Test cases data.
   */
  public function filterRangeDataProvider(): array {
    $cases = [];

    foreach ([
      // UTC-12, no DST.
      'Pacific/Kwajalein',
      // UTC-7, no DST.
      'America/Phoenix',
      // UTC.
      'UTC',
      // UTC+5:30, no DST.
      'Asia/Kolkata',
      // UTC+13, no DST.
      'Pacific/Tongatapu',
    ] as $timezone) {
      $cases[] = [
        'filter_values' => [
          'min' => NULL,
          'max' => NULL,
        ],
        'timezone' => $timezone,
      ];
      $cases[] = [
        'filter_values' => [
          'min' => '2022-02-10T07:30:00',
          'max' => NULL,
        ],
        'timezone' => $timezone,
      ];
      $cases[] = [
        'filter_values' => [
          'min' => NULL,
          'max' => '2022-02-10T07:30:00',
        ],
        'timezone' => $timezone,
      ];
      $cases[] = [
        'filter_values' => [
          'min' => '2022-01-02T10:45:00',
          'max' => '2022-02-11T08:35:00',
        ],
        'timezone' => $timezone,
      ];
    }

    return $cases;
  }

  /**
   * Data provider method for single date filter tests.
   *
   * @return mixed[]
   *   Test cases data.
   */
  public function filterSingleDataProvider(): array {
    $cases = [];

    foreach ([
      // UTC-12, no DST.
      'Pacific/Kwajalein',
      // UTC-7, no DST.
      'America/Phoenix',
      // UTC.
      'UTC',
      // UTC+5:30, no DST.
      'Asia/Kolkata',
      // UTC+13, no DST.
      'Pacific/Tongatapu',
    ] as $timezone) {
      $cases[] = [
        'filter_value' => NULL,
        'timezone' => $timezone,
      ];
      $cases[] = [
        'filter_value' => '2022-01-02T11:05:00',
        'timezone' => $timezone,
      ];
      $cases[] = [
        'filter_value' => '2022-02-11T08:35:00',
        'timezone' => $timezone,
      ];
    }

    return $cases;
  }

}
