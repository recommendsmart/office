/**
 * Implements ajax form.
 */

(function (Drupal) {

  /**
   * Implements ajax form behaviour.
   */
  Drupal.behaviors.frontendEditingAjax = {
    attach: function (context, settings) {
      // Callback for click function on an editable element
      const editingClick = function (e) {
        e.preventDefault();
        // Setup container
        //Frontend-editing sidebar and full widths
        const wideClassWidth = settings.full_width + '%';
        const sidebarClassWidth = settings.sidebar_width + '%';

        let editContainer = document.getElementById('editing-container');
        if (!editContainer) {
          editContainer = document.createElement('div');
          editContainer.id = 'editing-container';
          editContainer.classList.add('editing-container', 'editing-container--loading');
          document.body.append(editContainer);
          editContainer.style.width = sidebarClassWidth;
        }
        else {
          editContainer.innerHTML = '';
        }
        // Setup width toggle button
        const editWideClass = 'editing-container--wide';
        let widthToggle = document.createElement('div');
        widthToggle.className = 'editing-container__toggle';
        widthToggle.addEventListener('click', function (e) {
          if (editContainer.classList.contains(editWideClass)) {
            editContainer.classList.remove(editWideClass);
            editContainer.style.width = sidebarClassWidth;
          }
          else {
            editContainer.classList.add(editWideClass);
            editContainer.style.width = wideClassWidth;
          }
        });
        // Setup close button
        let editClose = document.createElement('div');
        editClose.className = 'editing-container__close';
        editClose.addEventListener('click', function (e) {
          editContainer.remove();
        });

        // Populate container
        editContainer.appendChild(widthToggle);
        editContainer.appendChild(editClose);
        // Load editing iFrame and append
        let iframe = document.createElement('iframe');
        iframe.onload = function () {
          editContainer.classList.remove('editing-container--loading');
        };
        editContainer.appendChild(iframe);
        iframe.src = e.target.href;
      }

      //Find all elements with attribute data-editing-url setup edit button and attach event listener
      document.querySelectorAll('[data-editing-url]').forEach(function (containerElement) {
        // Don't add button if it is already added
        const setupClass = "frontend-editing-processed";
        if (containerElement.classList.contains(setupClass)) {
          return;
        }
        else {
          containerElement.classList.add(setupClass);
        }
        // Add wrapper class to container
        containerElement.classList.add('frontend-editing');

        const urls = [];
        // Get url and create editing link
        urls.push(containerElement.getAttribute('data-editing-url'));
        if (containerElement.hasAttribute('data-move-up')) {
          urls.push(containerElement.getAttribute('data-move-up'));
        }
        if (containerElement.hasAttribute('data-move-down')) {
          urls.push(containerElement.getAttribute('data-move-down'));
        }
        for (let i = 0; i < urls.length; i++) {
          let editingElement = document.createElement('a');
          editingElement.href = urls[i];
          editingElement.classList.add('frontend-editing__action', 'frontend-editing__action--hidden', 'frontend-editing__action--' + i);
          let url_parts = urls[i].split('/');
          if (isNaN(url_parts[url_parts.length - 1])) {
            editingElement.classList.add('frontend-editing__action--' + url_parts[url_parts.length - 1]);
          }
          // Prepend editing link
          containerElement.prepend(editingElement);
          // Add hover function to editing link
          editingElement.addEventListener('mouseover', function () {
            containerElement.classList.add('frontend-editing--outline');
          });
          editingElement.addEventListener('mouseout', function () {
            containerElement.classList.remove('frontend-editing--outline');
          });
          if (i === 0) {
            editingElement.addEventListener('click', editingClick);
          }
        }
      });

    }
  };

  /**
   * Implements frontend editing toggle behaviour.
   *
   * @type {{attach: Drupal.behaviors.cancelFrontendEditing.attach}}
   */
  Drupal.behaviors.cancelFrontendEditing = {
    attach: function (context, settings) {
      const cancelButton = context.querySelector('#edit-cancel');
      if (!cancelButton || cancelButton.length === 0) {
        return;
      }
      cancelButton.addEventListener('click', function (e) {
        e.preventDefault();
        // Close the side panel
        Drupal.AjaxCommands.prototype.closeSidePanel({}, {}, 'success');
      });
    }
  }

  /**
   * Ajax command closeSidePanel.
   *
   * @param ajax
   * @param response
   * @param status
   */
  Drupal.AjaxCommands.prototype.closeSidePanel = function (ajax, response, status) {
    if (status === 'success') {
      // Reload the page
      window.parent.location.reload();
      // Remove iframe while we wait for the reload
      window.parent.document.getElementById('editing-container').remove();
    }
  }

  // Client side toggle for frontend editing
  Drupal.behaviors.toggleFrontendEditing = {
    attach: function (context, settings) {

      // Global variables
      const editingActionClass = 'frontend-editing__action';
      const storageId = 'frontendEditingToggle';
      const enabledValue = 'enabled';
      const disabledValue = 'disabled';
      const disabledClass = 'frontent_editing_toggle--disabled';
      const hiddenClass = 'frontend-editing__action--hidden';
      const toggleText = Drupal.t('Toggle frontend editing');
      const toggleId = 'frontent_editing_toggle';

      // Function to check if frontend editing is available
      const frontendEditingAvailable = function () {
        if (document.getElementsByClassName(editingActionClass).length > 0) {
          return true;
        }
        else {
          return false;
        }
      };

      // Function to check, that this behavior only modifies the DOM once
      const frontendEditingNeedsSetup = function () {
        let body = document.body;
        if (body) {
          const setupClass = "frontend-editing-processed";
          if (body.classList.contains(setupClass)) {
            return false;
          }
          else {
            body.classList.add(setupClass);
            return true;
          }
        }
        else {
          return false
        }
      }

      // Function to check local storage for a toggle setting
      const isToggled = function () {
        let toggleValue = localStorage.getItem(storageId);
        // If the value is not set, we assume it is enabled
        if (!toggleValue) {
          localStorage.setItem(storageId, enabledValue);
          return true;
        }
        if (toggleValue == enabledValue) {
          return true;
        }
        else {
          return false;
        }
      }

      // Function to check if admin theme Gin or Seven is used
      const isGin = function () {
        if (document.querySelector('[class*="gin--"]')) {
          return true;
        }
        else {
          return false;
        }
      }

      // Function to show or hide all frontend editing icons
      const showEditingIcons = function (enable) {
        let elements = document.getElementsByClassName(editingActionClass);
        for (let i = 0; i < elements.length; i++) {
          element = elements[i];
          if (enable) {
            element.classList.remove(hiddenClass);
          }
          else {
            element.classList.add(hiddenClass);
          }
        }
      }

      // Callback for click function in toolbar
      const toolbarClick = function (e) {
        e.preventDefault();
        let linkElement = e.currentTarget.firstElementChild;
        if (linkElement.classList.contains(disabledClass)) {
          // Toggle is disabled, enable it now
          localStorage.setItem(storageId, enabledValue);
          linkElement.classList.remove(disabledClass);
          showEditingIcons(true);
        }
        else {
          // Toggle is enabled, disable it now
          localStorage.setItem(storageId, disabledValue);
          linkElement.classList.add(disabledClass);
          showEditingIcons(false);
        }
      }

      // Function to add button to admin toolbar in Gin theme
      const addButtonGin = function (enabled) {
        // Set up surrounding div
        let toggleLi = document.createElement('li');
        toggleLi.className = 'menu-item menu-item--toggle-frontend-editing';
        // Add inner link
        let toggleLink = document.createElement('a');
        toggleLink.id = toggleId;
        toggleLink.setAttribute('aria-label', toggleText);
        if (enabled) {
          toggleLink.className = 'frontent_editing_toggle frontent_editing_toggle--gin';
          showEditingIcons(true);
        }
        else {
          toggleLink.className = 'frontent_editing_toggle frontent_editing_toggle--gin ' + disabledClass;
        }
        toggleLi.appendChild(toggleLink);
        // Add event listener
        toggleLi.addEventListener('click', toolbarClick);
        // Add to toolbar
        const toolbarUl = document.querySelector('[class*="gin--"] #toolbar-item-administration-tray .toolbar-menu');
        toolbarUl.appendChild(toggleLi);
      }

      // Function to add button to admin toolbar in Seven theme
      const addButtonSeven = function (enabled) {
        // Set up surrounding div
        let toggleDiv = document.createElement('div');
        toggleDiv.className = 'toolbar-tab toolbar-tab--toggle-frontend-editing';
        // Add inner link
        let toggleLink = document.createElement('a');
        toggleLink.id = toggleId;
        toggleLink.setAttribute('aria-label', toggleText);
        if (enabled) {
          toggleLink.className = 'frontent_editing_toggle frontent_editing_toggle--seven';
          showEditingIcons(true);
        }
        else {
          toggleLink.className = 'frontent_editing_toggle frontent_editing_toggle--seven ' + disabledClass;
        }
        toggleDiv.appendChild(toggleLink);
        // Add event listener
        toggleDiv.addEventListener('click', toolbarClick);
        // Add to toolbar
        document.querySelector('.toolbar-horizontal #toolbar-bar').prepend(toggleDiv);
      }

      // Setup frontend editing toggle
      if (frontendEditingNeedsSetup() && frontendEditingAvailable()) {
        // Check if frontend editing is toggeled
        if (isToggled()) {
          if (isGin()) {
            // Add enabled button to Gin
            addButtonGin(true);
          }
          else {
            // Add enabled button to Seven
            addButtonSeven(true);
          }
        }
        else {
          if (isGin()) {
            // Add disabled button to Gin
            addButtonGin(false);
          }
          else {
            // Add disabled button to Seven
            addButtonSeven(false);
          }
        }
      }
    }
  }

})(Drupal);
