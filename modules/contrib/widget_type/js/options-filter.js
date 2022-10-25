(function(once) {

  function toggleRadioButtons(inputQuery, fieldset) {
    if (inputQuery.length === 0) {
      for (var radio of fieldset.querySelectorAll('input[type="radio"]')) {
        radio.parentElement.parentElement.parentElement.hidden = false;
      }
      return;
    }
    var matching = Array.from(fieldset.querySelectorAll('.radio-details--search'))
      .filter(e => e.innerText.search(inputQuery) !== -1);
    // Hide all inputs, then show the matching and selected.
    for (var radio of fieldset.querySelectorAll('input[type="radio"]')) {
      // Always show the checked option.
      radio.parentElement.parentElement.parentElement.hidden = !radio.checked;
    }
    for (var matchingElement of matching) {
      matchingElement.parentElement.parentElement.hidden = false;
    }
  }

  function subscribeToChanges(fieldset) {
    var search = fieldset.querySelector('input[type="search"]');
    var changeSearchText = (event) => toggleRadioButtons(event.target.value, fieldset);
    search.addEventListener('input', changeSearchText);
  }

  var renderCurrentlySelected = (info, selectedContainer) => {
    var id = selectedContainer.querySelector('input[type="radio"]').value;
    var name = selectedContainer.querySelector('.radio-details--human-name').innerText;
    var description = selectedContainer.querySelector('.radio-details--description').innerText;
    var status = selectedContainer.querySelector('.radio-details--status').innerText;
    var languages = selectedContainer.querySelector('.radio-details--languages').innerHTML;
    var source = selectedContainer.querySelector('.radio-details--source').innerText;
    var version = selectedContainer.querySelector('.radio-details--version').innerText;
    var createdDate = selectedContainer.querySelector('.radio-details--created').innerText;
    var updatedDate = selectedContainer.querySelector('.radio-details--updated').innerText;
    const imgElement = selectedContainer.querySelector('.radio-details--image');
    var img = imgElement ? imgElement.outerHTML : '';
    var previewUrl = selectedContainer.querySelector('.radio-details--preview-url').innerText;
    info.innerHTML = Drupal.theme('currentlySelectedClComponent', id, name, description, status, languages, version, source, createdDate, updatedDate, img, previewUrl);
    if (previewUrl) {
      info.querySelector('a.button').addEventListener('click', (event) => {
        var button = event.target;
        var iframe = document.createElement('iframe');
        iframe.src = previewUrl;
        button.replaceWith(iframe);
        return false;
      });
    }
    info.hidden = false;
  };

  /**
   * Set up options filter
   */
  Drupal.behaviors.optionsFilter = {
    attach: (context, settings) => {
      var fieldsets = once('options-filter', '.widget-type--selector', context);
      for (var fieldset of fieldsets) {
        var search = fieldset.querySelector('input[type="search"]');
        if (!search.value) {
          var selected = fieldset.querySelector('input[type="radio"][checked]');
          search.value = selected ? selected.parentElement.parentElement.querySelector('.radio-details--machine-name').innerText : '';
        }
        toggleRadioButtons(search.value, fieldset);
        subscribeToChanges(fieldset);
      }
    },
  };

  Drupal.theme.currentlySelectedClComponent = (id, name, description, status, languages, version, source, createdDate, updatedDate, img, previewUrl) => `
    <summary>${Drupal.t('ℹ️ More information about <em>@name</em>', { '@name': name })}</summary>
    <p>${description}</p>
    <div class='image-table--wrapper'>
      <table>
        <tr><th>${Drupal.t('Version')}</th><td>${version}</td></tr>
        <tr><th>${Drupal.t('Created')}</th><td>${createdDate}</td></tr>
        <tr><th>${Drupal.t('Updated')}</th><td>${updatedDate}</td></tr>
        <tr><th>${Drupal.t('Source')}</th><td>${source}</td></tr>
        <tr><th>${Drupal.t('Status')}</th><td>${status}</td></tr>
        <tr><th>${Drupal.t('Available Languages')}</th><td>${languages}</td></tr>
      </table>
      <div class='currently-selected--image--wrapper${img ? '' : ' currently-selected--image--wrapper__empty'}'>
        ${img ? img : ''}
      </div>
    </div>
    ${previewUrl
      ? `<div style='display: none' id='preview-url'>${previewUrl}</div>
      <div class="try-now--wrapper"><a href="#preview-url" class='try-now button button--primary'>${Drupal.t('Try now')}</a></div>`
      : ''
    }`;

  /**
   * Render more info about the currently selected component.
   */
  Drupal.behaviors.currentlySelected = {
    attach: (context, settings) => {
      var fieldsets = once('currently-selected', '.widget-type--selector', context);
      for (var fieldset of fieldsets) {
        var info = fieldset.querySelector('.currently-selected');
        info.hidden = true;
        var selected = fieldset.querySelector('input[type="radio"][checked]');
        if (selected) {
          selected.parentElement.parentElement.parentElement.classList.add('form-type--radio__selected');
          renderCurrentlySelected(info, selected.parentElement.parentElement);
        }
        var radios = once('radio-change-subscribed', 'input[type="radio"]', fieldset);
        for (var radio of radios) {
          radio.addEventListener('change', (event) => {
            if (event.target.checked) {
              var all = event.target.parentElement.parentElement.parentElement.parentElement.querySelectorAll('input[type="radio"]');
              for (var item of all) {
                item.parentElement.parentElement.parentElement.classList.remove('form-type--radio__selected');
              }
              event.target.parentElement.parentElement.parentElement.classList.add('form-type--radio__selected');
              renderCurrentlySelected(info, event.target.parentElement.parentElement);
              var searchElement = fieldset.querySelector('input[type="search"]');
              var machineName = event.target.parentElement.parentElement.querySelector('.radio-details--machine-name').innerText;
              searchElement.value = machineName;
              toggleRadioButtons(machineName, fieldset);
            }
          });
        }
      }
    },
  };

}(once));
