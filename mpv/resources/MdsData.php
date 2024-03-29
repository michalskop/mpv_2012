<?php
/**
 * \ingroup mpv
 *
 * Creates datafile with calculated multidimensional scaling coordinates by joining several datafiles (to make it 'motion')
 */
class MdsData {
  /**
  * Creates datafile with calculated multidimensional scaling coordinates by joining several datafiles (to make it 'motion')
  *
  * \param $params An array of pairs <em>parameter => value</em>. Available parameters are:
  * - \c input required  (required input or input_dir)json file
  * - \c input_dir required (required input or input_dir) directory of input json files with coordinates (if there are more of them)
  * - \c output required output json file
  *
  * - \c color **not implemented yet
  * - \c rotation rotates files to keep a group at a position; e.g. rotation=Party,PT,-1,1,1,1
  * - \c columns specifies columns; e.g. columns=Name,last_name|Date,number|Dimension 1|Dimension 2|Party,political group:name|State,constituency:name
  * - \c local_id if sets, add a local id into names (to keep them unique); e.g. Albert -> Albert (1)
  */
  
  /// API client reference used for all API calls
  private $ad;
  
  	/**
	 * Creates API client reference to use during the whole process.
	 */
	public function __construct()
	{
		$this->ad = new ApiDirect('data');

	}
  
  public function read($params) {
	if (isset($params['input'])) {
	  $tmp = explode('/',$params['input']);
	  $files = array(end($tmp));
	  array_pop($tmp);
	  $dir = implode('/',$tmp) . '/';
	} else if (isset($params['input_dir'])) {
	  $handle = opendir($params['input_dir']);
	  $files = array();
	  while (false !== ($file = readdir($handle))) {
	    if (($file != '.') and ($file != '..'))
          $files[] = $file;
      }
      sort($files);
      $dir = $params['input_dir'];
	} else return array();
	if (count($files) < 1) return array();
    
    //COLUMNS
    //get array
    $input = json_decode(file_get_contents($dir.$files[0]));
    //number of dimensions
    $dims = count($input->coordinates[0]);
    //columns
    if (!isset($params['columns'])) {
        //default
        $params['columns'] = 'Name,last_name|Date,number,0';
        for ($i = 1; $i <= $dims; $i++)
          $params['columns'] .= '|Dimension ' . $i;
    }
	//create columns
	$col_ar = explode('|',$params['columns']);
	$columns = array();
	$c = array();
	foreach ($col_ar as $crow) {
	    $c[] = explode(',',$crow);
	}
	$columns[0] = array('name' => $c[0][0],'type' => 'string');
	$columns[1] = array('name' => $c[1][0],'type' => $c[1][1]);
	for ($i = 2; $i <= $dims+1; $i++)
	    $columns[$i] = array('name' => $c[$i][0],'type' => 'number');
	for ($i = $dims+2; $i < count($c); $i++)
	    $columns[$i] = array('name' => $c[$i][0],'type' => 'string');
    
    //ROWS
    $data = array();
    $mps = array();
    $groups = array();
    $constits = array();
    if (isset($params['local_id'])) {
      $local_i = 1;
      $local_id = array();
    }
    $local_text = '';
    
    foreach ($files as $file) {
      //get array
      $input = json_decode(file_get_contents($dir.$file));
      
      //date for 'mp in group' = last division
	  $datetime = $input->info->until;
	  $datetime_alt = $input->info->since;
	  
	  //date for results
	  $tmp = explode('.',$file);
	  array_pop($tmp);
	  $date_data = implode('.',$tmp);
	  
	  //data rows
	  $data_rows = array();
	  foreach ($input->mps as $key=>$mp_id) {
	    $row = array();
	    if (!isset($mps[$mp_id])) 
	      $mps[$mp_id] = $this->ad->readOne('Mp',array('id' => $mp_id));
	    if (isset($params['local_id'])) {
	      if (!isset($local_id[$mp_id])) {
	        $local_id[$mp_id] = $local_i;
	        $local_i++;
	      }
	      $local_text = ' (' . $local_id[$mp_id] . ')';
	    }
	    $row[] = $mps[$mp_id][$c[0][1]] . ((isset($c[0][2])) ? ' ' . $mps[$mp_id][$c[0][2]] : '') . $local_text;
	    $row[] = $date_data;
	    //coordinates
	    for ($i = 0; $i < $dims; $i++)
	      $row[] = $input->coordinates[$key][$i];
	    //groups
	    for ($i = $dims+2; $i < count($c); $i++) {
	      $kind = explode(':',$c[$i][1]);
	      $memberships = $this->ad->read('MpInGroup', array('mp_id' => $mp_id, 'role_code' => 'member', '_datetime' => $datetime)); 
	      if (count($memberships) == 0) //try 'since'
	        $memberships = $this->ad->read('MpInGroup', array('mp_id' => $mp_id, 'role_code' => 'member', '_datetime' => $datetime_alt));
	        $ok = false;
	        foreach ($memberships as $membership) {
	          if ($kind[0] == 'constituency') {
	          	if ($membership['constituency_id'] != '') {
	          	  if (!isset($constits[$membership['constituency_id']]))
	          	    $constits[$membership['constituency_id']] = $this->ad->readOne('Constituency', array('id' => $membership['constituency_id']));
	          	  if ($constits[$membership['constituency_id']]['parliament_code'] == $input->info->parliament_code) {
	          	    $row[] = $constits[$membership['constituency_id']][$kind[1]];
	          	    $ok = true;
	          	    continue;
	          	  }
	          	}
	          } else {
	          	  if (!isset($groups[$membership['group_id']]))
	          	    $groups[$membership['group_id']] = $this->ad->readOne('Group', array('id' => $membership['group_id']));
			      if (($groups[$membership['group_id']]['group_kind_code'] == $kind[0]) and
			       ($groups[$membership['group_id']]['parliament_code'] == $input->info->parliament_code))
			      {
			        if (!$ok) { //if there are 2 memberships, which should not be, use only 1
			          $row[] = $groups[$membership['group_id']][$kind[1]];
			          $ok = true;
			        }
			        continue;
			        
			      }
			  }
	        }
		if (!$ok) $row[] = '-';
	    }
	    $data_rows[] = $row;
	  }
	  
	  //rotation
	  if (isset($params['rotation'])) {
	    $rot_ar = explode('|',$params['rotation']);
	    foreach ($rot_ar as $rot) {
	      $rotation = explode(',',$rot);
	      //find column
	      foreach ($columns as $key => $column)
	        if ($column['name'] == $rotation[0]) { $rkey = $key; continue; }
	      //sum group
	      $sum = array();
	      for ($i = 2; $i <= $dims+1; $i++) $sum[$i] = 0;
	      foreach ($data_rows as $row) {
	        if ($row[$rkey] == $rotation[1]) {
	          for ($i = 2; $i <= $dims+1; $i++)
	            $sum[$i] += $row[$i];
	        }
	      }
	      //compare and rotate 
	      // (we are lucky, i is the same for sum and rotate)
	      for ($i = 2; $i <= $dims+1; $i++) {
	        if ($sum[$i]*$rotation[$i] < 0) {
	          //rotate
	          foreach ($data_rows as $dkey=>$row) {
	            $data_rows[$dkey][$i] = -1*$data_rows[$dkey][$i];
	          }
	        }
	      }    
	    }
	  }
	  
	  //merge data from the current file into output
	  $data = array_merge($data,$data_rows);
    }
    //output
    $out = array(
      'columns' => $columns,
      'data' => $data
    );
    
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

?>
