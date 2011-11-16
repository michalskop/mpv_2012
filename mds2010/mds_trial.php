<?php

/*echo urlencode('http://localhost/m/kohovolit/working/mds/mds_matrix2coord.php?key=public_201007&link=http%3A%2F%2Flocalhost%2Fm%2Fkohovolit%2Fworking%2Fmds%2Fmds_raw2matrix.php%3Fkey%3Dpublic_201007%26modulo%3D1000%26parliament%3Dsk_nrsr%26format%3Dphp&format=php');
die();*/

set_time_limit(0);
	include ('mds_common_latest.php');
//get parameters
	//REQUIERED link OR file (not o,[lemented yet)
$link = $_GET['link'];

$link = 'http://test.kohovolit.sk/mds/mds_mp_attr.php?key=public_201007&format=php&rotation=poslaneck%C3%BD+klub,ODS,1,1,1,1&group_kind=poslaneck%C3%BD+klub,volebn%C3%AD+kraj&parliament=cz_psp&link=http%3A%2F%2Ftest.kohovolit.sk%2Fmds%2Fmds_matrix2coord.php%3Fkey%3Dpublic_201007%26format%3Dphp%26link%3Dhttp%253A%252F%252Ftest.kohovolit.sk%252Fmds%252Fmds_raw2matrix.php%253Fkey%253Dpublic_201007%2526parliament%253Dcz_psp%2526format%253Dphp%2526lo_limit%253D.1%2526modulo%253D1';
//$link = 'http://test.kohovolit.sk/mds/mds_mp_attr.php?key=public_201007&format=php&rotation=poslaneck%C3%BD+klub,SNS,1,1,1,1&group_kind=poslaneck%C3%BD+klub&parliament=sk_nrsr&link=http%3A%2F%2Ftest.kohovolit.sk%2Fmds%2Fmds_matrix2coord.php%3Fkey%3Dpublic_201007%26format%3Dphp%26link%3Dhttp%253A%252F%252Ftest.kohovolit.sk%252Fmds%252Fmds_raw2matrix.php%253Fkey%253Dpublic_201007%2526parliament%253Dsk_nrsr%2526format%253Dphp%2526lo_limit%253D.1%2526modulo%253D1000';
$link = 'http://test.kohovolit.sk/mds/mds_mp_attr.php?key=public_201007&format=php&parliament=sk_nrsr&link=http%3A%2F%2Ftest.kohovolit.sk%2Fmds%2Fmds_matrix2coord.php%3Fkey%3Dpublic_201007%26format%3Dphp%26link%3Dhttp%253A%252F%252Ftest.kohovolit.sk%252Fmds%252Fmds_raw2matrix.php%253Fkey%253Dpublic_201007%2526parliament%253Dsk_nrsr%2526format%253Dphp%2526lo_limit%253D.1%2526modulo%253D15';
$url = $link;
$array = unserialize(Grabber($url));


echo "
<html>
  <head>
    <meta http-equiv='content-type' content='text/html; charset=utf-8'> 
		Ukázka zobrazení parlamentu v čase dle toho, jak poslanci hlasovali. Poslanecká sněmovna, 5. volební období. Osa x odpovídá rozdělení koalice - opozice (nebo též 'pravice' - 'levice'), význam osy y se v čase asi mění. Lze zde velmi dobře sledovat různé chování 'přeběhlíků'. (Výpočteno váženou metodou multidimensional scaling.)
    <script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {'packages':['motionchart'],'language' : 'en_GB'});
      google.setOnLoadCallback(drawChart);
      function drawChart() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Poslanec');
        data.addColumn('string', 'Datum');
        data.addColumn('string', 'Klub');
        data.addColumn('number', 'D1');
        data.addColumn('number', 'D2');
		data.addColumn('string', 'Kraj');
        data.addRows([
";
foreach ($array['mds']['coordinates'] as $row) {
  $mps .= "['{$row['last_name']} {$row['first_name']}','2010Q1','{$row['poslanecky-klub']['short_name']}',{$row['dim_1']},{$row['dim_2']},'{$row['volebni-kraj']['short_name']}'],";
}
$mps = rtrim($mps,',');
echo $mps;
echo '
  ]);
        var chart = new google.visualization.MotionChart(document.getElementById("chart_div"));
		var options = {};
		options["state"] =
\'{"iconKeySettings":[],"stateVersion":3,"time":"notime","xAxisOption":"_NOTHING","playDuration":15,"iconType":"BUBBLE","sizeOption":"_NOTHING","xZoomedDataMin":null,"xZoomedIn":false,"duration":{"multiplier":1,"timeUnit":"none"},"yZoomedDataMin":null,"xLambda":1,"colorOption":3,"nonSelectedAlpha":0.4,"dimensions":{"iconDimensions":[]},"yZoomedIn":false,"yAxisOption":"_NOTHING","yLambda":1,"yZoomedDataMax":null,"showTrails":true,"xZoomedDataMax":null};\';
        chart.draw(data, {width: 600, height:400});
      }
    </script>
  </head>

  <body>
    <div id="chart_div" style="width: 600px; height: 300px;"></div>

  </body>
</html>
';

?>