<?php
/**
 * Copyright (c) 2011 Georg Ehrke <ownclouddev at georgswebsite dot de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

require_once ('../../lib/base.php');
OC_Util::checkLoggedIn();
OC_Util::checkAppEnabled('calendar');
$cal = isset($_GET['calid']) ? $_GET['calid'] : NULL;
$event = isset($_GET['eventid']) ? $_GET['eventid'] : NULL;
if(isset($cal)){
	$calendar = OC_Calendar_App::getCalendar($cal);
	$calobjects = OC_Calendar_Object::all($cal);
	header('Content-Type: text/Calendar');
	header('Content-Disposition: inline; filename=' . $calendar['displayname'] . '.ics'); 
	foreach($calobjects as $calobject){
		echo $calobject['calendardata'] . '\n';
	}
}elseif(isset($event)){
	$data = OC_Calendar_App::getEventObject($_GET['eventid']);
	$calendarid = $data['calendarid'];
	$calendar = OC_Calendar_App::getCalendar($calendarid);
	header('Content-Type: text/Calendar');
	header('Content-Disposition: inline; filename=' . $data['summary'] . '.ics'); 
	echo $data['calendardata'];
}
?>
