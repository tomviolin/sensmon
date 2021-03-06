<?php
// global utility functions 

require_once('/var/www/www-prod/html/ereg.php');

function GraphLabelFormat($dtVal) {
	return date('D d Hi', $dtVal);
}
function datapoint($val) {
	if ($val != "") {
		return $val;
	} else {
		return '-';
	}
}

// this class will handle all JpGraph calls
define('JPGRAPH', '/var/www/jpgraph-current/src');
require_once(JPGRAPH."/jpgraph.php");
require_once(JPGRAPH."/jpgraph_line.php");
require_once(JPGRAPH."/jpgraph_scatter.php");
require_once(JPGRAPH."/jpgraph_date.php");


// constant declarations
define('SQL_ERR', 'if (mysqli_errno($this->mysqli_conn) > 0) { die(__FILE__. ": MySQL error: ".mysqli_error($mysqli_conn)."\n"); }');
define('DEFAULT_SENSORID', 0);

// class for monitoring sensor readings, typically environmental
// parameters in a building.

class SensorMonitor {

	protected $sensorID;		// record ID of sensor
	protected $config;		// array containing configuration options
	protected $mysqli_conn;
	// the following values are found in the database:
	/*
		dbTable			// where to obtain the data
		dbSensorColumn		// which column we are monitoring
		dbAlarmMinColumn	// current alarm minimum setting
		dbAlarmMaxColumn	// current alarm maximum setting
		dbRecDate		// datetime of data collection
		dbWhere			// filter for data
		alarmMin		// alarm minimum
		alarmMax		// alarm maximum
		alertEmail		// e-mail to send alarm
		avgOver			// average over this time interval
					//	(MySQL interval syntax, e.g. "20 minute")
		minRecs;		// minimum number of records for alarm
					//   to be considered "real"
		alarmTimeoutTime;	// time interval from present that
					//   data is missing to trigger
					//   a sensor timeout alarm
					//   (MySQL interval syntax, e.g. "30 minute")
	*/

	function ReadSMConfig() {
		$query = "select * from sensmon.sensor_config where sensor_id={$this->sensorID}";
		$result = @mysqli_query($this->mysqli_conn,$query);
		eval(SQL_ERR);
		if (mysqli_num_rows($result) == 1) {
			return mysqli_fetch_array($result);
		} else {
			return NULL;
		}
	}

	// constructor
	function SensorMonitor($c_sensorID, $dbServer="", $dbUser="", $dbPass="") {
		$this->sensorID = $c_sensorID;
		if ($dbServer != "") {
			@$this->mysqli_conn = @mysqli_connect($dbServer, $dbUser, $dbPass);
			eval(SQL_ERR);
		}
		if (($this->config = $this->ReadSMConfig()) === NULL) {
			$this->sensorID = 0;
			$this->config = $this->ReadSMConfig();
			if ($this->config === NULL) {
				die ("no default sensor config found.");
			}
		}
	}

	public function WriteSMConfig($sensorID) {
		if ($sensorID == "") {
			// we are inserting
			$icols = "";
			$ivals = "";
			foreach ($this->config as $key => $val) {
				$icols .= "\`$key\`,";
				$ivals .= "\"$val\",";
				if (strlen($icols) > 0) {
					$icols = substr($icols, 0, strlen($icols)-1);
				}
				if (strlen($icols) > 0) {
					$ivals = substr($ivals, 0, strlen($ivals)-1);
				}
			}
			$query = "insert into sensmon.sensor_config ($icols) values ($ivals)";
		} else {
			// we are updating
			$query = "update sensmon.sensor_config ";
			foreach ($this->config as $key -> $val) {
				if ($key != "recid") {
					$query .= "set \`$key\` = \"$val\",";
				}
			}
			$query = substr($query,0,strlen($query)-1) . " where recid == {$this->sensorID}";
		}
		$result = @mysqli_query($this->mysqli_conn,$query);
		eval(SQL_ERR);
	}

	public function FetchCurrentAlarmReading() {
		// Returns current sensor value to be used for checking the alarm.
		//   This value is the average over the most recent $avgOver interval.

		// first obtain highest date on file
		$query = "select max({$this->config['datecolumn']}) as maxdbrecdate from {$this->config['database']}.{$this->config['table']}";
		if ($this->config['sqlwhere'] != "") {
			$query .= " ".$this->config['sqlwhere'];
		}
		$result = @mysqli_query($this->mysqli_conn,$query);
		eval(SQL_ERR);
		if (mysqli_num_rows($result) != 1) {
			die("MySQL query: $query\ntable {$this->config['table']} has no rows for sensor {$this->config['table']}.{$this->config['datacolumn']}");
		}
		$row = @mysqli_fetch_array($result);
		eval(SQL_ERR);
		$maxDate = $row['maxdbrecdate'];
		//echo "max recdate=$maxDate\n";

		$query = "select avg({$this->config['datacolumn']}) as sensorVal, count({$this->config['datacolumn']}) as dataCount from {$this->config['database']}.{$this->config['table']} where ({$this->config['datecolumn']} >= date_sub(convert_tz(now(),'SYSTEM','GMT'), interval {$this->config['alarm_avg_time']}))";
		if ($dbWhere != "") {
			$query .= " and ($dbWhere)";
		}
		$result = @mysqli_query($this->mysqli_conn,$query);
		eval(SQL_ERR);
		if (mysqli_num_rows($result) == 0) {
			die("MySQL query: $query\ntable {$this->config['database']}.{$this->config['table']} has no rows for sensor {$this->config['datacolumn']}");
		}
		$row = @mysqli_fetch_array($result);
		eval(SQL_ERR);
		return array($row['sensorVal'],$row['dataCount']);
	}

	protected function SendAlarm($errorMessage) {
		// send alarm
		mail($this->config['alert_email'], "ALARM", $errorMessage, "X-Mailer: GLWI alarm www.glwi.uwm.edu");
	}


	protected function RecordAlarmCheck($dataValue, $alarmTripType = "") {
		$alarmValue = "";
		switch ($alarmTripType) {
			case "min":	$alarmField = "alarm_trip_min"; break;
			case "max":	$alarmField = "alarm_trip_max"; break;
			case "timeout":	$alarmField = "alarm_trip_timeout"; break;
			default:	$alarmField = "";
		}
		if ($alarmField != "") {
			$alarmField = ", " . $alarmField;
			$alarmValue = ", 1";
		}
		$query = "insert into sensmon.alarm_check_history (sensor_id, recdate, alarm_min, alarm_max, data_value $alarmField) "
			."values ({$this->config['sensor_id']}, convert_tz(now(), 'SYSTEM','GMT'), {$this->config['alarm_min']},{$this->config['alarm_max']}, $dataValue $alarmValue);";
		$result = @mysqli_query($this->mysqli_conn,$query);
		eval(SQL_ERR);
	}


	public function CheckAlarmCondition() {
		// check alarm conditions
		if ($this->config['alarm_min'] == 0 && $this->config['alarm_max'] == 0) {
			return;
		}
		list($currentAlarmReading, $rowCount) = $this->FetchCurrentAlarmReading();
		if ($rowCount < $this->config['alarm_min_readings']) {
			// insufficient data to determine an alarm condition
			//    based on sensor out of range.
			//    We must check time since last measurement.
			//    NOTE: This is with respect to the database server's time.
			//    This eliminates any issues with client/server time mismatch.
			$query = "select count({$this->config['datacolumn']}) as dataCount from {$this->config['database']}.{$this->config['table']} where {$this->config['datecolumn']} > date_sub(convert_tz(now(),'SYSTEM','GMT'), interval {$this->config['alarm_timeout_time']}";
			$result = @mysqli_query($this->mysqli_conn,$query);
			eval(SQL_ERR);
			$row = @mysqli_fetch_array($result);
			eval(SQL_ERR);
			if ((mysqli_num_rows($result) == 0) or ($row['dataCount'] == 0)) {
				// *** ALARM! ***
				$this->RecordAlarmCheck("NULL", "timeout");
				$this->SendAlarm("no readings from {$this->config['database']}.{$this->config['table']}.{$this->config['datacolumn']} for {$this->config['alarm_timeout_time']}"."s");
				exit(1);
			}
		}
		if ($currentAlarmReading < $this->config['alarm_min']) {
			// *** ALARM! ***
			$this->RecordAlarmCheck($currentAlarmReading, "min");
			$this->SendAlarm("{$this->config['description']}: reading of $currentAlarmReading{$this->config['datatype_short']} less than limit of {$this->config['alarm_min']}{$this->config['datatype_short']}");
			exit(1);
		}
		if ($currentAlarmReading > $this->config['alarm_max']) {
			// *** ALARM! ***
			$this->RecordAlarmCheck($currentAlarmReading, "max");
			$this->SendAlarm("{$this->config['description']}: reading of $currentAlarmReading{$this->config['datatype_short']} greater than limit of {$this->config['alarm_max']}{$this->config['datatype_short']}");
			exit(1);
		}
		// record the check without any alarm
		$this->RecordAlarmCheck($currentAlarmReading);
	} // end CheckAlarmCondition()


	public static function GraphLabelFormat($dtVal) {
		return date('D m/d H:i:s', $dtVal);
	}

	public function ShowGraph($interval = "", $yearsago = 0) {
		// *** graph last (graph range) of data ***

		// min and max date values initialized
		$mingraphdate = strtotime("2030-01-01");
		$maxgraphdate = 0;

		$mingraphval = "";
		$maxgraphval = "";

		$timeinterval = $this->config['graph_timescale'];
		if ($interval != "") {
			$timeinterval = $interval;
		}
		// chop off any trailing 's'
		if (strtoupper(substr($timeinterval, strlen($timeinterval)-1,1)) == 'S') {
			$timeinterval = substr($timeinterval, 0, strlen($timeinterval)-1);
		}

		// ** recorded sensor values  **
		// prepare SQL query
		$sensordateexp[0] = "concat(mid(convert_tz({$this->config['datecolumn']},'GMT','SYSTEM'), 1, 15),'0')";
		$sensordateexp[1] = "concat(mid(convert_tz({$this->config['datecolumn']},'GMT','SYSTEM'), 1, 13),':00')";
		$sensordateexp[2] = "concat(mid(convert_tz({$this->config['datecolumn']},'GMT','SYSTEM'), 1, 11),'00:00')";
		$sensordateexp[3] = "";
		$si = 0;
		if (stripos($timeinterval, "month") > 0) $si=1;
		if (stripos($timeinterval, "year") > 0) $si = 2;
		while (1==1) {
			$query = "select UNIX_TIMESTAMP({$sensordateexp[$si]}) as sensorDate, round(avg({$this->config['datacolumn']}),3) as sensorVal from {$this->config['database']}.{$this->config['table']} where {$this->config['datecolumn']} >= date_sub(convert_tz(date_add(now(), interval -$yearsago YEAR), 'SYSTEM','GMT'), interval $timeinterval) and {$this->config['datecolumn']} <= convert_tz(date_add(now(), interval -$yearsago YEAR), 'SYSTEM','GMT') group by sensorDate order by sensorDate";
			$result = @mysqli_query($this->mysqli_conn,$query);
			eval(SQL_ERR);
			if (mysqli_num_rows($result) > 2000) {
				$si++;
				if ($sensordateexp[$si] == "") {
					break;
				}
			} else {
				break;
			}
		}

		// prepare input vectors for graph
		unset($dates);
		unset($vals);
		$lasttemp="";
		$i = 0;
		while (($row = mysqli_fetch_array($result)) != NULL) {
			if (($dates[$i] = $row['sensorDate']) > 0) {
				$mingraphdate = min($dates[$i],$mingraphdate);
				$maxgraphdate = max($dates[$i],$maxgraphdate);
			}
			$vals[$i] = $row['sensorVal'];
			if ($mingraphval == "" || $mingraphval > $vals[$i]) $mingraphval = $vals[$i];
			if ($maxgraphval == "" || $maxgraphval < $vals[$i]) $maxgraphval = $vals[$i];
			++$i;
		}
		$lasttemp = $vals[$i-1]+0;

		// ** record of range checks **
		$query = "select UNIX_TIMESTAMP(convert_tz(sensmon.alarm_check_history.recdate,'GMT','SYSTEM')) as checkDate, "
			."alarm_min, alarm_max, data_value, alarm_trip_min, alarm_trip_max, alarm_trip_timeout "
			."from sensmon.alarm_check_history where recdate >= date_sub(convert_tz(now(),'SYSTEM','GMT'), interval {$this->config['graph_timescale']}) order by recdate";
		//echo $query;
		$result = mysqli_query($this->mysqli_conn,$query);
		eval(SQL_ERR);
		unset($checkdates);
		unset($maxvals);
		unset($minvals);
		unset($alarmmax);
		unset($alarmmin);
		unset($alarmtimeout);

		$i=0;
		$alarmmincount=0;
		$alarmmaxcount=0;
		$amarmtimecount=0;
		while (($row = mysqli_fetch_array($result)) != NULL) {
			if (($checkdates[$i] = $row['checkDate']) > 0) {
				$mingraphdate = min($checkdates[$i],$mingraphdate);
				$maxgraphdate = max($checkdates[$i],$maxgraphdate);
			}
			$minvals[$i] = $row['alarm_min'];
			$maxvals[$i] = $row['alarm_max'];
			$alarmmin[$i] = datapoint($row['alarm_trip_min']);
			if ($alarmmin[$i] == 1) $alarmmincount++;
			$alarmmax[$i] = datapoint($row['alarm_trip_max']);
			if ($alarmmax[$i] == 1) $alarmmaxcount++;
			$alarmtimeout[$i] = datapoint($row['alarm_trip_timeout']);
			if ($alarmtimeout[$i] == 1) $alarmtimecount++;
			++$i;
		}

		// figure out the ticks
		$daterange = $maxgraphdate - $mingraphdate;

		if ($daterange > (86400 * 40) ) {
			// range greater than 40 days
			// major=2 days, minor=1 day
			// compute next multiple of 24 hours
			$startmod = $mingraphdate % (3600*24);
			if ($startmod > 0) {
				$startdayticks = $mingraphdate + (3600*24) - $startmod;
			} else {
				// very small chance but it's covered.
				$startdayticks = $mingraphdate;
			}
			unset($majtickpos);
			unset($majticklabel);
			$i = 0;
			$monthyet = false;
			for ($tick = $startdayticks; $tick <= $maxgraphdate; $tick += (3600)) {
				if (date("j",$tick) == 1 && date("G",$tick) == 0) {
					// we are on a month mark
					$majtickpos[$i] = $tick;
					$majticklabel[$i] = date("j\nM\nY", $tick);
					$i++;
					$monthyet = true;
				} elseif ((date("G",$tick) == 0) && ( date("j", $tick)==15) ) {    // && (floor($tick/(86400*2)) % 2 == 0)  ) {
					// we are on a 24-hour mark
					if ($monthyet == false && date("j", $tick) <= 29) {
						// place starter month desc
						$majticklabel[$i] = date("j\nM\nY", $tick);
						$monthyet = true;
					} else {
						if (date("j",$tick) < 25 && date("j",$tick) > 5) {
							$majticklabel[$i] = date("j\n", $tick);
						} else {
							$majticklabel[$i] = " ";
						}
					}
					$majtickpos[$i] = $tick;
					$i++;
				}
			}


		} elseif ($daterange > (86400 * 14) ) {
			// range greater than two weeks
			// major=days, minor=12 hours
			// compute next multiple of 12 hours
			$startmod = $mingraphdate % (3600*24);
			if ($startmod > 0) {
				$startdayticks = $mingraphdate + (3600*24) - $startmod;
			} else {
				// very small chance but it's covered.
				$startdayticks = $mingraphdate;
			}
			unset($majtickpos);
			unset($majticklabel);
			$i = 0;
			$monthyet = false;
			for ($tick = $startdayticks; $tick <= $maxgraphdate; $tick += (3600)) {
				if (date("j",$tick) == 1 && date("G",$tick) == 0) {
					// we are on a month mark
					$majtickpos[$i] = $tick;
					$majticklabel[$i] = date("j\nM\nY", $tick);
					$i++;
					$monthyet = true;
				} elseif ((date("G",$tick) % 24) == 0) {
					// we are on a 24-hour mark
					if ($monthyet == false && date("j", $tick) <= 29) {
						// place starter month desc
						$majticklabel[$i] = date("j\nM\nY", $tick);
						$monthyet = true;
					} else {
						$majticklabel[$i] = date("j\n", $tick);
					}
					$majtickpos[$i] = $tick;
					$i++;
				}
			}




		} elseif ($daterange > (86400 * 3) ) {
			// range greater than three days
			// major=days, minor=12 hours
			// compute next multiple of 12 hours
			$startmod = $mingraphdate % (3600*12);
			if ($startmod > 0) {
				$startdayticks = $mingraphdate + (3600*12) - $startmod;
			} else {
				// very small chance but it's covered.
				$startdayticks = $mingraphdate;
			}
			unset($majtickpos);
			unset($majticklabel);
			$i = 0;
			for ($tick = $startdayticks; $tick <= $maxgraphdate; $tick += (3600)) {
				if (date("G",$tick) == 0) {
					// we are on a day mark
					$majtickpos[$i] = $tick;
					$majticklabel[$i] = date("H:00\nD\nm/d", $tick);
					$i++;
				} elseif ((date("G",$tick) % 12) == 0) {
					// we are on a 2-hour mark
					$majtickpos[$i] = $tick;
					$majticklabel[$i] = date("H:00\n", $tick);
					$i++;
				}
			}
		} else { // if ($daterange > 86400) {
			// range greater than one day
			// major=days, minor=3 hours
			// compute next multiple of 1 hour
			$startmod = $mingraphdate % 3600;
			if ($startmod > 0) {
				$startdayticks = $mingraphdate + 3600 - $startmod;
			} else {
				// very small chance but it's covered.
				$startdayticks = array($mingraphdate);
			}
			unset($majtickpos);
			unset($majticklabel);
			$i = 0;
			for ($tick = $startdayticks; $tick <= $maxgraphdate; $tick += 3600) {
				if (date("G",$tick) == 0) {
					// we are on a day mark
					$majtickpos[$i] = $tick;
					$majticklabel[$i] = date("H\nD\nm/d", $tick);
					$i++;
				} elseif ((date("G",$tick) % 2) == 0) {
					// we are on a 2-hour mark
					$majtickpos[$i] = $tick;
					$majticklabel[$i] = date("H\n", $tick);
					$i++;
				}
			}
		}




		// store graph dimensions in easy-to-use packages
		$gwid = $this->config['graph_width'];
		$ghgt = $this->config['graph_height'];


		// instantiate the graph object
		$graph = new Graph($gwid, $ghgt, "auto");

		$graph->SetMargin(40,100,30,60);

		$graph->SetScale('linlin', $mingraphval , $maxgraphval, $mingraphdate, $maxgraphdate);
		$graph->xaxis->SetTickPositions($majtickpos, NULL, $majticklabel);

		// create the line plot
		$graph->title->Set($this->config['description']);
		$graph->subtitle->Set("as of ".date("D M d Y, h:i a ", ((int)(time()/05))*05));
		// $graph->xaxis->SetLabelFormatCallback('GraphLabelFormat');
		$graph->xaxis->SetLabelAngle(0);
		$graph->xaxis->SetLabelAlign('center','top','center');
		$graph->xaxis->SetTickSide(SIDE_TOP);
		$graph->xaxis->SetFont(FF_ARIAL,FS_NORMAL, 8.5);
		$graph->xgrid->Show(true,false);

		// values line
		$valline = new LinePlot ($vals, $dates);
		$valline->SetLegend($this->config['datatype_short']);
		$valline->SetColor($this->config['graph_value_color']);
		$valline->SetWeight(3);
		$graph->Add($valline);

		if ( ! ($this->config['alarm_min'] == 0 && $this->config['alarm_max'] == 0)) {

			// min checks
			$minline = new LinePlot($minvals, $checkdates);
			$minline->SetLegend("min");
			$minline->SetColor("blue");
			$minline->SetWeight(1);
			$graph->Add($minline);

			// max checks
			$maxline = new LinePlot($maxvals, $checkdates);
			$maxline->SetLegend("max");
			$maxline->SetColor("red");
			$maxline->SetWeight(1);
			$graph->Add($maxline);

			// alarm mins
			//print_r($alarmmin);
			//print_r($alarmmincount);
			if ($alarmmincount > 0) {
				$aminline = new ScatterPlot($alarmmin, $checkdates);
				$aminline->SetLegend("Alarm min");
				$aminline->SetWeight(1);
				$aminline->mark->SetType(MARK_IMG, '/opt/sensmon/bin/warningblue.png', 0.15);
				$graph->Add($aminline);
			}

			// alarm maxs
			if ($alarmmaxcount > 0) {
				$amaxline = new ScatterPlot($alarmmax, $checkdates);
				$amaxline->SetLegend("Alarm max");
				$amaxline->SetColor("red");
				$amaxline->SetWeight(1);
				$amaxline->mark->SetType(MARK_IMG, '/opt/sensmon/bin/warningred.png', 0.15);
				$graph->Add($amaxline);
			}
		}
		// describe current conditions in a text box
		$txt = new Text("$lasttemp{$this->config['datatype_short']}");
		$txt->SetFont(FF_ARIAL, FS_BOLD, 12);
		$txt->SetBox('#FFFF99','black','darkgray', 0, 2);
		$txt->SetPos($gwid-05,$ghgt-50,'right','top');

		$graph->AddText($txt);

		$graph->Stroke();
	}

}
?>
