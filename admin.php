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

// +-----------------------------------------------------------------------+
// | Virtualize files                                                      |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit']))
{
  $nb_virtualized = 0;

  $query = '
SELECT
    path AS oldpath,
    date_available,
    representative_ext,
    md5sum,
    id
  FROM '.IMAGES_TABLE.'
  WHERE path NOT LIKE \'./upload/%\'
    AND md5sum IS NOT NULL
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    if (!file_exists($row['oldpath']))
    {
      fatal_error('photo #'.$row['id'].' file '.$row['oldpath'].' is missing');
    }

    if (!preg_match('/^[a-f0-9]{32}$/', $row['md5sum']))
    {
      fatal_error('photo #'.$row['id'].' file '.$row['oldpath'].', md5sum "'.$row['md5sum'].'" is invalid');
    }

    list($year, $month, $day, $hour, $minute, $second) = preg_split('/[^\d]+/', $row['date_available']);

    $upload_dir = './upload/'.$year.'/'.$month.'/'.$day;
    mkgetdir($upload_dir);

    $extension = get_extension($row['oldpath']);

    $newfilename_wo_ext = $year.$month.$day.$hour.$minute.$second.'-'.substr($row['md5sum'], 0, 8);
    $newfilename = $newfilename_wo_ext.'.'.$extension;
    $newpath = $upload_dir.'/'.$newfilename;

    while (file_exists($newpath))
    {
      // if the file already exists (same file from different "galleries" directories, added during the same sync)
      // we fake a new random string. We do not want to have 2 images sharing the same path
      $newfilename_wo_ext = preg_replace('/-[a-f0-9]{8}/', '-'.substr(md5(random_bytes(5)), 0, 8), $newfilename_wo_ext);
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
      );

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

      $nb_virtualized++;
    }
  }

  // find "physical" categories with no more "physical" files inside
  $query = '
SELECT
    storage_category_id
  FROM '.IMAGES_TABLE.'
  WHERE storage_category_id IS NOT NULL
;';
  $storage_category_ids = query2array($query);

  $query = '
UPDATE '.CATEGORIES_TABLE.'
  SET dir = NULL
  WHERE dir IS NOT NULL';

  if (count($storage_category_ids) > 0)
  {
    $query.= '
    AND id NOT IN ('.implode(',', $storage_category_ids).')';
  }

  $query.= '
;';
  pwg_query($query);

  array_push($page['infos'], l10n('%d photos have been virtualized', $nb_virtualized));
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
      'ADMIN_PAGE_TITLE' => 'Virtualize',
      'F_ADD_ACTION'=> $admin_base_url,
    )
  );

$show_virtualize_button = false;

if (!isset($_POST['submit']))
{
  // check if there are photos to virtualize : if none, just display a $page['infos'] "all is already virtual, you're good to go :-)"
  $query = '
SELECT
    md5sum
  FROM '.IMAGES_TABLE.'
  WHERE path NOT LIKE \'./upload/%\'
;';
  $candidates = query2array($query);
  $nb_candidates_wo_md5sum = 0;
  foreach ($candidates as $candidate)
  {
    if (empty($candidate['md5sum']))
    {
      $nb_candidates_wo_md5sum++;
    }
  }

  if (count($candidates) == 0)
  {
    $page['infos'][] = l10n('nothing to virtualize, all is already virtual, you\'re good to go :-)');
  }
  else
  {
    $show_virtualize_button = true;
    $page['messages'][] = l10n('you have %d photos to virtualize', count($candidates));

    if ($nb_candidates_wo_md5sum > 0)
    {
      $show_virtualize_button = false;

      $msg = l10n('%d photos to virtualize have no checksum yet...', $nb_candidates_wo_md5sum);
      $msg.= ' <a href="admin.php?page=batch_manager&amp;filter=prefilter-no_sync_md5sum">'.l10n('Compute them first in the batch manager').' <i class="icon-right"></i></a>';

      $page['warnings'][] = $msg;
    }

    $page['messages'][] = l10n('This plugin moves all your photos from <em>"galleries"</em> (added with the synchronization process) to <em>"upload"</em> and mark categories as virtual.');
    $page['messages'][] = l10n('Once categories are virtual, you can move them the way you want.');

    $page['warnings'][] = l10n('Make sure you have a backup of your <em>"galleries"</em> directory and a dump of your database.');
  }
  // check formats
}

$template->assign('show_virtualize_button', $show_virtualize_button);

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
?>
