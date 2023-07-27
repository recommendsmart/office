/**
 * @file
 * Custom JS for the taxonomy hierarchical select widget.
 */

(function (drupalSettings, $, once) {
  'use strict';

  /**
   * Attach behavior for JSON Fields.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.hierarchical_select = {
    attach: function (context) {
      $(once('hierarchical-select', '.hierarchical_select', context)).each(function () {
        let parents = $(this).data('parents');
        let parent = $(this).parent();
        let vocabulary = $(this).data('vocabulary');
        let category = drupalSettings.datafieldHierarchicalSelect[vocabulary];
        let url = drupalSettings.datafieldHierarchicalAjax[vocabulary];
        let $categorySelect = $(this).parent();
        createSelects(parents);
        if (parents != undefined) {
          $.ajax({
            url: url,
            type: "POST",
            data: {parent: parents},
            success: function (response) {
              let data = response.data;
              if (data != undefined) {
                category = [...category, ...data];
                createSelects(parents);
              }
            }
          });
        }
        function createSelects(parents) {
          parent.find('select').remove();
          let valDefault = (typeof parents == 'string') ? parents.split('-') : [parents];
          let index = 0;
          $.each(category, function(i, obj) {
            let select = $('<select class="form-select form-element form-element--type-select">');
            select.change(handleSelectChange);
            select.append(createOption('', Drupal.t('- Select -')));
            $.each(obj, function(value, text) {
              select.append(createOption(value, text))
            });
            if(valDefault[index] != undefined){
              select.val(valDefault[index]);
            }
            parent.append(select);
            index++;
          });
        }
        function createOption(value, text) {
          return $('<option>').val(value).text(text);
        }
        function handleSelectChange() {
          let selectedCategory = $(this).val();
          parent.find('input').val(selectedCategory);
          $(this).nextAll('select').remove();
          if (selectedCategory) {
            $.ajax({
              url: url,
              type: "POST",
              data: {parent: selectedCategory},
              success: function (response) {
                let data = response.data;
                if (data != undefined) {
                  let keys = Object.keys(data);
                  let firstKey = keys[0];
                  let childCategories = data[firstKey];
                  if(childCategories != undefined){
                    let $childSelect = $('<select class="form-select form-element form-element--type-select child"></select>');
                    $childSelect.change(handleSelectChange);
                    $childSelect.append(createOption('', Drupal.t('- Select -')));
                    $.each(childCategories, function (index, category) {
                      $childSelect.append(createOption(index, category));
                    });
                    $categorySelect.append($childSelect);
                  }
                }
              },
              error: function (xhr, status, error) {
                console.error(error);
              }
            });
          }
        }

      });
    }
  };
}(drupalSettings, jQuery, once));
