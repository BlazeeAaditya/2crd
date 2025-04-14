<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.7 Patch Level 2
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions, Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 39862 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array();

$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_rss_poster.php');

header('Content-Type: text/xml; charset=utf-8');



/*======================================================================*\
|| ####################################################################
|| #
|| # CVS: $RCSfile$ - $Revision: 39862 $
|| ####################################################################
\*======================================================================*/
?>
