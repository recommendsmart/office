(function(once) {

  const deprecatedMessage = 'The selected widget is deprecated and will be removed. Evaluate migrating to a stable widget.';

  window.optionsFilter = {
    states: [],

    initIndex: function(index = 0) {
      optionsFilter.states[index] = {};
    },

    setContainer: function(container, index = 0) {
      optionsFilter.states[index].container = container;
      optionsFilter.states[index].widgets = Array.from(container.querySelectorAll('.js-form-type-radio'));
      var deprecationCheckbox = container.querySelector('input.deprecation-checkbox');
      optionsFilter.states[index].deprecatedFilterValue = true;
      if (deprecationCheckbox !== null) {
        optionsFilter.states[index].deprecatedFilterValue = container.querySelector(
          'input.deprecation-checkbox').checked;
      }
      optionsFilter.states[index].infoLayer = container.querySelector('.currently-selected');
      optionsFilter.states[index].infoLayer.hidden = true;
      optionsFilter.states[index].warningLayer = container.querySelector('.warning');
      optionsFilter.states[index].warningLayer.hidden = true;

      optionsFilter.setDefaultWidgetInDOM(index);
    },

    getContainer: function(index = 0) {
      return optionsFilter.states[index].container;
    },

    getInfoLayer: function(index = 0) {
      return optionsFilter.states[index].infoLayer;
    },

    setSearchboxValue: function(value, index = 0) {
      optionsFilter.states[index].searchboxValue = value;
    },

    getSearchboxValue: function(index = 0) {
      return optionsFilter.states[index].searchboxValue;
    },

    setDeprecatedFilterValue: function(value, index = 0) {
      optionsFilter.states[index].deprecatedFilterValue = value;
    },

    getDeprecatedFilterValue: function(index = 0) {
      return optionsFilter.states[index].deprecatedFilterValue;
    },

    setSelectedWidget: function(selectedWidget, index = 0) {
      optionsFilter.states[index].selectedWidget = selectedWidget;
    },

    getSelectedWidget: function(index = 0) {
      return optionsFilter.states[index].selectedWidget;
    },

    setDefaultWidgetInDOM: function(index = 0) {
      const container = optionsFilter.getContainer(index);
      const selectedWidget = container.querySelector('input[type="radio"]:checked');
      if (selectedWidget === null) {
        return null;
      }
      optionsFilter.selectWidget(selectedWidget, index);
    },

    setSearchboxValueInDOM: function(value, index = 0) {
      const container = optionsFilter.getContainer(index);
      const searchBox = container.querySelector('input.search-box');
      searchBox.value = value;
      optionsFilter.setSearchboxValue(value);
    },

    selectWidget: function(selectedWidget, index = 0) {
      var widgetWrapper = selectedWidget.closest('.js-form-type-radio');
      optionsFilter.setSelectedWidget(widgetWrapper, index);
      optionsFilter.states[index].widgets.map(function(element) {
        element.classList.remove('form-type--radio__selected');
      });
      widgetWrapper.classList.add('form-type--radio__selected');
      optionsFilter.showWarningMessageIfNeeded(index);
      optionsFilter.setSearchboxValueInDOM(widgetWrapper.querySelector('.radio-details--machine-name').innerText);
    },

    showAllWidgets: function(index = 0) {
      optionsFilter.states[index].widgets.map(function(element) {
        element.hidden = false;
      });
    },

    showSelectedWidget: function(index = 0) {
      optionsFilter.getSelectedWidget(index).hidden = false;
    },

    hideAllWidgets: function(index = 0) {
      optionsFilter.states[index].widgets.map(function(element) {
        element.hidden = true;
      });
    },

    hideAllWidgetsBut: function(widgetsToShow, index = 0) {
      optionsFilter.hideAllWidgets(index);
      for (const matchingElement of widgetsToShow) {
        matchingElement.parentElement.parentElement.hidden = false;
      }
    },

    filterWidgetsSearched: function(index = 0) {
      const container = optionsFilter.getContainer(index);
      const searchboxValue = optionsFilter.getSearchboxValue(index);
      const matchingWidgets = Array.from(
        container.querySelectorAll('.radio-details--search')).
        filter(e => e.innerText.search(searchboxValue) !== -1);
      optionsFilter.hideAllWidgetsBut(matchingWidgets, index);
    },

    isWidgetDeprecated: function(widget) {
      return widget.innerText.search('deprecated') >= 0;
    },

    getDeprecatedWidgets: function(index = 0) {
      return optionsFilter.states[index].widgets.filter(function(widget) {
        return optionsFilter.isWidgetDeprecated(widget);
      });
    },

    filterWidgetsDeprecated: function(index = 0) {
      var showDeprecated = optionsFilter.getDeprecatedFilterValue(index);
      optionsFilter.getDeprecatedWidgets(index).map(function(widget) {
        if (!showDeprecated) {
          widget.hidden = true;
        }
      });
    },

    showWarningMessageIfNeeded: function(index = 0) {
      var selectedWidget = optionsFilter.getSelectedWidget(index);
      optionsFilter.states[index].warningLayer.hidden = true;
      optionsFilter.states[index].warningLayer.innerText = '';
      if (optionsFilter.isWidgetDeprecated(selectedWidget)) {
        optionsFilter.states[index].warningLayer.hidden = false;
        optionsFilter.states[index].warningLayer.innerText = deprecatedMessage;
      }
    },

    refreshWidgets: function(index = 0) {
      optionsFilter.showAllWidgets(index);
      optionsFilter.filterWidgetsSearched(index);
      optionsFilter.filterWidgetsDeprecated(index);
      optionsFilter.showSelectedWidget(index);
    },

    renderSelectedWidget: function(index = 0) {
      var info = optionsFilter.getInfoLayer(index);
      var selectedWidget = optionsFilter.getSelectedWidget(index);

      var id = selectedWidget.querySelector('input[type="radio"]').value;
      var name = selectedWidget.querySelector('.radio-details--human-name').innerText;
      var description = selectedWidget.querySelector('.radio-details--description').innerText;
      var status = selectedWidget.querySelector('.radio-details_remote-status').innerText;
      var languages = selectedWidget.querySelector('.radio-details--languages').innerHTML;
      var source = selectedWidget.querySelector('.radio-details--source').innerText;
      var version = selectedWidget.querySelector('.radio-details--version').innerText;
      var createdDate = selectedWidget.querySelector('.radio-details--created').innerText;
      var updatedDate = selectedWidget.querySelector('.radio-details--updated').innerText;
      const imgElement = selectedWidget.querySelector('.radio-details--image');
      var img = imgElement ? imgElement.outerHTML : '';
      var previewUrl = selectedWidget.querySelector('.radio-details--preview-url').innerText;
      info.innerHTML = Drupal.theme('currentlySelectedWidget', id, name, description, status, languages, version,
        source, createdDate, updatedDate, img, previewUrl);
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
    },
  };

  const subscribeSearchboxToChanges = function(index = 0) {
    const container = optionsFilter.getContainer(index);
    const searchBox = container.querySelector('input.search-box');
    if (searchBox === null) {
      return;
    }
    searchBox.addEventListener('input', function(event) {
      optionsFilter.setSearchboxValue(event.target.value, index);
      optionsFilter.refreshWidgets(index);
    });
  };

  const includeResetButton = function(index) {
    const container = optionsFilter.getContainer(index);
    const searchBox = container.querySelector('input.search-box');
    if (searchBox === null) {
      return;
    }
    var resetButton = document.createElement('button');
    resetButton.classList.add('reset-search-box');
    resetButton.innerText = '⨯';
    resetButton.title = 'Reset filter';
    resetButton.addEventListener('click', function(event) {
      event.preventDefault();
      container.querySelector('input.search-box').value = '';
      optionsFilter.setSearchboxValue('', index);
      optionsFilter.refreshWidgets(index);
      event.target.blur();
    });

    searchBox.parentElement.insertBefore(resetButton, searchBox.nextSibling);
  };

  const subscribeDeprecatedSearchboxToChanges = function(index = 0) {
    const container = optionsFilter.getContainer(index);
    const deprecationCheckbox = container.querySelector('input.deprecation-checkbox');
    if (deprecationCheckbox === null) {
      return;
    }
    deprecationCheckbox.addEventListener('change', function(event) {
      optionsFilter.setDeprecatedFilterValue(event.target.checked, index);
      optionsFilter.refreshWidgets(index);
    });
  };

  const subscribeRadiosToChanges = function(index = 0) {
    const container = optionsFilter.getContainer(index);
    var radios = once('radio-change-subscribed', 'input[type="radio"]', container);
    radios.map(function(radio) {
      radio.addEventListener('change', function(event) {
        optionsFilter.selectWidget(event.target, index);
        optionsFilter.refreshWidgets(index);
        optionsFilter.renderSelectedWidget(index);
      });
    });
  };

  Drupal.behaviors.optionsFilter = {
    attach: (context, settings) => {
      once('options-filter', '.widget-type--selector', context).map(function(container, index) {
        optionsFilter.initIndex(index);
        optionsFilter.setContainer(container, index);
        subscribeSearchboxToChanges(index);
        subscribeDeprecatedSearchboxToChanges(index);
        subscribeRadiosToChanges(index);
        includeResetButton(index);
        optionsFilter.refreshWidgets(index);
        if (optionsFilter.getSelectedWidget(index) !== undefined) {
          optionsFilter.renderSelectedWidget(index);
        }
      });
    },
  };

  Drupal.theme.currentlySelectedWidget = (
    id, name, description, status, languages, version, source, createdDate,
    updatedDate, img, previewUrl) => `
    <summary>${Drupal.t('ℹ️ More information about <em>@name</em>',
    {'@name': name})}</summary>
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
      <div class='currently-selected--image--wrapper${img
    ? ''
    : ' currently-selected--image--wrapper__empty'}'>
        ${img ? img : ''}
      </div>
    </div>
    ${previewUrl
    ? `<div style='display: none' id='preview-url'>${previewUrl}</div>
      <div class="try-now--wrapper"><a href="#preview-url" class='try-now button button--primary'>${Drupal.t(
      'Try now')}</a></div>`
    : ''
  }`;
}(once));
