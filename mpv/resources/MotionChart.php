<?php
/**
 * \ingroup mpv
 *
 * Creates the Google Motion Chart from data json file
 */
class MotionChart {
  /**
  * Creates the Google Motion Chart from data json file
  * \param $params An array of pairs <em>parameter => value</em>. Available parameters are:
  * - \c input required input json datafile in proper format
  * - \c include_script whether to include the line <script type='text/javascript' src='http://www.google.com/jsapi'></script>; default = true
  * - \c include_tags whether to include the lines <script type='text/javascript'> </script>; default true
  * - \c include_div whether to include the line <div id="chart_div" ></div>; default = true
  * - \c custom_code included to differ the script (if more such charts on a webpage); e.g. custom_code='xyz' makes <div id='chart_div'> into <div id='chart_div_xyz'>
  * - \c options set of options; e.g., options=showChartButtons:false,showXScalePicker:false
  * - \c language
  * - \c order groups; e.g., order=Klub,ODS,TOP09-S,VV,KSČM,ČSSD|...
  * - \c order_dim order by one dimension; e.g. order_dim=Party:Dimension 1
  *
  * \return script for motion chart
  */
  public function read($params) {
    $out = '';
   
    if (!isset($params['input']))
    	return array();
    else $input = json_decode(file_get_contents($params['input']));
    
    //custom code
    if (isset($params['custom_code'])) $custom_code = '_' . $params['custom_code'];
    else $custom_code = '';
    
    //order by group by dimension
    if (isset($params['order_dim']))
      $params['order'] = $this->order_dim($input,$params['order_dim']);
    
    //order groups
    if (isset($params['order'])) 
      $input = $this->order($input,$params['order']);
    
    //include script
    if ((!isset($params['include_script'])) or ($params['include_script']))
    	$out .= "<script type='text/javascript' src='http://www.google.com/jsapi'></script>\n";
    
    //include starting tag
    if ((!isset($params['include_tags'])) or ($params['include_tags']))
    	$out .= "<script type='text/javascript'>\n";
    
    //include part with language
    if (isset($params['language'])) $lang = ",'language' : '{$params['language']}'"; else $lang = '';
    $out .= "    google.load('visualization', '1', {'packages':['motionchart']{$lang}});
    google.setOnLoadCallback(drawChart{$custom_code});
    function drawChart{$custom_code}() {
    var data = new google.visualization.DataTable();\n";
    
    //include columns
    foreach($input->columns as $col) {
  	  $out .= "    data.addColumn('" . $col->type ."', '" . $col->name . "');\n";
	}
	
	//include rows
	$d = '';
	$out .= "    data.addRows([\n";
	foreach($input->data as $row) {
	  $j = 0;
	  $r = array();
	  $d .= "	[";
	  foreach($row as $item) {
		if ($input->columns[$j]->type == 'string')
		  $r[] = "'" . str_replace("'"," ",html_entity_decode($item)) . "'";
		else 
		  $r[] = $item ;
		$j++;
	  }
	  $d .= implode(',',$r);
	  $d .= "],\n";
	}
	$out .= rtrim(rtrim($d),',');
	$out .= "\n    ]);\n";
	
	//include new
	$out .="    var chart = new google.visualization.MotionChart(document.getElementById('chart_div{$custom_code}'));
    var options = {};\n";
    
    //include options
    if (isset($params['options'])) {
      $options = explode('|',$params['options']);
      foreach ($options as $option) {
        $op = explode('::',$option);
        $out .= "    options['{$op[0]}'] = {$op[1]};\n";
      }
    }
    
    //include end of function
    $out .= "    chart.draw(data, options);
    }\n";
    
    //include closing tag
    if ((!isset($params['include_tags'])) or ($params['include_tags']))
    	$out .= "</script>\n";
    
    //include div
    if ((!isset($params['include_div'])) or ($params['include_div']))
    	$out .= "<div id='chart_div{$custom_code}'></div>";

    
	return $out;//json_encode($input);
  }
  
  /**
  * create 'order text' by ordering using sums of one dimension 
  * \param param e.g., 'Klub:Dimenze 1'
  */
  private function order_dim($input,$param) {
    //
    $p = explode(':',$param);
    //find dimension
    foreach ($input->columns as $key=>$col) {
      if ($col->name == $p[0]) $ckey = $key;
      if ($col->name == $p[1]) $skey = $key; 
    }
    if (!isset($ckey) or !isset($skey)) return '';
    // sums
    $sums = array();
    $ns = array();
    foreach ($input->data as $row) {
      if (isset($sums[$row[$ckey]])) {
        $sums[$row[$ckey]] -= $row[$skey];
        $ns[$row[$ckey]] ++;
      } else {
        $sums[$row[$ckey]] = -1*$row[$skey];
        $ns[$row[$ckey]] = 1;
      }
    }
    foreach ($sums as $key => $sum) {
      $sums[$key] = $sums[$key]/$ns[$key];
    }
    asort($sums);
    //get string
    return implode(',',array($p[0],implode(',',array_keys($sums))));

  }
  
  /**
  * orders group (adds number before the name); e.g. ODS -> 1-ODS
  */
  
  private function order($input,$order_param) {
    $order_ar = explode('|',$order_param);
    //for each column t oorder
    foreach ($order_ar as $ocol) {
      // get number of group; e.g., $order['ČSSD'] = 5 
      $order_ar2 = explode(',',$ocol);
      foreach ($order_ar2 as $key => $o) {
        $order[$o] = $key;
      }
      array_shift($order);
      
      //get number of digits
      $digits = strlen(end($order));
      
      //find column
      foreach ($input->columns as $key => $column)
	    if ($column->name == $order_ar2[0]) { $okey = $key; continue; }
	  
	  //rename  
	  foreach ($input->data as &$row) {
	    $row[$okey] = self::n($order[$row[$okey]],$digits) . '-' . $row[$okey];
	  }
	        
    }
    return $input;
  }
  
  private function n($number,$digits) {
    $diff = $digits - strlen($number);
    for($i = 1; $i <= $diff; $i++)
      $number = '0' . $number;
    return $number;  
  }
}
?>
