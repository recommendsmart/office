# AGGRID

## CONTENTS OF THIS FILE

 - Introduction
 - Recommended modules
 - Installation
 - Configuration
 - Troubleshooting
 - Maintainers

## INTRODUCTION

 Provides a ag-Grid JSON field to store data in spreadsheet form. The ag-Grid
 Library is required for using this module. Please see the installation
 instructions on drupal.org for setting up the module and downloading the
 required library files.

 - For a full description of the module, visit the project page:
   <https://drupal.org/project/aggrid>

 - To submit bug reports and feature suggestions, or to track changes:
   <https://drupal.org/project/issues/aggrid>

 - ag-Grid Official Website (This module and the maintainer is not affiliated)
   <http://www.ag-grid.com>

## RECOMMENDED MODULES

 - No extra module is required other than some obvious Drupal core items.

## INSTALLATION

 - Please see the documentation for aggrid on drupal.org
   <https://www.drupal.org/docs/8/modules/aggrid/installation>

   The aggrid module requires the download of both the Community and Enterprise
   edition of the ag-Grid library. There are instructions for doing this both on
   the above documentation and inside of the module itself. You can either use
   the Drush aggrid:download tool or download the library manually from the
   github repository.

   <https://github.com/ag-grid/ag-grid>

## COMPOSER SETUP

 - Make sure these are added to your composer.json

 composer require wikimedia/composer-merge-plugin composer/installers

 - Edit the "composer.json" file of your website and under the "extra": { section add:

"merge-plugin": {
            "include": [
                "web/modules/contrib/aggrid/composer.libraries.json"
            ]
        }

** Note - May need to modify the include based on your site implementation from root.

 - Re-run composer to include the required libraries

 composer update drupal/aggrid --with-dependencies

## CONFIGURATION

 Once enabled, the ag-Grid module provides a link for setting version under
 Drupal 8 Configuration > Content Authoring section. In this area, you can set
 the module to either use the Community or Enterprise version. If using
 Enterprise, you can also provide a license key. If you would like a trial of
 the Enterprise edition, please see the <http://www.ag-grid.com> website. You will
 receive a trial and full support from the company for a limited time. (Besides
 extra ag-Grid features.)

 Buy it and help them support this great open library!

## TROUBLESHOOTING

 - A module is included that will provide a demo aggrid config entity, content
 type, and node. Please note, multi-cell selection is only available through
 ag-Grid Enterprise edition.

## MAINTAINERS

Current maintainers:

 - Mike Feranda - <https://www.drupal.org/u/mferanda>
