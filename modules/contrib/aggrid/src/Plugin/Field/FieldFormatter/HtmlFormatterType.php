<?php

namespace Drupal\aggrid\Plugin\Field\FieldFormatter;

use Drupal\aggrid\AggridConfigHelpers;
use Drupal\aggrid\AggridSuppression;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'html_formatter_type' formatter.
 *
 * @FieldFormatter(
 *   id = "html_formatter_type",
 *   label = @Translation("HTML grid view mode"),
 *   field_types = {
 *     "aggrid"
 *   }
 * )
 */
class HtmlFormatterType extends FormatterBase {

  /**
   * Complimentary suppression for aggrid item.
   *
   * @var boolean
   */
  protected $suppComplimentary;

  /**
   * Complimentary suppression for aggrid item.
   *
   * @var array
   */
  protected $rowSuppression;
  
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'suppression_override' => false,
        // Implement default settings.
      ] + parent::defaultSettings();
  }
  
  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    
    $form = parent::settingsForm($form, $form_state);
    
    $form['suppression_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Suppression override'),
      '#description' => $this->t('If enabled, suppression will be ignored'),
      '#default_value' => $this->getSetting('suppression_override'),
    ];
    
    return $form;
    
  }
  
  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    // Implement settings summary.
    if ($override = $this->getSetting('suppression_override') ? 'Yes' : 'No') {
      $summary[] = $this->t('Suppression override: @suppover', ['@suppover' => $override]);
    }
    return $summary;
  }
  
  /**
   * {@inheritdoc}
   */
  public function createAggridRowData($rowSettings, $columns, $headers, $rowData) {

    $aggridSuppression = new AggridSuppression();

    // Get configuration for suppression setting
    $settings = $this->getSettings();

    // Default override
    $suppOverride = false;

    // Get suppression override setting
    if (!empty($settings['suppression_override'])) {
      $suppOverride = $settings['suppression_override'];
    }

    // Clear any previous suppression
    $this->rowSuppression = [];
    
    // Cycle through suppression multiple times
    if (is_array($rowData)) {
      for ($i = 0; $i < 4; $i++) {
        $processSuppression = $aggridSuppression->processSuppression($rowSettings, $headers, $rowData, $suppOverride, $this->suppComplimentary);
        $rowData = $processSuppression['rowData'];
        $this->rowSuppression = $processSuppression['rowSuppression'];
      }
    }
    
    // HTML rendering
    $table_render = '';
    $spanSkip[][] = 0;
    if (is_array($rowData)) {
      for ($i = 0; $i < count($rowData); $i++) {
        // Each row... then each cell in each row.
        $table_render .= '<tr>';
        $colCount = -1;
        foreach ($headers as $field) {
          $colCount++;
          // Loop and look for cell data.
          $colSpan = 1;
          $rowSpan = 1;
          $cellClass = "";
          // Check if a spanCount exists for item. If not, create it.
          if (!isset($spanSkip[$i][$colCount])) {
            $spanSkip[$i][$colCount] = 0;
          }
          
          // If it exists, do it
          if (($spanSkip[$i][$colCount] == 0
            || $spanSkip[$i][$colCount] == '')) {
            // Has data, put it to cell.
            // Get the colspan and rowspan.
            if (isset($rowSettings[$i][$field]['colSpan'])) {
              $colSpan = $rowSettings[$i][$field]['colSpan'];
            }
            if (isset($rowSettings[$i][$field]['rowSpan'])) {
              $rowSpan = $rowSettings[$i][$field]['rowSpan'];
            }
            if ($rowSpan == '' || $rowSpan == NULL) {
              $rowSpan = 1;
            }
            if ($colSpan == '' || $colSpan == NULL) {
              $colSpan = 1;
            }
            
            // Loop span and set skips.
            for ($si = 0; $si < $rowSpan; $si++) {
              $rowNum = $i + $si;
              for ($sc = 0; $sc < $colSpan; $sc++) {
                $colNum = $colCount + $sc;
                $spanSkip[$rowNum][$colNum] = 1;
              }
            }
            
            $cellClass = '';
            
            // Get the class, switch the name from just aggrid to aggrid-html.
            if (isset($rowSettings[$i][$field]['cellClass'])) {
              $cellClass = str_replace('aggrid-', 'aggrid-html', $rowSettings[$i][$field]['cellClass']);
            }
            
            // Get the class, switch the name from just aggrid to aggrid-html.
            if (isset($rowSettings[$i][$field]['formatType'])
              && $rowSettings[$i][$field]['formatType'] != '') {
              $cellClass = $cellClass . ' aggrid-html-ftype-' . $rowSettings[$i][$field]['formatType'];
            }
            
            // Check if this cell item is actually a label. If so, define as a row for scope (accessibility).
            if (strpos($cellClass, 'aggrid-htmlcell-label') !== false) {
              $cellScope = 'scope="row"';
            } else {
              $cellScope = '';
            }

            // Check settings
            if (isset($columns[0][$field])
              && $columns[0][$field]['viewHide']) {
              $cellClass = $cellClass . ' aggrid-htmlcell-viewhide';
            }
            
            // Check if field/cell exists. If not, blank it as default.
            if (isset($rowData[$i]->$field)) {
              $cellData = $rowData[$i]->$field;
            }
            else {
              $cellData = "";
            }
            
            // Finally, display the cell.
            $table_render .= '<td ' . $cellScope . ' rowspan="' . $rowSpan . '" colspan="' . $colSpan . '" class="' . $cellClass . '">' . $cellData . '</td>';
          } elseif ($spanSkip[$i][$colCount] > 0) {
            // No need to render the cell.
          } else {
            // No data, just a blank cell.
            $table_render .= '<td></td>';
          }
        }
        // Close up the row.
        $table_render .= '</tr>';
      }
    }
    return $table_render;
  }
  
  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // Set the agGrid Configuration Helpers
    $aggridConfigHelpers = new AggridConfigHelpers();

    foreach ($items as $delta => $item) {
      $field_name = $this->fieldDefinition->getName();
      
      $item_id = Html::getUniqueId("ht-$field_name-$delta");

      $aggridDefault = $aggridConfigHelpers->getDefaults($items[$delta]->aggrid_id);
      
      if (empty($aggridDefault)) {
        
        $elements[$delta]['container'] = [
          '#plain_text' => $this->t('Missing ag-Grid Config Entity'),
          '#prefix' => '<div class="aggrid-widget-missing">',
          '#suffix' => '</div>',
        ];
        
      }
      else {

        if ($items[$delta]->value == '' || $items[$delta]->value == '{}') {
          $aggridValue = $aggridDefault['default']->rowData;
        }
        else {
          $aggridValue = json_decode($items[$delta]->value);
        }

        $pinnedTopRowData = @$aggridDefault['addOptions']->pinnedTopRowData;
        $pinnedBottomRowData = @$aggridDefault['addOptions']->pinnedBottomRowData;

        $aggridRowSettings = $aggridDefault['aggridRowSettings'];

        // Set complimentary suppression variable
        // Check to see if complimentary is enabled on the default row and rowDefault
        if (isset($aggridRowSettings['default']['rowDefault']['valueSuppression']['complimentary'])) {
          $this->suppComplimentary = true;
        }
        else {
          $this->suppComplimentary = false;
        }

        // Set the row data
        $rowData = $aggridValue;

        // Get header information
        $getHeaders = $aggridConfigHelpers->getHeaders($aggridDefault['default']->columnDefs);

        // Set the variables from header information
        $rowIndex = $getHeaders['rowIndex'];
        $colIndex = $getHeaders['colIndex'];
        $headers = $getHeaders['headers'];
        $columns = $getHeaders['columns'];

        // Data Header Settings.
        $dataHeaderSettings[][][] = "";
        $dataHeaderSettings = $aggridConfigHelpers->getRowSettings($aggridRowSettings, $headers, $columns, 'dh-');

        // Run the headers again for colSpan
        // Get header information
        $getHeaders = $aggridConfigHelpers->getHeaders($aggridDefault['default']->columnDefs, $dataHeaderSettings);

        // Set the variables from header information
        $headers = $getHeaders['headers'];
        $columns = $getHeaders['columns'];

        // Build table.
        $table_render = '';
        $table_render .= '<table id="' . $item_id . '-table" class="aggrid-html-widget"><thead>';

        // Get the header rows.
        for ($y = 1; $y <= $rowIndex; $y++) {
          // Each header row and each column cell with spanning.
          $table_render .= '<tr>';
          for ($x = 1; $x <= $colIndex; $x++) {
            if (!array_key_exists($x, $columns[$y])) {
              $table_render .= '<th id="'. $item_id .'-h' . $y . 'c' . $x .'"></th>';
            }
            else {
              foreach ($columns[$y][$x] as $count => $value) {
                // Set variables for columns
                $colClass = [];
                $field = $columns[$y][$x][$count]['field'];

                // Check settings
                if (isset($columns[0][$field])
                  && $columns[0][$field]['viewHide']) {
                  $colClass[] = 'aggrid-htmlcell-viewhide';
                }

                // Render the header
                $table_render .= '<th scope="col" id="'. $item_id .'-h' . $y . 'c' . $x .'" class="'. implode(' ', $colClass) .'" colspan="' . $columns[$y][$x][$count]['colspan'] . '" data-width="' . $columns[$y][$x][$count]['width'] . '" data-minWidth="' . $columns[$y][$x][$count]['minWidth'] . '">' . $columns[$y][$x][$count]['headerName'] . '</th>';
              }
            }
          }
          $table_render .= '</tr>';
        }

        // Close up the headers and start on data rows.
        $table_render .= '</thead><tbody>';

        // Pinned Top Row Settings.
        $pinnedTopRowSettings[][][] = "";
        $pinnedTopRowSettings = $aggridConfigHelpers->getRowSettings($aggridRowSettings, $headers, $pinnedTopRowData, 't-');

        // Pinned Top Rows.
        $table_render .= $this->createAggridRowData($pinnedTopRowSettings, $columns, $headers, $pinnedTopRowData);

        // (Data) Row Settings.
        $rowSettings = $aggridConfigHelpers->getRowSettings($aggridRowSettings, $headers, $rowData, '');

        // Data rows.
        $table_render .= $this->createAggridRowData($rowSettings, $columns, $headers, $rowData);
        // Set the row suppression from after the actual rowData is run
        $rowSuppression = $this->rowSuppression;

        // Pinned Bottom Row Settings.
        $pinnedBottomRowSettings = $aggridConfigHelpers->getRowSettings($aggridRowSettings, $headers, $pinnedBottomRowData, 'b-');

        // Pinned Bottom Rows.
        $table_render .= $this->createAggridRowData($pinnedBottomRowSettings, $columns, $headers, $pinnedBottomRowData);

        // Close up the table.
        $table_render .= '</tbody></table>';

        $elements[$delta]['container'] = [
          '#theme' => 'aggrid_table',
          '#title' => $this->fieldDefinition->label(),
          '#description' => $this->fieldDefinition->getDescription(),
          '#headers' => $headers,
          '#rowSettings' => $rowSettings,
          '#rowSuppression' => $rowSuppression,
          '#rowData' => $rowData,
          '#suffix' => $table_render,
          '#attached' => [
            'library' => [
              'aggrid/aggrid.widget',
            ],
          ],
        ];

        /*
         * Putting this code to the side for now. They're currently working on multiple headers
         * for the '#type' => 'table'
         *
         *

        // Loop through header array and dive down max 3 header rows. Squash all down to single row with only the items with fields.
        // Header 1
        foreach($aggridDefault->columnDefs as $column) {
          if (isset($column->children)) { // If children, then dive down for headers, otherwise establish column
            foreach ($column->children as $child) {
              // Header 2
              if (isset($child->children)) {
                foreach ($child->children as $subchild) {
                  if (isset($subchild->field)) {
                    // Header from row 3
                    $columns[$column->headerName . ' - ' . $child->headerName . ' - ' . $subchild->headerName] = [];
                    $columns[$column->headerName . ' - ' . $child->headerName . ' - ' . $subchild->headerName]['field'] = $subchild->field;
                  }
                }
              } else {
                if (isset($child->field)) {
                  // Header from row 2
                  $columns[$column->headerName . ' - ' . $child->headerName] = [];
                  $columns[$column->headerName . ' - ' . $child->headerName]['field'] = $child->field;
                }
              }
            }
          } else {
            if (isset($column->field)) {
              // Header from row 1
              $columns[$column->headerName] = [];
              $columns[$column->headerName]['field'] = $column->field;
            }
          }
        }

        // Headers
        foreach($columns as $column => $value) {
          array_push($headers, $column);
        }

        // Row Data
        for ($i = 0; $i < count($rowData); $i++) {
          foreach($columns as $column => $value) {
            $colField = $columns[$column]['field'];
            $tabledata[$i][$columns[$column]['field']] = [
              'data' => $rowData[$i]->$colField,
              'class' => ['row_' . $columns[$column]['field'], 'col_' . $columns[$column]['field']],
            ];
          }
        }

        $elements[$delta]['tablefield'] = [
          '#type' => 'table',
          '#headers' => $headers,
          '#rows' => $tabledata,
          '#attributes' => [
            'id' => [$item_id . '-table'],
            'class' => ['aggrid-html-widget'],
          ],
          '#prefix' => '<div id="tablefield-wrapper-' . $delta . '" class="tablefield-wrapper">',
          '#suffix' => '</div>',
        ];
        */
        
      }
      
    }
    
    return $elements;
  }
  
  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    /*
     * The text value has no text format assigned to it, so the user input
     * should equal the output, including newlines.
     */
    return nl2br(Html::escape($item->value));
  }
  
}
