<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.7 Patch Level 2
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2011 vBulletin Solutions, Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'subscription');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('user', 'forumdisplay','thread');

// get special data templates from the datastore
$specialtemplates = array(
	'iconcache',
	'noavatarperms'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'USERCP_SHELL',
	'usercp_nav_folderbit',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'viewsubscription' => array(
		'forumdisplay_sortarrow',
		'threadbit',
		'SUBSCRIBE'
	),
	'addsubscription' => array(
		'subscribe_choosetype'
	),
	'editfolders' => array(
		'subscribe_folderbit',
		'subscribe_showfolders'
	),
	'dostuff' => array(
		'subscribe_move'
	)
);

$actiontemplates['none'] =& $actiontemplates['viewsubscription'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'viewsubscription';
}

if ((!$vbulletin->userinfo['userid'] AND $_REQUEST['do'] != 'removesubscription')
	OR ($vbulletin->userinfo['userid'] AND !($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
	OR $vbulletin->userinfo['usergroupid'] == 4
	OR !($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
{
	print_no_permission();
}

// select correct part of forumjump
$frmjmpsel['subs'] = 'class="fjsel" selected="selected"';
construct_forum_jump();

// start the navbits breadcrumb
$navbits = array('usercp.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['user_control_panel']);

($hook = vBulletinHook::fetch_hook('usersub_start')) ? eval($hook) : false;

if ($_POST['do'] == 'doaddsubscription')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'emailupdate' => TYPE_UINT,
		'folderid'    => TYPE_INT
	));

	if (!$foruminfo['forumid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['forum'], $vbulletin->options['contactuslink'])));
	}

	$forumperms = fetch_permissions($foruminfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		print_no_permission();
	}

	if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
	{
		eval(standard_error(fetch_error('forumclosed')));
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	($hook = vBulletinHook::fetch_hook('usersub_doadd')) ? eval($hook) : false;

	if ($threadinfo['threadid'])
	{
		if ((!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')) OR ($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')))
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
		}

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] OR !$vbulletin->userinfo['userid']) AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
		{
			print_no_permission();
		}

/*
		if (!$threadinfo['open'])
		{
			if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
			{
				$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
				eval(standard_error(fetch_error('threadclosed')));
			}
		}
*/

		/*insert query*/
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
			VALUES (" . $vbulletin->userinfo['userid'] . ", $threadinfo[threadid], " . $vbulletin->GPC['emailupdate'] . ", " . $vbulletin->GPC['folderid'] . ", 1)
		");
		$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
		eval(print_standard_redirect('redirect_subsadd_thread', true, true));
	}
	else if ($foruminfo['forumid'])
	{
		/*insert query*/
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "subscribeforum (userid, emailupdate, forumid)
			VALUES (" . $vbulletin->userinfo['userid'] . ", " . $vbulletin->GPC['emailupdate'] . ", " . $vbulletin->GPC['forumid'] . ")
		");

		$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$foruminfo[forumid]";
		eval(print_standard_redirect('redirect_subsadd_forum', true, true));
	}
}

// ############################### start add subscription ###############################
if ($_REQUEST['do'] == 'addsubscription')
{
	if (!$foruminfo['forumid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['forum'], $vbulletin->options['contactuslink'])));
	}

	$forumperms = fetch_permissions($foruminfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		print_no_permission();
	}

	if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
	{
		eval(standard_error(fetch_error('forumclosed')));
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	($hook = vBulletinHook::fetch_hook('usersub_add_start')) ? eval($hook) : false;

	if ($threadinfo['threadid'])
	{
		if ((!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')) OR $threadinfo['isdeleted'])
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
		}

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] OR !$vbulletin->userinfo['userid']) AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
		{
			print_no_permission();
		}

/*
		if (!$threadinfo['open'])
		{
			if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
			{
				$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
				eval(standard_error(fetch_error('threadclosed')));
			}
		}
*/

		require_once(DIR . '/includes/functions_misc.php');

		$type = 'thread';
		$id = $threadinfo['threadid'];

		// select the correct option
		$choice = verify_subscription_choice($vbulletin->userinfo['autosubscribe'], $vbulletin->userinfo, 9999);
		if ($choice == 9999)
		{
			$choice = 0; // default of 0 means subscribe but do not email
		}

		$emailselected = array($choice => 'selected="selected"');
		$emailchecked = array($choice => 'checked="checked"');

		$folderbits = construct_folder_jump(1);

		// find out what type of updates they want
		$navbits['subscription.php?' . $vbulletin->session->vars['sessionurl'] . 'do=viewsubscription'] = $vbphrase['subscriptions'];

		$show['folders'] = iif ($folderbits != '', true, false);
	}
	else if ($foruminfo['forumid'])
	{
		$subscribe = $db->query_first_slave("
			SELECT emailupdate
			FROM " . TABLE_PREFIX . "subscribeforum
			WHERE forumid = $foruminfo[forumid]
				AND userid = " . $vbulletin->userinfo['userid']
		);

		$type = 'forum';
		$id = $foruminfo['forumid'];

		$emailselected = array(intval($subscribe['emailupdate']) => 'selected="selected"');
		$emailchecked = array(intval($subscribe['emailupdate']) => 'checked="checked"');
	}

	$navbits[''] = $vbphrase['add_subscription'];
	$navbits = construct_navbits($navbits);

	$show['subscribetothread'] = iif ($type == 'thread', true, false);

	construct_usercp_nav();
	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('usersub_add_complete')) ? eval($hook) : false;

	$url =& $vbulletin->url;
	eval('$HTML = "' . fetch_template('subscribe_choosetype') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

}

// ############################### start remove subscription ###############################
if ($_REQUEST['do'] == 'removesubscription' OR $_REQUEST['do'] == 'usub')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'return' 	     => TYPE_STR,
		'auth'           => TYPE_STR,
		'type'           => TYPE_STR,
		'subscriptionid' => TYPE_UINT
	));

	($hook = vBulletinHook::fetch_hook('usersub_remove')) ? eval($hook) : false;

	if ($vbulletin->GPC['subscriptionid'])
	{
		if ($vbulletin->GPC['type'] == 'thread' OR $vbulletin->GPC['type'] == 'forum')
		{
			$substable = 'subscribe' . $vbulletin->GPC['type'];
			$idfield = $substable . 'id';

			if ($db->query_first_slave("
					SELECT $idfield
					FROM " . TABLE_PREFIX . "$substable AS $substable
					LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid=$substable.userid)
					WHERE $idfield = " . $vbulletin->GPC['subscriptionid'] . "
						AND MD5(CONCAT(user.userid, $substable.$idfield, user.salt, '" . COOKIE_SALT . "')) = '" . $db->escape_string($vbulletin->GPC['auth']) . "'
				"))
			{
				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "$substable
					WHERE $idfield = " . $vbulletin->GPC['subscriptionid'] . "
				");

				eval(print_standard_redirect('redirect_subsremove_' . $vbulletin->GPC['type'], true, true));
			}
			else
			{
				eval(standard_error(fetch_error('nosubtype')));
			}
		}
		else
		{
			eval(standard_error(fetch_error('nosubtype')));
		}
	}

	if ($threadinfo['threadid'])
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "subscribethread
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
				AND threadid = $threadinfo[threadid]
		");

		if ($vbulletin->GPC['return'] == 'ucp')
		{
			$vbulletin->url = 'usercp.php' . $vbulletin->session->vars['sessionurl_q'];
		}
		else
		{
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
		}

		eval(print_standard_redirect('redirect_subsremove_thread', true, true));
	}
	else if ($foruminfo['forumid'])
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "subscribeforum
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
				AND forumid = $foruminfo[forumid]
		");

		if ($vbulletin->url == $vbulletin->options['forumhome'] . '.php')
		{
			// No referring url ( was set to home page in init) so redirect to usercp
			$vbulletin->url = 'usercp.php' . $vbulletin->session->vars['sessionurl_q'];
		}

		eval(print_standard_redirect('redirect_subsremove_forum', true, true));
	}
	else
	{
		eval(standard_error(fetch_error('nosubtype')));
	}
}

// ############################### start empty folder ###############################
if ($_REQUEST['do'] == 'emptyfolder')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'folderid'   => TYPE_NOHTML,
	));

	$folderid = $vbulletin->GPC['folderid'];

	$navbits[''] = $vbphrase['subscriptions'];
	$navbits = construct_navbits($navbits);

	// build the cp nav
	construct_usercp_nav('substhreads_editfolders');

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('subscribe_confirm_delete') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

// ############################### start do empty folder ###############################
if ($_POST['do'] == 'doemptyfolder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'folderid'   => TYPE_NOHTML,
		'deny'       => TYPE_NOHTML,
	));

	if ($vbulletin->GPC['deny'])
	{
		eval(print_standard_redirect('action_cancelled'));
	}

	if ($vbulletin->GPC['folderid'] == '' OR $vbulletin->GPC['folderid'] == 'all')
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['folder'], $vbulletin->options['contactuslink'])));
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "subscribethread
		WHERE userid = " . $vbulletin->userinfo['userid'] . "
			AND folderid = " . intval($vbulletin->GPC['folderid'])
	);

	if ($vbulletin->url == $vbulletin->options['forumhome'] . '.php')
	{
		// No referring url (was set to home page in init) so redirect to usercp
		$vbulletin->url = 'usercp.php' . $vbulletin->session->vars['sessionurl_q'];
	}

	eval(print_standard_redirect('redirect_subsremove_forum', true, true));
}

// ############################### start view threads ###############################
if ($_REQUEST['do'] == 'viewsubscription')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'folderid'   => TYPE_NOHTML,
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'sortfield'  => TYPE_NOHTML,
		'sortorder'  => TYPE_NOHTML,
	));

	($hook = vBulletinHook::fetch_hook('usersub_view_start')) ? eval($hook) : false;

	// Values that are reused in templates
	$sortfield  =& $vbulletin->GPC['sortfield'];
	$perpage    =& $vbulletin->GPC['perpage'];
	$pagenumber =& $vbulletin->GPC['pagenumber'];
	$folderid   =& $vbulletin->GPC['folderid'];

	if ($folderid == 'all')
	{
		$getallfolders = true;
		$show['allfolders'] = true;
	}
	else
	{
		$folderid = intval($folderid);
	}

	$folderselect["$folderid"] = 'selected="selected"';

	require_once(DIR . '/includes/functions_misc.php');
	$folderjump = construct_folder_jump(1, $folderid); // This is the "Jump to Folder"

	// look at sorting options:
	if ($vbulletin->GPC['sortorder'] != 'asc')
	{
		$vbulletin->GPC['sortorder'] = 'desc';
		$sqlsortorder = 'DESC';
		$order = array('desc' => 'selected="selected"');
	}
	else
	{
		$sqlsortorder = '';
		$order = array('asc' => 'selected="selected"');
	}

	switch ($sortfield)
	{
		case 'title':
		case 'lastpost':
		case 'replycount':
		case 'views':
		case 'postusername':
			$sqlsortfield = 'thread.' . $sortfield;
			break;
		default:
			$handled = false;
			($hook = vBulletinHook::fetch_hook('usersub_view_sort')) ? eval($hook) : false;
			if (!$handled)
			{
				$sqlsortfield = 'thread.lastpost';
				$sortfield = 'lastpost';
			}
	}
	$sort = array($sortfield => 'selected="selected"');

	if ($getallfolders)
	{
		$totalallthreads = array_sum($subscribecounters);
	}
	else
	{
		$totalallthreads = $subscribecounters["$folderid"];
	}

	// set defaults
	sanitize_pageresults($totalallthreads, $pagenumber, $perpage, 200, $vbulletin->options['maxthreads']);

	// display threads
	$limitlower = ($pagenumber - 1) * $perpage + 1;
	$limitupper = ($pagenumber) * $perpage;

	if ($limitupper > $totalallthreads)
	{
		$limitupper = $totalallthreads;
		if ($limitlower > $totalallthreads)
		{
			$limitlower = $totalallthreads - $perpage;
		}
	}
	if ($limitlower <= 0)
	{
		$limitlower = 1;
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('usersub_view_query_threadid')) ? eval($hook) : false;

	$getthreads = $db->query_read_slave("
		SELECT thread.threadid, emailupdate, subscribethreadid, thread.forumid, thread.postuserid
			$hook_query_fields
		FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = subscribethread.threadid)
		$hook_query_joins
		WHERE subscribethread.userid = " . $vbulletin->userinfo['userid'] . "
			AND thread.visible = 1
			AND canview = 1
		" . iif(!$getallfolders, "	AND folderid = $folderid") . "
			$hook_query_where
		ORDER BY $sqlsortfield $sqlsortorder
		LIMIT " . ($limitlower - 1) . ", $perpage
	");

	if ($totalthreads = $db->num_rows($getthreads))
	{
		$forumids = array();
		$threadids = array();
		$emailupdate = array();
		$killthreads = array();
		while ($getthread = $db->fetch_array($getthreads))
		{
			$forumperms = fetch_permissions($getthread['forumid']);

			if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR ($getthread['postuserid'] != $vbulletin->userinfo['userid'] AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
			{
				$killthreads["$getthread[subscribethreadid]"] = $getthread['subscribethreadid'];
				$totalallthreads--;
				continue;
			}
			$forumids["$getthread[forumid]"] = true;
			$threadids[] = $getthread['threadid'];
			$emailupdate["$getthread[threadid]"] = $getthread['emailupdate'];
			$subscribethread["$getthread[threadid]"] = $getthread['subscribethreadid'];
		}
		$threadids = implode(',', $threadids);
	}
	unset($getthread);
	$db->free_result($getthreads);

	if (!empty($killthreads))
	{  // Update thread subscriptions
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "subscribethread
			SET canview = 0
			WHERE subscribethreadid IN (" . implode(', ', $killthreads) . ")
		");
	}

	if (!empty($threadids))
	{
		cache_ordered_forums(1);
		$colspan = 5;
		$show['threadicons'] = false;

		// get last read info for each thread
		$lastread = array();
		foreach (array_keys($forumids) AS $forumid)
		{
			if ($vbulletin->options['threadmarking'])
			{
				$lastread["$forumid"] = max($vbulletin->forumcache["$forumid"]['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
			}
			else
			{
				$lastread["$forumid"] = max(intval(fetch_bbarray_cookie('forum_view', $forumid)), $vbulletin->userinfo['lastvisit']);
			}
			if ($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'])
			{
				$show['threadicons'] = true;
				$colspan = 6;
			}
		}

		// get thread preview?
		if ($vbulletin->options['threadpreview'] > 0)
		{
			$previewfield = 'post.pagetext AS preview,';
			$previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
		}
		else
		{
			$previewfield = '';
			$previewjoin = '';
		}

		$hasthreads = true;
		$threadbits = '';
		$pagenav = '';
		$counter = 0;
		$toread = 0;

		$vbulletin->options['showvotes'] = intval($vbulletin->options['showvotes']);

		if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
		{
			$lastpost_info = "IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost, " .
				"IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter, " .
				"IF(tachythreadpost.userid IS NULL, thread.lastpostid, tachythreadpost.lastpostid) AS lastpostid";

			$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
				"(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
		}
		else
		{
			$lastpost_info = 'thread.lastpost, thread.lastposter, thread.lastpostid';
			$tachyjoin = '';
		}

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('usersub_view_query')) ? eval($hook) : false;

		$threads = $db->query_read_slave("
			SELECT
				IF(thread.votenum >= " . $vbulletin->options['showvotes'] . ", thread.votenum, 0) AS votenum,
				IF(thread.votenum >= " . $vbulletin->options['showvotes'] . " AND thread.votenum > 0, thread.votetotal / thread.votenum, 0) AS voteavg,
				thread.votetotal,
				$previewfield thread.threadid, thread.title AS threadtitle, thread.forumid, thread.pollid,
				thread.open, thread.replycount, thread.postusername, thread.prefixid,
				$lastpost_info, thread.postuserid, thread.dateline, thread.views, thread.iconid AS threadiconid,
				thread.notes, thread.visible, thread.attach, thread.taglist
				" . ($vbulletin->options['threadmarking'] ? ", threadread.readtime AS threadread" : '') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "thread AS thread
			$previewjoin
			" . ($vbulletin->options['threadmarking'] ? " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")" : '') . "
			$tachyjoin
			$hook_query_joins
			WHERE thread.threadid IN ($threadids)
			ORDER BY $sqlsortfield $sqlsortorder
		");
		unset($sqlsortfield, $sqlsortorder);

		require_once(DIR . '/includes/functions_forumdisplay.php');

		// Get Dot Threads
		$dotthreads = fetch_dot_threads_array($threadids);
		if ($vbulletin->options['showdots'] AND $vbulletin->userinfo['userid'])
		{
			$show['dotthreads'] = true;
		}
		else
		{
			$show['dotthreads'] = false;
		}

		if ($vbulletin->options['threadpreview'] AND $vbulletin->userinfo['ignorelist'])
		{
			// Get Buddy List
			$buddy = array();
			if (trim($vbulletin->userinfo['buddylist']))
			{
				$buddylist = preg_split('/( )+/', trim($vbulletin->userinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
					foreach ($buddylist AS $buddyuserid)
				{
					$buddy["$buddyuserid"] = 1;
				}
			}
			DEVDEBUG('buddies: ' . implode(', ', array_keys($buddy)));
			// Get Ignore Users
			$ignore = array();
			if (trim($vbulletin->userinfo['ignorelist']))
			{
				$ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
				foreach ($ignorelist AS $ignoreuserid)
				{
					if (!$buddy["$ignoreuserid"])
					{
						$ignore["$ignoreuserid"] = 1;
					}
				}
			}
			DEVDEBUG('ignored users: ' . implode(', ', array_keys($ignore)));
		}

		$foruminfo['allowratings'] = true;
		$show['notificationtype'] = true;
		$show['threadratings'] = true;
		$show['threadrating'] = true;

		while ($thread = $db->fetch_array($threads))
		{
			// unset the thread preview if it can't be seen
			$forumperms = fetch_permissions($thread['forumid']);
			if ($vbulletin->options['threadpreview'] > 0 AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
			{
				$thread['preview'] = '';
			}

			$threadid = $thread['threadid'];
			// build thread data
			$thread = process_thread_array($thread, $lastread["$thread[forumid]"]);

			switch ($emailupdate["$thread[threadid]"])
			{
				case 0:
					$thread['notification'] = $vbphrase['none'];
					break;
				case 1:
					$thread['notification'] = $vbphrase['instant'];
					break;
				case 2:
					$thread['notification'] = $vbphrase['daily'];
					break;
				case 3:
					$thread['notification'] = $vbphrase['weekly'];
					break;
				default:
					$thread['notification'] = $vbphrase['n_a'];
			}

			($hook = vBulletinHook::fetch_hook('threadbit_display')) ? eval($hook) : false;

			eval('$threadbits .= "' . fetch_template('threadbit') . '";');

		}

		$db->free_result($threads);
		unset($threadids);
		$sorturl = 'subscription.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewsubscription&amp;pp=$perpage&amp;folderid=$folderid";
		$pagenav = construct_page_nav($pagenumber, $perpage, $totalallthreads, $sorturl . "&amp;sort=$sortfield" . iif(!empty($vbulletin->GPC['sortorder']), "&amp;order=" . $vbulletin->GPC['sortorder']));
		$oppositesort = iif($vbulletin->GPC['sortorder'] == 'asc', 'desc', 'asc');

		eval('$sortarrow[' . $sortfield . '] = "' . fetch_template('forumdisplay_sortarrow') . '";');

		$show['havethreads'] = true;
	}
	else
	{
		$totalallthreads = 0;
		$show['havethreads'] = false;
	}

	$navbits[''] = $vbphrase['subscriptions'];
	$navbits = construct_navbits($navbits);

	// build the cp nav
	construct_usercp_nav('substhreads_listthreads');

	($hook = vBulletinHook::fetch_hook('usersub_view_complete')) ? eval($hook) : false;

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('SUBSCRIBE') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

// ########################## Do move of threads ##############################################
if ($_POST['do'] == 'movethread')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'ids'      => TYPE_BINARY,
		'folderid' => TYPE_UINT
	));

	if ($ids = verify_client_string($vbulletin->GPC['ids']))
	{
		$ids = explode(',', $ids);
	}

	if (!is_array($ids) OR empty($ids))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['subscribed_threads'], $vbulletin->options['contactuslink'])));
	}

	$subids = array();
	foreach ($ids AS $subid)
	{
		$id = intval($subid);
		$subids["$id"] = $id;
	}

	($hook = vBulletinHook::fetch_hook('usersub_movethread')) ? eval($hook) : false;

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "subscribethread
		SET folderid = " . $vbulletin->GPC['folderid'] . "
		WHERE userid = " . $vbulletin->userinfo['userid'] . " AND subscribethreadid IN(" . implode(', ', $subids) . ")
	");

	$vbulletin->url = 'subscription.php?' . $vbulletin->session->vars['sessionurl'] . 'folderid=' . $vbulletin->GPC['folderid'];
	eval(print_standard_redirect('sub_threadsmoved'));

}

// ########################## Start Move / Delete / Update Email ##############################
if ($_POST['do'] == 'dostuff')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'deletebox' => TYPE_ARRAY_BOOL,
		'folderid'  => TYPE_NOHTML,
		'what'      => TYPE_STR,
	));

	if ($vbulletin->GPC['folderid'] != 'all')
	{
		$vbulletin->GPC['folderid'] = intval($vbulletin->GPC['folderid']);
	}

	if (empty($vbulletin->GPC['deletebox']))
	{
		eval(standard_error(fetch_error('subsnoselected')));
	}

	$deletebox = array_keys($vbulletin->GPC['deletebox']);

	if (strstr($vbulletin->GPC['what'], 'update'))
	{
		$notifytype = intval($vbulletin->GPC['what'][6]);
		if ($notifytype < 0 OR $notifytype > 3)
		{
			$notifytype = 0;
		}
		$vbulletin->GPC['what'] = 'update';
	}

	($hook = vBulletinHook::fetch_hook('usersub_manage_start')) ? eval($hook) : false;

	switch($vbulletin->GPC['what'])
	{
		// *************************
		// Delete Subscribed Threads
		case 'delete':
			$sql = '';
			foreach ($deletebox AS $threadid)
			{
				$ids .= ',' . intval($threadid);
			}

			($hook = vBulletinHook::fetch_hook('usersub_manage_delete')) ? eval($hook) : false;

			if ($ids)
			{
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "subscribethread WHERE subscribethreadid IN (0$ids) AND userid = " . $vbulletin->userinfo['userid']);
			}
			$vbulletin->url = 'subscription.php?' . $vbulletin->session->vars['sessionurl'] . 'do=viewsubscription&amp;folderid=' . $vbulletin->GPC['folderid'];
			eval(print_standard_redirect('redirect_subupdate'));
			break;

		// *************************
		// Move to new Folder
		case 'move':

			$ids = array();
			foreach ($deletebox AS $id)
			{
				$id = intval($id);
				$ids["$id"] = $id;
			}

			$numthreads = sizeof($ids);

			$ids = sign_client_string(implode(',', $ids));
			unset($id, $deletebox);

			require_once(DIR . '/includes/functions_misc.php');

			if ($vbulletin->GPC['folderid'] === 'all')
			{
				$exclusions = false;
			}
			else
			{
				$exclusions = array($vbulletin->GPC['folderid'], -1);
			}

			$folderoptions = construct_folder_jump(1, 0, $exclusions);

			($hook = vBulletinHook::fetch_hook('usersub_manage_move')) ? eval($hook) : false;

			if ($folderoptions)
			{
				if ($vbulletin->GPC['folderid'] === 'all')
				{
					$fromfolder = $vbphrase['all'];
				}
				else
				{
					$folders = unserialize($vbulletin->userinfo['subfolders']);
					$fromfolder = $folders["{$vbulletin->GPC['folderid']}"];
				}

				// build the cp nav
				construct_usercp_nav('substhreads_listthreads');

				$navbits[''] = $vbphrase['subscriptions'];
				$navbits = construct_navbits($navbits);
				eval('$navbar = "' . fetch_template('navbar') . '";');

				$folderid =& $vbulletin->GPC['folderid'];
				eval('$HTML = "' . fetch_template('subscribe_move') . '";');
				eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
			}
			else
			{
				eval(standard_error(fetch_error('subscription_nofolders', 'subscription.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editfolders')));
			}

			$vbulletin->url = 'subscription.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewsubscription&amp;folderid=" . $vbulletin->GPC['folderid'];
			eval(print_standard_redirect('redirect_submove'));
			break;

		// *************************
		// Change Notification Type
		case 'update':
			foreach ($deletebox AS $threadid)
			{
				$ids .= ',' . intval($threadid);
			}

			($hook = vBulletinHook::fetch_hook('usersub_manage_update')) ? eval($hook) : false;

			if ($ids)
			{
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "subscribethread
					SET emailupdate = $notifytype
					WHERE subscribethreadid IN (0$ids) AND
						userid = " . $vbulletin->userinfo['userid']
				);
			}

			$vbulletin->url = 'subscription.php?' . $vbulletin->session->vars['sessionurl'] . 'do=viewsubscription&amp;folderid=' . $vbulletin->GPC['folderid'];
			eval(print_standard_redirect('redirect_subupdate'));
			break;

		// *****************************
		// unknown action specified
		default:
			eval(standard_error(fetch_error('invalidid', $vbphrase['action'], $vbulletin->options['contactuslink'])));
	}
}

// ############################### start edit folders ###############################
if ($_REQUEST['do'] == 'editfolders')
{
	$folders = unserialize($vbulletin->userinfo['subfolders']);

	if (!$folders[0])
	{
		$defaultfolder = $vbphrase['subscriptions'];
		$folders[0] = $defaultfolder;
	}
	else
	{
		$defaultfolder = $folders[0];
	}

	natcasesort($folders);

	if (is_array($folders))
	{
		$foldercount = 1;
		foreach ($folders AS $folderid => $title)
		{
			eval('$folderboxes .= "' . fetch_template('subscribe_folderbit') . '";');
			$foldercount++;
		}
	}

	$foldercount = 1;
	$folderid = 0;
	$title = '';
	while ($foldercount < 4)
	{
		for ($x = $folderid + 1; 1 == 1; $x++)
		{
			if (!$folders["$x"])
			{
				$folderid = $x;
				break;
			}
		}
		eval('$newfolderboxes .= "' . fetch_template('subscribe_folderbit') . '";');
		$foldercount++;
	}

	// generate navbar
	$navbits['subscription.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewsubscription"] = $vbphrase['subscriptions'];
	$navbits[''] = $vbphrase['edit_folders'];
	$navbits = construct_navbits($navbits);

	// build the cp nav
	construct_usercp_nav('substhreads_editfolders');

	$show['customfolders'] = iif($folderboxes != '', true, false);

	($hook = vBulletinHook::fetch_hook('usersub_editfolders')) ? eval($hook) : false;

	eval('$navbar = "' . fetch_template('navbar') . '";');
	eval('$HTML = "' . fetch_template('subscribe_showfolders') . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');

} #end editfolders

// ############################### start update folders ###############################
if ($_POST['do'] == 'doeditfolders')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'folderlist' => TYPE_ARRAY_NOHTML,
	));

	$folders = unserialize($vbulletin->userinfo['subfolders']);

	($hook = vBulletinHook::fetch_hook('usersub_doeditfolders')) ? eval($hook) : false;

	if (!empty($vbulletin->GPC['folderlist']))
	{
		foreach ($vbulletin->GPC['folderlist'] AS $folderid => $title)
		{
			$folderid = intval($folderid);

			if (empty($title))
			{
				if ($folders["$folderid"])
				{
					$deletefolders .= iif($deletefolders, ',', '') . $folderid;
				}
				unset($folders["$folderid"]);
			}
			else
			{
				$folders["$folderid"] = $title;
			}

		}
		if ($deletefolders)
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "subscribethread
				SET folderid = 0
				WHERE folderid IN ($deletefolders) AND
					userid = " . $vbulletin->userinfo['userid']
			);
		}
		if (!empty($folders))
		{
			natcasesort($folders);
		}

		require_once(DIR . '/includes/functions_databuild.php');
		build_usertextfields('subfolders', iif(empty($folders), '', serialize($folders)));
	}

	$itemtype = $vbphrase['subscription'];
	$itemtypes = $vbphrase['subscriptions'];
	$vbulletin->url = 'subscription.php?' . $vbulletin->session->vars['sessionurl'] . 'do=viewsubscription';
	eval(print_standard_redirect('foldersedited'));

} #end doeditfolders


/*======================================================================*\
|| ####################################################################
|| #
|| # CVS: $RCSfile$ - $Revision: 39862 $
|| ####################################################################
\*======================================================================*/
?>
