Form Fields Filter for Drupal 8/9/10
====================================

Description:
A module for managing field dependencies. This module extends the functionality
of Business Rules dependent fields.

//-------------------------------------------------------------------------

Requirements:
* Drupal 8.x/9.x/10.x

//-------------------------------------------------------------------------

Installation:
1. Copy the entire webform directory the Drupal /modules/ directory or use
composer.
2. Enable the module in the "Manage" -> "Extend" or through drush.
3. Establish relationships by creating a field on the dependent entity. Add
dependencies on other entities.
4. Create a view which gets a term ID from the control entity (contextual
filter). Rewrite the results so it's in this kind of format:
  tid|Name
  ex. 45|Ball
5. Go to /admin/config/content/form_filter_fields to add the relationship
to your two fields.
6. Test and profit!

//-------------------------------------------------------------------------

Support:
Developer email: niles38@yahoo.com

//-------------------------------------------------------------------------

Thank you!
- Janis M
