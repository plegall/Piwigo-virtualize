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
// | Functions                                                             |
// +-----------------------------------------------------------------------+

/**
 * list all columns of each given table
 *
 * @return array of array
 */
function virtualize_get_columns_of($tables)
{
  $columns_of = array();

  foreach ($tables as $table)
  {
    $query = '
DESC `'.$table.'`
;';
    $result = pwg_query($query);

    $columns_of[$table] = array();

    while ($row = pwg_db_fetch_row($result))
    {
      $columns_of[$table][] = $row[0];
    }
  }

  return $columns_of;
}

// +-----------------------------------------------------------------------+
// | Virtualize files                                                      |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit']))
{
  $columns_of = virtualize_get_columns_of(array(IMAGES_TABLE));

  $md5sum_colname = 'md5sum';
  if (isset($columns_of[IMAGES_TABLE]['md5sum_original']))
  {
    $md5sum_colname = 'md5sum_original';
  }

  $query = '
SELECT
    path AS oldpath,
    date_available,
    representative_ext,
    '.$md5sum_colname.' AS checksum,
    id
  FROM '.IMAGES_TABLE.'
  WHERE path NOT LIKE \'./upload/%\'
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    if (!file_exists($row['oldpath']))
    {
      fatal_error('photo #'.$row['id'].' file '.$row['oldpath'].' is missing');
    }

    $file_for_md5sum  = $row['oldpath'];

    $md5sum = $row['checksum'];

    if (strlen($md5sum ?? '') != 32)
    {
      $md5sum = md5_file($file_for_md5sum);
    }

    if (empty($md5sum))
    {
      fatal_error('photo #'.$row['id'].' file '.$row['oldpath'].', md5sum is empty');
    }

    list($year, $month, $day, $hour, $minute, $second) = preg_split('/[^\d]+/', $row['date_available']);

    $upload_dir = './upload/'.$year.'/'.$month.'/'.$day;
    mkgetdir($upload_dir);

    $extension = get_extension($row['oldpath']);

    $newfilename_wo_ext = $year.$month.$day.$hour.$minute.$second.'-'.substr($md5sum, 0, 8);
    $newfilename = $newfilename_wo_ext.'.'.$extension;
    $newpath = $upload_dir.'/'.$newfilename;

    while (file_exists($newpath))
    {
      // if the file already exists (same file from different "galleries" directories, added during the same sync)
      // we fake a new random string. We do not want to have 2 images sharing the same path
      $newfilename_wo_ext = preg_replace('/-[a-f0-9]{8}/', '-'.substr(md5(random_bytes(1000)), 0, 8), $newfilename_wo_ext);
      $newfilename = $newfilename_wo_ext.'.'.$extension;
      $newpath = $upload_dir.'/'.$newfilename;
    }

    if (rename($row['oldpath'], $newpath))
    {
      if (!empty($row['representative_ext']))
      {
        $rep_dir = $upload_dir.'/pwg_representative';
        mkgetdir($rep_dir);
        
        $rep_oldpath = original_to_representative($row['oldpath'], $row['representative_ext']);
        rename($rep_oldpath, $rep_dir.'/'.$newfilename_wo_ext.'.'.$row['representative_ext']);
      }

      $datas = array(
        'path' => $newpath,
        'storage_category_id' => null,
        $md5sum_colname => $md5sum,
      );

      if (isset($columns_of[IMAGES_TABLE]['md5sum_fs']))
      {
        $datas['md5sum_fs'] = $md5sum;
      }

      single_update(
        IMAGES_TABLE,
        $datas,
        array('id' => $row['id'])
      );

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
