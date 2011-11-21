<?php
/**
 * \ingroup mpv
 *
 * Creates directory of datafiles with calculated multidimensional scaling coordinates
 */
class MdsMotion {
  /**
  * Creates datafile with calculated multidimensional scaling coordinates
  *
  * \param $params An array of pairs <em>parameter => value</em>. Available parameters are:
  * - \c parliament_code required parliament code (e.g., 'cz/psp')
  * - \c modulo selects only each modulo's division, default 1 (e.g., '10')
  * - \c since date since in ISO format, default '-infinity' (e.g., '2011-09-01')
  * - \c until date until in ISO format, default 'infinity' (e.g., '2012-12-01')
  * - \c vote_kind_codes list of vote_kind_codes meaning 'for', default 'y,n,a'
  * - \c number_mps maximum number of MPs in one division
  *
  * - \c low_limit lower limit for inclusion in computation, default 0=all MPs
  * - \c dim number of dimension, default 4
  * - \c digit number of digits, default 4
  * - \c temp_path path to writable temporary directory, default '\tmp'
  * - \c leave_file if set, leaves R file undeleted
  *  
  * - \c period if set, calculates more MDSs; possible values: 'y','q','m'
  * - \c output required output directory
  *
  * \return directory
  *
  * http://api.kohovolit.eu/mpv/MdsMotion?parliament_code=cz/psp&since=2010-07-01&period=q&modulo=10&number_mps=200&output=/home/michal/project/mpv/matrix/cz_psp_6/&low_limit=.1
  */

  /// API client reference used for all API calls
  private $ad;
  
  	/**
	 * Creates API client reference to use during the whole process.
	 */
	public function __construct()
	{
		$this->ad = new ApiDirect('mpv');

	}
	
  public function read($params) {
    if (!isset($params['parliament_code']))
    	return array(); 
  
    //defaults
    $default_since = '-infinity';
    $default_until = 'infinity';
    $default_vote_kind_codes = "'y','n','a'";
    $default_modulo = 1;
    
    $default_temp_path = '/tmp/';
    $default_dim = 4;
    $default_digit = 4;
    
    $default_output = '/tmp/';
	  
	
	//global since and until
	$query = new Query();
    $query->setQuery("
        SELECT min(date) as since, max(date) as until FROM division
        WHERE date>=$1 AND date<$2 AND parliament_code=$3
	  ");
	  //parameters
	if (isset($params['since'])) $query->appendParam($params['since']);
		else $query->appendParam($default_since);
	if (isset($params['until'])) $query->appendParam($params['until']);
		else $query->appendParam($default_until);
	$query->appendParam($params['parliament_code']);
		
	$limits_ar = $query->execute();
	$limits = $limits_ar[0];
	
	$since = new DateTime($limits['since']);
	$until = new DateTime($limits['until']);
	
	
	//set start date and interval
	switch ($params['period']) {
	  case 'y':
	    $start = new DateTime($since->format("Y").'-01-01');
	    $interval = new DateInterval('P1Y');
	    break;
	  case 'm':
	    $start = new DateTime($since->format("Y-m").'-01');
	    $interval = new DateInterval('P1M');
	    break;
	  case 'q':
	    $start = new DateTime($since->format("Y") . '-' . (floor(($since->format("m")-1)/3)*3+1) . '-01');
	    $interval = new DateInterval('P3M');
	}
	$end = clone $start;
	$end->add($interval);
	//MdsMatrix common parameters
	$mdsmatrix_params = array(
	  'parliament_code' => $params['parliament_code'],
	  'output' => $params['output'] . 'temp.json',
	);
    if (isset($params['vote_kind_codes'])) $mdsmatrix_params['vote_kind_codes'] = $params['vote_kind_codes'];
    if (isset($params['modulo'])) $mdsmatrix_params['modulo'] = $params['modulo'];
    if (isset($params['number_mps'])) $mdsmatrix_params['number_mps'] = $params['number_mps'];
    //Mds common parameters
    $mds_params = array('input' => $params['output'] . 'temp.json');
	if (isset($params['low_limit'])) $mds_params['low_limit'] = $params['low_limit'];
	if (isset($params['dim'])) $mds_params['dim'] = $params['dim'];
	if (isset($params['digit'])) $mds_params['digit'] = $params['digit'];
	if (isset($params['temp_path'])) $mds_params['temp_path'] = $params['temp_path'];
    
	
	while ($start->diff($until)->format("%r%a") > 0)  {
	  //MdsMatrix parameters
	  if ($start->diff($since)->format("%r%a") > 0)
	    $mdsmatrix_params['since'] = $since->format("Y-m-d");
	  else
	    $mdsmatrix_params['since'] = $start->format("Y-m-d");
	  if ($end->diff($until)->format("%r%a") < 0)
	    $mdsmatrix_params['until'] = $until->format("Y-m-d");
	  else
	    $mdsmatrix_params['until'] = $end->format("Y-m-d");
	  //Mds parameters
	  switch ($params['period']) {
	    case 'y':
	      $mds_params['output'] = $params['output'] . $start->format("Y") . '.json';
	      break;
	    case 'm': 
	      $mds_params['output'] = $params['output'] . $start->format("Y") . '-' . $start->format("m") . '-01.json';
	      break;
	    case 'q':
	      $mds_params['output'] = $params['output'] . $start->format("Y") . 'Q' . (($start->format("m")-1)/3 + 1) . '.json';
	    break;
	  }
	  //triangular matrix
	  $this->ad->read('MdsMatrix',$mdsmatrix_params);
	  //mds in R
	  $this->ad->read('Mds',$mds_params);
	  //delete temp file with matrix
	  unlink($params['output'] . 'temp.json');
	  //add intervals
	  $start->add($interval);
	  $end->add($interval);
	}
	
	
	return $params['output'];//$start->diff($until)->format("%r%a") ;//$start->add($interval)->format("Y-m-d");
	  
  }

}

?>
