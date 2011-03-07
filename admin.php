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
    has_high,
    tn_ext,
    id
  FROM '.IMAGES_TABLE.'
  WHERE path NOT LIKE \'./upload/%\'
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    $file_for_md5sum  = $row['oldpath'];
    if ('true' == $row['has_high'])
    {
      $file_for_md5sum = dirname($row['oldpath']).'/pwg_high/'.basename($row['oldpath']);
    }
    $md5sum = md5_file($file_for_md5sum);

    list($year, $month, $day, $hour, $minute, $second) = preg_split('/[^\d]+/', $row['date_available']);

    $upload_dir = './upload/'.$year.'/'.$month.'/'.$day;
    if (!is_dir($upload_dir))
    {
      umask(0000);
      $recursive = true;
      if (!@mkdir($upload_dir, 0777, $recursive))
      {
        echo 'error during "'.$upload_dir.'" directory creation';
        exit();
      }
    }
    secure_directory($upload_dir);

    $newfilename = $year.$month.$day.$hour.$minute.$second.'-'.substr($md5sum, 0, 8).'.jpg';

    $newpath = $upload_dir.'/'.$newfilename;

    $query = '
UPDATE '.IMAGES_TABLE.'
  SET path = \''.$newpath.'\',
      storage_category_id = NULL
  WHERE id = '.$row['id'].'
;';
    pwg_query($query);

    rename($row['oldpath'], $newpath);

    # high definition
    if ('true' == $row['has_high'])
    {
      $high_dir = $upload_dir.'/pwg_high';
      
      if (!is_dir($high_dir))
      {
        umask(0000);
        $recursive = true;
        if (!@mkdir($high_dir, 0777, $recursive))
        {
          echo 'error during "'.$high_dir.'" directory creation';
          exit();
        }
      }
      
      rename(
        dirname($row['oldpath']).'/pwg_high/'.basename($row['oldpath']),
        $high_dir.'/'.$newfilename
        );
    }

    # thumbnail
    $tn_dir = $upload_dir.'/thumbnail';

    if (!is_dir($tn_dir))
    {
      umask(0000);
      $recursive = true;
      if (!@mkdir($tn_dir, 0777, $recursive))
      {
        echo 'error during "'.$tn_dir.'" directory creation';
        exit();
      }
    }

    $tn_oldname = $conf['prefix_thumbnail'];
    $tn_oldname.= get_filename_wo_extension(basename($row['oldpath']));
    $tn_oldname.= '.'.$row['tn_ext'];
    
    rename(
      dirname($row['oldpath']).'/thumbnail/'.$tn_oldname,
      $tn_dir.'/'.$conf['prefix_thumbnail'].$newfilename
      );
    
    // break;
  }

  $query = '
UPDATE '.CATEGORIES_TABLE.'
  SET dir = NULL
;';
  pwg_query($query);
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
