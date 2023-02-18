Content Entity Clone
====================

This module enables "cloning" content entities.

In the context of this module, cloning means prepopulating an entity creation
from with the field values of an existing entity.

Features
--------

This module provides an overview of the content entity types and their bundles
that can be cloned at /admin/config/content_entity_clone.

This overview has links to configure cloning for a bundle (or entity type) and
manage how to process the fields (ex: skip, copy or something else). See below.

This module also adds a "clone" local task link for entities for which cloning
has been enabled.

Field processing
----------------

A simple field processor plugin system is provided that allows transforming
a field's values before it is copied to the new entity to be created.

See `\Drupal\content_entity_clone\Plugin\FieldProcessorInterface`.

Compatibility
-------------

This module should work with any entity type implementing the
`\Drupal\Core\Entity\ContentEntityTypeInterface` and with an entity creation
form.

Requirements
------------

This module requires no modules outside the Drupal core.

Installation
------------

Install the Cloner module as you would normally install a contributed Drupal
module. Visit https://www.drupal.org/node/1897420 for further information.

Alternatives
------------

- [Quick node clone](https://www.drupal.org/project/quick_node_clone)
- [Entity Clone](https://www.drupal.org/project/entity_clone)
- [Cloner](https://www.drupal.org/project/cloner)

TODO
----

- [ ] Add form to add settings to the plugins.
- [ ] Add tests.
