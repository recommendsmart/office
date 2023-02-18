<?php

use Symfony\Component\Yaml\Yaml;

/******************************************************************************/
// aggrid-8.x-1.0-alpha29
/******************************************************************************/

/**
 * Issue #: Move JS settings to config... add with defaults
 */
function aggrid_update_8001()
{
  
  $file_path = \Drupal::service('extension.list.module')->getPath('aggrid') . '/config/install/aggrid.general.yml';
  
  $file_contents = file_get_contents($file_path);
  $ymldata = Yaml::parse($file_contents);
  
  $aggrid_config = \Drupal::service('config.factory')->getEditable('aggrid.general');
  
  $aggrid_config->set('aggridgsjson', $ymldata['aggridgsjson']);
  $aggrid_config->set('aggridexcelstyles', $ymldata['aggridexcelstyles']);
  $aggrid_config->set('aggridoptions', $ymldata['aggridoptions']);
  
  $aggrid_config->save();
  
}