# KCTS 9 Media Manager

This repository contains the custom module KCTS 9 Media Manager as developed by
KCTS 9 for the [kcts9.org](https://www.kcts9.org) website.

## AS-IS

This is module is **not complete** as it does not include full configuration
elements necessary for use. The module expects two content types -- "show" and 
"video_content" -- and two Taxonomy Vocabularies -- "genre" and
"editorial_genre" -- to exist in order to function. Example configuration files
are provided in the [`example-config`](example-config) folder, but they may not
be complete.

## Functionality

The primary functionality for _syncing_ of content from Media Manager can be
found in the [`ShowManager`](src/ShowManager.php) and 
[`VideoContentManager`](src/VideoContentManager.php) classes.

Note: a certain quantity of content for this module has been removed in order to
focus on the primary functionality of syncing data between Drupal and Media
Manager. Some remnants of the removed functionality may remain.

## Dependencies

Although it is not explicitly defined by the module configuration, this module
requires the [openpublicmedia/pbs-media-manager-php](https://packagist.org/packages/openpublicmedia/pbs-media-manager-php)
library.
