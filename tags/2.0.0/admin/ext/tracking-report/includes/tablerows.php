<?php
// needed for async calls to this file
session_start();
/* Defining a relative path to smt2 root in this script is a bit tricky,
 * because this file can be called both from Ajax and regular HTML requests. 
 */
$base = realpath(dirname(__FILE__).'/../../../../');
require $base.'/config.php';
// use ajax settings
require dirname(__FILE__).'/settings.php';

// get ajax data
if (!empty($_GET['page'])) { $page = $_GET['page']; }
$show = (!empty($_SESSION['limit'])) ? $_SESSION['limit'] : db_option(TBL_PREFIX.TBL_CMS, "recordsPerTable");
// set query limits
$start = $page * $show - $show;
$limit = "$start,$show";
// is JavaScript enabled?
if (isset($_GET[$resetFlag])) { $limit = $page*$show; }

// query priority: filtered or default
$where = (!empty($_SESSION['filterquery'])) ? $_SESSION['filterquery'] : "1"; // will group by id

$records = db_select_all(TBL_PREFIX.TBL_RECORDS, 
                         "id,client_id,cache_id,os_id,browser_id,ftu,sess_date,sess_time,coords_x", // ask for these columns always 
                         $where." ORDER BY sess_date DESC, client_id LIMIT $limit");
// if there are no more records, display message
if ($records) 
{ 
  $GROUPED = '<acronym title="Data is grouped">&mdash;</acronym>';
  // show pretty dates over timestamps if PHP >= 5.2.0
  if (check_systemversion("php", "5.2.0")) {
    $usePrettyDate = true;
    require_once INC_PATH.'sys/class.prettydate.php';
  }
  // call this function once, using session data for Ajax request
  $ROOT = is_root();
  // dump (smt) records  
  foreach ($records as $i => $r) 
  {
    // wait for very recent visits
    $timeDiff = time() - strtotime($r['sess_date']);
    $receivingData = ($timeDiff < 5); 
    $safeToDelete = ($timeDiff > 3600);
    // delete logs with no mouse data
    if ( $safeToDelete && !count(array_sanitize(explode(",", $r['coords_x']))) ) {
      db_delete(TBL_PREFIX.TBL_RECORDS, "id='".$r['id']."' LIMIT 1");
      continue;
    }
    
    $cssClass = ($i%2 == 0) ? "odd" : "even";

    if ($_SESSION['groupby'] === "cache_id") 
    {
      $displayId = 'pid='.$r['cache_id'];
      $pageId = $r['cache_id'];
      $clientId = $GROUPED;
      // check if cached page exists
      $cache = db_select(TBL_PREFIX.TBL_CACHE, "file", "id='".$pageId."'");
      if (!is_file(CACHE.$cache['file'])) { continue; }
    }
    else if ($_SESSION['groupby'] === "client_id") 
    {
      $displayId = 'cid='.$r['client_id'];
      $pageId = $GROUPED;
      $clientId = mask_client($r['client_id']);
    }
    
    if (!empty($_SESSION['groupby'])) {
      $displayDate = $GROUPED;
      $time = $GROUPED;
    } else {
      // display a start on first time visitors
      $ftu = ($r['ftu']) ? ' class="ftu"' : null;
      // use pretty date?
      $displayDate = ($usePrettyDate) ? 
      '<acronym title="'.prettyDate::getStringResolved($r['sess_date']).'">'.$r['sess_date'].'</acronym>' : 
      $r['sess_date'];
      
      $time = $r['sess_time'];
      $displayId = 'id='.$r['id'];
      $pageId = $r['cache_id'];
      $clientId = mask_client($r['client_id']);
    }
    
    // create list item
    $tablerow .= '<tr class="'.$cssClass.'">'.PHP_EOL;
    $tablerow .= ' <td'.$ftu.'>'.$clientId.'</td>'.PHP_EOL;
    $tablerow .= ' <td>'.$pageId.'</td>'.PHP_EOL;
    $tablerow .= ' <td>'.$displayDate.'</td>'.PHP_EOL;
    $tablerow .= ' <td>'.$time.'</td>'.PHP_EOL;
    $tablerow .= ' <td>'.PHP_EOL;
    
    if (!$receivingData) {
      if ($_SESSION['groupby'] === "client_id") {
        $tablerow .= $GROUPED;
      } else {
        $tablerow .= '  <a href="track.php?'.$displayId.'&amp;api=swf" rel="external" title="use the interactive Flash drawing API">SWF</a>'.PHP_EOL; 
        $tablerow .= ' | <a href="track.php?'.$displayId.'&amp;api=js" rel="external" title="use the old JavaScript drawing API">JS</a>'.PHP_EOL;
      }
    } else {
      $tablerow .= '<em>please wait...</em>';
    }
    $tablerow .= ' </td>'.PHP_EOL;
    $tablerow .= ' <td>'.PHP_EOL;
    if (!$receivingData) {
      $tablerow .= '  <a href="analyze.php?'.$displayId.'">analyze</a>'.PHP_EOL;
      if ($ROOT) {
        $tablerow .= ' | <a href="delete.php?'.$displayId.'" class="del">delete</a>'.PHP_EOL;
      }
    } else {
      $tablerow .= '<em>receiving data</em>';
    }
    $tablerow .= ' </td>'.PHP_EOL;
    $tablerow .= '</tr>'.PHP_EOL;
  }
    
  echo $tablerow;
  // check both normal and async (ajax) requests
  if ($start + $show < db_records()) {
    $displayMoreButton = true;
  } else {
    echo '<!--'.$noMoreText.'-->'.PHP_EOL;
  }

} else { echo '<!--'.$noMoreText.'-->'; }
?>