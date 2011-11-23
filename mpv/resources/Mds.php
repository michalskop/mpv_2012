<?php

/**
 * \ingroup mpv
 *
 * Calculates multidimensional scaling for given matrix
 */
class Mds
{
  /**
  * Calculates multidimensional scaling for given matrix
  * Matrix may be triangular
  * Matrix should look like Array('0' => array('mp_id_1' => 4,'mp_id_2' => 71,'together' => 2.35, correlation => 0.93)
  *
  * \param $params An array of pairs <em>parameter => value</em>. Available parameters are:
  * - \c input required json file
  * - \c low_limit lower limit for inclusion in computation, default 0=all MPs
  * - \c output output json file
  * - \c dim number of dimension, default 4
  * - \c digit number of digits, default 4
  * - \c temp_path  path to writable temporary directory, default '/tmp/'
  * - \c leave_file if set, leaves R file undeleted
  *
  * \return Matrix of calculated positions using multidimensional scaling
  */
  public function read($params)
  {
    if (!isset($params['input']))
    	return array(); 
    //defaults
    $default_temp_path = '/tmp/';
    $default_dim = 4;
    $default_digit = 4;
    
    //triangular matrix
    $triang_matrix_ar = json_decode(file_get_contents($params['input']));
    $triang_matrix = $triang_matrix_ar->matrix;
    
    //apply low_limit
    $low = array();
    if (isset($params['low_limit'])) {
      //first find max of together
      $max = $triang_matrix[0]->together;
      foreach($triang_matrix as $row) {
        if ($row->together > $max) $max = $row->together;
      }
      //now find mps with lower than low_limit
      foreach($triang_matrix as $row) {
        if (($row->mp_id_1 == $row->mp_id_2) and ($row->together < ($params['low_limit']*$max))) 
          $low[] = $row->mp_id_1;
      }
      //delete mps within $low
      if (!empty($low)) {
          $new_matrix = array();
		  foreach($triang_matrix as $row) {
		    if (in_array($row->mp_id_1,$low) or in_array($row->mp_id_2,$low)) {
		    
		    } else {
		      $new_matrix[] = $row;
		    }
		  }
		  $triang_matrix = $new_matrix;
      }
    }
    
    //reorder triangular matrix into regular matrix + get ids of mps
    $matrix = array();
    $mps = array();
    foreach ($triang_matrix as $row) {
      if ($row->mp_id_1 == $row->mp_id_2) {
        $mps[] = $row->mp_id_1;
        $matrix[$row->mp_id_1][$row->mp_id_2] = round($row->correlation,3);
      } else {
        $matrix[$row->mp_id_1][$row->mp_id_2] = round($row->correlation,3);
        $matrix[$row->mp_id_2][$row->mp_id_1] = round($row->correlation,3);
      }
    }
    
    //get string of values for R
    $string = '';
    foreach ($matrix as $row) {
      $string .= implode(',',$row) . ",\n";
    }
    $string = rtrim(rtrim($string),',');
    
    //calculation in R
        //random number to allow more processes in once
        $rand = '';
		for ($i=0;$i<=7;$i++) {
		  $rand .= rand(0,9);
		}
    $nrow = count($mps);
    if (isset($param['digit'])) $digit = $param['digit'];
    else $digit = $default_digit;
    if (isset($param['dim'])) $dim = $param['dim'];
    else $dim = $default_digit;
    if (isset($param['temp_path'])) $temp_path = $param['temp_path'];
    else $temp_path = $default_temp_path;
    
    $r_string = "
	A = matrix(c({$string}),nrow={$nrow});".'
	B = (1-A)/2;
	CB = cmdscale(B,k='.$dim.',eig=TRUE);
	write.table(formatC(CB$points[,1:4],format="f",digits='.$digit.'));
	write.table(formatC(CB$eig,format="f",digits='.$digit.'));
	write.table(formatC(CB$GOF,format="f",digits='.$digit.'));';
	$file = fopen($temp_path . "mds_{$rand}.r","w+");
	fwrite($file,$r_string);
	fclose($file);
	
	exec("/usr/bin/Rscript --vanilla {$temp_path}mds_{$rand}.r",$r_output);
	
	//delete input file
	if (!isset($params['leave_file'])) unlink ("{$temp_path}mds_{$rand}.r");
	
	//output
	$out = array('mps_low' => $low, 'mps' => $mps);
	//coordinates
	$coordinates = array();
	for ($i = 1; $i <= count($mps); $i++) {
	  $tmp = explode(' ',str_replace('"','',$r_output[$i]));
	  array_shift($tmp);
	  $coordinates[] = $tmp;
	}
	$out['coordinates'] = $coordinates;
	//eigenvalues
	$eigenvalues = array();
	for ($i = count($mps) + 2; $i < count($mps) + 2 + $dim; $i++) {
	  $tmp = explode(' ',str_replace('"','',$r_output[$i]));
	  $eigenvalues[] = $tmp[1];
	}
	$out['eigenvalues'] = $eigenvalues;
	//gof
	$gof = array();
	for ($i = 2*count($mps) + 3; $i <= 2*count($mps) + 4;$i++) {
	  $tmp = explode(' ',str_replace('"','',$r_output[$i]));
	  $gof[] = $tmp[1];
	}
	$out['gof'] = $gof;
	
	//info
	$out['info'] = $triang_matrix_ar->info;
	
    //write to file
    if (isset($params['output'])) {
      $json = json_encode($out);
      $file = fopen($params['output'],"w+");
      fwrite($file,$json);
      fclose($file);
    }
	
    return $out;
    
    
    
    
  }
}
