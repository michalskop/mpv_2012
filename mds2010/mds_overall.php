<?php

set_time_limit(0);

// include common functions
include ('mds_common_latest.php');



$path = "http://localhost/m/kohovolit/working/mds/";
$path="http://test.kohovolit.sk/mds/";

$url = $path."mds_raw2matrix.php?key=public_201007&modulo=1&parliament=cz_psp&format=php&lo_limit=.1
";
$triang_matrix = unserialize(Grabber($url));

if ($array['error']['error']) {
 die ('ERROR');
} 

//reorder and add the other half
unset($matrix);
$matrix = to_matrix($triang_matrix['correlation'],array(),$version,$format='php');

matrix2coord($matrix);

/**
*
* 
*/
function matrix2coord($matrix,$rotation = array(),$version='',$format='xml') {
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
	$file = fopen('/var/www/test.kohovolit.sk/mds/temp_mds.r',"w+");
	fwrite($file,$r_string);
	fclose($file);
	/*$r_string[0] = "A = matrix(c({$string}),nrow={$nrow});";
	$r_string[1] = "B = 1-A;";
	$r_string[x] = "CB = cmdscale(B,k=4,eig=TRUE);";
	$r_string[2] = "CB = cmdscale(B,k=4);";
	$r_string[3] = "write.table(CB[,1:4]);";*/
  //echo $r_string;
  //exec("\"c:\\Program Files\\R\\R-2.10.1\\bin\\Rscript.exe\" --vanilla -e \"{$r_string[0]}\" -e \"{$r_string[1]}\" -e \"{$r_string[2]}\" -e \"{$r_string[3]}\"",$r_output);
  exec("\"c:\\Program Files\\R\\R-2.10.1\\bin\\Rscript.exe\" --vanilla c:\\xampp\\htdocs\\m\\kohovolit\\working\\mds\\trial2.r",$r_output);
  //exec("/usr/bin/Rscript --vanilla -e \"{$r_string[0]}\" -e \"{$r_string[1]}\" -e \"{$r_string[2]}\" -e \"{$r_string[3]}\"",$r_output);
  exec("/usr/bin/Rscript --vanilla /var/www/test.kohovolit.sk/mds/temp_mds.r",$r_output);
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
  $out = format_array($out,$format);
  echo($out);
}

function rotate($string) {
	$ar = explode(',',$string);
	foreach ($ar as $row){
		$out_ar[] = -1*$row;
	}
	$out = implode(',',$out_ar);
	return $out;
}

function to_matrix($array) {
	$n_row = 0;
	global $rank2id;
	foreach ($array as $row) {
	  if (!isset($id2rank[$row['mp_id_1']])) { 
		$id2rank[$row['mp_id_1']] = $n_row;
		$rank2id[$n_row] = $row['mp_id_1'];
		$n_row++;
	  }
	} unset ($row);

	foreach ($array as $row) {
			$pom[$id2rank[$row['mp_id_1']]][$id2rank[$row['mp_id_2']]] = round($row['cor'],3);
			//$pom[$id2rank[$row['mp_id_2']]][$id2rank[$row['mp_id_1']]] = round($row['cor'],3);
			$average = $average + $row['cor'];
	} unset ($row);
	$average = ($average - $n_row/2) / ($n_row*($n_row-1)/2);
	for ($i = 0; $i < $n_row; $i++) {
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

?>