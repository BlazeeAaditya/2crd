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
define('GET_EDIT_TEMPLATES', true);
define('CSRF_PROTECTION', true);
define('THIS_SCRIPT', 'editpost');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'threadmanage',
	'posting',
	'postbit',
	'prefix',
	'reputationlevel',
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'editpost',
	'newpost_attachment',
	'newpost_attachmentbit',
	'optgroup',
	'postbit',
	'postbit_wrapper',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_editor.php');
require_once(DIR . '/includes/functions_log_error.php');
require_once(DIR . '/includes/functions_prefix.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ### STANDARD INITIALIZATIONS ###
$checked = array();
$edit = array();
$postattach = array();

// get decent textarea size for user's browser
$textareacols = fetch_textarea_width();

// sanity checks...
if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'editpost';
}

if (!$postinfo['postid'] OR $postinfo['isdeleted'] OR (!$postinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
}

if (!$threadinfo['threadid'] OR $threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
}

if ($vbulletin->options['wordwrap'])
{
	$threadinfo['title'] = fetch_word_wrapped_string($threadinfo['title']);
}

// get permissions info
$_permsgetter_ = 'edit post';
$forumperms = fetch_permissions($threadinfo['forumid']);
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0)))
{
	print_no_permission();
}

$foruminfo = fetch_foruminfo($threadinfo['forumid'], false);

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

// need to get last post-type information
cache_ordered_forums(1);

// determine if we are allowed to be updating the thread's info
$can_update_thread = (
	$threadinfo['firstpostid'] == $postinfo['postid']
	AND (can_moderate($threadinfo['forumid'], 'caneditthreads')
		OR ($postinfo['dateline'] + $vbulletin->options['editthreadtitlelimit'] * 60) > TIMENOW
	)
);

// ############################### start permissions checking ###############################
if ($_REQUEST['do'] == 'deletepost')
{
	// is post being deleted? if so check delete specific permissions
	if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{
		if (!$threadinfo['open'])
		{
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$postinfo[threadid]";
			eval(print_standard_redirect('redirect_threadclosed'));
		}
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletepost']))
		{
			print_no_permission();
		}
		else
		{
			if ($vbulletin->userinfo['userid'] != $postinfo['userid'])
			{
				// check user owns this post since they failed the Mod Delete permission check for this forum
				print_no_permission();
			}
		}
	}
}
else
{
	// otherwise, post is being edited
	if (!can_moderate($threadinfo['forumid'], 'caneditposts'))
	{ // check for moderator
		if (!$threadinfo['open'])
		{
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
			eval(standard_error(fetch_error('threadclosed')));
		}
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['caneditpost']))
		{
			print_no_permission();
		}
		else
		{
			if ($vbulletin->userinfo['userid'] != $postinfo['userid'])
			{
				// check user owns this post
				print_no_permission();
			}
			else
			{
				// check for time limits
				if ($postinfo['dateline'] < (TIMENOW - ($vbulletin->options['edittimelimit'] * 60)) AND $vbulletin->options['edittimelimit'] != 0)
				{
					eval(standard_error(fetch_error('edittimelimit', $vbulletin->options['edittimelimit'], $vbulletin->options['contactuslink'])));
				}
			}
		}
	}
}

($hook = vBulletinHook::fetch_hook('editpost_start')) ? eval($hook) : false;

// ############################### start update post ###############################
if ($_POST['do'] == 'updatepost')
{
	// Variables reused in templates
	$posthash = $vbulletin->input->clean_gpc('p', 'posthash', TYPE_NOHTML);
	$poststarttime = $vbulletin->input->clean_gpc('p', 'poststarttime', TYPE_UINT);

	$vbulletin->input->clean_array_gpc('p', array(
		'stickunstick'    => TYPE_BOOL,
		'openclose'       => TYPE_BOOL,
		'wysiwyg'         => TYPE_BOOL,
		'message'         => TYPE_STR,
		'title'           => TYPE_STR,
		'prefixid'        => TYPE_NOHTML,
		'iconid'          => TYPE_UINT,
		'parseurl'        => TYPE_BOOL,
		'signature'	      => TYPE_BOOL,
		'disablesmilies'  => TYPE_BOOL,
		'reason'          => TYPE_NOHTML,
		'preview'         => TYPE_STR,
		'folderid'        => TYPE_UINT,
		'emailupdate'     => TYPE_UINT,
		'ajax'            => TYPE_BOOL,
		'advanced'        => TYPE_BOOL,
		'postcount'       => TYPE_UINT,
		'podcasturl'      => TYPE_STR,
		'podcastsize'     => TYPE_UINT,
		'podcastexplicit' => TYPE_BOOL,
		'podcastkeywords' => TYPE_STR,
		'podcastsubtitle' => TYPE_STR,
		'podcastauthor'   => TYPE_STR,

		'quickeditnoajax' => TYPE_BOOL // true when going from an AJAX edit but not using AJAX
	));
	// Make sure the posthash is valid

	($hook = vBulletinHook::fetch_hook('editpost_update_start')) ? eval($hook) : false;

	if (md5($poststarttime . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']) != $posthash)
	{
		$posthash = 'invalid posthash'; // don't phrase me
	}

	// ### PREP INPUT ###
	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$edit['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $foruminfo['allowhtml']);
	}
	else
	{
		$edit['message'] =& $vbulletin->GPC['message'];
	}

	$cansubscribe = true;
	// Are we editing someone else's post? If so load that users subscription info for this thread.
	if ($vbulletin->userinfo['userid'] != $postinfo['userid'])
	{
		if ($postinfo['userid'])
		{
			$userinfo = fetch_userinfo($postinfo['userid']);
			cache_permissions($userinfo);
		}

		$cansubscribe = (
			$userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canview'] AND
			$userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewthreads'] AND
			($threadinfo['postuserid'] == $userinfo['userid'] OR $userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewothers'])
		);

		if ($cansubscribe AND $otherthreadinfo = $db->query_first_slave("
			SELECT emailupdate, folderid
			FROM " . TABLE_PREFIX . "subscribethread
			WHERE threadid = $threadinfo[threadid] AND
				userid = $postinfo[userid] AND
				canview = 1"))
		{
			$threadinfo['issubscribed'] = true;
			$threadinfo['emailupdate'] = $otherthreadinfo['emailupdate'];
			$threadinfo['folderid'] = $otherthreadinfo['folderid'];
		}
		else
		{
			$threadinfo['issubscribed'] = false;
			// use whatever emailupdate setting came through
		}
	}

	if ($vbulletin->GPC['ajax'] OR $vbulletin->GPC['quickeditnoajax'])
	{
		// quick edit
		$tmpmessage = ($vbulletin->GPC['ajax'] ? convert_urlencoded_unicode($edit['message']) : $edit['message']);

		$edit = $postinfo;
		$edit['message'] =& $tmpmessage;
		$edit['title'] = unhtmlspecialchars($edit['title']);
		$edit['signature'] =& $edit['showsignature'];
		$edit['enablesmilies'] =& $edit['allowsmilie'];
		$edit['disablesmilies'] = $edit['enablesmilies'] ? 0 : 1;
		$edit['parseurl'] = true;
		$edit['prefixid'] = $threadinfo['prefixid'];

		$edit['reason'] = fetch_censored_text(
			$vbulletin->GPC['ajax'] ? convert_urlencoded_unicode($vbulletin->GPC['reason']) : $vbulletin->GPC['reason']
		);
	}
	else
	{
		$edit['iconid'] =& $vbulletin->GPC['iconid'];
		$edit['title'] =& $vbulletin->GPC['title'];
		$edit['prefixid'] = (($vbulletin->GPC_exists['prefixid'] AND can_use_prefix($vbulletin->GPC['prefixid'])) ? $vbulletin->GPC['prefixid'] : $threadinfo['prefixid']);

		$edit['podcasturl'] =& $vbulletin->GPC['podcasturl'];
		$edit['podcastsize'] =& $vbulletin->GPC['podcastsize'];
		$edit['podcastexplicit'] =& $vbulletin->GPC['podcastexplicit'];
		$edit['podcastkeywords'] =& $vbulletin->GPC['podcastkeywords'];
		$edit['podcastsubtitle'] =& $vbulletin->GPC['podcastsubtitle'];
		$edit['podcastauthor'] =& $vbulletin->GPC['podcastauthor'];

		// Leave this off for quickedit->advanced so that a post with unparsed links doesn't get parsed just by going to Advanced Edit
		if ($vbulletin->GPC['advanced'])
		{
			$edit['parseurl'] = false;
		}
		else
		{
			$edit['parseurl'] =& $vbulletin->GPC['parseurl'];
		}
		$edit['signature'] =& $vbulletin->GPC['signature'];
		$edit['disablesmilies'] =& $vbulletin->GPC['disablesmilies'];
		$edit['enablesmilies'] = $edit['allowsmilie'] = ($edit['disablesmilies']) ? 0 : 1;
		$edit['stickunstick'] =& $vbulletin->GPC['stickunstick'];
		$edit['openclose'] =& $vbulletin->GPC['openclose'];

		$edit['reason'] = fetch_censored_text($vbulletin->GPC['reason']);
		$edit['preview'] =& $vbulletin->GPC['preview'];
		$edit['folderid'] =& $vbulletin->GPC['folderid'];
		if (!$vbulletin->GPC['advanced'])
		{
			if ($vbulletin->GPC_exists['emailupdate'])
			{
				$edit['emailupdate'] =& $vbulletin->GPC['emailupdate'];
			}
			else
			{
				$edit['emailupdate'] = array_pop($array = array_keys(fetch_emailchecked($threadinfo)));
			}
		}
	}

	$dataman =& datamanager_init('Post', $vbulletin, ERRTYPE_ARRAY, 'threadpost');
	$dataman->set_existing($postinfo);

	($hook = vBulletinHook::fetch_hook('editpost_update_process')) ? eval($hook) : false;

	// set info
	$dataman->set_info('parseurl', (($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) AND $foruminfo['allowbbcode'] AND $edit['parseurl']));
	$dataman->set_info('posthash', $posthash);
	$dataman->set_info('forum', $foruminfo);
	$dataman->set_info('thread', $threadinfo);
	$dataman->set_info('show_title_error', true);
	$dataman->set_info('podcasturl', $edit['podcasturl']);
	$dataman->set_info('podcastsize', $edit['podcastsize']);
	$dataman->set_info('podcastexplicit', $edit['podcastexplicit']);
	$dataman->set_info('podcastkeywords', $edit['podcastkeywords']);
	$dataman->set_info('podcastsubtitle', $edit['podcastsubtitle']);
	$dataman->set_info('podcastauthor', $edit['podcastauthor']);
	if ($postinfo['userid'] == $vbulletin->userinfo['userid'])
	{
		$dataman->set_info('user', $vbulletin->userinfo);
	}

	// set options
	$dataman->setr('showsignature', $edit['signature']);
	$dataman->setr('allowsmilie', $edit['enablesmilies']);

	// set data
	/*$dataman->setr('userid', $vbulletin->userinfo['userid']);
	if ($vbulletin->userinfo['userid'] == 0)
	{
		$dataman->setr('username', $post['username']);
	}*/
	$dataman->setr('title', $edit['title']);
	$dataman->setr('pagetext', $edit['message']);
	if ($postinfo['userid'] != $vbulletin->userinfo['userid'])
	{
		$dataman->setr('iconid', $edit['iconid'], true, false);
	}
	else
	{
		$dataman->setr('iconid', $edit['iconid']);
	}

	$postusername = $vbulletin->userinfo['username'];

	$dataman->pre_save();
	if ($dataman->errors)
	{
		$errors = $dataman->errors;
	}
	if ($dataman->info['podcastsize'])
	{
		$edit['podcastsize'] = $dataman->info['podcastsize'];
	}

	if (sizeof($errors) > 0)
	{
		// ### POST HAS ERRORS ###
		if ($vbulletin->GPC['ajax'])
		{
			require_once(DIR . '/includes/class_xml.php');
			$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
			$xml->add_group('errors');
			foreach ($errors AS $error)
			{
				$xml->add_tag('error', $error);
			}
			$xml->close_group();
			$xml->print_xml();
		}
		else
		{
			$postpreview = construct_errors($errors);
			construct_checkboxes($edit);
			$previewpost = true;
			$_REQUEST['do'] = 'editpost';
		}
	}
	else if ($edit['preview'])
	{
		$attachs = $db->query_read_slave("
			SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
				IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
				attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
			FROM " . TABLE_PREFIX . "attachment
			LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
			WHERE postid = $postinfo[postid]
			ORDER BY attachmentid
		");
		while ($attachment = $db->fetch_array($attachs))
		{
			if (!$attachment['build_thumbnail'])
			{
				$attachment['hasthumbnail'] = false;
			}
			$postattach["$attachment[attachmentid]"] = $attachment;
		}

		// Attachments added since the edit began.
		$attachs = $db->query_read("
			SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
				IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
				attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
			FROM " . TABLE_PREFIX . "attachment AS attachment
			LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
			WHERE posthash = '" . $db->escape_string($posthash) . "'
				AND (userid = " . $vbulletin->userinfo['userid'] . " OR userid = $postinfo[userid])
			ORDER BY attachmentid
		");
		while ($attachment = $db->fetch_array($attachs))
		{
			if (!$attachment['build_thumbnail'])
			{
				$attachment['hasthumbnail'] = false;
			}
			$postattach["$attachment[attachmentid]"] = $attachment;
		}

		// ### PREVIEW POST ###
		$postpreview = process_post_preview($edit, $postinfo['userid'], $postattach);
		$previewpost = true;
		$_REQUEST['do'] = 'editpost';
	}
	else if ($vbulletin->GPC['advanced'])
	{
		// Don't display preview on QuickEdit->Advanced as parseurl is turned off and so the preview won't be correct unless the post originally had checked to not parse links
		// If you turn on parseurl then the opposite happens and you have to go unparse your links if that is what you want. Compromise
		$_REQUEST['do'] = 'editpost';
	}
	else
	{
		// ### POST HAS NO ERRORS ###

		$dataman->save();

		$update_edit_log = true;

		// don't show edited by AND reason unchanged - don't update edit log
		if (!($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['showeditedby']) AND $edit['reason'] == $postinfo['edit_reason'])
		{
			$update_edit_log = false;
		}

		if ($update_edit_log)
		{
			// ug perm: show edited by
			if ($postinfo['dateline'] < (TIMENOW - ($vbulletin->options['noeditedbytime'] * 60)) OR !empty($edit['reason']))
			{
				// save the postedithistory
				if ($vbulletin->options['postedithistory'])
				{
					// insert original post on first edit
					if (!$db->query_first("SELECT postedithistoryid FROM " . TABLE_PREFIX . "postedithistory WHERE original = 1 AND postid = " . $postinfo['postid']))
					{
						$db->query_write("
							INSERT INTO " . TABLE_PREFIX . "postedithistory
								(postid, userid, username, title, iconid, dateline, reason, original, pagetext)
							VALUES
								($postinfo[postid],
								" . $postinfo['userid'] . ",
								'" . $db->escape_string($postinfo['username']) . "',
								'" . $db->escape_string($postinfo['title']) . "',
								$postinfo[iconid],
								" . $postinfo['dateline'] . ",
								'',
								1,
								'" . $db->escape_string($postinfo['pagetext']) . "')
						");
					}
					// insert the new version
					$db->query_write("
						INSERT INTO " . TABLE_PREFIX . "postedithistory
							(postid, userid, username, title, iconid, dateline, reason, pagetext)
						VALUES
							($postinfo[postid],
							" . $vbulletin->userinfo['userid'] . ",
							'" . $db->escape_string($vbulletin->userinfo['username']) . "',
							'" . $db->escape_string($edit['title']) . "',
							$edit[iconid],
							" . TIMENOW . ",
							'" . $db->escape_string($edit['reason']) . "',
							'" . $db->escape_string($edit['message']) . "')
					");
				}
				/*insert query*/
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "editlog
						(postid, userid, username, dateline, reason, hashistory)
					VALUES
						($postinfo[postid],
						" . $vbulletin->userinfo['userid'] . ",
						'" . $db->escape_string($vbulletin->userinfo['username']) . "',
						" . TIMENOW . ",
						'" . $db->escape_string($edit['reason']) . "',
						" . ($vbulletin->options['postedithistory'] ? 1 : 0) . ")
				");
			}
		}

		$date = vbdate($vbulletin->options['dateformat'], TIMENOW);
		$time = vbdate($vbulletin->options['timeformat'], TIMENOW);

		// initialize thread / forum update clauses
		$forumupdate = false;

		$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$threadman->set_existing($threadinfo);

		if ($can_update_thread AND $edit['title'] != '')
		{
			// need to update thread title and iconid
			if (!can_moderate($threadinfo['forumid']))
			{
				$threadman->set_info('skip_moderator_log', true);
			}

			$threadman->set_info('skip_first_post_update', true);

			if ($edit['title'] != $postinfo['title'])
			{
				$threadman->set('title', unhtmlspecialchars($edit['title']));
			}

			if ($edit['iconid'] != $postinfo['iconid'])
			{
				$threadman->set('iconid', $edit['iconid']);
			}

			if ($vbulletin->GPC_exists['prefixid'] AND can_use_prefix($vbulletin->GPC['prefixid']))
			{
				$threadman->set('prefixid', $vbulletin->GPC['prefixid']);
				if ($threadman->thread['prefixid'] === '' AND ($foruminfo['options'] & $vbulletin->bf_misc_forumoptions['prefixrequired']))
				{
					// the prefix wasn't valid or was set to an empty one, but that's not allowed
					$threadman->do_unset('prefixid');
				}
			}

			// do we need to update the forum counters?
			$forumupdate = ($foruminfo['lastthreadid'] == $threadinfo['threadid']) ? true : false;
		}

		// can this user open/close this thread if they want to?
		if ($vbulletin->GPC['openclose'] AND (($threadinfo['postuserid'] != 0 AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']) OR can_moderate($threadinfo['forumid'], 'canopenclose')))
		{
			$threadman->set('open', ($threadman->fetch_field('open') == 1 ? 0 : 1));
		}
		if ($vbulletin->GPC['stickunstick'] AND can_moderate($threadinfo['forumid'], 'canmanagethreads'))
		{
			$threadman->set('sticky', ($threadman->fetch_field('sticky') == 1 ? 0 : 1));
		}

		($hook = vBulletinHook::fetch_hook('editpost_update_thread')) ? eval($hook) : false;

		$threadman->save();

		// if this is a mod edit, then log it
		if ($vbulletin->userinfo['userid'] != $postinfo['userid'] AND can_moderate($threadinfo['forumid'], 'caneditposts'))
		{
			$modlog = array(
				'threadid' => $threadinfo['threadid'],
				'forumid'  => $threadinfo['forumid'],
				'postid'   => $postinfo['postid']
			);
			log_moderator_action($modlog, 'post_x_edited', $postinfo['title']);
		}

		require_once(DIR . '/includes/functions_databuild.php');

		// do forum update if necessary
		if ($forumupdate)
		{
			build_forum_counters($threadinfo['forumid']);
		}

		// don't do thread subscriptions if we are doing quick edit
		if (!$vbulletin->GPC['ajax'] AND !$vbulletin->GPC['quickeditnoajax'])
		{
			// ### DO THREAD SUBSCRIPTION ###
			// We use $postinfo[userid] so that we update the user who posted this, not the user who is editing this
			if (!$threadinfo['issubscribed'] AND $edit['emailupdate'] != 9999)
			{
				// user is not subscribed to this thread so insert it
				/*insert query*/
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
					VALUES ($postinfo[userid], $threadinfo[threadid], $edit[emailupdate], $edit[folderid], 1)
				");
			}
			else
			{ // User is subscribed, see if they changed the settings for this thread
				if ($edit['emailupdate'] == 9999)
				{
					// Remove this subscription, user chose 'No Subscription'
					/*insert query*/
					$db->query_write("
						DELETE FROM " . TABLE_PREFIX . "subscribethread
						WHERE threadid = $threadinfo[threadid]
							AND userid = $postinfo[userid]
					");
				}
				else if ($threadinfo['emailupdate'] != $edit['emailupdate'] OR $threadinfo['folderid'] != $edit['folderid'])
				{
					// User changed the settings so update the current record
					/*insert query*/
					$db->query_write("
						REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
						VALUES ($postinfo[userid], $threadinfo[threadid], $edit[emailupdate], $edit[folderid], 1)
					");
				}
			}
		}

		($hook = vBulletinHook::fetch_hook('editpost_update_complete')) ? eval($hook) : false;

		if ($vbulletin->GPC['ajax'])
		{
			// #############################################################################
			// #############################################################################
			// #############################################################################
			require_once(DIR . '/includes/class_postbit.php');
			require_once(DIR . '/includes/functions_bigthree.php');
			require_once(DIR . '/includes/class_xml.php');

			$postcount = 0;
			$thread =& $threadinfo;
			$forum =& $foruminfo;

			$hook_query_fields = $hook_query_joins = $hook_query_where = '';
			($hook = vBulletinHook::fetch_hook('editpost_edit_ajax')) ? eval($hook) : false;

			$post = $db->query_first("
				SELECT
					post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
					user.*, userfield.*, usertextfield.*,
					" . iif($forum['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
					" . iif($vbulletin->options['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight,') . "
					editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline,
					editlog.reason AS edit_reason, editlog.hashistory,
					postparsed.pagetext_html, postparsed.hasimages,
					sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
					sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
					IF(user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid
					" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
					$hook_query_fields
				FROM " . TABLE_PREFIX . "post AS post
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
				LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
				LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
				" . iif($forum['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
				" . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
				LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
				LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
				LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
				LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
				$hook_query_joins
				WHERE post.postid = $postinfo[postid]
					$hook_query_where
			");

			// determine ignored users
			$ignore = array();
			if (trim($vbulletin->userinfo['ignorelist']))
			{
				$ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
				foreach ($ignorelist AS $ignoreuserid)
				{
					$ignore["$ignoreuserid"] = 1;
				}
			}

			$see_deleted = ($forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice'] OR can_moderate($threadinfo['forumid']));

			$postbit_factory = new vB_Postbit_Factory();
			$postbit_factory->registry =& $vbulletin;
			$postbit_factory->forum =& $foruminfo;
			$postbit_factory->thread =& $thread;
			$postbit_factory->cache = array();
			$postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

			if ($postinfo['attach'])
			{
				$attachments = $db->query_read_slave("
					SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
						postid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
						attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
					FROM " . TABLE_PREFIX . "attachment
					LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
					WHERE postid = $postinfo[postid]
					ORDER BY attachmentid
				");
				while ($attachment = $db->fetch_array($attachments))
				{
					if (!$attachment['build_thumbnail'])
					{
						$attachment['hasthumbnail'] = false;
					}
					$post['attachments']["$attachment[attachmentid]"] = $attachment;
				}
			}

			$postbit = '';

			// work out if quickreply should be shown or not
			if (
				$vbulletin->options['quickreply']
				AND
				!$thread['isdeleted'] AND !is_browser('netscape') AND $vbulletin->userinfo['userid']
				AND (
					($vbulletin->userinfo['userid'] == $threadinfo['postuserid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown'])
					OR
					($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])
				) AND
				($thread['open'] OR can_moderate($threadinfo['forumid'], 'canopenclose'))
			)
			{
				$show['quickreply'] = true;
			}
			else
			{
				$show['quickreply'] = false;
				$show['wysiwyg'] = 0;
				$quickreply = '';
			}

			if (!$forum['allowposting'])
			{
				$show['quickreply'] = false;
			}

			$show['postcount'] = ($vbulletin->GPC['postcount'] ? true : false);
			$show['managepost'] = iif(can_moderate($threadinfo['forumid'], 'candeleteposts') OR can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);
			$show['approvepost'] = (can_moderate($threadinfo['forumid'], 'canmoderateposts')) ? true : false;
			$show['managethread'] = can_moderate($threadinfo['forumid'], 'canmanagethreads') ? true : false;
			$show['inlinemod'] = ($show['managethread'] OR $show['managepost'] OR $show['approvepost']) ? true : false;
			$show['multiquote_global'] = ($vbulletin->options['multiquote'] AND $vbulletin->userinfo['userid']);

			if ($show['multiquote_global'])
			{
				$vbulletin->input->clean_array_gpc('c', array(
					'vbulletin_multiquote' => TYPE_STR
				));
				$vbulletin->GPC['vbulletin_multiquote'] = explode(',', $vbulletin->GPC['vbulletin_multiquote']);
			}

			if ($ignore["$post[userid]"])
			{
				$fetchtype = 'post_ignore';
			}
			else if ($post['visible'] == 2)
			{
				$fetchtype = 'post_deleted';
			}
			else
			{
				$fetchtype = 'post';
			}

			($hook = vBulletinHook::fetch_hook('showthread_postbit_create')) ? eval($hook) : false;

			$show['spacer'] = false;
			$post['postcount'] = $vbulletin->GPC['postcount'];
			$postbit_obj =& $postbit_factory->fetch_postbit($fetchtype);

			$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
			$xml->add_tag('postbit', process_replacement_vars($postbit_obj->construct_postbit($post)));
			$xml->print_xml(true);

			// #############################################################################
			// #############################################################################
			// #############################################################################
		}
		else
		{
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$postinfo[postid]#post$postinfo[postid]";
			eval(print_standard_redirect('redirect_editthanks'));
		}
	}
}

// ############################### start edit post ###############################
if ($_REQUEST['do'] == 'editpost')
{
	($hook = vBulletinHook::fetch_hook('editpost_edit_start')) ? eval($hook) : false;

	// get message
	if ($edit['message'] != '')
	{
		$newpost['message'] = htmlspecialchars_uni($edit['message']);
	}
	else
	{
		$newpost['message'] = htmlspecialchars_uni($postinfo['pagetext']);
	}

	// handle checkboxes
	if ($previewpost)
	{
		$checked['parseurl'] = ($edit['parseurl']) ? 'checked="checked"' : '';
		$checked['signature'] = ($edit['signature']) ? 'checked="checked"' : '';
		$checked['disablesmilies'] = ($edit['disablesmilies']) ? 'checked="checked"' : '';
		$checked['stickunstick'] = ($edit['stickunstick']) ? 'checked="checked"' : '';
		$checked['openclose'] = ($edit['openclose']) ? 'checked="checked"' : '';
	}
	else
	{
		$checked['parseurl'] = 'checked="checked"';
		$checked['signature'] = ($postinfo['showsignature']) ? 'checked="checked"' : '';
		$checked['disablesmilies'] = (!$postinfo['allowsmilie']) ? 'checked="checked"' : '';
	}

	// Are we editing someone else's post? If so load that users folders and subscription info for this thread.
	if ($vbulletin->userinfo['userid'] != $postinfo['userid'])
	{
		$userfolders = $db->query_first_slave("
			SELECT subfolders, signature
			FROM " . TABLE_PREFIX . "usertextfield
			WHERE userid = $postinfo[userid]
		");

		// temporarily assign this user's signature to $vbulletin->userinfo so we can see whether or not to show the sig checkbox option
		$vbulletin->userinfo['signature'] = $userfolders['signature'];

		if ($userfolders['subfolders'] == '')
		{
			$folders = array();
		}
		else
		{
			$folders = unserialize($userfolders['subfolders']);
		}
		if (empty($folders)) // catch user who has no folders or an empty serialized array
		{
			$folders = array($vbphrase['subscriptions']);
		}
		if ($otherthreadinfo = $db->query_first_slave("
			SELECT emailupdate, folderid
			FROM " . TABLE_PREFIX . "subscribethread
			WHERE threadid = $threadinfo[threadid] AND
				userid = $postinfo[userid] AND
				canview = 1"))
		{
			$threadinfo['issubscribed'] = true;
			$threadinfo['emailupdate'] = $otherthreadinfo['emailupdate'];
			$threadinfo['folderid'] = $otherthreadinfo['folderid'];
		}
		else
		{
			$threadinfo['issubscribed'] = false;
			$threadinfo['emailupdate'] = 9999;
		}
	}
	else
	{
		$folders = unserialize($vbulletin->userinfo['subfolders']);
	}

	// Get subscribed thread folders
	if ($edit['emailupdate'] !== NULL)
	{
		$folderselect["{$edit['folderid']}"] = 'selected="selected"';
		$emailchecked["{$edit['emailupdate']}"] = 'selected="selected"';
	}
	else
	{
		if ($threadinfo['issubscribed'])
		{
			$folderselect["$threadinfo[folderid]"] = 'selected="selected"';
		}
		else
		{
			$folderselect[0] = 'selected="selected"';
		}
		// get the checked option for auto subscription
		$emailchecked = fetch_emailchecked($threadinfo);
	}

	// Don't show the folderjump if we only have one folder, would be redundant ;)
	if (sizeof($folders) > 1)
	{
		require_once(DIR . '/includes/functions_misc.php');
		$folderbits = construct_folder_jump(1, $threadinfo['folderid'], false, $folders);
		$show['subscriptionfolders'] = true;
	}

	if ($previewpost OR $vbulletin->GPC['advanced'])
	{
		$newpost['reason'] = $edit['reason'];
	}
	else if ($vbulletin->userinfo['userid'] == $postinfo['edit_userid'])
	{
		// Only carry the reason over if the editing user owns the previous edit
		$newpost['reason'] = $postinfo['edit_reason'];
	}

	$postinfo['postdate'] = vbdate($vbulletin->options['dateformat'], $postinfo['dateline']);
	$postinfo['posttime'] = vbdate($vbulletin->options['timeformat'], $postinfo['dateline']);

	// find out if first post
	$getpost = $db->query_first("
		SELECT postid
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = $threadinfo[threadid]
		ORDER BY dateline
		LIMIT 1
	");
	if ($getpost['postid'] == $postinfo['postid'])
	{
		$isfirstpost = true;
	}
	else
	{
		$isfirstpost = false;
	}
	if ($isfirstpost AND $postinfo['title'] == '' AND ($postinfo['dateline'] + $vbulletin->options['editthreadtitlelimit'] * 60) > TIMENOW)
	{
		$postinfo['title'] = $threadinfo['title'];
	}

	if ($edit['title'] != '')
	{
		$title = $edit['title'];
	}
	else
	{
		$title = $postinfo['title'];
	}

	// load prefix stuff if necessary
	if ($can_update_thread)
	{
		$prefix_options = fetch_prefix_html($foruminfo['forumid'], (isset($edit['prefixid']) ? $edit['prefixid'] : $threadinfo['prefixid']), true);
		$show['empty_prefix_option'] = ($threadinfo['prefixid'] == '' OR !($foruminfo['options'] & $vbulletin->bf_misc_forumoptions['prefixrequired']));
	}
	else
	{
		$prefix_options = '';
	}

	if ($postinfo['userid'])
	{
		$userinfo = fetch_userinfo($postinfo['userid']);
		$postinfo['username'] = $userinfo['username'];
	}

	if ($edit['iconid'])
	{
		$posticons = construct_icons($edit['iconid'], $foruminfo['allowicons']);
	}
	else
	{
		$posticons = construct_icons($postinfo['iconid'], $foruminfo['allowicons']);
	}

	$attach_editor = array();
	$attachment_js = '';

	// edit / add attachment
	if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'] AND !empty($vbulletin->userinfo['attachmentextensions']))
	{
		require_once(DIR . '/includes/functions_file.php');
		$inimaxattach = fetch_max_upload_size();
		$maxattachsize = vb_number_format($inimaxattach, 1, true);

		if (!$posthash OR !$poststarttime)
		{
			$poststarttime = TIMENOW;
			$posthash = md5($poststarttime . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);

		}

		if (empty($postattach))
		{
			// <-- This is done in two queries since Mysql will not use an index on an OR query which gives a full table scan of the attachment table
			// A poor man's UNION
			// Attachments that existed before the edit began.
			$currentattaches1 = $db->query_read_slave("
				SELECT dateline, filename, filesize, attachmentid
				FROM " . TABLE_PREFIX . "attachment
				WHERE postid = $postinfo[postid]
				ORDER BY attachmentid
			");
			// Attachments added since the edit began. Used when editpost is reloaded due to an error on the user side
			$currentattaches2 = $db->query_read_slave("
				SELECT dateline, filename, filesize, attachmentid
				FROM " . TABLE_PREFIX . "attachment
				WHERE posthash = '" . $db->escape_string($posthash) . "'
					AND userid = " . $vbulletin->userinfo['userid'] . "
				ORDER BY attachmentid
			");
			$attachcount = 0;
			for ($x = 1; $x <= 2; $x++)
			{
				$currentattaches =& ${currentattaches . $x};
				while ($attach = $db->fetch_array($currentattaches))
				{
					$postattach["$attach[attachmentid]"] = $attach;
				}
			}
		}
		// DON'T PUT AN ELSE HERE! OR ELSE!! ;)
		if (!empty($postattach))
		{
			foreach($postattach AS $attachmentid => $attach)
			{
				$attachcount++;
				$attach['filename'] = htmlspecialchars_uni($attach['filename']);
				$attach['filesize'] = vb_number_format($attach['filesize'], 1, true);
				$attach['extension'] = strtolower(file_extension($attach['filename']));
				$attach['imgpath'] = "$stylevar[imgdir_attach]/$attach[extension].gif";
				$show['attachmentlist'] = true;
				eval('$attachments .= "' . fetch_template('newpost_attachmentbit') . '";');

				$attachment_js .= construct_attachment_add_js($attachmentid, $attach['filename'], $attach['filesize'], $attach['extension']);

				$attach_editor["$attachmentid"] = $attach['filename'];
			}
		}

		if (!$foruminfo['allowposting'] AND $attachcount == 0)
		{
			$attachmentoption = '';
			$attach_editor = array();
		}
		else
		{
			$attachurl = "p=$postinfo[postid]&amp;editpost=1";
			$newpost_attachmentbit = prepare_newpost_attachmentbit();
			eval('$attachmentoption = "' . fetch_template('newpost_attachment') . '";');

			$attach_editor['hash'] = $postinfo['postid'];
			$attach_editor['url'] = "newattachment.php?" . $vbulletin->session->vars['sessionurl'] . "p=$postinfo[postid]&amp;editpost=1&amp;poststarttime=$poststarttime&amp;posthash=$posthash";
		}
	}
	else
	{
		$attachmentoption = '';
	}

	$editorid = construct_edit_toolbar(
		$newpost['message'],
		0,
		$foruminfo['forumid'],
		iif($foruminfo['allowsmilies'], 1, 0),
		($postinfo['allowsmilie'] AND !$edit['disablesmilies']) ? 1 : 0,
		($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'] AND $postinfo['userid'] AND !empty($vbulletin->userinfo['attachmentextensions']))
	);

	if ($isfirstpost AND can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$show['deletepostoption'] = true;
	}
	else if (!$isfirstpost AND can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{
		$show['deletepostoption'] = true;
	}
	else if (((($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletepost']) AND !$isfirstpost) OR (($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletethread']) AND $isfirstpost)) AND $vbulletin->userinfo['userid'] == $postinfo['userid'])
	{
		$show['deletepostoption'] = true;
	}
	else
	{
		$show['deletepostoption'] = false;
	}

	if ($foruminfo['podcast'] AND $threadinfo['firstpostid'] == $postinfo['postid'])
	{
		$show['podcasturl'] = true;
		if ($edit['podcasturl'])
		{
			$podcasturl = htmlspecialchars_uni($edit['podcasturl']);
			$podcastsize = intval($edit['podcastsize']);
			$podcastkeywords = htmlspecialchars_uni($edit['podcastkeywords']);
			$podcastsubtitle = htmlspecialchars_uni($edit['podcastsubtitle']);
			$podcastauthor = htmlspecialchars_uni($edit['podcastauthor']);
			$explicitchecked = $edit['podcastexplicit'] ? 'checked="checked"' : '';
		}
		else
		{
			$podcastinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "podcastitem WHERE postid = $postinfo[postid]");
			$podcasturl = htmlspecialchars_uni($podcastinfo['url']);
			$podcastsize = ($podcastinfo['length']) ? $podcastinfo['length'] : '';
			$podcastkeywords = htmlspecialchars_uni($podcastinfo['keywords']);
			$podcastsubtitle = htmlspecialchars_uni($podcastinfo['subtitle']);
			$podcastauthor = htmlspecialchars_uni($podcastinfo['author']);
			$explicitchecked = $podcastinfo['explicit'] ? 'checked="checked"' : '';
		}
	}

	// can this user open / close this thread?
	if (($threadinfo['postuserid'] AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose']) OR can_moderate($threadinfo['forumid'], 'canopenclose'))
	{
		$show['openclose'] = true;
	}
	else
	{
		$show['openclose'] = false;
	}
	// can this user stick this thread?
	if (can_moderate($threadinfo['forumid'], 'canmanagethreads'))
	{
		$show['stickunstick'] = true;
	}
	else
	{
		$show['stickunstick'] = false;
	}
	if ($show['openclose'] OR $show['stickunstick'])
	{
		$show['closethread'] = iif($threadinfo['open'], true, false);
		$show['unstickthread'] = iif($threadinfo['sticky'], true, false);
		eval('$threadmanagement = "' . fetch_template('newpost_threadmanage') . '";');
	}
	else
	{
		$threadmanagement = '';
	}

	$show['physicaldeleteoption'] = iif (can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);
	$show['keepattachmentsoption'] = iif ($attachcount, true, false);
	$show['firstpostnote'] = $isfirstpost;

	construct_forum_rules($foruminfo, $forumperms);

	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// draw nav bar
	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle =& $vbulletin->forumcache["$forumID"]['title'];
		$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
	}
	$navbits['showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$postinfo[postid]#post$postinfo[postid]"] = $threadinfo['prefix_plain_html'] . ' ' . $threadinfo['title'];
	$navbits[''] = $vbphrase['edit_post'];
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	$show['parseurl'] = (($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) AND $foruminfo['allowbbcode']);
	$show['misc_options'] = ($vbulletin->userinfo['signature'] != '' OR $show['parseurl'] OR !empty($disablesmiliesoption));

	($hook = vBulletinHook::fetch_hook('editpost_edit_complete')) ? eval($hook) : false;

	$url =& $vbulletin->url;
	eval('print_output("' . fetch_template('editpost') . '");');

}

// ############################### start delete post ###############################
if ($_POST['do'] == 'deletepost')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'deletepost'      => TYPE_STR,
		'reason'          => TYPE_STR,
		'keepattachments' => TYPE_BOOL,
	));

	($hook = vBulletinHook::fetch_hook('editpost_delete_start')) ? eval($hook) : false;

	if (!can_moderate($threadinfo['forumid'], 'candeleteposts'))
	{	// Keep attachments for non moderator deletes (post owner)
		$vbulletin->GPC['keepattachments'] = true;
	}

	if ($vbulletin->GPC['deletepost'] != '')
	{
		//get first post in thread
		$getfirst = $db->query_first_slave("
			SELECT postid, dateline
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = $postinfo[threadid]
			ORDER BY dateline
			LIMIT 1
		");
		if ($getfirst['postid'] == $postinfo['postid'])
		{
			// delete thread
			if ($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletethread'] OR can_moderate($threadinfo['forumid'], 'canmanagethreads'))
			{
				if ($vbulletin->GPC['deletepost'] == 'remove' AND can_moderate($threadinfo['forumid'], 'canremoveposts'))
				{
					$removaltype = true;
				}
				else
				{
					$removaltype = false;
				}

				$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
				$threadman->set_existing($threadinfo);
				$threadman->delete($foruminfo['countposts'], $removaltype, array('userid' => $vbulletin->userinfo['userid'], 'username' => $vbulletin->userinfo['username'], 'reason' => $vbulletin->GPC['reason'], 'keepattachments' => $vbulletin->GPC['keepattachments']));
				unset($threadman);

				if ($foruminfo['lastthreadid'] != $threadinfo['threadid'])
				{
					// just decrement the reply and thread counter for the forum
					$forumdm =& datamanager_init('Forum', $vbulletin, ERRTYPE_SILENT);
					$forumdm->set_existing($foruminfo);
					$forumdm->set('threadcount', 'threadcount - 1', false);
					$forumdm->set('replycount', 'replycount - 1', false);
					$forumdm->save();
					unset($forumdm);
				}
				else
				{
					// this thread is the one being displayed as the thread with the last post...
					// so get a new thread to display.
					build_forum_counters($threadinfo['forumid']);
				}

				($hook = vBulletinHook::fetch_hook('editpost_delete_complete')) ? eval($hook) : false;

				$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$threadinfo[forumid]";
				eval(print_standard_redirect('redirect_deletethread'));
			}
			else
			{
				print_no_permission();
			}
		}
		else
		{
			//delete just this post
			if ($vbulletin->GPC['deletepost'] == 'remove' AND can_moderate($threadinfo['forumid'], 'canremoveposts'))
			{
				$removaltype = true;
			}
			else
			{
				$removaltype = false;
			}

			$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$postman->set_existing($postinfo);
			$postman->delete($foruminfo['countposts'], $threadinfo['threadid'], $removaltype, array('userid' => $vbulletin->userinfo['userid'], 'username' => $vbulletin->userinfo['username'], 'reason' => $vbulletin->GPC['reason'], 'keepattachments' => $vbulletin->GPC['keepattachments']));
			unset($postman);

			build_thread_counters($threadinfo['threadid']);

			if ($foruminfo['lastthreadid'] != $threadinfo['threadid'])
			{
				// just decrement the reply counter
				$forumdm =& datamanager_init('Forum', $vbulletin, ERRTYPE_SILENT);
				$forumdm->set_existing($foruminfo);
				$forumdm->set('replycount', 'replycount - 1', false);
				$forumdm->save();
				unset($forumdm);
			}
			else
			{
				// this thread is the one being displayed as the thread with the last post...
				// need to get the lastpost datestamp and lastposter name from the thread.
				build_forum_counters($threadinfo['forumid']);
			}

			($hook = vBulletinHook::fetch_hook('editpost_delete_complete')) ? eval($hook) : false;

			$url = unhtmlspecialchars($vbulletin->url);
			if (preg_match('/\?([^#]*)(#.*)?$/s', $url, $match))
			{
				parse_str($match[1], $parts);

				if ($parts['postid'] == $postinfo['postid'] OR $parts['p'] == $postinfo['postid'])
				{
					// we've deleted the post that we came into this thread from
					// blank the redirect as it will be set below
					$vbulletin->url = '';
				}
			}
			else if ($removaltype OR !can_moderate($threadinfo['forumid'], 'candeleteposts'))
			{
				// hard deleted or not moderating -> redirect back to the thread
				$vbulletin->url = '';
			}

			if (!stristr($vbulletin->url, 'showthread.php')) // no referring url?
			{
				$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . 't=' . $threadinfo['threadid'];
			}

			eval(print_standard_redirect('redirect_deletepost'));
		}
	}
	else
	{
		($hook = vBulletinHook::fetch_hook('editpost_delete_complete')) ? eval($hook) : false;

		$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$postinfo[postid]#post$postinfo[postid]";
		eval(print_standard_redirect('redirect_nodelete'));
	}
}

/*======================================================================*\
|| ####################################################################
|| #
|| # CVS: $RCSfile$ - $Revision: 39862 $
|| ####################################################################
\*======================================================================*/
?>
