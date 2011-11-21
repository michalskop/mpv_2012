<?php

/**
 * \ingroup mpv
 *
 * Calculates matrix of 'correlations' between MPs
 */
class MdsMatrix
{
  /**
  * Calculates matrix of 'correlations' between MPs
  *
  * \param $params An array of pairs <em>parameter => value</em>. Available parameters are:
  * - \c parliament_code required parliament code (e.g., 'cz/psp')
  * - \c modulo selects only each modulo's division, default 1 (e.g., '10')
  * - \c since date since in ISO format, default '-infinity' (e.g., '2011-09-01')
  * - \c until date until in ISO format, default 'infinity' (e.g., '2012-12-01')
  * - \c vote_kind_codes list of vote_kind_codes meaning 'for', default 'y,n,a'
  * - \c number_mps maximum number of MPs in one division
  * - \c output json file, if set, writes json into it
  *
  * \return Array: Matrix of 'correlations', Parliament, Dates (since, until)
  */

  public function read($params)
  {
    if (!isset($params['parliament_code']))
    	return array(); 	
    //defaults
    $default_since = '-infinity';
    $default_until = 'infinity';
    $default_vote_kind_codes = "'y','n','a'";
    $default_modulo = 1;
        
    //random number to allow more processes in once
    $rand = '';
    for ($i=0;$i<=7;$i++) {
      $rand .= rand(0,9);
    }
    
    //lists of vote_kind_codes
    if (isset($params['vote_kind_codes'])) {
      $array = explode(',',$params['vote_kind_codes']);
      $vote_kind_codes = "'".implode("','")."'";
    } else $vote_kind_codes = "'y','n','a'";
    
    //query selecting only relevant mp_votes
    $query = new Query();
    $query->setQuery("   
    	SELECT 
		  mv.mp_id, 
		  mv.division_id, 
		  CASE WHEN (mv.vote_kind_code = 'y') THEN 1 ELSE -1 END as vote
		INTO x_mds_mp_vote_{$rand}

		FROM mp_vote as mv
		LEFT JOIN division as d
		ON mv.division_id = d.id
		WHERE \"date\" >= $1 AND \"date\" <= $2 
		AND d.parliament_code = $3
		AND vote_kind_code IN ({$vote_kind_codes})
		AND mod(d.id,$4) = 0;
    ");
    //parameters
    if (isset($params['since'])) $query->appendParam($params['since']);
    else $query->appendParam($default_since);
    
    if (isset($params['until'])) $query->appendParam($params['until']);
    else $query->appendParam($default_until);
    
    $query->appendParam($params['parliament_code']);
    
    //if (isset($params['vote_kind_codes'])) $query->appendParam($params['vote_kind_codes']);
    //else $query->appendParam($default_vote_kind_codes);
    
    if (isset($params['modulo'])) $query->appendParam($params['modulo']);
    else $query->appendParam($default_modulo);
   
    $query->execute(); 
    
    //add primary key
    $query = new Query();
    $query->setQuery("ALTER TABLE x_mds_mp_vote_{$rand}
 	  ADD CONSTRAINT x_mds_mp_vote_{$rand}_pkey PRIMARY KEY(mp_id, division_id);");
 	$query->execute(); 
 	
 	//get max number of mps
 	if (isset($params['number_mps'])) $max = $params['number_mps'];
 	else {
	 	$query = new Query();
	 	$query->setQuery("
	 	  SELECT max(count) as max FROM
	 	  (SELECT count(*) as count
	 	  FROM mp_vote as mv
			LEFT JOIN division as d
			ON mv.division_id = d.id
	 	  WHERE \"date\" >= $1 AND \"date\" <= $2 
		  AND d.parliament_code = $3
		  GROUP BY d.id) as t
	 	");
	 	//parameters
		if (isset($params['since'])) $query->appendParam($params['since']);
		else $query->appendParam($default_since);
		
		if (isset($params['until'])) $query->appendParam($params['until']);
		else $query->appendParam($default_until);
		
		$query->appendParam($params['parliament_code']);
		
		$max_ar = $query->execute(); 
		$max = $max_ar[0]['max'];
	}
 	
 	
 	//query selecting only relevant divisions
 	$query = new Query();
    $query->setQuery("
	 	SELECT 
		  d.id,
		  max(d.date) as date,
		  cast(count(*) as float)/$1 as w1, 
		  1/(abs( ((count(*)+sum(mv.vote))/2) - round(count(*)/2+1)-.5 ) +.5) as w2,--1/(abs(n_yes-needed)+.5); needed=round(count/2+1)-.5
		  ($1-cast(count(*)+sum(mv.vote) as float)/2)/($1-1) as wy, --n_yes = (count(*)+sum(mv.vote))/2; n_no = (count(*)-sum(mv.vote))/2
		  ($1-cast(count(*)-sum(mv.vote) as float)/2)/($1-1) as wn
		INTO x_mds_division_{$rand}
		FROM x_mds_mp_vote_{$rand} as mv
		LEFT JOIN division as d
		ON mv.division_id = d.id
		GROUP BY d.id;
	");
	
	$query->appendParam($max);
	
	$query->execute();
	
	//add primary key
	$query = new Query();
    $query->setQuery("ALTER TABLE x_mds_division_{$rand}
	  ADD CONSTRAINT x_mds_division_{$rand}_pkey PRIMARY KEY(id) ");
	$query->execute();
	
	//query calculate the matrix
	$query = new Query();
    $query->setQuery("
		SELECT 
		  t.*, 
		  CASE WHEN (together>0) THEN 2*same/together-1 ELSE 0 END as correlation
		--INTO x_mds_cor_{$rand}
		FROM
		(
			SELECT
			  a1.mp_id_1,
			  a1.mp_id_2,
			  coalesce(together,0) as together,
			  coalesce(same,0) as same
			  
			FROM
			-- this part ensures all doubles are present, even with cor=0
			(SELECT d1.mp_id as mp_id_1,d2.mp_id as mp_id_2 FROM
			  (SELECT distinct(mp_id) FROM x_mds_mp_vote_{$rand}) as d1
			  FULL JOIN 
			  (SELECT distinct(mp_id) FROM x_mds_mp_vote_{$rand}) as d2
			  ON 1=1
			  WHERE d1.mp_id <= d2.mp_id
			) as a1
			LEFT JOIN
			(
			SELECT 
			mv1.mp_id as mp_id_1,
			mv2.mp_id as mp_id_2,

			sum(
				w1*w2*sqrt(
				( ((mv1.vote+1)/2*wy + abs(mv1.vote-1)/2 ) *
				  ((mv2.vote+1)/2*wy + abs(mv2.vote-1)/2 ) )
				)
			) as together,
			sum(
				w1*w2*(mv1.vote*mv2.vote +1)/2 *
				sqrt(
				( ((mv1.vote+1)/2*wy + abs(mv1.vote-1)/2 ) *
				  ((mv2.vote+1)/2*wy + abs(mv2.vote-1)/2 ) )
				)
			) as same

			FROM x_mds_mp_vote_{$rand} as mv1
			JOIN x_mds_mp_vote_{$rand} as mv2
			ON mv1.division_id = mv2.division_id

			LEFT JOIN 
			x_mds_division_{$rand} as d
			ON mv1.division_id = d.id

			WHERE mv1.mp_id <= mv2.mp_id

			GROUP BY mv1.mp_id, mv2.mp_id
			) as a2
			ON a1.mp_id_1=a2.mp_id_1 AND a1.mp_id_2=a2.mp_id_2
		
		) as t
		ORDER BY mp_id_1, mp_id_2
	");
	$triang_matrix = $query->execute();
	
	//get min and max date from division
		//query calculate the matrix
	$query = new Query();
    $query->setQuery("
      SELECT min(date) as since, max(date) as until FROM x_mds_division_{$rand}
    ");
    $dates = $query->execute();
    //set info
    $out = array(
      'info' => array('since' => $dates[0]['since'], 'until' => $dates[0]['until'], 'parliament_code' => $params['parliament_code']),
      'matrix' => $triang_matrix
    );
	
	//clean up
	$query = new Query();
    $query->setQuery("DROP TABLE x_mds_division_{$rand}");
    $query->execute();
    $query = new Query();
    $query->setQuery("DROP TABLE x_mds_mp_vote_{$rand}");
    $query->execute();
    
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
