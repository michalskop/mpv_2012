<?php
/**
*Adds attributes/MPs (groups, etc.) to the coordinates
*/

set_time_limit(0);

/*echo urlencode('volební kraj');
die();*/

// include common functions
	include ('mds_common_latest.php');
//include  possible private functions
	//include ('mds_private_latest.php');


//set error
$result['error']['error'] = FALSE;
$e = 0;

//get parameters
	//REQUIERED link OR file (not o,[lemented yet)
$link = $_GET['link'];
	//REQUIERED parliament; eg. cz_psp, sk_nrsr, ...
$parliament = $_GET['parliament'];
	//group kind, e.g. group_kind=Klub,Volební kraj,Kandidátka
$group_kind = $_GET['group_kind'];
	//rotation, e.g. rotation=Klub,ČSSD,-1,1,1,1
$rotation = $_GET['rotation'];
	//format of outcome; values: php, json, xml (default), ...
$format = $_GET['format'];
	//number of version
$version = $_GET['version'];
	//key
$key = $_GET['key'];
if (($key == '') and function_exists('generate_custom_key')) {
  $key = generate_custom_key();
}
$key_ok = check_key($key);
if (!($key_ok)) {
  add_error($result,wrong_key(),$e);
	$out = format_array($result,$format);
	echo $out;
	die();
}

//DEFAULTS
if ($format == '') $format = 'xml';


//get coordinates
$url = $link;
$result = unserialize(Grabber($url));


//basic information
	$version = version($version);
	$result['description'] = 'Adds attributes/MPs (groups, etc.) to the coordinates';
	$result['author'] = 'Michal Škop, KohoVolit.eu';
	$result['version'] = $version;
	$result['chartset'] = 'utf-8';

//QUERIES
//names etc.
$q[1] = "
  SELECT * FROM mp 
  WHERE mp_id = ANY ($1);
";

//groups
//the last membership (may be more lasts,)
$q[2] = "
SELECT t1.*,ga._value as color FROM
	(SELECT mp_id,since,until,g.group_id,g.short_name,g.full_name FROM
	mp_in_group as mig
	JOIN _group as g
		ON mig.group_id = g.group_id
	JOIN group_kind as gk
		ON g.group_kind_id = gk.group_kind_id
	WHERE mp_id = ANY ($1)
		AND gk.full_name=$2 ) as t1
RIGHT JOIN
	(SELECT mp_id,max(until) as until FROM
	mp_in_group as mig
	JOIN _group as g
		ON mig.group_id = g.group_id
	JOIN group_kind as gk
		ON g.group_kind_id = gk.group_kind_id
	WHERE mp_id = ANY ($1)
		AND gk.full_name=$2
	GROUP BY mp_id ) as t2
	ON t1.mp_id = t2.mp_id AND t1.until = t2.until
LEFT JOIN 
	group_attr as ga
	ON t1.group_id = ga.group_id
WHERE ga.name = 'color' OR ga.name IS NULL
";


//get mp_ids into string, e.g. {4,31,64,67}
$id_string = '{';
foreach ($result['mds']['coordinates'] as $row) {
  $id_string .= $row['id'] . ',';
} unset($row);
$id_string = rtrim($id_string,',') . '}';

//MPs' attributes (names, etc.)
$mps = exec_sql($q[1],array($id_string),$parliament);
foreach ($mps as $row) {
  foreach ($row as $key=>$item) {
    $result['mds']['coordinates']['mp_'.$row['mp_id']][$key] = $item;
  } unset($item);
} unset($row);

// groups (political groups, regions,...)
if ($group_kind != '') {
	$i = 0;
	$gks = explode(',',$group_kind);
	foreach ($gks as $row) {
		$result['mds']['group_kinds']['group_kind_'.$i] = $row;
		$i++;
		$mmships = exec_sql($q[2],array($id_string,$row),$parliament);
		if (count($mmship > 0)) {
			foreach ($mmships as $row2) {
				foreach ($row2 as $key=>$item) {
					$result['mds']['coordinates']['mp_'.$row2['mp_id']][friendly_url($row)][$key] = $item;
				} unset ($item);
			} unset($row2);
		}
	} unset ($row);
}

//rotation
if ($rotation != '') {
  $rots = explode(',',$rotation);
  $result = rotate($result,$rots);
}

//format output and publish
$result = format_array($result,$format);
echo $result;




/**
* rotates the array
*/
function rotate ($array, $rots) {
  $group_kind_rot = $rots[0];
  $group_rot = $rots[1];
  unset($rots[0]);
  unset($rots[1]);
  $rotate_yes = FALSE;
  
  foreach ($array['mds']['coordinates'] as $row) {
    if ($row[$group_kind_rot]['short_name'] == $group_rot) {
	  foreach ($rots as $key=>$row2) {
	    $dim = $key-1;
	    $sum[$dim] = $sum[$dim] + $row['dim_'.$dim];
	  } unset ($row2);
	}
  } unset ($row);
  foreach ($rots as $key=>$row2) {
    $dim = $key-1;
    if ($sum[$dim]*$row2 < 0) {
	  $rotate[$dim] = -1;
	  $rotate_yes = TRUE;
	} else {
	  $rotate[$dim] = 1;
	}
  } unset ($row2);
  if ($rotate_yes) {
    foreach ($array['mds']['coordinates'] as &$row) {
	  foreach ($rotate as $dim=>$val) {
	    $row['dim_'.$dim] = $row['dim_'.$dim]*$val;
	  }
    }
  }
  return $array;
}

/**
* version
*/
function version($version) {
  return '20100722';
}