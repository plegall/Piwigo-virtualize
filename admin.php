<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based picture gallery                                  |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2011      Pierrick LE GALL             http://piwigo.org |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if( !defined("PHPWG_ROOT_PATH") )
{
  die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

$admin_base_url = get_root_url().'admin.php?page=plugin&section=virtualize%2Fadmin.php';
load_language('plugin.lang', dirname(__FILE__).'/');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                            add permissions                            |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit']))
{
  $query = '
SELECT
    path AS oldpath,
    date_available,
    representative_ext,
    id
  FROM '.IMAGES_TABLE.'
  WHERE path NOT LIKE \'./upload/%\'
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    $file_for_md5sum  = $row['oldpath'];
    $md5sum = md5_file($file_for_md5sum);

    list($year, $month, $day, $hour, $minute, $second) = preg_split('/[^\d]+/', $row['date_available']);

    $upload_dir = './upload/'.$year.'/'.$month.'/'.$day;
    mkgetdir($upload_dir);

    $newfilename_wo_ext = $year.$month.$day.$hour.$minute.$second.'-'.substr($md5sum, 0, 8);
    
    $extension = get_extension($row['oldpath']);
    $newfilename = $newfilename_wo_ext.'.'.$extension;

    $newpath = $upload_dir.'/'.$newfilename;

    if (rename($row['oldpath'], $newpath))
    {
      if (!empty($row['representative_ext']))
      {
        $rep_dir = $upload_dir.'/pwg_representative';
        mkgetdir($rep_dir);
        
        $rep_oldpath = original_to_representative($row['oldpath'], $row['representative_ext']);
        rename($rep_oldpath, $rep_dir.'/'.$newfilename_wo_ext.'.'.$row['representative_ext']);
      }
      // check for multi-format images
      $query_ft = 'SELECT 
              image_id, ext FROM '.IMAGE_FORMAT_TABLE.'
              WHERE image_id = '.$row['id'].';';
      $result_ft = pwg_query($query_ft);
      $format_dir = $upload_dir.'/pwg_format';
      mkgetdir($format_dir);
      while ($row_ft = pwg_db_fetch_assoc($result_ft))
      {
         $format_oldpath = original_to_format($row['oldpath'], $row_ft['ext']);
         rename($format_oldpath, $format_dir.'/'.$newfilename_wo_ext.'.'.$row_ft['ext']);
      }

      $query = '
UPDATE '.IMAGES_TABLE.'
  SET path = \''.$newpath.'\',
      storage_category_id = NULL
  WHERE id = '.$row['id'].'
;';
      pwg_query($query);

      delete_element_derivatives(
        array(
          'path' => $row['oldpath'],
          'representative_ext' => $row['representative_ext'],
          )
        );
    }
  }

  $query = '
UPDATE '.CATEGORIES_TABLE.'
  SET dir = NULL
;';
  pwg_query($query);

  array_push($page['infos'], l10n('Information data registered in database'));
}


// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(
  array(
    'plugin_admin_content' => dirname(__FILE__).'/admin.tpl'
    )
  );

$template->assign(
    array(
      'F_ADD_ACTION'=> $admin_base_url,
    )
  );

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
?>
