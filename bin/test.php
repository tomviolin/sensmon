#!/usr/bin/php
<?php
	require_once('SensorMonitor.php');

	$watersensor = new SensorMonitor(1, 'waterdata.glwi.uwm.edu', 'monitoru', 'sens56mon');
	$watersensor->CheckAlarmCondition();
?>
