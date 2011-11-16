<?php
/**
*stahovaci funkce
*/
function Grabber($url)
{
$ch = curl_init ();
curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt ($ch, CURLOPT_URL, $url);
curl_setopt ($ch, CURLOPT_TIMEOUT, 120);
return curl_exec($ch);
curl_close ($ch);
} 

/**
* Create  connection to the legacy database
* @return connection
*/
function  create_connection() {
    include  'database_credentials.php';//include the database credentials; necessary  to use     'include' and not 'include_once' so that multiple queries  can be made from the same web page.
    
// Connect to  pgsql
  $connection = pg_connect("host={$host}  port={$port} dbname={$dbname} user={$user} password={$password}");
  return  $connection;
} // end modulename_create_connection()

/**
* Perform database queries.
* @return result  Database query result
*/
function  exec_sql($query_string,$params = array(),$schema = '') {
  // Create  connection
  $connection = create_connection();
  if  (!$connection){
    return false;
  } else {
    // Perform  DB Query
	//pg_query_params("SET  search_path='{$parliament},{$parliament}_prep';",array());
	pg_query_params("SET search_path='{$schema}';",array());
	pg_query_params("SET client_encoding TO 'UTF-8';",array());
    $result = pg_fetch_all(pg_query_params($query_string,$params));   
    pg_close  ($connection);
    return $result;
  }
} // end  modulename_perform_query()


/**
* formats the array for output
* due to inconsistent  output from json_encode, the output as an object is used in case of json
*@return fotmatted array
*/
function format_array($in,$format='') {
  switch ($format) {
    case 'php':
		$out = serialize($in);
		break;
	case 'json':
		//$out = json_encode ( $in, JSON_FORCE_OBJECT );	//requires(PHP 5 >= 5.2.0, PECL json >= 1.2.0)
		$out = json_encode ( $in);
		break;
	case 'xml':
	default:
		$out = array2xml($in,'KohoVolit.eu',true);
		break;
  }
  return $out;
}

/**
* check the key
*/
function check_key($key = '') {
  if ($key == 'public_'.date("Y").date("m")) {
    return TRUE;
  } else {
    if (!function_exists('check_custom_key')) {
	    function check_custom_key($key) {
		  return FALSE;
		}
	}
	if (check_custom_key($key)) {
	  return TRUE;
	} else {
      return FALSE;
	}
  }
}

/**
* output information if wrong key
*/
function wrong_key() {
  return 'Wrong key.';
}

/**
* adds an error
*/
function add_error (&$result, $text='Error', &$e = 0) {
   	$result['error']['description']['description_'.$e] = $text;
	$result['error']['error'] = TRUE;
	$e++;
}


// oreze diakritiku, napr. 'ČSSD' zmeni na 'cssd'
function friendly_url($text,$encoding='cs_CZ.utf-8') {
setlocale(LC_ALL,$encoding);
    $url = $text;
    $url = preg_replace('~[^\\pL0-9_]+~u', '-', $url);
    $url = trim($url, "-");
    $url = iconv("utf-8", "us-ascii//TRANSLIT", $url);
    $url = strtolower($url);
    $url = preg_replace('~[^-a-z0-9_]+~', '', $url);
    return $url;
}

/**
 * array2xml() will convert any given array into a XML structure.
 *
 * Version:     1.0
 *
 * Created by:  Marcus Carver © 2008
 *
 * Email:       marcuscarver@gmail.com
 *
 * Link:        http://marcuscarver.blogspot.com/
 *
 * Arguments :  $array      - The array you wish to convert into a XML structure.
 *              $name       - The name you wish to enclose the array in, the 'parent' tag for XML.
 *              $standalone - This will add a document header to identify this solely as a XML document.
 *              $beginning  - INTERNAL USE... DO NOT USE!
 *
 * Return:      Gives a string output in a XML structure
 *
 * Use:         echo array2xml($products,'products');
 *              die;
*/

function array2xml($array, $name='array', $standalone=TRUE, $beginning=TRUE) {

  global $nested;

  if ($beginning) {
    if ($standalone) header("content-type:text/xml;charset=utf-8");
    $output .= '<'.'?'.'xml version="1.0" encoding="UTF-8"'.'?'.'>';
    $output .= '<' . $name . '>';
    $nested = 0;
  }
 
  // This is required because XML standards do not allow a tag to start with a number or symbol, you can change this value to whatever you like:
  $ArrayNumberPrefix = 'ARRAY_NUMBER_';
 
   foreach ($array as $root=>$child) {
    if (is_array($child)) {
      $output .= str_repeat(" ", (2 * $nested)) . '  <' . (is_string($root) ? $root : $ArrayNumberPrefix . $root) . '>';
      $nested++;
      $output .= array2xml($child,NULL,NULL,FALSE);
      $nested--;
      $output .= str_repeat(" ", (2 * $nested)) . '  </' . (is_string($root) ? $root : $ArrayNumberPrefix . $root) . '>';
    }
    else {
      $output .= str_repeat(" ", (2 * $nested)) . '  <' . (is_string($root) ? $root : $ArrayNumberPrefix . $root) . '><![CDATA[' . $child . ']]></' . (is_string($root) ? $root : $ArrayNumberPrefix . $root) . '>';
    }
  }
 
  if ($beginning) $output .= '</' . $name . '>';
 
  return $output;
}
?>