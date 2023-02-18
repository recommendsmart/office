/**
 * Behavior for 'aggrid_widget_type' widget.
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';
  var aggridFieldName = [];
  var aggridDataEdit = [];
  var aggridValidationErrors = [];
  var aggridShowError = [];
  var aggridPasteStart = [];

  var eGridDiv = [];
  var gridOptions = [];

  // Math Function for truncate instead of rounding decimals.
  Number.prototype.toFixedDown = function(digits) {
    var re = new RegExp("(\\d+\\.\\d{" + digits + "})(\\d)"),
        m = this.toString().match(re);
    return m ? parseFloat(m[1]) : this.valueOf();
  };

  // Settings:
  // Get the license for aggrid.
  const aggridLicense = drupalSettings.aggrid.settings.license_key;

  // Get the general settings.
  const aggridGSJSON = JSON.parse(drupalSettings.aggrid.settings.aggridgsjson);

  // Get the Excel Styles.
  const aggridExcelStyles = JSON.parse(drupalSettings.aggrid.settings.aggridexcelstyles);

  // Get the global default options.
  const aggridOpt = JSON.parse(drupalSettings.aggrid.settings.aggridoptions);

  Drupal.aggridInstances = [];

  Drupal.behaviors.aggridIntegration = {
    attach: function (context) {
      function makeJson(item) {
        var aggridOutput = {};
        aggridOutput = gridOptions[item].rowData;

        // Write back to Drupal 'Value' field.
        $('#' + aggridFieldName[item] + '_rowData').val(
            JSON.stringify(aggridOutput)
                .replace(/NaN/g, 0)
                .replace(/Infinity/g, 0)
        );
      }

      function smartJSONextend(obj1, obj2) {
        // clone
        var mergedObj = JSON.parse(JSON.stringify(obj1));

        (function recurse(currMergedObj, currObj2) {
          var key;

          for (key in currObj2) {
            if (currObj2.hasOwnProperty(key)) {
              if (!currMergedObj[key]) {
                currMergedObj[key] = undefined;
              }
              // if object, then dive in / recurse.
              if (typeof currObj2[key] === 'object' && currObj2[key] !== null) {
                // obj2 is nested and currMergedObj[key] is undefined, sync types.
                if (!currMergedObj[key]) {
                  // obj2[key] ifArray.
                  if (currObj2[key].length !== undefined) {
                    currMergedObj[key] = [];
                  }
                  else {
                    currMergedObj[key] = {};
                  }
                }
                recurse(currMergedObj[key], currObj2[key]);
              }
              else {
                // overwrite if obj2 is leaf and not nested.
                currMergedObj[key] = currObj2[key];
              }
            }
          }
        })(mergedObj, obj2);

        return mergedObj;
      }

      let selector = $('.aggrid-widget');
      let idArray = [];

      selector.each(function () {
        idArray.push(this.id);
      });

      $.each(idArray, function (index, item) {
        let aggridDiv;
        let colDefsValue;
        let rowSettingsValue;
        let rowDataValue;
        let addOptValue;
        // variable used temporarily for full header name creation loop.
        let tempList = [];

        let aggridJSON_colDefs;
        let aggridJSON_rowSettings;
        let aggridJSON_rowData;
        let aggridJSON_addOpt;

        // all field columns are placed in here.
        let aggridFields;
        // Will contain full header name for each.
        let aggridFieldFullHeaderName = [];

        // column field: parent - child - child.
        // Will contain a label for each row if provided.
        let aggridRowLabels = [];

        // Set the aggrid div variable
        aggridDiv = $('#' + item);

        aggridFieldName[item] = aggridDiv.data('target');
        aggridDataEdit[item] = aggridDiv.data('edit');
        // Set the validation errors variable for this aggrid
        aggridValidationErrors[item] = {};
        aggridShowError[item] = false;
        aggridPasteStart[item] = false;

        // If aggrid instance is already registered on Element. There is no
        // need to register it again.
        if (once('aggridDiv', aggridDiv).length !== aggridDiv.length) {
           return;
        }

        // Get the data from Drupal.
        colDefsValue = drupalSettings.aggrid.settings[aggridFieldName[item]].columnDefs;
        rowSettingsValue = drupalSettings.aggrid.settings[aggridFieldName[item]].rowSettings;
        rowDataValue = $('#' + aggridFieldName[item] + '_rowData').val();
        addOptValue = drupalSettings.aggrid.settings[aggridFieldName[item]].addOptions;

        // If it's not blank, parse the json. otherwise, null.
        aggridJSON_colDefs = colDefsValue ? JSON.parse(colDefsValue) : null;
        aggridJSON_rowSettings = rowSettingsValue ? JSON.parse(rowSettingsValue) : null;
        aggridJSON_rowData = rowDataValue ? JSON.parse(rowDataValue) : null;
        aggridJSON_addOpt = addOptValue ? JSON.parse(addOptValue) : null;

        // If we have a global rowSettings default, then process things.
        if (typeof aggridGSJSON['rowSettings'] !== 'undefined') {
          // Merge the global rowSettings with the table rowSettings.
          aggridJSON_rowSettings = smartJSONextend(aggridGSJSON['rowSettings'], aggridJSON_rowSettings);
        }

        function cellStyleFunc(params) {
          return false;
        }

        function cellClassFunc(params) {
          let ftName;

          let cellClassVar = [];
          let rscParamsRowId;
          let rscParamsColDefField;

          // Check if this is a pinned row or not.
          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId =
              params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          // Check for a cell setting and put to array
          cellClassVar = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'cellClass'
          ).split(" ");

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          ftName = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'formatType'
          );

          // If the formatType and has an excelType, apply it.
          if (
              ftName !== '' &&
              typeof aggridGSJSON.formatTypes[ftName] !== 'undefined' &&
              aggridGSJSON.formatTypes[ftName].excelType !== 'undefined'
          ) {
            cellClassVar.push(aggridGSJSON.formatTypes[ftName].excelType);
          }

          // Add if cell can be edited or not.
          if (editableFunc(params)) {
            cellClassVar.push("aggrid-cell-edit-ok");
          }
          else {
            cellClassVar.push("aggrid-cell-edit-no");
          }

          return cellClassVar;
        }

        function colSpanFunc(params) {
          let colSpanVar;
          let rscParamsRowId;
          let rscParamsColDefField;

          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId = params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          // Check for a cell setting.
          colSpanVar = rowSettingsCheck(
            rscParamsRowId,
            rscParamsColDefField,
            'colSpan'
          );

          // If var is good, send it on. Otherwise, send default.
          if (colSpanVar === '') {
            colSpanVar = 1;
          }

          return colSpanVar;
        }

        function editableFunc(params) {
          let rscParamsRowId;
          let rscParamsColDefField;

          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId = params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          let editableVar;

          // Only check if editable when the grid is set to be editable.
          // Otherwise, it's false.
          if (aggridDataEdit[item]) {
            // Check editable rowsetting.
            editableVar = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'editable'
            );
            // if the variable is blank, and grid editing is on, default to editable.
            if (editableVar === '') {
              editableVar = true;
            }
          }
          else {
            // grid is not editable then always send false.
            editableVar = false;
          }

          // output.
          return editableVar;
        }

        function getHeaderParentItems(data) {
          tempList.push(data.getDefinition('headerName').headerName);
          if (data.parent !== null) {
            getHeaderParentItems(data.parent);
          }
        }

        function getRowLabels() {
          // Loop through each row and get fields assigned as isRowLabel.
          $.each(gridOptions[item].rowData, function (row) {
            tempList[row] = [];
            $.each(aggridFields, function (count, field) {
              if (rowSettingsCheck(row, field.colId,'isRowLabel') === true) {
                tempList[row].push(gridOptions[item].rowData[row][field.colId]);
              }
            });
            aggridRowLabels[row] = tempList[row]
              .filter(function (e) {
                return e;
              })
              .join(' - ');
          });
        }

        function restrictInputFunc(params) {
          // Only allow specific characters.
          let ptName;
          let rscParamsRowId;
          let rscParamsColDefField;
          let inputField = 'input.ag-cell-edit-input';

          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId = params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          ptName = rowSettingsCheck(
            rscParamsRowId,
            rscParamsColDefField,
            'parserType'
          );

          // If the ptName is set and restrictInput is available, move forward.
          // Otherwise it's an error.
          if (
            ptName !== '' &&
            typeof aggridGSJSON.parserTypes[ptName] !== 'undefined' &&
            typeof aggridGSJSON.parserTypes[ptName].restrictInput !== 'undefined' &&
            typeof aggridGSJSON.restrictInput[aggridGSJSON.parserTypes[ptName].restrictInput].regEx !== 'undefined'
          ) {
            let regEx = new RegExp(aggridGSJSON.restrictInput[aggridGSJSON.parserTypes[ptName].restrictInput].regEx);

            // Put a keydown trigger on the cell input (appears when a user is editing a cell).
            $(inputField).on('keyup', function (e) {
                if (typeof e.keyCode !== 'undefined' && e.keyCode !== 9) { // Ignore TAB for navigation purposes
                  this.value = this.value.replace(regEx, '');
                }
              }).trigger('keyup');

          }
          else {
            // If the code reaches here, it's an error, so write the error to console.
            let errorMsg = 'D8 agGrid restrictInput Error: ';
            // ptName is available but there are issues.
            if (
              ptName !== '' &&
              typeof aggridGSJSON.parserTypes[ptName] === 'undefined'
            ) {
              // parserType not found, tell the user.
              console.log(
                Drupal.t(
                  errorMsg + ptName + ' parserType not found.', {}, {
                    context: 'aggrid error parserType not found'
                  }
                )
              );
            }
            else if (
              typeof aggridGSJSON.parserTypes[ptName] !== 'undefined' &&
              typeof aggridGSJSON.parserTypes[ptName].restrictInput === 'undefined'
            ) {
              // No restriction set on parserType. no error message.
            }
            else if (
              typeof aggridGSJSON.parserTypes[ptName] !== 'undefined' &&
              typeof aggridGSJSON.restrictInput[aggridGSJSON.parserTypes[ptName].restrictInput].regEx !== 'undefined'
            ) {
              // Restriction set but no regEx defined.
              console.log(
                Drupal.t(errorMsg + ptName +
                  ' regEx missing for restrictInput "jserr_restrictInput"', {
                    jserr_restrictInput: aggridGSJSON.parserTypes[ptName].restrictInput
                  }, {
                    context: 'aggrid error restrictInput missing regEx'
                  }
                )
              );
            }
          }
        }

        function rowSettingsCheck(rscParamsRowId, rscParamsColDefField, field) {
          // Check values and return a result.
          if (
            aggridJSON_rowSettings !== null &&
            aggridJSON_rowSettings !== ''
          ) {
            if (
              typeof aggridJSON_rowSettings[rscParamsRowId] !== 'undefined' &&
              typeof aggridJSON_rowSettings[rscParamsRowId][rscParamsColDefField] !== 'undefined' &&
              typeof aggridJSON_rowSettings[rscParamsRowId][rscParamsColDefField][field] !== 'undefined'
            ) {
              return aggridJSON_rowSettings[rscParamsRowId][rscParamsColDefField][field];
            }
            else if (
              typeof aggridJSON_rowSettings[rscParamsRowId] !== 'undefined' &&
              typeof aggridJSON_rowSettings[rscParamsRowId].rowDefault !== 'undefined' &&
              typeof aggridJSON_rowSettings[rscParamsRowId].rowDefault[field] !== 'undefined'
            ) {
              return aggridJSON_rowSettings[rscParamsRowId].rowDefault[field];
            }
            else if (
              typeof aggridJSON_rowSettings.default !== 'undefined' &&
              typeof aggridJSON_rowSettings.default[rscParamsColDefField] !== 'undefined' &&
              typeof aggridJSON_rowSettings.default[rscParamsColDefField][field] !== 'undefined'
            ) {
              return aggridJSON_rowSettings.default[rscParamsColDefField][field];
            }
            else if (
              typeof aggridJSON_rowSettings.default !== 'undefined' &&
              typeof aggridJSON_rowSettings.default.rowDefault !== 'undefined' &&
              typeof aggridJSON_rowSettings.default.rowDefault[field] !== 'undefined'
            ) {
              return aggridJSON_rowSettings.default.rowDefault[field];
            }
          }

          return ''; // all else fails, send blank.
        }

        function rowSpanFunc(params) {
          let rowSpanVar;
          let rscParamsRowId;
          let rscParamsColDefField;

          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId = params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          // Check for a cell setting.
          rowSpanVar = rowSettingsCheck(
            rscParamsRowId,
            rscParamsColDefField,
            'rowSpan'
          );

          // If var is good, send it on. Otherwise, send default.
          if (rowSpanVar !== '') {
            return rowSpanVar;
          }
          else {
            return 1;
          }
        }

        // Check error.
        function validationErrorCheck(paramsNodeId, paramsColumnColId) {
          // return status of cell.
          return aggridValidationErrors[item] !== '' &&
              typeof aggridValidationErrors[item][paramsNodeId] !== 'undefined' &&
              typeof aggridValidationErrors[item][paramsNodeId][paramsColumnColId] !== 'undefined';

        }

        // Clear the error.
        function validationErrorClear(params, ptName) {
          let paramsNodeId = params.node.id;
          let paramsColumnColId = params.column.colId;

          // Clear any previous error on ptName if available.
          if (ptName !== '' &&
              typeof aggridValidationErrors[item][paramsNodeId] !== 'undefined' &&
              typeof aggridValidationErrors[item][paramsNodeId][paramsColumnColId] !== 'undefined' &&
              typeof aggridValidationErrors[item][paramsNodeId][paramsColumnColId][ptName] !== 'undefined') {
            delete aggridValidationErrors[item][paramsNodeId][paramsColumnColId][ptName];
            // Clear field if empty.
            if ($.isEmptyObject(aggridValidationErrors[item][paramsNodeId][paramsColumnColId])) {
              delete aggridValidationErrors[item][paramsNodeId][paramsColumnColId];
            }
            // clear row if empty.
            if ($.isEmptyObject(aggridValidationErrors[item][paramsNodeId])) {
              delete aggridValidationErrors[item][paramsNodeId];
            }
            // Make sure validation error dialog is updated.
            validationErrorUpdate();
          }
        }

        function validationErrorShow() {
          // show it.
          $('#' + aggridFieldName[item] + '_error').dialog({
            dialogClass: 'aggrid-error-dialog',
            height: 250,
            width: 400
          });

          // reset show error on regular editing.
          aggridShowError[item] = false;
        }

        function validationErrorUpdate() {
          let errorRow = '';

          // loop through and get errors for dialog.
          $.each(aggridValidationErrors[item], function (rowindex) {
            errorRow += Drupal.t(
                '<h3>Row jserr_rowNum: jserr_rowLabel</h3>', {
                  jserr_rowNum: Number(rowindex) + 1,
                  jserr_rowLabel: aggridRowLabels[rowindex]
                }, {
                  context: 'Display aggrid cell validation error list: row'
                }
            );
            errorRow += '<div>';
            $.each(aggridValidationErrors[item][rowindex],
                function (fieldname) {
                  errorRow += Drupal.t(
                      '<h4>jserr_headerName</h4>', {
                        jserr_headerName: aggridFieldFullHeaderName[fieldname]
                      }, {
                        context: 'Display aggrid cell validation error messages list: column'
                      }
                  );
                  $.each(
                      aggridValidationErrors[item][rowindex][fieldname],
                      function (errType, errItems) {
                        // If NaN, then replace with [text].
                        if (errItems.newValue === 'NaN') {
                          errItems.newValue = '[text]';
                        }
                        // Add the error.
                        errorRow += Drupal.t(
                            '<p>"jserr_newValue" did not validate. jserr_message [jserr_errType]</p>',
                            {
                              jserr_newValue: errItems.newValue,
                              jserr_errType: errType,
                              jserr_message: errItems.message
                            },
                            {
                              context: 'Display aggrid cell validation error messages to end users'
                            }
                        );
                      }
                  );
                });
            errorRow += '</div>';
          });

          // if no errors, tell em.
          if (errorRow === '') {
            // reset show error on regular editing.
            errorRow = '<h2>No validation errors</h2>';
          }

          // change dialog html.
          $('#' + aggridFieldName[item] + '_error').html('<div>' + errorRow + '</div>');
        }

        function valueConstraintFunc(params) {
          // valueConstraintFunc is executed from valueParserFunc after positive return.
          let ctJSON;
          let ftName;
          let paramsNodeId = params.node.id;
          let paramsColumnColId = params.column.colId;
          let constraintType_errorJSON = {};

          let value;

          let rscParamsRowId;
          let rscParamsColDefField;

          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId = params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          ctJSON = rowSettingsCheck(
            rscParamsRowId,
            rscParamsColDefField,
            'constraintType'
          );

          ftName = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'formatType'
          );

          // If the ctName is set and constraint/errorMsg is available, move forward.
          // Otherwise it's an error.
          if (
            ctJSON !== '' &&
            typeof ctJSON.constraint !== 'undefined' &&
            typeof ctJSON.errorMsg !== 'undefined'
          ) {
            let constIf = ctJSON.constraint;

            // Clear the current error for this item, if there is one.
            validationErrorClear(params, 'constraint');

            // Check the value against the constraint. if it's good, apply.
            // Otherwise tell the user and flip back.
            if (eval(constIf)) {
              // We're good, let the new value go into place.
              return params.newValue;
            }
            else {
              // New value doesn't meet the constraint. Process.
              if (params.newValue === '') {
                // If delete, return nothing.
                value = '';
                if (ftName !== '' &&
                    typeof aggridGSJSON.formatTypes[ftName] !== 'undefined' &&
                    aggridGSJSON.formatTypes[ftName].type === 'number') {
                  // If delete and number, just change to zero.
                  value = 0;
                }
                return value;
              }
              else {
                // Has a newValue, though it's not meeting requirements.
                // Process it. Make sure our variable is ready for data.
                constraintType_errorJSON[paramsNodeId] = {};
                constraintType_errorJSON[paramsNodeId][paramsColumnColId] = {};

                // Set the error values. old, new, and the message.
                constraintType_errorJSON[paramsNodeId][paramsColumnColId]['constraint'] = {
                  oldValue: params.oldValue,
                  newValue: params.newValue,
                  message: ctJSON.errorMsg
                };

                // Merge without writing over objects.
                aggridValidationErrors[item] = smartJSONextend(
                    aggridValidationErrors[item],
                    constraintType_errorJSON
                );

                // Make sure validation error dialog is updated.
                validationErrorUpdate();

                // Place the value and refresh the cells, this fixed an issue with multi copy and paste.
                gridOptions[item].rowData[paramsNodeId][paramsColumnColId] = params.oldValue;
                gridOptions[item].api.refreshCells();

                // Make sure this is marked to show.
                aggridShowError[item] = true;

                // Show it.
                validationErrorShow();

                // Return it.
                return params.oldValue;
              }
            }
          }
          else {
            // If the code reaches here, it's an error, so write the error to console.
            let errorMsg = 'D8 agGrid constraintType Error: ';
            //  is available but there are issues.
            if (
              ctJSON === '' &&
              typeof ctJSON === 'undefined' &&
              typeof ctJSON.constraint === 'undefined' &&
              typeof ctJSON.errorMsg === 'undefined'
            ) {
              // found in aggridGSJSON, but missing the necessary objects.
              console.log(
                Drupal.t(
                  errorMsg + ' missing constraint or errorMsg', {}, {
                    context: 'aggrid error constraintType missing constraint or errorMsg'
                  }
                )
              );
            }

            // Error but return the newValue anyway.
            return params.newValue;
          }
        }

        // Change the value if needed when editing.
        function valueConversion(params) {
          let ftName;

          let rscParamsRowId;
          let rscParamsColDefField;
          let inputField = 'input.ag-cell-edit-input';

          if (params.node === null) {
            rscParamsRowId = 0;
          }
          else if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId = params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          ftName = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'formatType'
          );

          // no ftName? error or no settings. Just allow any value.
          if (
              ftName !== '' &&
              ftName === 'numPer'
          ) {
            // Change decimal to whole number percent.
            if ($(inputField).length > 0) {
              let curVal = params.value;

              // If user is just clicking to edit, change decimal.
              if (Number(curVal) === Number($(inputField).val())) {
                $(inputField).val($(inputField).val() * 100).select();
              }
            }
          }
        }

        // Format the values.
        function valueFormatterFunc(params) {
          let ftName;

          let rscParamsRowId;
          let rscParamsColDefField;

          if (params.node === null) {
            rscParamsRowId = 0;
          }
          else if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId = params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          ftName = rowSettingsCheck(
            rscParamsRowId,
            rscParamsColDefField,
            'formatType'
          );

          // no ftName? error or no setting. Just allow any value.
          if (
            ftName !== '' &&
            typeof aggridGSJSON.formatTypes[ftName] !== 'undefined' &&
            aggridGSJSON.formatTypes[ftName].type === 'number'
          ) {
            // Set variables for formatNumber.
            let locale = 'en'; // Default to English (USA).
            let options = {};
            let ftItem = aggridGSJSON.formatTypes[ftName];

            // Optional settings for NumberFormat for locale and options.
            if (typeof ftItem.locale !== 'undefined') {
              locale = ftItem.locale;
            }
            if (typeof ftItem.options !== 'undefined') {
              options = ftItem.options;
            }

            // Make number value NaN or Infinity just 0.
            if (params.value === 'NaN' || params.value === 'Infinity'){
              params.value = 0;
            }

            // if it is a number, format it. otherwise, dont.
            if ($.isNumeric(params.value)) {
              // Format based on locale and extra options.
              return Intl.NumberFormat(locale, options).format(params.value);
            }
            else {
              // Return without format.
              return params.value;
            }
          }
          else {
            if (ftName !== '' && $.isNumeric(params.node.id)) {
              console.log(
                Drupal.t(
                  'D8 agGrid formatType Error: jserr_ftName or "type" not found in function',
                  {
                    jserr_ftName: ftName
                  }
                )
              );
            }

            // Return without format.
            return params.value;
          }
        }

        function valueGetterFunc(params) {
          let valueGetterItems;
          let valueGot;
          // Auto value change when needed.
          let rscParamsRowId;
          let rscParamsColDefField;

          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId =
              params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          valueGetterItems = rowSettingsCheck(
            rscParamsRowId,
            rscParamsColDefField,
            'valueGetter'
          );

          // Exclude if getter is empty and exclude pinned row header and footer.
          if (
            valueGetterItems !== '' &&
            typeof gridOptions[item].rowData[params.node.id] !== 'undefined' &&
            typeof gridOptions[item].rowData[params.node.id][params.column.colId] !== 'undefined'
          ) {
            // Return valueGetter.
            valueGot = eval(valueGetterItems);

            // Update the cell value.
            gridOptions[item].rowData[params.node.id][params.column.colId] = valueGot;
            makeJson(item); // Make sure the rowData output is updated.
            // Return it
            return valueGot;
          }
          else {
            // else, just return the current value.
            return params.data[params.column.colId];
          }
        }

        function valueParserFunc(params) {
          let ptName;
          let ftName;
          let paramsNodeId = params.node.id;
          let paramsColumnColId = params.column.colId;
          let parserType_errorJSON = {};

          let rscParamsRowId;
          let rscParamsColDefField;
          let restrictInput;

          let newValue = params.newValue.valueOf();
          let value = params.oldValue;

          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId =
              params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          ptName = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'parserType'
          );

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          ftName = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'formatType'
          );

          // Get restricted input type.
          if (
              typeof aggridGSJSON.parserTypes[ptName] !== 'undefined' &&
              typeof aggridGSJSON.parserTypes[ptName].restrictInput !== 'undefined'
          ) {
            restrictInput = aggridGSJSON.parserTypes[ptName].restrictInput;
          }

          // Move forward if ptName is set and regEx/errorMsg is available.
          // Otherwise it is an error.
          if (
            ptName !== '' &&
            ptName !== 'dropdown' &&
            typeof aggridGSJSON.parserTypes[ptName] !== 'undefined' &&
            typeof aggridGSJSON.parserTypes[ptName].regEx !== 'undefined' &&
            typeof aggridGSJSON.parserTypes[ptName].errorMsg !== 'undefined'
          ) {
            let regEx = new RegExp(aggridGSJSON.parserTypes[ptName].regEx);

            // Clear the current error for this item, if there is one.
            validationErrorClear(params, ptName);

            // Check the value against the regEx. if it is good, apply.
            // Otherwise tell the user and flip back.
            if (regEx.test(newValue)) {
              // Update the cell value.
              gridOptions[item].rowData[params.node.id][params.column.colId] = newValue;
              params.newValue = newValue;
              gridOptions[item].api.refreshCells();
              // Value is potentially correct for the type, now check any
              // constraints.
              value = valueConstraintFunc(params);

              // Change percentage to decimal.
              if (ftName !== '' && ftName === 'numPer' && Number(value) !== Number(params.oldValue)) {
                // Add a large dec due to javascript precision division issue with decimals
                value = Number(value) + .000000000001
                // Round it to drop off by 10 decimals.
                value = (value / 100).toFixed(10);

                // If paste, get the rowNode and update.
                if (aggridPasteStart[item]) {
                  gridOptions[item].rowData[paramsNodeId][paramsColumnColId] = value;
                  gridOptions[item].api.refreshCells();
                }
              }
              // If good and needs to be sent, send it.
              valueSendFunc(params, value);
              return value
            }
            else {
              // New value does not meet the requirements. Process.
              if (newValue === '') {
                // If delete, just change to zero if number. Otherwise blank.
                if (restrictInput === 'numeric') {
                  return 0;
                }
                else {
                  return newValue;
                }
              }
              else {
                // Has a newValue, though it is not meeting requirements.
                // Process it. Make sure our variable is ready for data.
                parserType_errorJSON[paramsNodeId] = {};
                parserType_errorJSON[paramsNodeId][paramsColumnColId] = {};
                parserType_errorJSON[paramsNodeId][paramsColumnColId][ptName] = {};

                // Set the error values. old, new, and the message.
                parserType_errorJSON[paramsNodeId][paramsColumnColId][ptName] = {
                  oldValue: params.oldValue,
                  newValue: newValue,
                  message: aggridGSJSON.parserTypes[ptName].errorMsg
                };

                // Merge without writing over objects.
                aggridValidationErrors[item] = smartJSONextend(
                    parserType_errorJSON,
                    aggridValidationErrors[item]
                );

                // Make sure validation error dialog is updated.
                validationErrorUpdate();

                // Place the value and refresh the cells.
                // this fixed an issue with multi copy and paste.
                gridOptions[item].rowData[paramsNodeId][paramsColumnColId] = params.oldValue;
                gridOptions[item].api.refreshCells();

                // Make sure this is marked to show.
                aggridShowError[item] = true;

                // Show it.
                validationErrorShow();

                // Return it.
                return value;
              }
            }
          }
          else if (
            ptName === 'dropdown' &&
            typeof aggridGSJSON.parserTypes[ptName] !== 'undefined' &&
            aggridGSJSON.parserTypes[ptName].errorMsg !== 'undefined'
          ) {
            // Process a dropdown check. if it is not in the values for
            // cellEditorParams, then err.

            // Clear the current error for this item, if there is one.
            validationErrorClear(params, ptName);

            if (
              typeof params.colDef.cellEditorParams.values !== 'undefined' &&
              params.colDef.cellEditorParams.values.indexOf(newValue) !== -1
            ) {
              // Update the cell value.
              gridOptions[item].rowData[params.node.id][params.column.colId] = newValue;
              // Value is potentially correct for the type, now check any constraints.
              value = valueConstraintFunc(params);
              // If good and needs to be sent, send it.
              valueSendFunc(params, value);
              return value;
            }
            else {
              // Has a newValue, though it is not meeting requirements. Process it.
              gridOptions[item].rowData[paramsNodeId][paramsColumnColId] = params.oldValue;
              gridOptions[item].api.refreshCells();

              return value;
            }
          }
          else {
            // If the code reaches here, it is an error.
            let errorMsg = 'D8 agGrid parserType Error: ';
            // ptName is available but there are issues.
            if (
              ptName !== '' ||
              (ptName !== '' &&
                typeof aggridGSJSON.parserTypes[ptName] === 'undefined')
            ) {
              // not found in aggridGSJSON at all, tell the user.
              console.log(
                Drupal.t(
                  errorMsg + ptName + ' not found.', {}, {
                    context: 'aggrid error parserType not found'
                  }
                )
              );
            }
            else if (
              ptName === '' &&
              typeof aggridGSJSON.parserTypes === 'undefined' &&
              typeof aggridGSJSON.parserTypes[ptName] === 'undefined' &&
              typeof aggridGSJSON.parserTypes[ptName].regEx === 'undefined' &&
              typeof aggridGSJSON.parserTypes[ptName].errorMsg === 'undefined'
            ) {
              // found in aggridGSJSON, but missing the necessary objects.
              console.log(
                Drupal.t(
                  errorMsg + ptName + ' missing regEx or errorMsg', {}, {
                    context: 'aggrid error parserType missing regEx or errorMsg'
                  }
                )
              );
            }

            // Update the cell value.
            gridOptions[item].rowData[params.node.id][params.column.colId] = newValue;
            // Value is correct for type, check constraints.
            value = valueConstraintFunc(params);
            // If good and needs to be sent, send it.
            valueSendFunc(params, value);
            return value;
          }
        }

        // Used with valueSend JSON item to send values to another aggrid on the same node.
        function valueSendFunc(params, cellValue) {
          let valueSendItems;
          let valueSet;
          // Auto value change when needed.
          let rscParamsRowId;
          let rscParamsColDefField;

          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId = params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          rscParamsColDefField = params.colDef.field;

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          valueSendItems = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'valueSend'
          );

          // Exclude if getter is empty and exclude pinned row header and footer.
          if (
              valueSendItems !== '' &&
              typeof gridOptions[item].rowData[params.node.id] !== 'undefined' &&
              typeof gridOptions[item].rowData[params.node.id][params.column.colId] !== 'undefined'
          ) {
            $.each(valueSendItems, function(key, value){
              // Look up the other grid and assign the variables.
              let t = $('div[data-aggrid-id="' + value['aggrid_id'] + '"]').attr('id');
              let i = value['id'];
              let f = (value['field'] = '[this]') ? params.column.colId : value['field'];
              let g = gridOptions[t];
              // Check if gridOption exists.
              // @todo need to add proper error messages.
              if (typeof g !== 'undefined') {
                let rowNode = g.api.getRowNode(i);
                // Check if rowNode exists.
                if (typeof rowNode !== 'undefined') {
                  // Found... change the values and refresh.
                  rowNode['data'][f] = cellValue;
                  g.api.refreshCells();
                }
              }
            });

            return true;
          }
          else {
           return false;
          }
        }

        // #######
        // ####### Context Menu Items
        // #######

        function getContextMenuItems(params) {
          let result = [
            // built in copy item.
            'copy',
            'copyWithHeaders',
            {
              // custom item.
              name: 'Excel Export (xls)',
              action: function() {
                onBtExport(params, 'xls');
              },
              icon: '<i class="ag-icon ag-icon-data" />'
            },
            {
              // custom item.
              name: 'CSV Export',
              action: function() {
                onBtExport(params, 'csv');
              },
              icon: '<i class="ag-icon ag-icon-data" />'
            }
          ];

          // Check if cell error. If error, give show box option.
          if (validationErrorCheck(params.node.id, params.column.colId)) {
            result.push({
              // Show validation error box.
              name: 'Show Error',
              action: validationErrorShow,
              icon: '<i class="ag-icon ag-icon-eye" />'
            });
          }

          return result;
        }

        function onBtExport(params, type) {
          // Export Excel.
          params.columnGroups = true;
          if (type === 'xls') {
            gridOptions[item].api.exportDataAsExcel(params);
          }
          else {
            gridOptions[item].api.exportDataAsCsv(params);
          }

        }

        // #######
        // ####### Helper Functions
        // #######

        // Cleanup Paste.
        function processCellFromClipboardFunc(params) {
          // Set variables.
          let ptName;
          let rscParamsRowId;
          let rscParamsColDefField;
          let restrictInput;

          // Set the value.
          let dataValue = params.value;

          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId = params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          // Set the field.
          rscParamsColDefField = params.column.colDef.field;

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          ptName = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'parserType'
          );

          // Get restricted input type.
          if (
              typeof aggridGSJSON.parserTypes[ptName] !== 'undefined' &&
              typeof aggridGSJSON.parserTypes[ptName].restrictInput !== 'undefined') {
            restrictInput = aggridGSJSON.parserTypes[ptName].restrictInput;
          }

          // Trim the edges just to make sure.
          dataValue = dataValue.trim();

          // If number, do clean-up.
          if (restrictInput === 'numeric' && dataValue !== '') {
            // ==== Numbers.
            // Remove dollar signs, percent, and commas.
            dataValue = dataValue.replace(/[,$%]/g, '');
          }

          // If blank, make zero.
          if (restrictInput === 'numeric' && (dataValue === '' || dataValue === null)) {
            dataValue = 0;
          }

          // Update the params value.
          return dataValue;
        }

        // Cleanup Copy.
        function processCellForClipboardFunc(params) {
          // Set variables.
          let ptName;
          let ftName;
          let rscParamsRowId;
          let rscParamsColDefField;
          let restrictInput;

          // Set the value.
          let dataValue = params.value;

          if (typeof params.node.rowPinned === 'undefined') {
            rscParamsRowId = params.node.id;
          }
          else {
            rscParamsRowId = params.node.rowPinned.substr(0, 1) + '-' + params.node.rowIndex;
          }

          // Set the field.
          rscParamsColDefField = params.column.colDef.field;

          // Check setting for cell, row, column, and then all columns.
          // First available is priority.
          ptName = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'parserType'
          );

          ftName = rowSettingsCheck(
              rscParamsRowId,
              rscParamsColDefField,
              'formatType'
          );

          // Get restricted input type
          if (typeof aggridGSJSON.parserTypes[ptName] !== 'undefined' && typeof aggridGSJSON.parserTypes[ptName].restrictInput !== 'undefined') {
            restrictInput = aggridGSJSON.parserTypes[ptName].restrictInput;
          }

          // If percent, then turn the decimal into a whole percent.
          if (restrictInput === 'numeric' && ftName === 'numPer') {
            dataValue = dataValue * 100;
          }

          // Update the params value.
          return dataValue;
        }

        // For adding/removing a focus class.
        function colFocusClass(params) {
          if (params.column !== null) {
            let colId = params.column.colId;

            // Remove any current focus class.
            $("div.aggrid-col-focus").removeClass("aggrid-col-focus");
            // Add to current.
            $("div[col-id='" + colId + "']").addClass("aggrid-col-focus");
          }
        }

        function columnTotal(field, idFrom, idTo) {
          // Used to help sum a spanning column total.
          let rowCount;
          let colCount;
          let valColumnTotal = 0;

          // Check if field is an array... if not, make it one.
          if (!Array.isArray(field)) {
            field = field.split(',');
          }

          // Check idFrom and idTo to make sure they are defined and correctly
          // span vs an incorrect crossover.
          if (
              typeof idFrom !== 'undefined' &&
              typeof idTo !== 'undefined' &&
              idFrom >= 0 &&
              idTo >= 0 &&
              idFrom <= idTo
          ) {
            // Loop through the span and sum the amounts.
            for (rowCount = idFrom; rowCount <= idTo; rowCount++) {
              for (colCount = 0; colCount <= field.length; colCount++) {
                if ($.isNumeric(gridOptions[item].rowData[rowCount][field[colCount]])) {
                  valColumnTotal += Number(gridOptions[item].rowData[rowCount][field[colCount]]);
                }
              }
            }
            // return the sum.
            return valColumnTotal;
          }
          else {
            // Issue with the idFrom and idTo, tell the user and return zero.
            console.log(
                Drupal.t(
                    'D8 agGrid columnTotal Error: jserr_field - Check idFrom & idTo for issues.',
                    {
                      jserr_field: field
                    },
                    {
                      context: 'aggrid error columnTotal idFrom & idTo not correct'
                    }
                )
            );
            return 0;
          }
        }

        // #######
        // ####### Build ag-Grid
        // #######

        // Build JSON for ag-Grid.
        var aggridJSON = {
          columnDefs: aggridJSON_colDefs,
          rowData: aggridJSON_rowData
        };

        // Merge Options Default + Grid options.
        aggridJSON_addOpt = smartJSONextend(aggridOpt, aggridJSON_addOpt);

        // Merge the grid.
        aggridJSON = smartJSONextend(aggridJSON_addOpt, aggridJSON);

        // Default Options for all ag-Grid.
        var default_gridOptions = {
          onCellFocused: function (params) {
            colFocusClass(params);
          },
          onCellEditingStarted: function (params) {
            restrictInputFunc(params);
            valueConversion(params);
          },
          onCellEditingStopped: function (params) {
          },
          onCellValueChanged: function (params) {
            if (aggridPasteStart[item]) {
              valueParserFunc(params);
            }

            makeJson(item);
          },
          onPasteStart: function () {
            aggridPasteStart[item] = true;
          },
          onPasteEnd: function (params) {
            aggridShowError[item] = false;
            aggridPasteStart[item] = false;
          },
          processCellFromClipboard: function(params) {
            return processCellFromClipboardFunc(params);
          },
          processCellForClipboard: function(params) {
            return processCellForClipboardFunc(params);
          },
          getContextMenuItems: getContextMenuItems,
          excelStyles: aggridExcelStyles
        };

        let default_gridOptions_rowSettingsOptions = {};
        default_gridOptions_rowSettingsOptions['defaultColDef'] = {};

        // If rowSettings are available, add other functions.
        if (aggridJSON_rowSettings !== null) {
          // rowSettings is there, so get the setting.
          default_gridOptions_rowSettingsOptions['defaultColDef'] = {
            cellClass: cellClassFunc,
            cellStyle: cellStyleFunc,
            colSpan: colSpanFunc,
            rowSpan: rowSpanFunc,
            valueFormatter: valueFormatterFunc
          };
          // Only add editable, valueGetter, and valueParser on Edit = true.
          if (aggridDataEdit[item]) {
            let default_gridOptions_rowSettingsOptions_edit = {
              cellClassRules: {
                'aggrid-cell-error': function(params) {return validationErrorCheck(params.node.id, params.colDef.field);}
              },
              editable: editableFunc,
              valueGetter: valueGetterFunc,
              valueParser: valueParserFunc
            };
            default_gridOptions_rowSettingsOptions['defaultColDef'] =
              $.extend(true,
                  default_gridOptions_rowSettingsOptions['defaultColDef'],
                  default_gridOptions_rowSettingsOptions_edit
              );
          }
        }
        else if (aggridDataEdit[item]) {
          // No rowSettings and edit = true, so add some defaults.
          default_gridOptions_rowSettingsOptions['defaultColDef'] = {
            editable: true
          };
        }

        // Merge grid options together.
        gridOptions[item] = $.extend(
            true,
            default_gridOptions,
            default_gridOptions_rowSettingsOptions
        );

        // Add the Default Options.
        gridOptions[item] = $.extend(true, gridOptions[item], aggridJSON);

        // Apply the license if it is available.
        if (aggridLicense !== '' && agGrid.LicenseManager) {
          agGrid.LicenseManager.setLicenseKey(aggridLicense);
        }

        // Get the ag-Grid Div and start it up.
        eGridDiv[item] = document.querySelector('#' + item);

        // create the grid passing in the div to use together with the columns
        // & data we want to use
        new agGrid.Grid(eGridDiv[item], gridOptions[item]);

        // Make sure the grid columns fit.
        gridOptions[item].api.sizeColumnsToFit();

        // Auto resize when window shifts in size.
        window.addEventListener('resize', function() {
          setTimeout(function() {
            gridOptions[item].api.sizeColumnsToFit();
          })
        });

        // Apply all columns to this variable.
        aggridFields = gridOptions[item].columnApi.getAllGridColumns();

        // Loop through columns and get the FULL name for each field.
        // Just run once for ag-Grid.
        $.each(aggridFields, function (rowIndex) {
          aggridFieldFullHeaderName[aggridFields[rowIndex].colId] = [];
          tempList = [];
          getHeaderParentItems(aggridFields[rowIndex]);
          // Take collected headers, reverse them, separate by dashes.
          // clear out blanks on up to a header with 3 rows.
          aggridFieldFullHeaderName[aggridFields[rowIndex].colId] =
            tempList
              .reverse()
              .filter(function (e) {
                return e;
              })
              .join(' - ');
        });

        getRowLabels();
      });
    }
  };

  Drupal.behaviors.HtmlIntegration = {
    attach: function (context) {

      // Make sure to only run once per page for each item.
      var selector = $('.aggrid-html-widget');
      var idArray = [];

      selector.each(function () {
        idArray.push(this.id);
      });

      $.each(idArray, function (index, item) {

        // Set the aggrid Table variable.
        let aggridTable = $('#' + item);

        // Only run this once.
        if (once('aggridTable', aggridTable).length !== aggridTable.length) {
          return;
        }

        // Set variables for formatNumber.
        let locale = 'en'; // Default to English (USA)
        let options = {};

        aggridTable.each(function () {
          // Loop through headers and set width/min-width if available.
          $('th', this).each(function () {
            $(this).css('width', $(this).attr('data-width'));
            $(this).css('min-width', $(this).attr('data-minWidth'));
          });

          // Loop through rows for formatting classes.
          $('td', this).each(function () {
            let td_value = $(this).text();
            let td_class = "";

            if ($(this).attr('class')) {
              td_class = $(this).attr('class').split(' ');
            }

            if (td_value !== '' && td_value !== '+') {
              for (let i in td_class) {
                if (td_class[i].substring(0, 17) === 'aggrid-html-ftype') {

                  let ftName = td_class[i].substring(18);
                  let ftItem = aggridGSJSON.formatTypes[ftName];

                  // Optional settings for NumberFormat for locale and options.
                  if (typeof ftItem.locale !== 'undefined') {
                    locale = ftItem.locale;
                  }
                  if (typeof ftItem.options !== 'undefined') {
                    options = ftItem.options;
                  }

                  let formatter = new Intl.NumberFormat(locale, options);

                  $(this).text(formatter.format(td_value));
                }
              }
            }

          });

        });

      });

    }
  };

  // All key events
  $('.aggrid-widget').bind('keypress keydown keyup', function(e){
    // No submit on aggrid with enter (allow enter cell for edit).
    if (e.keyCode === 13) { e.preventDefault(); }
  });
})(jQuery, Drupal, drupalSettings, once);
