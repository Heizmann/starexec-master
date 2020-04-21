<!DOCTYPE html>
<html lang='en'>
<head>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<?php
	include './definitions.php';
	
	if( $jobid == NULL ) {
		$jobid = $_GET['id'];
	}

	if( $jobid == NULL ) {
		echo '</head>';
		exit('no job to present');
	}
	$csv = jobid2csv($jobid);
	if( $_GET['refresh'] ) {
		cachezip(jobid2remote($jobid),$csv);
	}
	$scorefile = jobid2scorefile($jobid);

	echo " <title>$competitionname: $jobname</title>\n";
	echo "</head>\n";
	echo "<body>\n";
	echo "<h1>$competitionname: $jobname";
	echo "<a class=starexecid href='".jobid2url($jobid). "'>$jobid</a></h1>\n";
	echo "<a href='../$csv'>Job info CSV</a>\n";
	echo "<table>\n";
	$file = new SplFileObject($csv);
	$file->setFlags( SplFileObject::READ_CSV );
	$records = [];
	foreach( $file as $row ) {
	  if( !is_null($row[0]) ) {
	    $records[] = $row;
	  }
	}
	$pairid_idx = array_search('pair id', $records[0]);
	$benchmark_idx = array_search('benchmark', $records[0]);
	$benchmark_id_idx = array_search('benchmark id', $records[0]);
	$solver_idx = array_search('solver', $records[0]);
	$solverid_idx = array_search('solver id', $records[0]);
	$config_idx = array_search('configuration', $records[0]);
	$configid_idx = array_search('configuration id', $records[0]);
	$status_idx = array_search('status', $records[0]);
	$cputime_idx = array_search('cpu time', $records[0]);
	$wallclocktime_idx = array_search('wallclock time', $records[0]);
	$memoryusage_idx = array_search('memory usage', $records[0]);
	$result_idx = array_search('result', $records[0]);
	$certificationresult_idx = array_search('certification-result', $records[0]);
	unset( $records[0] );

	$participants = [];

	$i = 1;
	$configid = $records[$i][$configid_idx];
	$first = $configid;
	do {
		$participants[$configid] = [
			'solver' => $records[$i][$solver_idx],
			'solverid' => $records[$i][$solverid_idx],
			'config' => $records[$i][$config_idx],
			'configid' => $configid,
			'score' => 0,
			'conflicts' => 0,
			'done' => 0,
			'togo' => 0,
			'cpu' => 0,
			'time' => 0,
		];
		$last = $configid;
		$i++;
		$configid = $records[$i][$configid_idx];
	} while( $configid != $first );

	echo " <tr>\n";
	echo "  <th>benchmark\n";
	foreach( $participants as $participant ) {
		echo "  <th><a href='". solverid2url($participant['solverid']) . "'>".$participant['solver']."</a>\n";
	}
	echo " <tr><th>\n";
	foreach( $participants as $participant ) {
		echo "  <th class='config'><a href='". configid2url($participant['configid']) ."'>". $participant['config']."</a>\n";
	}
	echo " <tr>\n";
	$bench = [];

	$conflicts = 0;
	foreach( $records as $record ) {
		$configid = $record[$configid_idx];
		$participant =& $participants[$configid];
		$status = $record[$status_idx];
		$cpu = parse_time($record[$cputime_idx]);
		$time = parse_time($record[$wallclocktime_idx]);
		$result = $record[$result_idx];
		$cert = $certificationresult_idx ? $record[$certificationresult_idx] : '';
		if( $configid == $first ) {
			$bench = [];
			$benchmark = parse_benchmark( $record[$benchmark_idx] );
			$url = bmid2url($record[$benchmark_id_idx]);
			$resultcounter = []; /* collects results for each benchmark */
		}
		if( status2complete($status) ) {
			$participant['done'] += 1;
			$participant['cpu'] += $cpu;
			$participant['time'] += $time;
			$participant[$result] += 1;
			$participant['score'] += result2score($result);
			$resultscounter[$result]++;
		} else {
			$participant['togo'] += 1;
		}
		$bench[$configid] = [
			'status' => $status,
			'result' => $result,
			'cert' => $cert,
			'time' => $time,
			'cpu' => $cpu,
			'pair' => $record[$pairid_idx],
		];
		if( $configid == $last ) {
			$conflict = $resultcounter['YES'] > 0 && $resultcounter['NO'] > 0;
			if( $conflict ) {
				foreach( array_keys($bench) as $me ) {
					if( $bench[$me]['score'] > 0 ) {
						$participants[$me]['conflicts']++;
					}
				}
				$conflicts += 1;
				echo " <tr class=conflict>\n";
			} else {
				echo " <tr>\n";
			}
			echo "  <td class=benchmark>\n";
			if( $conflict && $conflicts == 1 ) {
				echo "   <a name='conflict'/>\n";
			}
			echo "   <a href='$url'>$benchmark</a></td>\n";
			foreach( array_keys($bench) as $me ) {
				$my = $bench[$me];
				$status = $my['status'];
				$result = $my['result'];
				$cert = $my['cert'];
				$url = pairid2url($my['pair']);
				$outurl = pairid2outurl($my['pair']);
				if( $status == 'complete' ) {
					echo '  <td class=' . result2class($result) . ">
   <a href='$outurl'>" . result2str($result) . "</a>
   <a href='$url'>
    <span class=time>" . $my['cpu'] . "/" . $my['time'] . "</span>
   </a>\n";
				} else {
					echo "  <td " . status2style($status) . ">
   <a href='$url'>" . status2str($status) . "</a>
   <a href='$outurl'>[out]</a>\n";
				}
			}
			echo " </tr>\n";
		}
	}
	echo " <tr><th>\n";
	foreach( $participants as $s ) {
		echo "  <th>".$s['score']."</th>\n";
	}
	$scorefileD = fopen($scorefile,"w");
	fwrite( $scorefileD, json_encode($participants) );
	fclose( $scorefileD );
?>
</table>
</body>
</html>


