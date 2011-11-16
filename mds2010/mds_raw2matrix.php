<?php
//Calculates "correlation" matrix of MPs according their votes
//http://piratepad.net/t601h21jw4

set_time_limit(0);

// include common functions
include ('mds_common_latest.php');
//include  possible private functions
//include ('mds_private_latest.php');

//set error
$result['error']['error'] = FALSE;
$e = 0;

//get parameters
	//REQUIERED parliament; eg. cz_psp, sk_nrsr, ...
$parliament = $_GET['parliament'];
	//since (format yyyy-mm-dd)
$since = $_GET['since'];
	//until (format yyyy-mm-dd)
$until = $_GET['until'];
	//weighting method; defualt 'natural', values: natural, none
$weight_method = $_GET['weight_method'];
	//modulo (to select only every Modulo division)
$modulo = $_GET['modulo'];
	//maximal number of MPs in the parliamenet; eg. 200, ...
$max_n_mp = $_GET['max_n_mp'];
	//lower limit to eliminate those with too few votes
$lo_limit = $_GET['lo_limit'];
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
if ($since == '') $since = '1000-01-01';
if ($until == '') $until = '3000-01-01';
if ($format == '') $format = 'xml';
if ($parliament == '') {
	add_error($result,'Missing "parliament" parameter',$e);
	$out = format_array($result,$format);
	echo $out;
	die();
}
if ($modulo == '') $modulo = 1;
if ($lo_limit == '') $lo_limit = .1;
if ($max_n_mp == '') {
	//max of MPs  in  a division
	$q[0] = "
	SELECT max(count) FROM (
	  SELECT count(*)  FROM mp_vote
	  GROUP BY division_id) as t1
	";
  $n_ar = exec_sql($q[0],array(),$parliament);
  $max_n_mp = $n_ar[0]['max'];
}

//basic information
	$version = version($version);
	$result['description'] = 'Calculates "correlation" matrix of MPs according their votes';
	$result['author'] = 'Michal Škop, KohoVolit.eu';
	$result['version'] = $version;
	$result['chartset'] = 'utf-8';

//QUERIES

//x_mds_6
//25s with 125 divisions and 200MPs !
//('y','n','a') ! not implemented yet
//LO_LIMIT + HI_LIMIT
// "4";"4";"y";"y";"43965";"2006-10-24 14:46:00"
$q['1.0'] = "
DROP TABLE IF EXISTS x_mds_LO_LIMIT_Y;
";
$q['1.1'] = "
SELECT mv1.mp_id as mp_id_1,mv2.mp_id as mp_id_2,mv1.vote_kind_code as vote1, 
mv2.vote_kind_code as vote2, d.division_id
INTO x_mds_LO_LIMIT_Y
FROM mp_vote as mv1

JOIN
mp_vote as mv2
ON mv1.division_id = mv2.division_id

LEFT JOIN 
division as d
ON mv1.division_id = d.division_id

WHERE d.divided_on >= $1 AND d.divided_on <= $2
AND mv1.mp_id >= mv2.mp_id
AND mv1.vote_kind_code IN ('y','n','a') AND mv2.vote_kind_code IN ('y','n','a')
AND mod(d.division_id,$3) = 0;
";
$q['1.2'] = "
ALTER TABLE x_mds_LO_LIMIT_Y
  ADD CONSTRAINT x_mds_LO_LIMIT_Y_pkey PRIMARY KEY(mp_id_1, mp_id_2, division_id);
";

//x_w_6
//LO_LIMIT
//weight_old: N  min / N half; 50x100 -> 50/75 = .666
//weight: 1/(N to change result ) * presence; 50x100 -> 1/26 * (150/200) = 0.03
//max(sum) = N majority (winners), sum(sum) - max(sum) = N minority (losers) ; - because of 0 minority!
$q['2.0'] = "
DROP TABLE IF EXISTS x_mds_w_LO_LIMIT_Y;
";
$q['2.1'] = "
SELECT 
division_id, 
(sum(my_sum)>0) as yes_win,
(sum(sum) - max(sum))/ sum(sum)*2 as weight_old, 
CASE WHEN (sum(sum) = max(sum)) THEN 1 ELSE
(sum(sum)/max(sum)) END 
 as winner,
CASE WHEN (sum(sum) = max(sum)) THEN 1 ELSE
(sum(sum)/min(sum)) END 
 as loser,
CASE WHEN (sum(sum) = max(sum)) THEN 1 ELSE
sqrt((sum(sum)/min(sum)) * (sum(sum)/max(sum))) END 
 as contra,
(1/((abs(sum(my_sum)-mod(sum(sum)+1,2))+1)/2))*(sum(sum))/$1 as weight
INTO x_mds_w_LO_LIMIT_Y

FROM
(SELECT division_id,_for,sum(count),
(CASE WHEN _for THEN sum(count) ELSE -sum(count) END) as my_sum 
FROM
(SELECT (vote_kind_code='y') as _for ,d.division_id,count(*)
FROM mp_vote as mv
LEFT JOIN
division as d
ON mv.division_id = d.division_id
WHERE divided_on >= $2 AND divided_on <= $3
AND vote_kind_code IN ('y','n','a')
AND mod(d.division_id,$4) = 0
GROUP BY mv.vote_kind_code,d.division_id) as t1
GROUP BY division_id, _for) as t2
GROUP BY division_id
ORDER BY division_id
;
";
$q['2.2'] = "
ALTER TABLE x_mds_w_LO_LIMIT_Y
  ADD CONSTRAINT x_mds_w_LO_LIMIT_Y_pkey PRIMARY KEY(division_id);
";

//matrix
//LO_LIMIT
//200MPs  * 125divisions -> 36s !
//the weight for division (weiight) and for winners/losers/contras are multiplied - can be changed for a sum, etc.
$q['4.0'] = "
DROP TABLE IF EXISTS x_mds_LO_LIMIT_Y_cor;
";
$q['4.01'] = "
SELECT t1.mp_id_1,t1.mp_id_2, together, same_vote, (CASE WHEN (together>0) THEN (2.0*same_vote)/together-1 ELSE 0 END) as cor 
INTO x_mds_LO_LIMIT_Y_cor
FROM
(SELECT mp_id_1,mp_id_2,
sum((
  CASE WHEN (((vote1='y' AND vote2 = 'y') AND yes_win) OR (vote1 IN ('n','a') 
    AND vote2 IN ('n','a') AND NOT(yes_win))) THEN winner ELSE
    CASE WHEN (((vote1='y' AND vote2 = 'y') AND NOT(yes_win)) OR (vote1 IN ('n','a') 
    AND vote2 IN ('n','a') AND yes_win)) THEN loser ELSE
    contra END END
   )*weight) as together
FROM x_mds_LO_LIMIT_Y as x5
LEFT JOIN x_mds_w_LO_LIMIT_Y  as xw5
ON x5.division_id = xw5.division_id
GROUP BY mp_id_1,mp_id_2
) as t1
LEFT JOIN
(SELECT mp_id_1,mp_id_2,
sum((
  CASE WHEN (((vote1='y' AND vote2 = 'y') AND yes_win) OR (vote1 IN ('n','a') 
    AND vote2 IN ('n','a') AND NOT(yes_win))) THEN winner ELSE loser END
   )*weight) as same_vote
FROM x_mds_LO_LIMIT_Y  as x5
LEFT JOIN x_mds_w_LO_LIMIT_Y  as xw5
ON x5.division_id = xw5.division_id
WHERE (vote1='y' AND vote2 = 'y') or (vote1 IN ('n','a') AND vote2 IN ('n','a'))
GROUP BY mp_id_1,mp_id_2
) as t2
ON t1.mp_id_1 = t2.mp_id_1 AND t1.mp_id_2 = t2.mp_id_2
ORDER BY t1.mp_id_1,t2.mp_id_2 DESC
;
";
$q['4.02'] = "
ALTER TABLE x_mds_LO_LIMIT_Y_cor
  ADD CONSTRAINT x_mds_LO_LIMIT_Y_cor_pkey PRIMARY KEY(mp_id_1,mp_id_2);
";

//selection of MPs
//$lower_limit
$q['4.10'] = "
DROP TABLE IF EXISTS x_mds_LO_LIMIT_Y_selected_mp;
";
$q['4.11'] = "
SELECT * 
  INTO x_mds_LO_LIMIT_Y_selected_mp
FROM 
  (SELECT mp_id_1 as mp_id, sum(together), max(together)
  FROM x_mds_LO_LIMIT_Y_cor
  WHERE mp_id_1 = mp_id_2
  GROUP BY mp_id_1) as t1
WHERE sum > $1*(SELECT max(together) FROM x_mds_LO_LIMIT_Y_cor WHERE mp_id_1 = mp_id_2);
";
$q['4.12'] = "
ALTER TABLE x_mds_LO_LIMIT_Y_selected_mp
  ADD CONSTRAINT x_mds_LO_LIMIT_Y_selected_mp_pkey PRIMARY KEY(mp_id);
";

//select the matrix (selected MPs only)
//200MPs -> 15s
$q['4.2'] = "
 SELECT mp_id_1,mp_id_2,cor 
FROM x_mds_LO_LIMIT_Y_cor as xc
WHERE 
mp_id_1 IN (SELECT mp_id FROM x_mds_LO_LIMIT_Y_selected_mp)
AND 
mp_id_2 IN (SELECT mp_id FROM x_mds_LO_LIMIT_Y_selected_mp)
ORDER BY mp_id_1,mp_id_2
";

$q['4.3'] = "
  SELECT count(*) FROM x_mds_LO_LIMIT_Y_selected_mp
";

//clear
$q['6.1'] = "
	DROP TABLE x_mds_LO_LIMIT_Y;
";
$q['6.2'] = "
	DROP TABLE x_mds_w_LO_LIMIT_Y;
";
$q['6.3'] = "DROP TABLE x_mds_LO_LIMIT_Y_selected_mp;
";
$q['6.4'] = "
	DROP TABLE x_mds_LO_LIMIT_Y_cor;
";



exec_sql($q['1.0'],array(),$parliament);
exec_sql($q['1.1'],array($since,$until,$modulo),$parliament);
exec_sql($q['1.2'],array(),$parliament);

exec_sql($q['2.0'],array(),$parliament);
exec_sql($q['2.1'],array($max_n_mp,$since,$until,$modulo),$parliament);
exec_sql($q['2.2'],array(),$parliament);

exec_sql($q['4.0'],array(),$parliament);
exec_sql($q['4.01'],array(),$parliament);
exec_sql($q['4.02'],array(),$parliament);

exec_sql($q['4.10'],array(),$parliament);
exec_sql($q['4.11'],array($lo_limit),$parliament);
exec_sql($q['4.12'],array(),$parliament);

$result['correlation'] = exec_sql($q['4.2'],array(),$parliament);
$pom = exec_sql($q['4.3'],array(),$parliament);
$result['mp_number'] = $pom[0]['count'];

exec_sql($q['6.1'],array(),$parliament);
exec_sql($q['6.2'],array(),$parliament);
exec_sql($q['6.3'],array(),$parliament);
exec_sql($q['6.4'],array(),$parliament);

$out = format_array($result,$format);
echo $out;



/**
* version
*/
function version($version) {
  return '20100722';
}

?>