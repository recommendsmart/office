CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* Maintainers


INTRODUCTION
------------

Views Default User Taxonomy module overrides the core Taxonomy default
argument plugin to provide the ability to enable "Load default filter
from user page" so you can load related items on a user by taxonomy
just like you can with nodes.

* For a full description of the module, visit the project page:
  https://www.drupal.org/project/views_default_user_taxonomy

* To submit bug reports and feature suggestions, or to track changes:
  https://www.drupal.org/project/issues/views_default_user_taxonomy


REQUIREMENTS
------------

This module requires Views and Taxonomy core modules installed.


INSTALLATION
------------

* Install the module as you would normally install a contributed Drupal
  module.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Structure > Views.
    3. Create or edit a View.
    4. Add the contextual filter "Has taxonomy term ID".
    5. Select "Load default filter from user page, that's good for related
       taxonomy blocks".
    6. Configure your options on the filter.
    7. Save the view.

Now on any route for user/%user or routes where %user is a defined route parameter
it will load this to power the taxonomy filtering for that View.

MAINTAINERS
-----------

Current maintainers:
* Kevin Quillen (kevinquillen) - https://kevinquillen.com

Supporting organizations:
* Velir - https://www.drupal.org/velir
