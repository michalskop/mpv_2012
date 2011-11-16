<?php
/**
*Calculates MDS coordinates from "correlations"
*/

//I must use a temporary file for R, because Rscript does not work with sooooo long parameter

//HARDCODING:
//local
	/*$rscript = '"c:\Program Files\R\R-2.10.1\bin\Rscript.exe"';
	$r_temp_file = 'C:\xampp\htdocs\m\kohovolit\working\mds\trial2.r';*/
//server
	$rscript = '/usr/bin/Rscript';
	$r_temp_file = '/var/www/test.kohovolit.sk/mds/temp_mds.r';


set_time_limit(0);


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

//basic information
	$version = version($version);
	$result['description'] = 'Calculates MDS coordinates from "correlations"';
	$result['author'] = 'Michal Škop, KohoVolit.eu';
	$result['version'] = $version;
	$result['chartset'] = 'utf-8';

//get "correlation" matrix
$url = $link;
$triang_matrix = unserialize(Grabber($url));

//check for error
if ($triang_matrix['error']['error']) {
 die ('ERROR of triangular matrix');
} 

//reorder and add the other half, averages
$matrix = to_matrix($triang_matrix['correlation']);

//calculate MDS using R
$result['mds'] = matrix2coord($matrix,$rscript,$r_temp_file);

//format output and publish
$result = format_array($result,$format);
echo $result;




/**
* calculates the MDS using R
*/
function matrix2coord($matrix,$rscript,$r_temp_file) {
  $ndim = 4;	//number of dimensions
  $digit = 4;	//number of digits in results (0.1234)
  $string = from_matrix($matrix);
  $nrow = count($matrix);
  $r_string = "
	A = matrix(c({$string}),nrow={$nrow});".'
	B = 1-A;
	CB = cmdscale(B,k='.$ndim.',eig=TRUE);
	write.table(formatC(CB$points[,1:4],format="f",digits='.$digit.'));
	write.table(formatC(CB$eig,format="f",digits='.$digit.'));
	write.table(formatC(CB$GOF,format="f",digits='.$digit.'));';
	$file = fopen($r_temp_file,"w+");
	fwrite($file,$r_string);
	fclose($file);
	// alternative approach, without using a file, however does not work with loooong parameters:
	/*$r_string[0] = "A = matrix(c({$string}),nrow={$nrow});";
	$r_string[1] = "B = 1-A;";
	$r_string[x] = "CB = cmdscale(B,k=4,eig=TRUE);";
	$r_string[2] = "CB = cmdscale(B,k=4);";
	$r_string[3] = "write.table(CB[,1:4]);";
	exec("{$rscript}  --vanilla -e \"{$r_string[0]}\" -e \"{$r_string[1]}\" -e \"{$r_string[2]}\" -e \"{$r_string[3]}\"",$r_output);*/
  exec("{$rscript} --vanilla {$r_temp_file}",$r_output);
  for ($i = 1; $i<=$nrow; $i++) {
    $pom = explode(' ',str_replace('"','',$r_output[$i]));
	unset ($pom[0]);
	global $rank2id;
    $out['coordinates']['mp_'.$rank2id[$i-1]]['id'] = $rank2id[$i-1];
	for ($dim = 1; $dim <= $ndim; $dim++) {
	  $out['coordinates']['mp_'.$rank2id[$i-1]]['dim_'.$dim] = $pom[$dim];
	}
	array(
	  'id' => $rank2id[$i-1],
	  '1' => $pom[1],
	  '2' => $pom[2],
	  '3' => $pom[3],
	  '4' => $pom[4],
	);
  }
  for ($i = $nrow+2; $i < $nrow+2+$ndim; $i++) {
    $pom = explode(' ',str_replace('"','',$r_output[$i]));
	$pom2 = $i-$nrow-1;
    $out['eigen']['eigen_'.$pom2] = $pom[1];
  }
  for ($i = $nrow+2+$ndim+1; $i < $nrow+2+$ndim+1+2; $i++) {
    $pom = explode(' ',str_replace('"','',$r_output[$i]));
	$pom2 = $i-$nrow-2-$ndim;
    $out['gof']['gof_'.$pom2] = $pom[1];
  }
  return $out;
}

/**
* reorder the values to matrix[][]
* add average instead of missing values
*/
function to_matrix($array) {
	$nrow = 0;
	global $rank2id;
	foreach ($array as $row) {
	  if (!isset($id2rank[$row['mp_id_1']])) { 
		$id2rank[$row['mp_id_1']] = $nrow;
		$rank2id[$nrow] = $row['mp_id_1'];
		$nrow++;
	  }
	} unset ($row);

	foreach ($array as $row) {
			$pom[$id2rank[$row['mp_id_1']]][$id2rank[$row['mp_id_2']]] = round($row['cor'],3);
			$average = $average + $row['cor'];
	} unset ($row);
	$average = ($average - $nrow/2) / ($nrow*($nrow-1)/2);
	for ($i = 0; $i < $nrow; $i++) {
	  for ($j = 0; $j <= $i; $j++) {
	    if (!isset($pom[$i][$j])) {
		  $out[$rank2id[$i]][$rank2id[$j]] = $average;
		  $out[$rank2id[$j]][$rank2id[$i]] = $average;
		} else {
		  $out[$rank2id[$i]][$rank2id[$j]] = $pom[$i][$j];
		  $out[$rank2id[$j]][$rank2id[$i]] = $pom[$i][$j];
		}
	  }
	}
	return $out;
}

/**
* format a line from the matrix
* for use in R
*/
function from_matrix($matrix) {
	foreach ($matrix as $row) {
		foreach ($row as $item) {
				$out .= $item . ',';
		}
		$out .= "\n";
	}
	$out = rtrim(rtrim($out),',');
	return $out;
}

/**
* version
*/
function version($version) {
  return '20100722';
}

?>