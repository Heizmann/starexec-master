<html lang='en'>
<head>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<link rel="stylesheet" type="text/css" href="../master.css">
<?php
	include './definitions.php';

	function str2lower( $string ) {
		if( preg_match( '/Omega\\(n\\^([0-9]+)\\)/', $string, $matches ) ) {
			return $matches[1];
		} else if( $string == 'NON_POLY' ) {
			return 1000;
		} else {
			return 0;
		}
	}

	function str2upper( $string ) {
		if( preg_match( '/O\\(n\\^([0-9]+)\\)/', $string, $matches ) ) {
			return $matches[1];
		} else if( $string == 'POLY' ) {
			return 999;
		} else {
			return 1001;
		}
	}

	function bound2str( $bound ) {
		if( $bound == 0 ) {
			return "1";
		} else if( $bound < 999 ) {
			return "n<sup>$bound</sup>";
		} else if( $bound == 999 ) {
			return 'n<sup>?</sup>';
		} else if( $bound == 1000 ) {
			return 'NonPoly';
		} else {
			return '&infin;';
		}
	}
	function lower2style( $bound ) {
		if( $bound == 0 ) {
			return "class=maybe";
		} else if( $bound == 1 ) {
			return "class=low1";
		} else if( $bound == 2 ) {
			return "class=low2";
		} else if( $bound < 999 ) {
			return "class=low3";
		} else if( $bound == 1000 ) {
			return "class=lowNP";
		} else {
			return "class=error";
		}	
	}
	function upper2style( $bound ) {
		if( $bound == 0 ) {
			return "class=up0";
		} else if( $bound == 1 ) {
			return "class=up1";
		} else if( $bound == 2 ) {
			return "class=up2";
		} else if( $bound < 999 ) {
			return "class=up3";
		} else if( $bound == 999 ) {
			return "class=upP";
		} else if( $bound == 1000 ) {
			return "class=yes";
		} else {
			return "class=maybe";
		}
	}
	function str2bounds( $string ) {
		if( preg_match( '/WORST_CASE\\(\\s*(.+)\\s*,\\s*(.+)\\s*\\)/', $string, $matches ) ) {
			return [ str2lower($matches[1]), str2upper($matches[2]) ];
		}
		return [0,1001];
	}

// initializing job info
	if( $jobid == NULL ) {
		$jobid = $_GET['id'];
		$jobname = $_GET['name'];
	}
	if( $jobid == NULL ) {
		echo "</head>\n</html>\n";
		exit('no job to present');
	}
	$csv = jobid2csv($jobid);
	cachezip(jobid2remote($jobid),$csv);
	$scorefile = jobid2scorefile($jobid);
	echo "<title>$jobname</title>\n";
	echo "</head>\n";
	echo "<body>\n";
	echo "<h1><a href='..'>$competitionname</a>: $jobname";
	echo "<a class=starexecid href='".jobid2url($jobid). "'>$jobid</a></h1>\n";
	echo "<table>\n";
	$file = new SplFileObject($csv);
	$file->setFlags( SplFileObject::READ_CSV );
	$records = [];
	foreach( $file as $row ) {
	  if( !is_null($row[0]) ) {
	    $records[] = $row;
	  }
	}
	unset( $records[0] );

	$solvers = [];
	$i = 1;
	$solver = $records[$i][4];
	$config = $records[$i][5];
	$configid = $records[$i][6];
	$firstsolver = $solver;
	do {
		$solvers[$solver] = [
			'name' => $records[$i][3],
			'config' => $config,
			'configid' => $configid,
			'score' => 0,
			'conflicts' => 0,
			'done' => 0,
			'togo' => 0,
			'cpu' => 0,
			'time' => 0,
			'UP' => 0,
			'LOW' => 0,
		];
		$lastsolver = $solver;
		$i++;
		$solver = $records[$i][4];
		$config = $records[$i][5];
		$configid = $records[$i][6];
	} while( $solver != $firstsolver );

	echo " <tr>\n";
	echo "  <th class=benchmark>benchmark</th>\n";
	foreach( array_keys($solvers) as $id ) {
		$s = $solvers[$id];
		echo "  <th colspan=3>\n";
		echo "   <a href='".solverid2url($id)."'>".$s['name']."</a>\n";
		echo "   <a class='config' href='".configid2url($s['configid'])."'>".$s['config']."</a>\n";
	}
	echo " <tr><th>\n";
	foreach( array_keys($solvers) as $id ) {
		echo "  <th>lower<th>upper<th class=time>time\n";
	}
	$bench = [];

	foreach( $records as $record ) {
		$solver = $record[4];
		$cpu = parse_time($record[8]);
		$time = parse_time($record[9]);
		if( $solver == $firstsolver ) {
			$bench = [];
			$benchmark = parse_benchmark( $record[1] );
			$url = bmid2url( $record[2] );
		}
		$status = $record[7];
		if( status2complete($status) ) {
			$solvers[$solver]['done'] += 1;
			$solvers[$solver]['cpu'] += $cpu;
			$solvers[$solver]['time'] += $time;
		} else {
			$solvers[$solver]['togo'] += 1;
		}
		$result = $record[11];
		$bounds = str2bounds( $result );
		$bench[$solver] = [
			'id' => $record[0],
			'status' => $status,
			'result' => $result,
			'lower' => $bounds[0],
			'upper' => $bounds[1],
			'time' => $time,
			'cpu' => $cpu,
		];
		if( $solver == $lastsolver ) {
			echo " <tr>\n";
			echo "  <td class=benchmark><a href='$url'>$benchmark</a></td>\n";
			foreach( array_keys($bench) as $me ) {
				$my = $bench[$me];
				$upper = $my['upper'];
				$lower = $my['lower'];
				$upscore = 0;
				$lowscore = 0;
				foreach( $bench as $your ) {
					if( $upper < 1000 && $upper <= $your['upper'] ) {
						$upscore++;
					}
					if( $lower > 0 && $lower >= $your['lower'] ) {
						$lowscore++;
					}
					if( $lower > $your['upper'] ) {
						$my['conflicts']++;
						$lowerstyle = 'class=conflict';
					} else {
						$lowerstyle = lower2style($lower);
					}
					if( $upper < $your['lower'] ) {
						$my['conflicts']++;
						$upperstyle = 'class=conflict';
					} else {
						$upperstyle = upper2style($upper);
					}
				}
				$a = "<a href='". pairid2url($my['id']) . "' class=fill>";
				$lower = $my['lower'];
				$upper = $my['upper'];
				$status = $my['status'];
				if( $status == 'complete' ) {
					$solvers[$me]['score'] += $upscore + $lowscore;
					$solvers[$me]['UP'] += $upscore;
					$solvers[$me]['LOW'] += $lowscore;
					echo "  <td $upperstyle>$a" . bound2str($upper) . " <span class=score>(+$upscore)</span></a>\n";
					echo "  <td $lowerstyle>$a" . bound2str($lower) . " <span class=score>(+$lowscore)</span></a>\n";
					echo "  <td class=time>$a" . $my['cpu'] . "/" . $my['time'] . "</a>\n";
				} else {
					echo "  <td colspan=3 ". status2style($status) . ">$a" .
						status2str($status) . "</a>\n";
				}
			}
			echo " </tr>\n";
		}
	}
	echo " <tr>\n";
	foreach( $solvers as $s ) {
		$score = $s['score'];
		echo "  <th><th colspan=3>$score</th>\n";
	}
	$scorefileD = fopen($scorefile,"w");
	fwrite( $scorefileD, json_encode($solvers) );
	fclose( $scorefileD );
?>
</table>
</body>
</html>


