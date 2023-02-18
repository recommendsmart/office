/**
 * @file
 * Calendar multiple day events behaviors.
 */

(function (Drupal, once) {

  const hashAttribute = 'data-calendar-view-hash';

  /**
   * Alter multiday events theming.
   *
   * This behavior is dependent on preprocess hook.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior.
   *
   * @see template_preprocess_calendar_view_day()
   */
  Drupal.behaviors.calendarViewMultiday = {
    attach(context, settings) {
      let firstInstances = context.querySelectorAll('[' + hashAttribute + '][data-calendar-view-instance="0"]');
      if (!firstInstances || firstInstances.length < 1) {
        return;
      }

      // Alter all other instances of a multiday event.
      once('calendar-view-multiday', firstInstances, context).forEach(function (firstInstance) {
        if (!firstInstance.hasAttribute(hashAttribute)) {
          return;
        }

        let rowHash = firstInstance.getAttribute(hashAttribute);
        let rowInstances = context.querySelectorAll('[' + hashAttribute + '="' + rowHash + '"]');
        if (!rowInstances || rowInstances.length < 2) {
          return;
        }

        // Get reference "sizes".
        let firstInstanceBound = firstInstance.getBoundingClientRect();

        // Loop on cloned events.
        rowInstances.forEach(function (instance) {
          // Hover all at once.
          instance.addEventListener('mouseover', function (event) {
            rowInstances.forEach(function (element) {
              element.classList.add('hover');
            });
          });
          instance.addEventListener('mouseleave', function (event) {
            rowInstances.forEach(function (element) {
              element.classList.remove('hover');
            });
          });

          // Simulate same size and position in cell.
          if (instance != firstInstance) {
            let instanceBound = instance.getBoundingClientRect();
            if (instanceBound.height < firstInstanceBound.height) {
              instance.style.height = firstInstanceBound.height + 'px';
            }
            if (instance.offsetTop < firstInstance.offsetTop) {
              instance.style.marginTop = (firstInstance.offsetTop - instance.offsetTop) + 'px';
            }
          }
        });
      });
    },
  };
})(Drupal, once);
