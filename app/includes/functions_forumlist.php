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

// ###################### Start getimodcache #######################
function cache_moderators($userid = null)
{
	global $vbulletin, $imodcache, $mod;

	$imodcache = array();
	$mod = array();

	$forummoderators = $vbulletin->db->query_read_slave("
		SELECT moderator.*, user.username,
		IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid
		FROM " . TABLE_PREFIX . "moderator AS moderator
		INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		" . ($userid != null ? "WHERE moderator.userid = " . intval($userid) : "") . "
	");
	while ($moderator = $vbulletin->db->fetch_array($forummoderators))
	{
		fetch_musername($moderator);
		$imodcache["$moderator[forumid]"]["$moderator[userid]"] = $moderator;
		$mod["$moderator[userid]"] = 1;
	}
	$vbulletin->db->free_result($forummoderators);
}

// ###################### Start getlastpostinfo #######################
// this function creates a lastpostinfo array that tells makeforumbit which forum
// each forum should grab its last post info from.
// it also tots up the thread/post totals for each forum. - PERMISSIONS are taken into account.
function fetch_last_post_array($root_forumid = -1)
{
	global $vbulletin, $lastpostarray, $counters;

	$cannotView = array();
	$children = array();

	// loop through the vbulletin->iforumcache
	foreach ($vbulletin->iforumcache AS $moo)
	{
		foreach ($moo AS $forumid)
		{
			$forum = $vbulletin->forumcache["$forumid"];
			if (!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
			{
				if ($root_forumid == -1 OR !in_array($root_forumid, explode(',', $forum['childlist'])))
				{
					// only going to show below this part of the tree if we're already there
					$cannotView["$forumid"] = 1;
				}
				continue;
			}

			// if we have no permission to view the forum's parent
			// set cannotView permissions cache for this forum and continue
			if (!empty($cannotView["$forum[parentid]"]))
			{
				$cannotView["$forumid"] = 1;
			}
			else
			{

				$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];

				// can view and validated password OR show counters for private forums -> show post count
				if (
					($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'] AND (!$forum['password'] OR verify_forum_password($forum['forumid'], $forum['password'], false)))
					OR ($vbulletin->forumcache["$forumid"]['showprivate'] AND $vbulletin->forumcache["$forumid"]['showprivate'] == 3)
					OR (!$vbulletin->forumcache["$forumid"]['showprivate'] AND $vbulletin->options['showprivateforums'] == 2)
				)
				{
					if (!isset($lastpostarray["$forumid"]))
					{
						$lastpostarray["$forumid"] = $forumid;
					}
					$parents = explode(',', $forum['parentlist']);
					foreach ($parents AS $parentid)
					{
						// for each parent, set an array entry containing this forum's number of posts & threads
						$children["$parentid"]["$forumid"] = array('threads' => $forum['threadcount'], 'posts' => $forum['replycount']);

						if ($parentid == -1 OR !isset($vbulletin->forumcache["$parentid"]))
						{
							continue;
						}

						if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $vbulletin->forumcache["$forumid"]['lastposter'] != $vbulletin->userinfo['username'])
						{
							// can't view others threads in this forum and the last post isn't by us
							// so don't try to traverse up the tree
							continue;
						}

						// compare the date for the last post info with the last post date
						// for the parent forum, and if it's greater, set the last post info
						// array for this forum to point to that forum... (erm..)
						if ($forum['lastpost'] > $vbulletin->forumcache["$parentid"]['lastpost'])
						{
							$lastpostarray["$parentid"] = $forumid;
							$vbulletin->forumcache["$parentid"]['lastpost'] = $forum['lastpost'];
						}
					}
				}
				else
				{
					$cannotView["$forumid"] = 1;
				}

			} // end can view parent
		}
	}

	$counters = array();
	if (is_array($vbulletin->forumcache))
	{
		foreach($vbulletin->forumcache AS $forum)
		{
			$this_forum =& $counters["$forum[forumid]"];
			$this_forum['threadcount'] = 0;
			$this_forum['replycount'] = 0;
			if (is_array($children["$forum[forumid]"]))
			{
				foreach($children["$forum[forumid]"] AS $id => $info)
				{
					$this_forum['threadcount'] += $info['threads'];
					$this_forum['replycount'] += $info['posts'];
				}
			}
		}
	}
}

// ###################### Start makeforumbit #######################
// this function returns the properly-ordered and formatted forum lists for forumhome,
// forumdisplay and usercp. Of course, you could use it elsewhere too..
function construct_forum_bit($parentid, $depth = 0, $subsonly = 0)
{
	global $vbulletin, $stylevar, $vbphrase, $show;
	global $imodcache, $lastpostarray, $counters, $inforum;

	// this function takes the constant MAXFORUMDEPTH as its guide for how
	// deep to recurse down forum lists. if MAXFORUMDEPTH is not defined,
	// it will assume a depth of 2.

	// call fetch_last_post_array() first to get last post info for forums
	if (!is_array($lastpostarray))
	{
		fetch_last_post_array($parentid);
	}

	if (empty($vbulletin->iforumcache["$parentid"]))
	{
		return;
	}

	if (!defined('MAXFORUMDEPTH'))
	{
		define('MAXFORUMDEPTH', 2);
	}

	$forumbits = '';
	$depth++;

	$banners = ['LegendaryRescator_big.gif', 'b1ack_big.gif'];

	// Array to track if the banner has been added for each category (parentid == -1)
	$bannerFlags = [];

	foreach ($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
		// grab the appropriate forum from the $vbulletin->forumcache
		$forum = $vbulletin->forumcache["$forumid"];
		//$lastpostforum = $vbulletin->forumcache["$lastpostarray[$forumid]"];
		$lastpostforum = (empty($lastpostarray[$forumid]) ? array() : $vbulletin->forumcache["$lastpostarray[$forumid]"]);

		if (!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
		{
			continue;
		}

		$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];

		// Add check for categories and ensure they are wrapped in a table
		if ($forum['parentid'] == -1) {
			// Category - Start a new table for each category
			$forumbits .= '<table class="tborder" cellpadding="' . $stylevar['cellpadding'] . '" cellspacing="' . $stylevar['cellspacing'] . '" border="0" width="100%" align="center" style="margin-bottom: 15px;>';
			$forumbits .= '<thead>
			<tr align="center">
				<td class="thead">&nbsp;</td>
				<td class="thead"> ' . $vbphrase['forums'] . ' </td>
				<td class="thead" align="center"> ' . $vbphrase['last_post'] . ' </td>
				<td class="thead" align="center"> ' . $vbphrase['threads'] . ' </td>
				<td class="thead" align="center"> ' . $vbphrase['posts'] . ' </td>';

			if ($vboptions['showmoderatorcolumn']) {
				$forumbits .= '<td class="thead"> ' . $vbphrase['moderator'] . ' </td>';
			}

			$forumbits .= '</tr>
			</thead>';
			$forumbits .= '<tbody>';
		}
		// Now process child forums correctly
		if ($subsonly) {
			$childforumbits = construct_forum_bit($forum['forumid'], 1, $subsonly);
		} else if ($depth < MAXFORUMDEPTH) {
			$childforumbits = construct_forum_bit($forum['forumid'], $depth, $subsonly);
		} else {
			$childforumbits = '';
		}


		// Now handle displaying forums that belong under the category (parentid matches forumid)
		if ($forum['parentid'] != -1) {
			// Do not render this inside a category's table
			$childforumbits = construct_forum_bit($forum['forumid'], $depth, $subsonly);
			$forumbits .= $childforumbits; // Append this to the main forumbits
		}

		// do stuff if we are not doing subscriptions only, or if we ARE doing subscriptions,
		// and the forum has a subscribedforumid
		if (!$subsonly OR ($subsonly AND !empty($forum['subscribeforumid'])))
		{

			$GLOBALS['forumshown'] = true; // say that we have shown at least one forum

			if (($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']))
			{ // get appropriate suffix for template name
				$tempext = '_post';
			}
			else

			{
				$tempext = '_nopost';
			}

			if (!$vbulletin->options['showforumdescription'])
			{ // blank forum description if set to not show
				$forum['description'] = '';
			}

			// dates & thread title
			$lastpostinfo = (empty($lastpostarray[$forumid]) ? array() : $vbulletin->forumcache["$lastpostarray[$forumid]"]);

			// compare last post time for this forum with the last post time specified by
			// the $lastpostarray, and if it's less, use the last post info from the forum
			// specified by $lastpostarray
			if (!empty($lastpostinfo) AND $vbulletin->forumcache["$lastpostarray[$forumid]"]['lastpost'] > 0)
			{
				if (!($lastpostforumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR (!($lastpostforumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND $lastpostinfo['lastposter'] != $vbulletin->userinfo['username']))
				{
					$forum['lastpostinfo'] = $vbphrase['private'];
				}
				else
				{
					$lastpostinfo['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $lastpostinfo['lastpost'], 1);
					$lastpostinfo['lastposttime'] = vbdate($vbulletin->options['timeformat'], $lastpostinfo['lastpost']);
					$lastpostinfo['trimthread'] = fetch_trimmed_title(fetch_censored_text($lastpostinfo['lastthread']));

					if ($lastpostinfo['lastprefixid'] AND $vbulletin->options['showprefixlastpost'])
					{
						$lastpostinfo['prefix'] = ($vbulletin->options['showprefixlastpost'] == 2 ?
							$vbphrase["prefix_$lastpostinfo[lastprefixid]_title_rich"] :
							htmlspecialchars_uni($vbphrase["prefix_$lastpostinfo[lastprefixid]_title_plain"])
						);
					}
					else
					{
						$lastpostinfo['prefix'] = '';
					}

					if ($vbulletin->forumcache["$lastpostforum[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'] AND $icon = fetch_iconinfo($lastpostinfo['lasticonid']))
					{
						$show['icon'] = true;
					}
					else
					{
						$show['icon'] = false;
					}

					$show['lastpostinfo'] = (!$lastpostforum['password'] OR verify_forum_password($lastpostforum['forumid'], $lastpostforum['password'], false));

					eval('$forum[\'lastpostinfo\'] = "' . fetch_template('forumhome_lastpostby') . '";');
				}
			}
			else if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				$forum['lastpostinfo'] = $vbphrase['private'];
			}
			else
			{
				$forum['lastpostinfo'] = $vbphrase['never'];
			}

			// do light bulb
			$forum['statusicon'] = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);

			// add lock to lightbulb if necessary
			// from 3.6.9 & 3.7.0 we now show locks only if a user can not post AT ALL
			// previously it was just if they could not create new threads
			if (
				$vbulletin->options['showlocks'] // show locks to users who can't post
				AND !$forum['link'] // forum is not a link
				AND
				(
					!($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting']) // forum does not allow posting
					OR
					(
						    !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) // can't post new threads
						AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) // can't reply to own threads
						AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers']) // can't reply to others' threads
					)
				)
			)
			{
				$forum['statusicon'] .= '_lock';
			}

			// get counters from the counters cache ( prepared by fetch_last_post_array() )
			$forum['threadcount'] = $counters["$forum[forumid]"]['threadcount'];
			$forum['replycount'] = $counters["$forum[forumid]"]['replycount'];

			// get moderators ( this is why we needed cache_moderators() )
			if ($vbulletin->options['showmoderatorcolumn'])
			{
				$showmods = array();
				$listexploded = explode(',', $forum['parentlist']);
				foreach ($listexploded AS $parentforumid)
				{
					if (!isset($imodcache["$parentforumid"]) OR $parentforumid == -1)
					{
						continue;
					}
					foreach($imodcache["$parentforumid"] AS $moderator)
					{
						if (isset($showmods["$moderator[userid]"]))
						{
							continue;
						}

						($hook = vBulletinHook::fetch_hook('forumbit_moderator')) ? eval($hook) : false;

						$showmods["$moderator[userid]"] = true;

						$show['comma_leader'] = isset($forum['moderators']);
						if (!isset($forum['moderators']))
						{
							$forum['moderators'] = '';
						}

						eval('$forum[\'moderators\'] .= "' . fetch_template('forumhome_moderator') . '";');
					}
				}
				if (!isset($forum['moderators']))
				{
					$forum['moderators'] = '';
				}
			}

			if ($forum['link'])
			{
				$forum['replycount'] = '-';
				$forum['threadcount'] = '-';
				$forum['lastpostinfo'] = '-';
			}
			else
			{
				$forum['replycount'] = vb_number_format($forum['replycount']);
				$forum['threadcount'] = vb_number_format($forum['threadcount']);
			}

			if (($subsonly OR $depth == MAXFORUMDEPTH) AND $vbulletin->options['subforumdepth'] > 0)
			{
				$forum['subforums'] = construct_subforum_bit($forumid, ($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads'] ) );
			}
			else
			{
				$forum['subforums'] = '';
			}

			$forum['browsers'] = 0;
			$children = explode(',', $forum['childlist']);
			foreach($children AS $childid)
			{
				$forum['browsers'] += (isset($inforum["$childid"]) ? $inforum["$childid"] : 0);
			}

			if ($depth == 1 AND $tempext == '_nopost')
			{
				global $vbcollapse;
				$collapseobj_forumid =& $vbcollapse["collapseobj_forumbit_$forumid"];
				$collapseimg_forumid =& $vbcollapse["collapseimg_forumbit_$forumid"];
				$show['collapsebutton'] = true;
			}
			else
			{
				$show['collapsebutton'] = false;
			}

			$show['forumsubscription'] = ($subsonly ? true : false);
			$show['forumdescription'] = ($forum['description'] != '' ? true : false);
			$show['subforums'] = ($forum['subforums'] != '' ? true : false);
			$show['browsers'] = ($vbulletin->options['displayloggedin'] AND !$forum['link'] AND $forum['browsers'] ? true : false);

			// build the template for the current forum

			($hook = vBulletinHook::fetch_hook('forumbit_display')) ? eval($hook) : false;

			eval('$forumbits .= "' . fetch_template("forumhome_forumbit_level$depth$tempext") . '";');
			// Add subforums if any
			if ($subsonly OR $depth == MAXFORUMDEPTH) {
				$forum['subforums'] = construct_subforum_bit($forumid, ($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads'] ));
			}

			// End the table for each category
			if ($forum['parentid'] == -1) {
				$forumbits .= '</tbody></table>';
			}

			// Check if the forum is a category (parentid == -1)
			if ($forum['parentid'] == -1) {
				// Get all categories (top-level forums)
				$categoryForumIds = $vbulletin->iforumcache["-1"];
				// Get the ID of the last category
				$lastCategoryId = end($categoryForumIds);

				// Check if this is not the last category
				if ($forum['forumid'] != $lastCategoryId) {
					// Check if the banner has been added for this specific category
					if (!isset($bannerFlags[$forum['forumid']])) {
						// Randomly pick one banner for this category
						$randomBanner = $banners[array_rand($banners)];
						// Add the banner below the category's table
						$forumbits .= '<img id="banner" src="banners/' . $randomBanner . '" alt="⭐⭐⭐RESCATOR.CN - BEST and OLDEST CC CVV&amp;DUMP SHOP⭐⭐⭐" title="⭐⭐⭐RESCATOR.CN - BEST and OLDEST CC CVV&amp;DUMP SHOP⭐⭐⭐" style="border: 1px solid black; display: block; margin: 15px auto;">';
						// Mark the banner as added for this specific category
						$bannerFlags[$forum['forumid']] = true;
					}
				}
			}

			} // <-- closes foreach ($vbulletin->iforumcache...)
		} // <-- closes if parentid == -1
	return $forumbits;
	} // <-- closes foreach


// ###################### Start getforumlightbulb #######################
// returns 'on' or 'off' depending on last post info for a forum
function fetch_forum_lightbulb(&$forumid, &$lastpostinfo, &$foruminfo)
{

	global $bb_view_cache, $vbulletin;

	if (is_array($foruminfo) AND !empty($foruminfo['link']))
	{ // see if it is a redirect
		return 'link';
	}
	else
	{
		if ($vbulletin->userinfo['lastvisitdate'] == -1)
		{
			return 'new';
		}
		else
		{
			if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
			{
				$userlastvisit = (!empty($foruminfo['forumread']) ? $foruminfo['forumread'] : (TIMENOW - ($vbulletin->options['markinglimit'] * 86400)));
			}
			else
			{
				$forumview = intval(fetch_bbarray_cookie('forum_view', $foruminfo['forumid']));

				//use which one produces the highest value, most likely cookie
				$userlastvisit = ($forumview > $vbulletin->userinfo['lastvisit'] ? $forumview : $vbulletin->userinfo['lastvisit']);
			}

			if ($lastpostinfo['lastpost'] AND $userlastvisit < $lastpostinfo['lastpost'])
			{
				return 'new';
			}
			else
			{
				return 'old';
			}
		}
	}
}

// ###################### Start makesubforumbit #######################
// gets a list of a forum's children and returns it
// based on the forumhome_subforumbit template
function construct_subforum_bit($parentid, $cancontainthreads, $output = '', $depthmark = '--', $depth = 0)
{
	global $vbulletin, $stylevar, $vbphrase;
	global $lastpostinfo, $lastpostarray;
	static $splitter;

	if ($cancontainthreads)
	{
		$canpost = 'post';
	}
	else
	{
		$canpost = 'nopost';
	}

	// get the splitter template
	if (!isset($splitter["$canpost"]))
	{
		eval('$splitter[$canpost] = "' . fetch_template("forumhome_subforumseparator_$canpost") . '";');
	}

	if (!isset($vbulletin->iforumcache["$parentid"]))
	{
		return $output;
	}

	foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
		$forum = $vbulletin->forumcache["$forumid"];
		$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($vbulletin->forumcache["$forumid"]['showprivate'] == 1 OR (!$vbulletin->forumcache["$forumid"]['showprivate'] AND !$vbulletin->options['showprivateforums'])))
		{ // no permission to view current forum
			continue;
		}

		if (!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
		{
			// forum not active
			continue;
		}
		else
		{ // get on/off status
			$lastpostinfo = $vbulletin->forumcache["$lastpostarray[$forumid]"];
			$forum['statusicon'] = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);
			$show['newposticon'] = ($forum['statusicon'] ? true : false);

			($hook = vBulletinHook::fetch_hook('forumbit_subforumbit')) ? eval($hook) : false;

			eval('$subforum = "' . fetch_template("forumhome_subforumbit_$canpost") . '";');
			if (!empty($output))
			{
				$subforum = $splitter["$canpost"] . $subforum;
			}
			if ($depth < $vbulletin->options['subforumdepth'])
			{
				$output .= construct_subforum_bit($forumid, $cancontainthreads, $subforum, $depthmark . '--', $depth + 1);
			}

			($hook = vBulletinHook::fetch_hook('forumbit_subforumbit2')) ? eval($hook) : false;
		}
	}

	return $output;

}

// ###################### Start geticoninfo #######################
function fetch_iconinfo($iconid = 0)
{
	global $vbulletin, $stylevar, $vbphrase, $vbulletin;

	$iconid = intval($iconid);

	switch($iconid)
	{
		case -1:
			DEVDEBUG('returning poll icon');
			return array('iconpath' => "$stylevar[imgdir_misc]/poll_posticon.gif", 'title' => $vbphrase['poll']);
		case 0:
			if (!empty($vbulletin->options['showdeficon']))
			{
				DEVDEBUG("returning default icon");
				return array('iconpath' => $vbulletin->options['showdeficon'], 'title' => '');
			}
			else
			{
				return false;
			}
		default:
			if ($vbulletin->iconcache !== null)
			{ // we can get the icon info from the template cache

				DEVDEBUG("returning iconid:$iconid from the template cache");
				return $vbulletin->iconcache["$iconid"];
			}
			else
			{ // we have to get the icon from a query
				DEVDEBUG("QUERYING iconid:$iconid)");
				return $vbulletin->db->query_first_slave("
					SELECT title, iconpath
					FROM " . TABLE_PREFIX . "icon
					WHERE iconid = $iconid
				");
			}
	}
}

/*======================================================================*\
|| ####################################################################
|| #
|| # CVS: $RCSfile$ - $Revision: 39862 $
|| ####################################################################
\*======================================================================*/
?>
