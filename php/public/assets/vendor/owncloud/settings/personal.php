<?php
/**
 * Copyright (c) 2011, Robin Appelman <icewind1991@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

require_once('../lib/base.php');
OC_Util::checkLoggedIn();

// Highlight navigation entry
OC_Util::addScript( 'settings', 'personal' );
OC_Util::addStyle( 'settings', 'settings' );
OC_Util::addScript( '3rdparty', 'chosen/chosen.jquery.min' );
OC_Util::addStyle( '3rdparty', 'chosen' );
OC_App::setActiveNavigationEntry( 'personal' );

// calculate the disc space
$used=OC_Filesystem::filesize('/');
$free=OC_Filesystem::free_space();
$total=$free+$used;
$relative=round(($used/$total)*10000)/100;

$email=OC_Preferences::getValue(OC_User::getUser(), 'settings','email','');

$lang=OC_Preferences::getValue( OC_User::getUser(), 'core', 'lang', 'en' );
$languageCodes=OC_L10N::findAvailableLanguages();
//put the current language in the front
unset($languageCodes[array_search($lang,$languageCodes)]);
array_unshift($languageCodes,$lang);
$languageNames=include 'languageCodes.php';
$languages=array();
foreach($languageCodes as $lang){
	$languages[]=array('code'=>$lang,'name'=>@$languageNames[$lang]);
}

// Return template
$tmpl = new OC_Template( 'settings', 'personal', 'user');
$tmpl->assign('usage',OC_Helper::humanFileSize($used));
$tmpl->assign('total_space',OC_Helper::humanFileSize($total));
$tmpl->assign('usage_relative',$relative);
$tmpl->assign('email',$email);
$tmpl->assign('languages',$languages);

$forms=OC_App::getForms('personal');
$tmpl->assign('forms',array());
foreach($forms as $form){
	$tmpl->append('forms',$form);
}
$tmpl->printPage();

?>
