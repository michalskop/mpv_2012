<?php

function q_common_chart($in) {
 $chart = 'http://chart.apis.google.com/chart?';
 
 $chart .= cht($in['type']);
 
 if (isset ($in['encoding'] )) {
   if (!isset($in['max'])) {
	   if (is_array($in['data'][0])) {
	     foreach ($in['data'] as $row) {
		   $max = max($max,$row);
		 }
	   } else {
	     $max = max($in['data']);
	   }
	   $in['max'] = $max;
	 }
   if (!isset($in['min'])) {
	   if (is_array($in['data'][0])) {
	     foreach ($in['data'] as $row) {
		   $min = min($min,$row);
		 }
	   } else {
	     $min = min($in['data']);
	   }
	   $in['min'] = $min;
	 }
   $chart .= '&' . chd($in['data'],$in['encoding'],$in['max'],$in['min']);
 } else {
   $chart .= '&' . chd($in['data']);
 }
 
 if (isset($in['size'])) {
   $chart .= '&' . chs($in['size']);
 } else {
   $chart .= '&' . chs();
 }
 
  if (isset($in['color'])) {
    if (isset($in['color_separator'])) {
      $chart .= '&' . chco($in['color'],$in['color_separator']);
    } else {
      $chart .= '&' . chco($in['color']);
    }
  }
 
 
  if (isset($in['scale'])) {
    $chart .= '&' . chds($in['scale']);
  }

  if (isset($in['fill'])) {
    $chart .= '&' . chf($in['fill']);
  }
  
  if (isset($in['axis'])) {
    $chart .= '&' . chxt($in['axis']);
  }
  
  if (isset($in['label'])) {
    $chart .= '&' . chdl($in['label']);
  }
  
  if (isset($in['axis_label_position'])) {
    $chart .= '&' . chxp($in['axis_label_position']);
  }
  
  if (isset($in['line_style'])) {
    $chart .= '&' . chls($in['line_style']);
  } 
  
  if (isset($in['axis_style'])) {
    $chart .= '&' . chxs($in['axis_style']);
  } 

  if (isset($in['label_position'])) {
    $chart .= '&' . chdlp($in['label_position']);
  } 
  if (isset($in['grid'])) {
    $chart .= '&' . chg($in['grid']);
  }
  
  if (isset($in['bar_width'])) {
    $chart .= '&' . chbh($in['bar_width']);
  }

  if (isset($in['axis_label'])) {
    $chart .= '&' . chxl($in['axis_label']);
  }
  
 $out .= '<img src="' . $chart . '" alt="' . $in['alt'] . '" title="' . $in['title'] .'"/>';
 return $out;

}


/** chs - SIZE of chart
* examples
* $size = array (200,100)
* $size = 200
* 'chs=200x100'
*/
function chs($size = array(300,150)) {
  //chs - size
  if (is_array($size)){
    $chs = "{$size[0]}x{$size[1]}";
  } else {
	$size2 = round($size/2);
	$chs = "{$size}x{$size2}";
  }
  return 'chs=' . $chs;
}


/**chdl - LABEL of chart
* examples
* $label = array('label1','lbael2')
* $label = 'my_label'
*  'chdl=label1|label2'
* 'chdl=my_label'
*/
function chdl($label) {
  //chdl	label
  if (is_array($label)) {  //more series
    foreach($label as $lab) {
	  $chdl .= rawurlencode($lab) . '|';
	}
	$chdl = rtrim($chdl,'|');
  } else { //single series
    $chdl = rawurlencode($label);
  }
  return 'chdl=' . $chdl;
}


/**chdlp - LABEL POSITION of chart
* examples
* $type = ;'b';
* 'chdl=b'
*/
function chdlp($label_position) {
  //chdlp	label position
  $chdlp = $label_position;
  return 'chdlp=' . $chdlp;
}

/**cht - TYPE of chart
* examples
* $type = 'bhs';
* 'chdl=bhs'
*/
function cht($type = 'bvs') {
  //cht	tye of chart
  $cht = $type;
  return 'cht=' . $cht;
}

/**chtbh- BAR width and spacing
* examples
*/
function chbh($bar_width) {
  //cht    tye of chart
  $chbh = $bar_width;
  return 'chbh=' . $chbh;
}
/**chds - SCALING of chart
* examples
* $scale = array(-100,100)';
* 'chds=-100,100'
*/
function chds($scale = array(0,100)) {
  $chds = $scale[0] . ',' . $scale[1];
  return 'chds=' . $chds;
}

/**chf - FILL of chart
* examples
* $fill = 'c,ls,0,FFB0B0,0.2,FFD0D0,0.2,FFFFFF,0.2,D0FFD0,0.2,B0FFB0,0.2';
* 'chf=c,ls,0,FFB0B0,0.2,FFD0D0,0.2,FFFFFF,0.2,D0FFD0,0.2,B0FFB0,0.2'
*/
function chf($fill) {
  return 'chf=' . $fill;
}

/**chg - GRID 
* examples
* $grid = '20,50,3,3,10,20';
* 'chg=c,20,50,3,3,10,20'
*/
function chg($grid) {
  return 'chg=' . $grid;
}

/**chco - COLORS of chart
* examples
* $color = array('008000','00A000','FF0000');
* $color = '001122';
* $separator = '|';
* 'chco=008000|00A000|FF0000'
* 'chco=001122'
*/
function chco ($color, $separator = ',') {
  //chco - color
  if (is_array($color)) {
	  foreach ($color as $col) {
		$chco .= $col . $separator;
	  }
	$chco = rtrim($chco,$separator);
  } else {
    $chco = $color;
  }
  return 'chco=' . $chco;
}

/**chxt - AXES of chart
* examples
* $axis = array('x','y');
* $axis = 'x';
* 'chxt=x,y'
* 'chxt=x'
*/
function chxt ($axis = array('x','y')) {
  //chxt	axis
  if (is_array($axis)) {
    foreach ($axis as $ax) {
	  $chxt .= $ax . ',';
    }
	$chxt = rtrim($chxt,',');
  } else {
    $chxt = $axis;
  } 
  return 'chxt=' . $chxt;
}
/**chxl - AXES' LABELS of chart
* examples
* $axis_label = array(0 => array(2010,2011)., 1 => array(1,2));
* 'chxl=0:|2010|2011|1:|1|2}
*/
function chxl ($axis_label){
  //chxl	axis label
  if (is_array($axis_label)) {
    foreach ($axis_label as $key => $series) {
	  $chxl .= $key . ':|';
	  foreach ($series as $a_l) {
	    $chxl .= rawurlencode($a_l) . '|';
	  }
	}
  } else {
     foreach ($axis_label as $a_l) {
	    $chxl .= rawurlencode($a_l) . '|';
	  }
  }
  
 $chxl = substr($chxl,0,strlen($chxl)-1);
  return 'chxl=' . $chxl;
}

/** chd - DATA
* examples
* $data = array(array(0,1,2),array(1,2,3)); $encoding = 's');
* $data = array(0,1,2); $encoding => 's';
* $data = array(0,1,2);
* $data = 1;
* max , min used only when encoding is 's' or 'e', for 's' should rather be max=61, so set it!
* 'chd=
*/
function chd($data, $encoding = 't', $max = 4095, $min = 0) {
  //chd - data
 
  if (is_array($data[0])) {  //more series
    foreach ($data as $series) {
      switch($encoding) {
	    case 't':
		  foreach($series as $val) {
	        $chd .= $val . ',';
	      }
		  $chd = rtrim($chd,',');
		  $chd .= '|';
		  break;
	    case 'e':
		  $chd = extEncode($series,$max,$min);
		  $chd .= ',';
		  break;
	    case 's':
		  $chd = simEncode($series,$max,$min);
		  $chd .= ',';
		  break;
	  }
	  unset($series);
	}
    $chd = rtrim($chd,'|,');
  } else { //single series
    switch($encoding) {
	    case 't':
		  foreach($data as $val) {
	        $chd .= $val . ',';
	      }
		  $chd = rtrim($chd,',');
		  $chd .= '|';
		  break;
	    case 'e':
		  $chd = extEncode($data,$max,$min);
		  $chd .= ',';
		  break;
	    case 's':
		  $chd = simEncode($data,$max,$min);
		  $chd .= ',';
		  break;
	  }
	$chd = rtrim($chd,'|,');
  }
  return 'chd=' . $encoding . ':' . $chd;
}


/** chls -AXIS STYLE
* examples
* $chxls= 
* 'chxs=
*/
function chxs($axis_style) {
 //chxs	axis style
  if (isset($axis_style)) {
    foreach ($axis_style as $key => $a_s) {
	  $chxs .= $key . ',' . $a_s . '|';
	}
  }
  $chxs = rtrim($chxs,'|');
  return 'chxs=' . $chxs;
}
/** chls - LINE STYLE
* examples
* $chxl = 
* 'chxl
*/
function chls($line_style,$n_series=0,$default_line_style=2) {
  $n_series_1 = max(array(count($line_style),$n_series)) - 1;
  //chls	line styles
  for ($i = 0; $i <= $n_series_1; $i++){
    if (isset($line_style[$i])) {
	  $chls .= _line_chart_line_style($line_style[$i],$default_line_style) . '|';
	} else {
	  $chls .=$default_line_style . '|';
	}
  }
  $chls = rtrim($chls,'|');
  return 'chls=' . $chls;
}

/** chd - AXIS LABEL POSITION
* examples
* $chxp = array(array(0,1,2),array(1,2,3))
* 'chxp='0:0|1|2|1:1|2}3'
*/
function chxp($axis_label_position) {
  //chxp	axis label position
    foreach ($axis_label_position as $key => $series) {
	  $chxp .= $key . ',';
	  foreach ($series as $a_l) {
	    $chxp .= $a_l . ',';
	  }
	  $chxp = rtrim($chxp,',');
	  $chxp .= '|';
	}
  $chxp = rtrim($chxp,'|');
  return 'chxp=' . $chxp;
}

//chds


//helper for line_chart
function _line_chart_line_style ($text) {
  $word = false;
 if (strstr($text,'dashed')) {
   $out_second = '4,4';
   $word = true;
 }
  if (strstr($text,'dotted')) {
   $out_second = '2,2';
    $word = true;
 }
 if (strstr($text,'thick')) {
   $out_first = '5';
    $word = true;
 }
 if (strstr($text,'thin')) {
   $out_first = '1';
   $word = true;
 }
  if (strstr($text,'normal')) {
   $out_first = '2';
   $word = true;
 }
 
 if ($text == '') {
   $out = $default_line_style;
 } else {
   if ($word) {
     if (!isset($out_first)) {
	   $out = $default_line_style . ',' . $out_second;
	 } else {
	   $out = $out_first . (isset($out_second) ? ',' . $out_second : '');
	 }
   } else {
     $out = $text;
   }
 }
 
  return $out;
 
}

//***************************************************************

function extEncode($values, $max = 4095, $min = 0){
        $extended_table =
'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-.';
        //$chardata = 'e:';
        $delta = $max - $min;
		$scale = 4095  * 1.0 / $delta;
        $size = (strlen($extended_table));
        foreach($values as $k => $v){
                if($v >= $min && $v <= $max){
                        $first = substr($extended_table, floor(round(($v-$min)*$scale)/$size),1);
                        $second = substr($extended_table, ((($v - $min) % $size) * $max /
$delta), 1);
                        $chardata .= "$first$second";
                }else{
                        $chardata .= '__'; // Value out of max range;
                }
        }
        return($chardata);

}
//*********
function simEncode($values, $max = 61, $min = 0) {
  $simple_table =
'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  //$chardata = 's:';
        $delta = $max - $min;
		$scale = 61  * 1.0 / $delta;
		$size = (strlen($simple_table));
		foreach($values as $k => $v){
                if($v >= $min && $v <= $max){
					$chardata .= substr($simple_table, round((($v - $min)*$scale) % $size), 1);
				}else{
                        $chardata .= '_'; // Value out of range;
                }
		}
		return($chardata);
}
?>