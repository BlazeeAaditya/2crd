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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);
@set_time_limit(0);
ignore_user_abort(true);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 39862 $');
if ($_POST['do'] == 'updatetemplate' OR $_POST['do'] == 'inserttemplate' OR $_REQUEST['do'] == 'createfiles')
{
	// double output buffering does some weird things, so turn it off in these three cases
	DEFINE('NOZIP', 1);
}

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('style');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_template.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminstyles'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'templateid'   => TYPE_INT,
	'dostyleid'    => TYPE_INT,
));

// ############################# LOG ACTION ###############################
log_admin_action(!empty($vbulletin->GPC['templateid']) ? 'template id = ' . $vbulletin->GPC['templateid'] : !empty($vbulletin->GPC['dostyleid']) ? 'style id = ' . $vbulletin->GPC['dostyleid'] : '');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}
else
{
	$nozipDos = array('inserttemplate', 'rebuild', 'kill', 'insertstyle', 'killstyle', 'updatestyle');
	if (in_array($_REQUEST['do'], $nozipDos))
	{
		$vbulletin->nozip = true;
	}
}

$full_product_info = fetch_product_list(true);

if ($_REQUEST['do'] != 'download')
{
	print_cp_header($vbphrase['style_manager'], iif($_REQUEST['do'] == 'files', 'js_fetch_style_title()'));
	?><script type="text/javascript" src="../clientscript/vbulletin_templatemgr.js"></script><?php
}

// #############################################################################
// find custom templates that need updating

if ($_REQUEST['do'] == 'findupdates')
{
	// query custom templates
	$customcache = array();
	$templates = $db->query_read("
		SELECT tCustom.templateid, tCustom.title, tCustom.styleid,
			tCustom.username AS customuser, tCustom.dateline AS customdate, tCustom.version AS customversion,
			tGlobal.username AS globaluser, tGlobal.dateline AS globaldate, tGlobal.version AS globalversion,
			tGlobal.product
		FROM " . TABLE_PREFIX . "template AS tCustom
		INNER JOIN " . TABLE_PREFIX . "template AS tGlobal ON (tGlobal.styleid = -1 AND tGlobal.title = tCustom.title)
		WHERE tCustom.styleid <> -1
			AND tCustom.templatetype = 'template'
		ORDER BY tCustom.title
	");
	while($template = $db->fetch_array($templates))
	{
		if (!$template['product'])
		{
			$template['product'] = 'vbulletin';
		}

		$product_version = $full_product_info["$template[product]"]['version'];

		// version in the template is newer than the version of the product,
		// which probably means it's using the vB version
		if (is_newer_version($template['globalversion'], $product_version))
		{
			$template['globalversion'] = $product_version;
		}
		if (is_newer_version($template['customversion'], $product_version))
		{
			$template['customversion'] = $product_version;
		}

		if (is_newer_version($template['globalversion'], $template['customversion']))
		{
			$customcache["$template[styleid]"]["$template[templateid]"] = $template;
		}
	}

	if (empty($customcache))
	{
		print_stop_message('all_templates_are_up_to_date');
	}

	cache_styles();

	print_form_header('', '');
	print_table_header($vbphrase['updated_default_templates']);
	print_description_row('<span class="smallfont">' . construct_phrase($vbphrase['updated_default_templates_desc'], $vbulletin->options['templateversion']) . '</span>');
	print_table_break(' ');

	foreach($stylecache AS $styleid => $style)
	{
		if (is_array($customcache["$styleid"]))
		{
			print_description_row($style['title'], 0, 2, 'thead');
			foreach($customcache["$styleid"] AS $templateid => $template)
			{
				if (!$template['customuser'])
				{
					$template['customuser'] = $vbphrase['n_a'];
				}
				if (!$template['customversion'])
				{
					$template['customversion'] = $vbphrase['n_a'];
				}

				$product_name = $full_product_info["$template[product]"]['title'];

				print_label_row("
					<b>$template[title]</b><br />
					<span class=\"smallfont\">" .
						construct_phrase($vbphrase['default_template_updated_desc'],
							"$product_name $template[globalversion]",
							$template['globaluser'],
							"$product_name $template[customversion]",
							$template['customuser'])
					. '</span>',
				'<span class="smallfont">' .
					construct_link_code($vbphrase['edit_template'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;templateid=$templateid", 'templatewin') . '<br />' .
					construct_link_code($vbphrase['view_history'], 'template.php?do=history&amp;dostyleid=' . $styleid . '&amp;title=' . urlencode($template['title']), 'templatewin') . '<br />' .
					construct_link_code($vbphrase['revert'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=delete&amp;templateid=$templateid&amp;dostyleid=$styleid", 'templatewin') .
				'</span>'
				);
			}
		}
	}
	print_table_footer();

}

// #############################################################################
// download style

if ($_REQUEST['do'] == 'download')
{

	if (function_exists('set_time_limit') AND !SAFEMODE)
	{
		@set_time_limit(1200);
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'filename' => TYPE_STR,
		'title'    => TYPE_NOHTML,
		'mode'     => TYPE_BOOL,
		'product'  => TYPE_STR
	));

	// --------------------------------------------
	// work out what we are supposed to do

	// set a default filename
	if (empty($vbulletin->GPC['filename']))
	{
		$vbulletin->GPC['filename'] = 'vbulletin-style.xml';
	}

	if ($vbulletin->GPC['dostyleid'] == -1)
	{
		// set the style title as 'master style'
		$style = array('title' => $vbphrase['master_style']);

		$sqlcondition = "styleid = -1";
	}
	else
	{
		// query everything from the specified style
		$style = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']);

		if ($vbulletin->GPC['mode'] == 1)
		{
			// get all items from this style and all parent styles (except master)
			$sqlcondition = "styleid <> -1 AND templateid IN(" . implode(',', unserialize($style['templatelist'])) . ")";
		}
		else
		{
			// get only items customized in THIS style
			$sqlcondition = "styleid = " . $vbulletin->GPC['dostyleid'];
		}
	}

	if ($vbulletin->GPC['product'] == 'vbulletin')
	{
		$sqlcondition .= " AND (product = '" . $db->escape_string($vbulletin->GPC['product']) . "' OR product = '')";
	}
	else
	{
		$sqlcondition .= " AND product = '" . $db->escape_string($vbulletin->GPC['product']) . "'";
	}

	// set a default title
	if ($vbulletin->GPC['title'] == '' OR $vbulletin->GPC['dostyleid'] == -1)
	{
		$vbulletin->GPC['title'] = $style['title'];
	}

	// --------------------------------------------
	// query the templates and put them in an array

	$templates = array();

	$gettemplates = $db->query_read("
		SELECT title, templatetype, username, dateline, version,
		IF(templatetype = 'template', template_un, template) AS template
		FROM " . TABLE_PREFIX . "template
		WHERE $sqlcondition
		ORDER BY title
	");
	while ($gettemplate = $db->fetch_array($gettemplates))
	{
		switch($gettemplate['templatetype'])
		{
			case 'template': // regular template
				$isgrouped = false;
				foreach(array_keys($only) AS $group)
				{
					if (strpos(strtolower(" $gettemplate[title]"), $group) == 1)
					{
						$templates["$group"][] = $gettemplate;
						$isgrouped = true;
					}
				}
				if (!$isgrouped)
				{
					$templates['zzz'][] = $gettemplate;
				}
			break;

			case 'stylevar': // stylevar
				$templates['StyleVar Special Templates'][] = $gettemplate;
			break;

			case 'css': // css
				$templates['CSS Special Templates'][] = $gettemplate;
			break;

			case 'replacement': // replacement
				$templates['Replacement Var Special Templates'][] = $gettemplate;
			break;
		}
	}
	unset($template);
	$db->free_result($gettemplates);

	if (empty($templates))
	{
		print_stop_message('download_contains_no_customizations');
	}

	ksort($templates);

	$only['zzz'] = 'Ungrouped Templates';

	// --------------------------------------------
	// now output the XML

	require_once(DIR . '/includes/class_xml.php');
	$xml = new vB_XML_Builder($vbulletin);
	$xml->add_group('style', array('name' => $vbulletin->GPC['title'], 'vbversion' => $full_product_info[$vbulletin->GPC['product']]['version'], 'product' => $vbulletin->GPC['product'], 'type' => iif($vbulletin->GPC['dostyleid'] == -1, 'master', 'custom')));

	foreach($templates AS $group => $grouptemplates)
	{
		$xml->add_group('templategroup', array('name' => iif(isset($only["$group"]), $only["$group"], $group)));
		foreach($grouptemplates AS $template)
		{
			$xml->add_tag('template', $template['template'], array('name' => htmlspecialchars($template['title']), 'templatetype' => $template['templatetype'], 'date' => $template['dateline'], 'username' => $template['username'], 'version' => htmlspecialchars_uni($template['version'])), true);
		}
		$xml->close_group();
	}

	$xml->close_group();

	$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";

	$doc .= $xml->output();
	$xml = null;

	require_once(DIR . '/includes/functions_file.php');
	file_download($doc, $vbulletin->GPC['filename'], 'text/xml');

}

// #############################################################################
// upload style

if ($_POST['do'] == 'upload')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'overwritestyleid' => TYPE_INT,
		'serverfile'       => TYPE_STR,
		'parentid'         => TYPE_INT,
		'title'            => TYPE_STR,
		'anyversion'       => TYPE_BOOL,
		'displayorder'     => TYPE_INT,
		'userselect'       => TYPE_BOOL,
	));

	$vbulletin->input->clean_array_gpc('f', array(
		'stylefile'        => TYPE_FILE,
	));

	// got an uploaded file?
	if (file_exists($vbulletin->GPC['stylefile']['tmp_name']))
	{
		$xml = file_read($vbulletin->GPC['stylefile']['tmp_name']);
	}
	// no uploaded file - got a local file?
	else if (file_exists($vbulletin->GPC['serverfile']))
	{
		$xml = file_read($vbulletin->GPC['serverfile']);
	}
	// no uploaded file and no local file - ERROR
	else
	{
		print_stop_message('no_file_uploaded_and_no_local_file_found');
	}

	xml_import_style($xml, $vbulletin->GPC['overwritestyleid'], $vbulletin->GPC['parentid'], $vbulletin->GPC['title'], $vbulletin->GPC['anyversion'], $vbulletin->GPC['displayorder'], $vbulletin->GPC['userselect']);

	print_cp_redirect("template.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuild", 0);

}

// #############################################################################
// file manager
if ($_REQUEST['do'] == 'files')
{

	cache_styles();
	?>
	<script type="text/javascript">
	<!--
	function js_confirm_upload(tform, filefield)
	{
		if (filefield.value == "")
		{
			return confirm("<?php echo construct_phrase($vbphrase['you_did_not_specify_a_file_to_upload'], '" + tform.serverfile.value + "'); ?>");
		}
		return true;
	}
	function js_fetch_style_title()
	{
		styleid = document.forms.downloadform.dostyleid.options[document.forms.downloadform.dostyleid.selectedIndex].value;
		document.forms.downloadform.title.value = style[styleid];
	}
	var style = new Array();
	style['-1'] = "<?php echo $vbphrase['master_style'] . '";';
	foreach($stylecache AS $styleid => $style)
	{
		echo "\n\tstyle['$styleid'] = \"" . addslashes_js($style['title'], '"') . "\";";
		$styleoptions["$styleid"] = construct_depth_mark($style['depth'], '--', iif($vbulletin->debug, '--', '')) . ' ' . $style['title'];
	}
	echo "\n";
	?>
	// -->
	</script>
	<?php

	print_form_header('template', 'download', 0, 1, 'downloadform" target="download');
	print_table_header($vbphrase['download']);
	print_label_row($vbphrase['style'], '
		<select name="dostyleid" onchange="js_fetch_style_title();" tabindex="1" class="bginput">
		' . iif($vbulletin->debug, '<option value="-1">' . $vbphrase['master_style'] . '</option>') . '
		' . construct_select_options($styleoptions, $vbulletin->GPC['dostyleid']) . '
		</select>
	', '', 'top', 'dostyleid');
	print_select_row($vbphrase['product'], 'product', fetch_product_list());
	print_input_row($vbphrase['title'], 'title');
	print_input_row($vbphrase['filename'], 'filename', 'vbulletin-style.xml');
	print_label_row($vbphrase['options'], '
		<span class="smallfont">
		<label for="rb_mode_0"><input type="radio" name="mode" value="0" id="rb_mode_0" tabindex="1" checked="checked" />' . $vbphrase['get_customizations_from_this_style_only'] . '</label><br />
		<label for="rb_mode_1"><input type="radio" name="mode" value="1" id="rb_mode_1" tabindex="1" />' . $vbphrase['get_customizations_from_parent_styles'] . '</label>
		</span>
	', '', 'top', 'mode');
	print_submit_row($vbphrase['download']);

	print_form_header('template', 'upload', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.stylefile);');
	print_table_header($vbphrase['import_style_xml_file']);
	print_upload_row($vbphrase['upload_xml_file'], 'stylefile', 999999999);
	print_input_row($vbphrase['import_xml_file'], 'serverfile', './install/vbulletin-style.xml');
	print_style_chooser_row('overwritestyleid', -1, '(' . $vbphrase['create_new_style'] . ')', $vbphrase['overwrite_style'], 1);
	print_yes_no_row($vbphrase['ignore_style_version'], 'anyversion', 0);
	print_description_row($vbphrase['following_options_apply_only_if_new_style'], 0, 2, 'thead" style="font-weight:normal; text-align:center');
	print_input_row($vbphrase['title_for_uploaded_style'], 'title');
	print_style_chooser_row('parentid', -1, $vbphrase['no_parent_style'], $vbphrase['parent_style'], 1);
	print_input_row($vbphrase['display_order'], 'displayorder', 1);
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', 1);

	print_submit_row($vbphrase['import']);

}

// #############################################################################
// find & replace
if ($_POST['do'] == 'replace')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'startat_template' => TYPE_INT,
		'startat_style'    => TYPE_INT,
		'requirerebuild'   => TYPE_BOOL,
		'test'             => TYPE_BOOL,
		'regex'            => TYPE_BOOL,
		'case_insensitive' => TYPE_BOOL,
		'searchstring'     => TYPE_NOTRIM,
		'replacestring'    => TYPE_NOTRIM,
	));

	$perpage = 50;
	$vbulletin->GPC['searchstring'] = str_replace(chr(0), '', $vbulletin->GPC['searchstring']);

	if (empty($vbulletin->GPC['searchstring']))
	{
		print_stop_message('please_complete_required_fields');
	}

	$editmaster = false;
	$limit_style = $vbulletin->GPC['startat_style'];
	if ($vbulletin->GPC['dostyleid'] == -1)
	{
		$conds = 'AND styleid ' . iif($vbulletin->debug, '<> -2', '> 0');
		if ($vbulletin->debug)
		{
			if ($vbulletin->GPC['startat_style'] == 0)
			{
				$editmaster = true;
			}
			else
			{
				$limit_style--; // since 0 means the master style, we have to renormalize
			}
		}
	}
	else
	{
		$conds = "AND styleid = " . $vbulletin->GPC['dostyleid'];
	}

	if ($editmaster != true)
	{
		$styleinfo = $db->query_first("SELECT styleid, title, templatelist FROM " . TABLE_PREFIX . "style WHERE 1=1 $conds LIMIT $limit_style, 1");
		if (!$styleinfo)
		{
			// couldn't grab a style, so we're done -- rebuild styles if necessary
			if ($vbulletin->GPC['requirerebuild'])
			{
				build_all_styles(0, 0, "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=search");
				print_cp_footer();
				exit;
			}
			else
			{
				define('CP_REDIRECT', 'template.php?do=search');
				print_stop_message('completed_search_successfully');
			}
		}
		$templatelist = unserialize($styleinfo['templatelist']);
	}
	else
	{
		$styleinfo = array(
			'styleid' => -1,
			'title' => 'MASTER STYLE'
		);
		$templatelist = array();

		$tids = $db->query_read("SELECT title, templateid FROM " . TABLE_PREFIX . "template WHERE styleid = -1");
		while ($tid = $db->fetch_array($tids))
		{
			$templatelist["$tid[title]"] = $tid['templateid'];
		}
		$styleinfo['templatelist'] = serialize($templatelist); // for sanity
	}
	echo "<p><b>" . construct_phrase($vbphrase['search_in_x'], "<i>$styleinfo[title]</i>") . "</b></p>\n";

	$loopend = $vbulletin->GPC['startat_template'] + $perpage;
	$process_templates = array(0);
	$i = 0;

	foreach ($templatelist AS $title => $tid)
	{
		if ($i >= $vbulletin->GPC['startat_template'] AND $i < $loopend)
		{
			$process_templates[] = $tid;
		}
		if ($i >= $loopend)
		{
			break;
		}
		$i++;
	}
	if ($i != $loopend)
	{
		// didn't get the $perpage templates, so we're done with this style
		$styledone = true;
	}
	else
	{
		$styledone = false;
	}

	$templates = $db->query_read("
		SELECT templateid, styleid, title, template_un, product
		FROM " . TABLE_PREFIX . "template
		WHERE templateid IN (" . implode(', ', $process_templates) . ")
	");

	$page = $vbulletin->GPC['startat_template'] / $perpage + 1;
	$first = $vbulletin->GPC['startat_template'] + 1;
	$last = $vbulletin->GPC['startat_template'] + $db->num_rows($templates);

	echo "<p><b>$vbphrase[search_results]</b><br />$vbphrase[page] $page, $vbphrase[templates] $first - $last</p>" . iif($vbulletin->GPC['test'], "<p><i>$vbphrase[test_replace_only]</i></p>") . "\n";
	if ($vbulletin->GPC['regex'])
	{
		echo "<p span=\"smallfont\"><b>" . $vbphrase['regular_expression_used'] . ":</b> " . htmlspecialchars_uni("#" . $vbulletin->GPC['searchstring'] . "#siU") . "</p>\n";
	}
	echo "<ol class=\"smallfont\" start=\"$first\">\n";

	while ($temp = $db->fetch_array($templates))
	{
		echo "<li><a href=\"template.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;templateid=$temp[templateid]&amp;dostyleid=$temp[styleid]\">$temp[title]</a>\n";
		vbflush();

		$insensitive_mod = ($vbulletin->GPC['case_insensitive'] ? 'i' : '');

		if ($vbulletin->GPC['test'])
		{
			if ($vbulletin->GPC['regex'])
			{
				$encodedsearchstr = str_replace('(?&lt;', '(?<', htmlspecialchars_uni($vbulletin->GPC['searchstring']));
			}
			else
			{
				$encodedsearchstr = preg_quote(htmlspecialchars_uni($vbulletin->GPC['searchstring']), '#');
			}
			$newtemplate = preg_replace("#$encodedsearchstr#sU$insensitive_mod", '<span class="col-i" style="text-decoration:underline;">' . htmlspecialchars_uni($vbulletin->GPC['replacestring']) . '</span>', htmlspecialchars_uni($temp['template_un']));

			if ($newtemplate != htmlspecialchars_uni($temp['template_un']))
			{
				echo "<hr />\n<font size=\"+1\"><b>$temp[title]</b></font> (templateid: $temp[templateid], styleid: $temp[styleid])\n<pre class=\"smallfont\">" . str_replace("\t", " &nbsp; &nbsp; ", $newtemplate) . "</pre><hr />\n</li>\n";
			}
			else
			{
				echo ' (' . $vbphrase['0_matches_found'] . ")</li>\n";
			}
		}
		else
		{
			if ($vbulletin->GPC['regex'])
			{
				$newtemplate = preg_replace("#" . $vbulletin->GPC['searchstring'] . "#sU$insensitive_mod", $vbulletin->GPC['replacestring'], $temp['template_un']);
			}
			else
			{
				$usedstr = preg_quote($vbulletin->GPC['searchstring'], '#');
				$newtemplate = preg_replace("#$usedstr#sU$insensitive_mod", $vbulletin->GPC['replacestring'], $temp['template_un']);
			}

			if ($newtemplate != $temp['template_un'])
			{
				if ($temp['styleid'] == $styleinfo['styleid'])
				{
					$db->query_write("
						UPDATE " . TABLE_PREFIX . "template SET
							template = '" . $db->escape_string(compile_template($newtemplate)) . "',
							template_un = '" . $db->escape_string($newtemplate) . "',
							dateline = " . TIMENOW . ",
							username = '" . $db->escape_string($vbulletin->userinfo['username']) . "',
							version = '" . $db->escape_string($full_product_info["$temp[product]"]['version']) . "'
						WHERE templateid = $temp[templateid]
					");
				}
				else
				{
					/*insert query*/
					$db->query_write("
						INSERT INTO " . TABLE_PREFIX . "template
							(styleid, title, template, template_un, dateline, username, version, product)
						VALUES
							($styleinfo[styleid],
							 '" . $db->escape_string($temp['title']) . "',
							 '" . $db->escape_string(compile_template($newtemplate)) . "',
							 '" . $db->escape_string($newtemplate) . "',
							 " . TIMENOW . ",
							 '" . $db->escape_string($vbulletin->userinfo['username']) . "',
							 '" . $db->escape_string($full_product_info["$temp[product]"]['version']) . "',
							 '" . $db->escape_string($temp['product']) . "')
					");
					$vbulletin->GPC['requirerebuild'] = true;
				}
				echo "<span class=\"col-i\"><b>" . $vbphrase['done'] . "</b></span></li>\n";
			}
			else
			{
				echo ' (' . $vbphrase['0_matches_found'] . ")</li>\n";
			}
		}
		vbflush();
	}
	echo "</ol>\n";

	if ($styledone == true)
	{
		// Go to the next style. If we're only doing replacements in one style,
		// this will trigger the finished message.
		$vbulletin->GPC['startat_style']++;
		$loopend = 0;
	}

	print_form_header('template', 'replace', false, false);
		construct_hidden_code('regex', $vbulletin->GPC['regex']);
		construct_hidden_code('case_insensitive', $vbulletin->GPC['case_insensitive']);
		construct_hidden_code('requirerebuild', $vbulletin->GPC['requirerebuild']);
		construct_hidden_code('test', $vbulletin->GPC['test']);
		construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
		construct_hidden_code('startat_template', $loopend);
		construct_hidden_code('startat_style', $vbulletin->GPC['startat_style']);
		construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
		construct_hidden_code('replacestring', $vbulletin->GPC['replacestring']);
		echo "<input type=\"submit\" class=\"button\" tabindex=\"1\" value=\"$vbphrase[next_page]\" accesskey=\"s\" />";
	print_table_footer();

	print_cp_footer();
}

// #############################################################################
// form for search / find & replace
if ($_REQUEST['do'] == 'search')
{

	// search only
	print_form_header('template', 'modify', false, true, 'sform', '90%', '', true, 'get');
	print_table_header($vbphrase['search_templates']);
	print_style_chooser_row("searchset", $vbulletin->GPC['dostyleid'], $vbphrase['search_in_all_styles'] . iif($vbulletin->debug, ' (' . $vbphrase['including_master_style'] . ')'), $vbphrase['search_in_style'], 1);
	print_textarea_row($vbphrase['search_for_text'], "searchstring");
	print_yes_no_row($vbphrase['search_titles_only'], "titlesonly", 0);
	print_submit_row($vbphrase['find']);

	// search & replace
	print_form_header('template', 'replace', 0, 1, 'srform');
	print_table_header($vbphrase['find_and_replace_in_templates']);
	print_style_chooser_row("dostyleid", $vbulletin->GPC['dostyleid'], $vbphrase['search_in_all_styles'] .  iif($vbulletin->debug, ' (' . $vbphrase['including_master_style'] . ')'), $vbphrase['search_in_style'], 1);
	print_textarea_row($vbphrase['search_for_text'], 'searchstring', '', 5, 60, 1, 0);
	print_textarea_row($vbphrase['replace_with_text'], 'replacestring', '', 5, 60, 1, 0);
	print_yes_no_row($vbphrase['test_replace_only'], 'test', 1);
	print_yes_no_row($vbphrase['use_regular_expressions'], 'regex', 0);
	print_yes_no_row($vbphrase['case_insensitive'], 'case_insensitive', 0);
	print_submit_row($vbphrase['find']);

	print_form_header('', '', 0, 1, 'regexform');
	print_table_header($vbphrase['notes_for_using_regex_in_find_replace']);
	print_description_row($vbphrase['regex_help']);
	print_table_footer(2, $vbphrase['strongly_recommend_testing_regex_replace']);

}

// #############################################################################
// query to insert a new style
// $dostyleid then gets passed to 'updatestyle' for cache and template list rebuild
if ($_POST['do'] == 'insertstyle')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title'        => TYPE_STR,
		'displayorder' => TYPE_INT,
	));

	if (!$vbulletin->GPC['title'])
	{
		print_stop_message('please_complete_required_fields');
	}

	/*insert query*/
	$insert = $db->query_write("
		INSERT INTO " . TABLE_PREFIX . "style
		(title)
		VALUES
		('" . $db->escape_string($vbulletin->GPC['title']) . "')
	");

	if ($vbulletin->GPC['displayorder'] == 0)
	{
		$vbulletin->GPC['displayorder'] = 1;
	}

	$vbulletin->GPC['dostyleid'] = $db->insert_id($insert);
	$_POST['do'] = 'updatestyle';

}

// #############################################################################
// form to create a new style
if ($_REQUEST['do'] == 'addstyle')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'parentid' => TYPE_INT,
	));

	cache_styles();

	if ($vbulletin->GPC['parentid'] > 0 AND is_array($stylecache["{$vbulletin->GPC['parentid']}"]))
	{
		$title = construct_phrase($vbphrase['child_of_x'], $stylecache["{$vbulletin->GPC['parentid']}"]['title']);
	}

	print_form_header('template', 'insertstyle');
	print_table_header($vbphrase['add_new_style']);
	print_style_chooser_row('parentid', $vbulletin->GPC['parentid'], $vbphrase['no_parent_style'], $vbphrase['parent_style'], 1);
	print_input_row($vbphrase['title'], 'title', $title);
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', 1);
	print_input_row($vbphrase['display_order'], 'displayorder');

	($hook = vBulletinHook::fetch_hook('admin_style_form')) ? eval($hook) : false;

	print_submit_row($vbphrase['save']);

}

// #############################################################################
// query to update a style
// also rebuilds parent lists and template id cache if parentid is altered
if ($_POST['do'] == 'updatestyle')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'parentid'     => TYPE_INT,
		'oldparentid'  => TYPE_INT,
		'userselect'   => TYPE_INT,
		'displayorder' => TYPE_UINT,
		'title'        => TYPE_STR,
		'group'        => TYPE_STR
	));

	if (!$vbulletin->GPC['title'])
	{
		print_stop_message('please_complete_required_fields');
	}

	// SANITY CHECK (prevent invalid nesting)
	if ($vbulletin->GPC['parentid'] == $vbulletin->GPC['dostyleid'])
	{
		print_stop_message('cant_parent_style_to_self');
	}
	$ts_info = $db->query_first("
		SELECT styleid, title, parentlist
		FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['parentid'] . "
	");
	$parents = explode(',', $ts_info['parentlist']);
	foreach($parents AS $childid)
	{
		if ($childid == $vbulletin->GPC['dostyleid'])
		{
			print_stop_message('cant_parent_x_to_child');
		}
	}
	// end Sanity check

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "style
		SET title = '" . $db->escape_string($vbulletin->GPC['title']) . "',
		parentid = " . $vbulletin->GPC['parentid'] . ",
		userselect = " . $vbulletin->GPC['userselect'] . ",
		displayorder = " . $vbulletin->GPC['displayorder'] . "
		WHERE styleid = " . $vbulletin->GPC['dostyleid'] . "
	");

	($hook = vBulletinHook::fetch_hook('admin_style_save')) ? eval($hook) : false;

	build_style_datastore();

	if ($vbulletin->GPC['parentid'] != $vbulletin->GPC['oldparentid'])
	{
		build_template_parentlists();
		print_rebuild_style($vbulletin->GPC['dostyleid'], $vbulletin->GPC['title'], 1, 1, 1, 1);
		print_cp_redirect("template.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&expandset=" . $vbulletin->GPC['dostyleid'] . "&modify&group=" . $vbulletin->GPC['group'], 1);
	}
	else
	{
		define('CP_REDIRECT', "template.php?do=modify&expandset=" . $vbulletin->GPC['dostyleid'] . "&modify&group=" . $vbulletin->GPC['group']);
		print_stop_message('saved_style_x_successfully', $vbulletin->GPC['title']);
	}


}

// #############################################################################
// form to edit a style
if ($_REQUEST['do'] == 'editstyle')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'dostyleid' => TYPE_INT,
	));

	$style = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']);

	print_form_header('template', 'updatestyle');
	construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
	construct_hidden_code('oldparentid', $style['parentid']);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['style'], $style['title'], $style['styleid']), 2, 0);
	print_style_chooser_row('parentid', $style['parentid'], $vbphrase['no_parent_style'], $vbphrase['parent_style'], 1);
	print_input_row($vbphrase['title'], 'title', $style['title']);
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', $style['userselect']);
	print_input_row($vbphrase['display_order'], 'displayorder', $style['displayorder']);

	($hook = vBulletinHook::fetch_hook('admin_style_form')) ? eval($hook) : false;

	print_submit_row($vbphrase['save']);

}

// #############################################################################
// kill a style, set parents for child forums and update template id caches for dependent styles
if ($_POST['do'] == 'killstyle')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'parentid'   => TYPE_INT,
		'parentlist' => TYPE_STR,
		'group'      => TYPE_STR,
	));

	// check to see if we are deleting the last style
	$check = $db->query_first("SELECT COUNT(*) AS numstyles FROM " . TABLE_PREFIX . "style");

	// Delete css file
	if ($vbulletin->options['storecssasfile'] AND $fetchstyle = $db->query_first("SELECT css FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']))
	{
		$fetchstyle['css'] .= "\n";
		$css = substr($fetchstyle['css'], 0, strpos($fetchstyle['css'], "\n"));

		// attempt to delete the old css file if it exists
		delete_css_file($vbulletin->GPC['dostyleid'], $css);
	}

	if ($check['numstyles'] <= 1)
	{
		// there is only one style remaining. we will completely empty the style table and start again

		// zap all non-master templates
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "template WHERE styleid <> -1");

		// empty the style table
		$db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "style");

		// insert a new default style
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "style
				(title, parentid, parentlist, userselect, displayorder)
			VALUES
				('Default Style', -1, '1,-1', 1, 1)
		");

		// set this to be the default style in $vbulletin->options
		$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = 1 WHERE varname = 'styleid'");

		// rebuild $vbulletin->options
		require_once(DIR . '/includes/adminfunctions_options.php');
		build_options();
	}
	else
	{
		// this is not the last style, just delete it and sort out any child styles

		// zap templates belonging to this style
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "template WHERE styleid = " . $vbulletin->GPC['dostyleid']);

		// delete the style itself
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']);

		// update parent info for child styles
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "style
			SET parentid = " . $vbulletin->GPC['parentid'] . ",
			parentlist = '" . $db->escape_string($vbulletin->GPC['parentlist']) . "'
			WHERE parentid = " . $vbulletin->GPC['dostyleid'] . "
		");
	}

	build_all_styles(0, 0, "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;group=" . $vbulletin->GPC['group']);

	print_cp_redirect("template.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;group=" . $vbulletin->GPC['group'], 1);

}

// #############################################################################
// delete style - confirmation for style deletion
if ($_REQUEST['do'] == 'deletestyle')
{

	if ($vbulletin->GPC['dostyleid'] == $vbulletin->options['styleid'])
	{
		print_stop_message('cant_delete_default_style');
	}

	// look at how many styles are being deleted
	$count = $db->query_first("SELECT COUNT(*) AS styles FROM " . TABLE_PREFIX . "style WHERE userselect = 1");
	// check that this isn't the last one that we're about to delete
	$last = $db->query_first("SELECT userselect FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']);
	if ($count['styles'] == 1 AND $last['userselect'] == 1)
	{
		print_stop_message('cant_delete_last_style');
	}

	$style = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']);
	$hidden = array();
	$hidden['parentid'] = $style['parentid'];
	$hidden['parentlist'] = $style['parentlist'];
	print_delete_confirmation('style', $vbulletin->GPC['dostyleid'], 'template', 'killstyle', 'style', $hidden, $vbphrase['please_be_aware_this_will_delete_custom_templates']);

}

// #############################################################################
// do revert all templates in a style
if ($_POST['do'] == 'dorevertall')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'group' => TYPE_STR,
	));

	if ($vbulletin->GPC['dostyleid'] != -1 AND $style = $db->query_first("SELECT styleid, parentid, parentlist, title FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']))
	{
		if (!$style['parentlist'])
		{
			$style['parentlist'] = '-1';
		}

		$templates = $db->query_read("
			SELECT DISTINCT t1.templateid, t1.title
			FROM " . TABLE_PREFIX . "template AS t1
			INNER JOIN " . TABLE_PREFIX . "template AS t2 ON
				(t2.styleid IN ($style[parentlist]) AND t2.styleid <> $style[styleid] AND t2.title = t1.title)
			WHERE t1.templatetype = 'template'
				AND t1.styleid = $style[styleid]
		");
		if ($db->num_rows($templates) == 0)
		{
			print_stop_message('nothing_to_do');
		}
		else
		{
			$deletetemplates = array();

			while ($template = $db->fetch_array($templates))
			{
				$deletetemplates["$template[title]"] = $template['templateid'];
			}
			$db->free_result($templates);

			if (!empty($deletetemplates))
			{
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "template WHERE templateid IN(" . implode(',', $deletetemplates) . ")");

				print_rebuild_style($style['styleid'], '', 0, 0, 0, 0);
			}

			print_cp_redirect("template.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;group=" . $vbulletin->GPC['group'] . "&amp;expandset=$style[styleid]", 1);
		}
	}
	else
	{
		print_stop_message('invalid_style_specified');
	}
}

// #############################################################################
// revert all templates in a style
if ($_REQUEST['do'] == 'revertall')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'group' => TYPE_STR,
	));

	if ($vbulletin->GPC['dostyleid'] != -1 AND $style = $db->query_first("SELECT styleid, title, parentlist FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']))
	{
		if (!$style['parentlist'])
		{
			$style['parentlist'] = '-1';
		}

		$templates = $db->query_read("
			SELECT DISTINCT t1.title
			FROM " . TABLE_PREFIX . "template AS t1
			INNER JOIN " . TABLE_PREFIX . "template AS t2 ON
				(t2.styleid IN ($style[parentlist]) AND t2.styleid <> $style[styleid] AND t2.title = t1.title)
			WHERE t1.templatetype = 'template'
				AND t1.styleid = $style[styleid]
		");
		if ($db->num_rows($templates) == 0)
		{
			print_stop_message('nothing_to_do');
		}
		else
		{
			$templatelist = '';
			while ($template = $db->fetch_array($templates))
			{
				$templatelist .= "<li>$template[title]</li>\n";
			}
			$db->free_result($templatelist);

			echo "<br /><br />";

			print_form_header('template', 'dorevertall');
			print_table_header($vbphrase['revert_all_templates']);
			print_description_row("
				<blockquote><br />
				" . construct_phrase($vbphrase["revert_all_templates_from_style_x"], $style['title'], $templatelist) . "
				<br /></blockquote>
			");
			construct_hidden_code('dostyleid', $style['styleid']);
			construct_hidden_code('group', $vbulletin->GPC['group']);
			print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
		}
	}
	else
	{
		print_stop_message('invalid_style_specified');
	}
}

// #############################################################################
// view the history of a template, including old versions and diffs between versions
if ($_REQUEST['do'] == 'history')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'title' => TYPE_STR
	));

	$revisions = array();
	$have_cur_def = false;
	$cur_temp_time = 0;

	$current_temps = $db->query_read("
		SELECT templateid, title, styleid, dateline, username, version
		FROM " . TABLE_PREFIX . "template
		WHERE title = '" . $db->escape_string($vbulletin->GPC['title']) . "'
			AND styleid IN (-1, " . $vbulletin->GPC['dostyleid'] . ")
	");
	while ($template = $db->fetch_array($current_temps))
	{
		$template['type'] = 'current';

		// the point of the second part of this key is to prevent dateline
		// collisions, as rare as that may be
		$revisions["$template[dateline]|b$template[templateid]"] = $template;

		if ($template['styleid'] == -1)
		{
			$have_cur_def = true;
		}
		else
		{
			$cur_temp_time = $template['dateline'];
		}
	}

	$historical_temps = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "templatehistory
		WHERE title = '" . $db->escape_string($vbulletin->GPC['title']) . "'
			AND styleid IN (-1, " . $vbulletin->GPC['dostyleid'] . ")
	");
	$history_count = $db->num_rows($historical_temps);
	while ($template = $db->fetch_array($historical_temps))
	{
		$template['type'] = 'historical';

		// the point of the second part of this key is to prevent dateline
		// collisions, as rare as that may be
		$revisions["$template[dateline]|a$template[templatehistoryid]"] = $template;
	}

	// I used a/b above, so current versions sort above historical versions
	usort($revisions, "history_compare");

	print_form_header('template', 'historysubmit');
	print_table_header(construct_phrase($vbphrase['history_of_template_x'], htmlspecialchars_uni($vbulletin->GPC['title'])), 7);
	print_cells_row(array(
		($history_count ? $vbphrase['delete'] : ''),
		$vbphrase['type'],
		$vbphrase['version'],
		$vbphrase['last_modified'],
		$vbphrase['view'],
		$vbphrase['old'],
		$vbphrase['new']
	), true, false, 1);

	$have_left_sel = false;
	$have_right_sel = false;

	foreach ($revisions AS $revision)
	{
		$left_sel = false;
		$right_sel = false;

		if ($revision['type'] == 'current')
		{
			// we are marking this entry (ignore all other entries)
			if ($revision['styleid'] == -1)
			{
				$type = $vbphrase['current_default'];
			}
			else
			{
				$type = $vbphrase['current_version'];
			}

			if ($have_right_sel)
			{
				$left_sel = ' checked="checked"';
				$have_left_sel = true;
			}
			else
			{
				$right_sel = ' checked="checked"';
				$have_right_sel = true;
				if (sizeof($revisions) == 1)
				{
					$left_sel = ' checked="checked"';
					$left_sel_sel = true;
				}
			}

			$id = $revision['templateid'];
			$deletebox = '&nbsp;';
		}
		else
		{
			if ($revision['styleid'] == '-1')
			{
				$type = $vbphrase['old_default'];
			}
			else
			{
				$type = $vbphrase['historical'];
			}

			$id = $revision['templatehistoryid'];
			$deletebox = '<input type="checkbox" name="delete[]" value="' . $id . '" />';
		}

		if (!$revision['version'])
		{
			$revision['version'] = '<i>' . $vbphrase['unknown'] . '</i>';
		}

		$date = vbdate($vbulletin->options['dateformat'], $revision['dateline']);
		$time = vbdate($vbulletin->options['timeformat'], $revision['dateline']);
		$last_modified = "<i>$date $time</i> / <b>$revision[username]</b>";

		$view_link = construct_link_code($vbphrase['view'], "template.php?$session[sessionurl]do=viewversion&amp;id=$id&amp;type=$revision[type]");

		$left = '<input type="radio" name="left_template" tabindex="1" value="' . "$id|$revision[type]" . "\"$left_sel />";
		$right = '<input type="radio" name="right_template" tabindex="1" value="' . "$id|$revision[type]" . "\"$right_sel />";

		if ($revision['comment'])
		{
			$comment = htmlspecialchars_uni($revision['comment']);

			$type = "<div title=\"$comment\">$type*</div>";
			$last_modified = "<div title=\"$comment\">$last_modified</div>";
			$revision['version'] = "<div title=\"$comment\">$revision[version]</div>";
			$view_link = "<div title=\"$comment\">$view_link</div>";
		}

		print_cells_row(array(
			$deletebox,
			$type,
			$revision['version'],
			$last_modified,
			$view_link,
			$left,
			$right
		), false, false, 1);
	}

	construct_hidden_code('wrap', 1);
	construct_hidden_code('inline', 1);
	construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
	construct_hidden_code('title', $vbulletin->GPC['title']);

	print_description_row(
		'<span style="float:' . $stylevar['right'] . '"><input type="submit" class="button" tabindex="1" name="docompare" value="' . $vbphrase['compare_versions'] . '" /></span>' .
		($history_count ? '<input type="submit" class="button" tabindex="1" name="dodelete" value="' . $vbphrase['delete'] . '" />' : '&nbsp;'), false, 7, 'tfoot');
	print_table_footer();

	echo '<div align="center" class="smallfont">' . $vbphrase['entry_has_a_comment'] . '</div>';
}

// #############################################################################
// generate a diff between two templates (current or historical versions)
if ($_REQUEST['do'] == 'viewversion')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'id' => TYPE_UINT,
		'type' => TYPE_STR
	));

	$template = fetch_template_current_historical($vbulletin->GPC['id'], $vbulletin->GPC['type']);

	if ($template['templateid'])
	{
		$type = ($template['styleid'] == -1 ? $vbphrase['current_default'] : $vbphrase['current_version']);
	}
	else
	{
		$type = ($template['styleid'] == -1 ? $vbphrase['old_default'] : $vbphrase['historical']);
	}

	$date = vbdate($vbulletin->options['dateformat'], $template['dateline']);
	$time = vbdate($vbulletin->options['timeformat'], $template['dateline']);
	$last_modified = "<i>$date $time</i> / <b>$template[username]</b>";

	print_form_header('', '');
	print_table_header(construct_phrase($vbphrase['viewing_version_of_x'], htmlspecialchars_uni($template['title'])));
	print_label_row($vbphrase['type'], $type);
	print_label_row($vbphrase['last_modified'], $last_modified);
	if ($template['version'])
	{
		print_label_row($vbphrase['version'], $template['version']);
	}
	if ($template['comment'])
	{
		print_label_row($vbphrase['comment'], $template['comment']);
	}
	print_description_row('<textarea class="code" style="width:95%; height:500px">' . htmlspecialchars_uni($template['templatetext']) . '</textarea>', false, 2, '', 'center');
	print_table_footer();

}

// #############################################################################
// just a small action to figure out which submit button was pressed
if ($_POST['do'] == 'historysubmit')
{
	$vbulletin->input->clean_array_gpc('p', array('dodelete' => TYPE_STR));

	if ($vbulletin->GPC['dodelete'])
	{
		$_POST['do'] = 'dodelete';
	}
	else
	{
		$_POST['do'] = 'docompare';
	}
}

// #############################################################################
// delete history points
if ($_POST['do'] == 'dodelete')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'delete' => TYPE_ARRAY_INT,
		'dostyleid' => TYPE_INT,
		'title' => TYPE_STR
	));

	if ($vbulletin->GPC['delete'])
	{
		$ids = implode(', ', $vbulletin->GPC['delete']);

		$db->query_write("DELETE FROM " . TABLE_PREFIX . "templatehistory WHERE templatehistoryid IN ($ids)");
	}

	define('CP_REDIRECT', 'template.php?do=history&amp;dostyleid=' . $vbulletin->GPC['dostyleid'] . '&amp;title=' . urlencode($vbulletin->GPC['title']));
	print_stop_message('template_history_entries_deleted');
}

// #############################################################################
// generate a diff between two templates (current or historical versions)
if ($_POST['do'] == 'docompare')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'left_template' => TYPE_STR,
		'right_template' => TYPE_STR,
		'switch_wrapping' => TYPE_NOHTML,
		'switch_inline' => TYPE_NOHTML,
		'wrap' => TYPE_BOOL,
		'inline' => TYPE_BOOL,
		'context_lines' => TYPE_UINT
	));

	$wrap = ($vbulletin->GPC_exists['switch_wrapping'] ? !$vbulletin->GPC['wrap'] : $vbulletin->GPC['wrap']);
	$inline = ($vbulletin->GPC_exists['switch_inline'] ? !$vbulletin->GPC['inline'] : $vbulletin->GPC['inline']);
	$context_lines = ($vbulletin->GPC_exists['context_lines'] ? $vbulletin->GPC['context_lines'] : 3);

	list($left_id, $left_type) = explode('|', $vbulletin->GPC['left_template']);
	list($right_id, $right_type) = explode('|', $vbulletin->GPC['right_template']);

	$left_template = fetch_template_current_historical($left_id, $left_type);
	$right_template = fetch_template_current_historical($right_id, $right_type);

	if (!$left_template OR !$right_template)
	{
		exit;
	}

	require_once(DIR . '/includes/class_diff.php');

	$diff = new vB_Text_Diff($left_template['templatetext'], $right_template['templatetext']);
	$entries =& $diff->fetch_diff();

	print_form_header('template', 'docompare', false, true, 'cpform', '90%', '', false, 'post', 0, true);
	print_table_header($vbphrase['display_options'], 1);
	?>
	<tr>
		<td colspan="4" class="tfoot" align="center">
			<input type="image" name="submit" src="<?php echo ('../' . $vbulletin->options['cleargifurl']); ?>" style="height:10px;width:10px" alt="<?php echo $vbphrase['update']; ?>" />
			<input type="submit" name="switch_inline" class="submit" value="<?php echo ($inline ? $vbphrase['view_side_by_side'] : $vbphrase['view_inline']); ?>" accesskey="r" />
			<input type="submit" name="switch_wrapping" class="submit" value="<?php echo ($wrap ? $vbphrase['disable_wrapping'] : $vbphrase['enable_wrapping']); ?>" accesskey="s" />
	<?php
	if ($inline)
	{
	?>
			&nbsp;&nbsp;&nbsp;&nbsp;
			<input type="text" name="context_lines" value="<?php echo $context_lines; ?>" size="2" class="ctrl_context_lines" dir="<?php echo $stylevar['textdirection'] ?>" accesskey="t" />
			<strong><?php echo $vbphrase['lines_around_each_diff']; ?></strong>
			&nbsp;&nbsp;&nbsp;&nbsp;
			<input type="submit" name="submit_diff" class="submit" value="<?php echo $vbphrase['update'] ?>" accesskey="u" />
	<?php
	}
	?>
		</td>
	</tr>
	<?php

	construct_hidden_code('left_template', $vbulletin->GPC['left_template']);
	construct_hidden_code('right_template', $vbulletin->GPC['right_template']);
	construct_hidden_code('wrap', $wrap);
	construct_hidden_code('inline', $inline);
	print_table_footer(1);

	print_table_start(true, '90%', '', '', true);
	print_table_header(construct_phrase($vbphrase['comparing_versions_of_x'], htmlspecialchars_uni($left_template['title'])), 4);

	if (!$inline)
	{
		// side by side
		print_cells_row(array(
			$vbphrase['old_version'],
			$vbphrase['new_version']
		), true, false, 1);

		foreach ($entries AS $diff_entry)
		{
			// possible classes: unchanged, notext, deleted, added, changed
			echo "<tr>\n\t";
			echo '<td width="50%" valign="top" class="diff-' . $diff_entry->fetch_data_old_class() . '" dir="ltr">';

			foreach ($diff_entry->fetch_data_old() AS $content)
			{
				echo $diff_entry->prep_diff_text($content, $wrap) . "<br />\n";
			}

			echo '</td><td width="50%" valign="top" class="diff-' . $diff_entry->fetch_data_new_class() . '" dir="ltr">';

			foreach ($diff_entry->fetch_data_new() AS $content)
			{
				echo $diff_entry->prep_diff_text($content, $wrap) . "<br />\n";
			}

			echo "</td></tr>\n\n";
		}
	}
	else
	{
		// inline
		echo "	<tr valign=\"top\" align=\"center\">
					<td class=\"thead\">$vbphrase[old]</td>
					<td class=\"thead\">$vbphrase[new]</td>
					<td class=\"thead\" width=\"100%\">$vbphrase[content]</td>
				</tr>";

		$wrap_buffer = array();
		$first_diff = true;

		foreach ($entries AS $diff_entry)
		{
			if ('unchanged' == $diff_entry->old_class)
			{
				$old_data = $diff_entry->fetch_data_old();
				$new_data_keys = array_keys($diff_entry->fetch_data_new());

				if (sizeof($entries) <= 1)
				{
					$context_lines = sizeof($old_data);
				}

				if (!$context_lines)
				{
					continue;
				}

				// add unchanged lines to wrap buffer
				foreach ($diff_entry->fetch_data_old() AS $lineno => $content)
				{
					$wrap_buffer[] = array('oldline' => $lineno, 'newline' => array_shift($new_data_keys), 'content' => $content);
				}

				continue;
			}
			else if(sizeof($wrap_buffer))
			{
				if (sizeof($wrap_buffer) > $context_lines)
				{
					if (!$first_diff)
					{
						$buffer = array_slice($wrap_buffer, 0, $context_lines);
						$buffer[] = array('oldline' => '', 'newline' => '', 'content' => '<hr />');
						$wrap_buffer = array_merge($buffer, array_slice($wrap_buffer, -$context_lines));
					}
					else
					{
						$wrap_buffer = array_slice($wrap_buffer, -$context_lines);
						$first_diff = false;
					}
				}

				foreach ($wrap_buffer AS $wrap_line)
				{
					if (!$wrap_line['oldline'] AND !$wrap_line['newline'])
					{
						echo '<tr><td class="diff-linenumber">...</td><td class="diff-linenumber">...</td>';
						echo '<td colspan="2" class="diff-unchanged diff-inline-break"></td></tr>';
					}
					else
					{
						echo "<tr>\n\t<td class=\"diff-linenumber\">$wrap_line[oldline]</td><td class=\"diff-linenumber\">$wrap_line[newline]</td>";
						echo '<td colspan="2" valign="top" class="diff-unchanged" dir="ltr">';
						echo $diff_entry->prep_diff_text($wrap_line['content'], $wrap);
						echo "</td></tr>\n\n";
					}
				}

				$wrap_buffer = array();
			}

			$data_old = $diff_entry->fetch_data_old();
			$data_new = $diff_entry->fetch_data_new();
			$data_old_len = sizeof($data_old);
			$data_new_len = sizeof($data_new);

			$first = true;
			$current = 1;

			foreach ($data_old AS $lineno => $content)
			{
				$class = 'diff-deleted';

				// only top border the first line
				$class .= ($first ? ' diff-inline-deleted-start' : '');

				// only bottom border the last line if it is not followed by a new diff
				$class .= ($current >= $data_old_len ? ($data_new_len ? '' : ' diff-inline-deleted-end') : '');

				echo "<tr>\n\t<td class=\"diff-linenumber\">$lineno</td><td class=\"diff-linenumber\">&nbsp;</td>";
				echo '<td colspan="" valign="top" class="' . $class . '" dir="ltr">';
				echo $diff_entry->prep_diff_text($content, $wrap);
				echo "</td></tr>\n\n";

				$first = false;
				$current++;
			}

			$first = true;
			$current = 1;

			foreach ($data_new AS $lineno => $content)
			{
				$class = 'diff-inline-added';

				// only top border the first line if it doesn't consecutively follow an old diff comparison
				$class .= ($first ? ($data_old_len ? '' : ' diff-inline-added-start') : '');

				// only bottom border the last line
				$class .= ($current >= $data_new_len ? ' diff-inline-added-end' : '');

				echo "<tr>\n\t<td class=\"diff-linenumber\">&nbsp;</td><td class=\"diff-linenumber\">$lineno</td>";
				echo '<td colspan="" valign="top" class="' . $class . '" dir="ltr">';
				echo $diff_entry->prep_diff_text($content, $wrap);
				echo "</td></tr>\n\n";

				$first = false;
				$current++;
			}
		}

		// If any buffer remains display the first two lines
		if (sizeof($wrap_buffer))
		{
			$i = 0;
			while ($i < $context_lines AND ($wrap_line = array_shift($wrap_buffer)))
			{
				echo "<tr>\n\t<td class=\"diff-linenumber\">$wrap_line[oldline]</td><td class=\"diff-linenumber\">$wrap_line[newline]</td>";
				echo '<td colspan="2" valign="top" class="diff-unchanged" dir="ltr">';
				echo $diff_entry->prep_diff_text($wrap_line['content'], $wrap);
				echo "</td></tr>\n\n";

				$i++;
			}
		}
		unset($wrap_buffer);
	}

	print_table_footer();

	print_form_header('template', 'docompare', false, true, 'cpform2', '90%', '', true, 'post', 0, true);
	print_table_header($vbphrase['display_options'], 1);
	?>
	<tr>
		<td colspan="4" class="tfoot" align="center">
			<input type="submit" name="switch_inline" class="submit" value="<?php echo ($inline ? $vbphrase['view_side_by_side'] : $vbphrase['view_inline']); ?>" accesskey="r" />
			<input type="submit" name="switch_wrapping" class="submit" value="<?php echo ($wrap ? $vbphrase['disable_wrapping'] : $vbphrase['enable_wrapping']); ?>" accesskey="s" />
	<?php
	if ($inline)
	{
	?>
			&nbsp;&nbsp;&nbsp;&nbsp;
			<input type="text" name="context_lines" value="<?php echo $context_lines; ?>" size="2" class="ctrl_context_lines" dir="<?php echo $stylevar['textdirection'] ?>" accesskey="t" />
			<strong><?php echo $vbphrase['lines_around_each_diff']; ?></strong>
			&nbsp;&nbsp;&nbsp;&nbsp;
			<input type="submit" name="submit_diff" class="submit" value="<?php echo $vbphrase['update'] ?>" accesskey="u" />
	<?php
	}
	?>
		</td>
	</tr>
	<?php

	construct_hidden_code('left_template', $vbulletin->GPC['left_template']);
	construct_hidden_code('right_template', $vbulletin->GPC['right_template']);
	construct_hidden_code('wrap', $wrap);
	construct_hidden_code('inline', $inline);
	print_table_footer(1);

	print_form_header('', '');
	print_table_header($vbphrase['comparison_key']);

	if ($inline)
	{
		echo "<tr><td class=\"diff-deleted diff-inline-deleted-end\" align=\"center\">$vbphrase[text_in_old_version]</td></tr>\n";
		echo "<tr><td class=\"diff-added diff-inline-added-end\" align=\"center\">$vbphrase[text_in_new_version]</td></tr>\n";
		echo "<tr><td class=\"diff-unchanged\" align=\"center\">$vbphrase[text_surrounding_changes]</td></tr>\n";
	}
	else
	{
		echo "<tr><td class=\"diff-deleted\" align=\"center\" width=\"50%\">$vbphrase[text_removed_from_old_version]</td><td class=\"diff-notext\">&nbsp;</td></tr>\n";
		echo "<tr><td class=\"diff-changed\" colspan=\"2\" align=\"center\">$vbphrase[text_changed_between_versions]</td></tr>\n";
		echo "<tr><td class=\"diff-notext\" width=\"50%\">&nbsp;</td><td class=\"diff-added\" align=\"center\">$vbphrase[text_added_in_new_version]</td></tr>\n";
	}

	print_table_footer();
}

// #############################################################################
// insert queries and cache rebuilt for template insertion
if ($_POST['do'] == 'inserttemplate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title'          => TYPE_STR,
		'product'        => TYPE_STR,
		'template'       => TYPE_NOTRIM,
		'searchstring'   => TYPE_STR,
		'expandset'      => TYPE_NOHTML,
		'searchset'      => TYPE_NOHTML,
		'savehistory'    => TYPE_BOOL,
		'histcomment'    => TYPE_STR,
		'return'         => TYPE_STR,
		'group'          => TYPE_STR,
		'confirmremoval' => TYPE_BOOL,
		'confirmerrors'  => TYPE_BOOL,
	));

	// remove escaped CDATA (just in case user is pasting template direct from an XML editor
	// where the CDATA tags will have been escaped by our escaper...
	//$template = xml_unescape_cdata($template);

	if (!$vbulletin->GPC['title'])
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($vbulletin->GPC['title'] == 'footer' AND !$vbulletin->GPC['confirmremoval'])
	{
		if (strpos($vbulletin->GPC['template'], '$vbphrase[powered_by_vbulletin]') === false)
		{
			print_form_header('template', 'inserttemplate', 0, 1, '', '75%');
			construct_hidden_code('confirmremoval', 1);
			construct_hidden_code('title', $vbulletin->GPC['title']);
			construct_hidden_code('template', $vbulletin->GPC['template']);
			construct_hidden_code('group', $vbulletin->GPC['group']);
			construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
			construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
			construct_hidden_code('savehistory', intval($vbulletin->GPC['savehistory']));
			construct_hidden_code('histcomment', $vbulletin->GPC['histcomment']);
			construct_hidden_code('product', $vbulletin->GPC['product']);
			print_table_header($vbphrase['confirm_removal_of_copyright_notice']);
			print_description_row($vbphrase['it_appears_you_are_removing_vbulletin_copyright']);
			print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
			print_cp_footer();
			exit;
		}
	}

	$get_existing = $db->query_read("
		SELECT templateid, styleid, product FROM " . TABLE_PREFIX . "template
		WHERE title = '" . $db->escape_string($vbulletin->GPC['title']) . "'
		AND templatetype = 'template'
	");
	$exists = array();
	while ($curtemplate = $db->fetch_array($get_existing))
	{
		$exists["$curtemplate[styleid]"] = $curtemplate;
	}
	$db->free_result($get_existing);

	$template_un = $vbulletin->GPC['template'];
	$vbulletin->GPC['template'] = compile_template($vbulletin->GPC['template']);

	// work out what we should be doing with the product field
	if ($exists['-1'] AND $vbulletin->GPC['dostyleid'] != -1)
	{
		// there is already a template with this name in the master set - don't allow a different product id
		$vbulletin->GPC['product'] = $exists['-1']['product'];
	}
	else if ($vbulletin->GPC['dostyleid'] != -1)
	{
		// we are not adding a new template to the master set - only allow the default product id
		$vbulletin->GPC['product'] = 'vbulletin';
	}
	else
	{
		// allow this - we are adding a totally new template to the master set
	}

	// error checking on conditionals
	if (empty($vbulletin->GPC['confirmerrors']))
	{
		$errors = check_template_errors($vbulletin->GPC['template']);

		if (!empty($errors))
		{
			print_form_header('template', 'inserttemplate', 0, 1, '', '75%');
			construct_hidden_code('confirmerrors', 1);
			construct_hidden_code('title', $vbulletin->GPC['title']);
			construct_hidden_code('template', $template_un);
			construct_hidden_code('templateid', $vbulletin->GPC['templateid']);
			construct_hidden_code('group', $vbulletin->GPC['group']);
			construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
			construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
			construct_hidden_code('savehistory', intval($vbulletin->GPC['savehistory']));
			construct_hidden_code('histcomment', $vbulletin->GPC['histcomment']);
			construct_hidden_code('product', $vbulletin->GPC['product']);
			print_table_header($vbphrase['vbulletin_message']);
			print_description_row(construct_phrase($vbphrase['template_eval_error'], $errors));
			print_submit_row($vbphrase['continue'], 0, 2, $vbphrase['go_back']);
			print_cp_footer();
			exit;
		}
	}

	// check if template already exists
	if (!$exists[$vbulletin->GPC['dostyleid']])
	{
		/*insert query*/
		$result = $db->query_write("
			INSERT INTO " . TABLE_PREFIX . "template
				(styleid, title, template, template_un, dateline, username, version, product)
			VALUES
				(" . $vbulletin->GPC['dostyleid'] . ",
				'" . $db->escape_string($vbulletin->GPC['title']) . "',
				'" . $db->escape_string($vbulletin->GPC['template']) . "',
				'" . $db->escape_string($template_un) . "',
				" . TIMENOW . ",
				'" . $db->escape_string($vbulletin->userinfo['username']) . "',
				'" . $db->escape_string($full_product_info[$vbulletin->GPC['product']]['version']) . "',
				'" . $db->escape_string($vbulletin->GPC['product']) . "')
		");
		$vbulletin->GPC['templateid'] = $db->insert_id($result);
		// now to update the template id list for this style and all its dependents...
		print_rebuild_style($vbulletin->GPC['dostyleid'], '', 0, 0, 0, 0);

		if (strpos($vbulletin->GPC['title'], 'bbcode_') === 0)
		{
			// begins with bbcode_ - empty the post parsed cache
			$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");
		}
	}
	else
	{
		print_form_header('template', 'updatetemplate', 0, 1, '', '75%');
		construct_hidden_code('confirmerrors', 1);
		construct_hidden_code('title', $vbulletin->GPC['title']);
		construct_hidden_code('oldtitle', $vbulletin->GPC['title']);
		construct_hidden_code('template', $template_un);
		construct_hidden_code('return', $vbulletin->GPC['return']);
		construct_hidden_code('templateid', $exists[$vbulletin->GPC['dostyleid']]['templateid']);
		construct_hidden_code('group', $vbulletin->GPC['group']);
		construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
		construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
		construct_hidden_code('savehistory', intval($vbulletin->GPC['savehistory']));
		construct_hidden_code('histcomment', $vbulletin->GPC['histcomment']);
		construct_hidden_code('product', $vbulletin->GPC['product']);
		print_table_header($vbphrase['vbulletin_message']);
		print_description_row(construct_phrase($vbphrase['template_x_exists_error'], $vbulletin->GPC['title']));
		print_submit_row($vbphrase['save'], 0, 2, $vbphrase['go_back']);
		print_cp_footer();
		exit;
	}

	if ($vbulletin->GPC['savehistory'])
	{
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "templatehistory
				(styleid, title, template, dateline, username, version, comment)
			VALUES
				(" . $vbulletin->GPC['dostyleid'] . ",
				'" . $db->escape_string($vbulletin->GPC['title']) . "',
				'" . $db->escape_string($template_un) . "',
				" . TIMENOW . ",
				'" . $db->escape_string($vbulletin->userinfo['username']) . "',
				'" . $db->escape_string($full_product_info[$vbulletin->GPC['product']]['version']) . "',
				'" . $db->escape_string($vbulletin->GPC['histcomment']) . "')
		");
	}

	if ($vbulletin->GPC['return'])
	{
		$goto = 'template.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;expandset=" . $vbulletin->GPC['expandset'] . "&amp;searchset=" . $vbulletin->GPC['searchset'] . "&amp;group=" . $vbulletin->GPC['group'] . "&amp;templateid=" . $vbulletin->GPC['templateid'] . "&amp;searchstring=" . urlencode($vbulletin->GPC['searchstring']);
	}
	else
	{
		$goto = 'template.php?' . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;expandset=" . $vbulletin->GPC['dostyleid'] . "&amp;searchset=" . $vbulletin->GPC['searchset'] . "&amp;group=" . $vbulletin->GPC['group'] . "&amp;templateid=" . $vbulletin->GPC['templateid'] . "&amp;searchstring=" . urlencode($vbulletin->GPC['searchstring']);
	}

	print_cp_redirect($goto, 1);
}

// #############################################################################
// add a new template form
if ($_REQUEST['do'] == 'add')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'title' => TYPE_STR,
		'group' => TYPE_STR,
		'searchstring' => TYPE_STR,
		'expandset' => TYPE_STR,
	));

	if ($vbulletin->GPC['dostyleid'] == -1)
	{
		$style['title'] = $vbphrase['global_templates'];
	}
	else
	{
		$style = $db->query_first("SELECT title FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']);
	}

	if ($vbulletin->GPC['title'])
	{
		$templateinfo = $db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "template
			WHERE styleid = -1 AND title = '" . $db->escape_string($vbulletin->GPC['title']) . "'
		");
	}
	else if ($vbulletin->GPC['templateid'])
	{
		$templateinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "template WHERE templateid = " . $vbulletin->GPC['templateid']);
		$vbulletin->GPC['title'] = $templateinfo['title'];
	}

	print_form_header('template', 'inserttemplate');
	print_table_header(iif($vbulletin->GPC['title'],
		construct_phrase($vbphrase['customize_template_x'], $vbulletin->GPC['title']),
		$vbphrase['add_new_template']
	));

	construct_hidden_code('group', $vbulletin->GPC['group']);

	$products = fetch_product_list();

	if ($vbulletin->GPC['title'])
	{
		construct_hidden_code('product', $templateinfo['product']);
		print_label_row($vbphrase['product'], $products["$templateinfo[product]"]);
	}
	else if ($vbulletin->debug)
	{
		print_select_row($vbphrase['product'], 'product', $products, $templateinfo['product']);
	}
	else
	{ // use the default as we dictate in inserttemplate, if they dont have debug mode on they can't add templates to -1 anyway
		construct_hidden_code('product', 'vbulletin');
	}

	if ($vbulletin->GPC['dostyleid'] > 0)
	{
		$history = $db->query_first("
			SELECT title
			FROM " . TABLE_PREFIX . "templatehistory
			WHERE title = '" . $db->escape_string($vbulletin->GPC['title']) . "'
				AND styleid = " . $vbulletin->GPC['dostyleid']
		);
	}
	else
	{
		$history = null;
	}

	construct_hidden_code('expandset', $vbulletin->GPC['expandset']);
	construct_hidden_code('searchset', $vbulletin->GPC['expandset']);
	construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
	print_style_chooser_row('dostyleid', $vbulletin->GPC['dostyleid'], $vbphrase['master_style'], $vbphrase['style'], iif($vbulletin->debug == 1, 1, 0));
	print_input_row(
		$vbphrase['title'] .
			($history ?
				'<dfn>' .
				construct_link_code($vbphrase['view_history'], 'template.php?do=history&amp;dostyleid=' . $vbulletin->GPC['dostyleid'] . '&amp;title=' . urlencode($vbulletin->GPC['title']), 1) .
				'</dfn>'
			: ''),
		'title',
		$vbulletin->GPC['title']);
	print_textarea_row($vbphrase['template'] . '
			<br /><br />
			<span class="smallfont">' .
			iif($vbulletin->GPC['title'], construct_link_code($vbphrase['show_default'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=view&amp;title=" . $vbulletin->GPC['title'], 1) . '<br /><br />', '') .
			'<!--' . $vbphrase['wrap_text'] . '<input type="checkbox" unselectable="on" onclick="set_wordwrap(\'ta_template\', this.checked);" accesskey="w" checked="checked" />-->
			</span>',
		'template', $templateinfo['template_un'], 22, '75" style="width:100%', true, true, 'ltr', 'code');
	print_template_javascript();
	print_label_row($vbphrase['save_in_template_history'], '<label for="savehistory"><input type="checkbox" name="savehistory" id="savehistory" value="1" tabindex="1" />' . $vbphrase['yes'] . '</label><br /><span class="smallfont">' . $vbphrase['comment'] . '</span> <input type="text" name="histcomment" value="" tabindex="1" class="bginput" size="50" />');
	print_submit_row($vbphrase['save'], '_default_', 2, '', "<input type=\"submit\" class=\"button\" tabindex=\"1\" name=\"return\" value=\"$vbphrase[save_and_reload]\" accesskey=\"e\" />");
	?>
	<script type="text/javascript">
	<!--
	var initial_crc32 = crc32(YAHOO.util.Dom.get(textarea_id).value);
	var confirmUnload = true;
	YAHOO.util.Event.addListener('cpform', 'submit', function(e) { confirmUnload = false; });
	YAHOO.util.Event.addListener(window, 'beforeunload', function(e) {
		if (initial_crc32 != crc32(YAHOO.util.Dom.get(textarea_id).value) && confirmUnload) {
			e.returnValue = '<?php echo addslashes_js($vbphrase[unsaved_data_may_be_lost]); ?>';
		}
	});
	//-->
	</script>
	<?php
}

// #############################################################################
// simple update query for an existing template
if ($_POST['do'] == 'updatetemplate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title'         => TYPE_STR,
		'oldtitle'      => TYPE_STR,
		'template'      => TYPE_NOTRIM,
		'group'         => TYPE_STR,
		'product'       => TYPE_STR,
		'savehistory'   => TYPE_BOOL,
		'histcomment'   => TYPE_STR,
		'string'        => TYPE_STR,
		'searchstring'  => TYPE_STR,
		'expandset'     => TYPE_NOHTML,
		'searchset'     => TYPE_NOHTML,
		'return'        => TYPE_STR,
		'confirmerrors' => TYPE_BOOL,
		'lastedit'      => TYPE_UINT,
	));

	// remove escaped CDATA (just in case user is pasting template direct from an XML editor
	// where the CDATA tags will have been escaped by our escaper...
	// $template = xml_unescape_cdata($template);

	$template_un = $vbulletin->GPC['template'];
	$vbulletin->GPC['template'] = compile_template($vbulletin->GPC['template']);

	// error checking on conditionals
	if (empty($vbulletin->GPC['confirmerrors']))
	{
		$errors = check_template_errors($vbulletin->GPC['template']);

		if (!empty($errors))
		{
			print_form_header('template', 'updatetemplate', 0, 1, '', '75%');
			construct_hidden_code('confirmerrors', 1);
			construct_hidden_code('title', $vbulletin->GPC['title']);
			construct_hidden_code('template', $template_un);
			construct_hidden_code('templateid', $vbulletin->GPC['templateid']);
			construct_hidden_code('group', $vbulletin->GPC['group']);
			construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
			construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
			construct_hidden_code('product', $vbulletin->GPC['product']);
			construct_hidden_code('savehistory', intval($vbulletin->GPC['savehistory']));
			construct_hidden_code('histcomment', $vbulletin->GPC['histcomment']);
			print_table_header($vbphrase['vbulletin_message']);
			print_description_row(construct_phrase($vbphrase['template_eval_error'], $errors));
			print_submit_row($vbphrase['continue'], 0, 2, $vbphrase['go_back']);
			print_cp_footer();
			exit;
		}
	}

	$old_template = $db->query_first("
		SELECT title, styleid, dateline, username, template_un
		FROM " . TABLE_PREFIX . "template
		WHERE templateid = " . $vbulletin->GPC['templateid'] . "
	");
	 if (strtolower($vbulletin->GPC['title']) != strtolower($old_template['title']) AND $db->query_first("
		SELECT templateid
		FROM " . TABLE_PREFIX . "template
		WHERE styleid = $old_template[styleid] AND title = '" . $db->escape_string($vbulletin->GPC['title']) . "'
	"))
	{
		print_stop_message('template_x_exists', $vbulletin->GPC['title']);
	}

	if ($vbulletin->GPC['lastedit'] AND $vbulletin->GPC['lastedit'] != $old_template['dateline'])
	{
		$comment = construct_phrase($vbphrase['template_edited_after_load'], $old_template['username']);
		echo "<p align=\"center\"><strong>$comment</strong></p>";
		/* Save what was in the template into the history */
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "templatehistory
				(styleid, title, template, dateline, username, version, comment)
			VALUES
				($old_template[styleid],
				'" . $db->escape_string($vbulletin->GPC['title']) . "',
				'" . $db->escape_string($old_template['template_un']) . "',
				" . TIMENOW . ",
				'" . $db->escape_string($vbulletin->userinfo['username']) . "',
				'" . $db->escape_string($full_product_info[$vbulletin->GPC['product']]['version']) . "',
				'" . $db->escape_string($comment) . "'
		)");
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "template SET
			title = '" . $db->escape_string($vbulletin->GPC['title']) . "',
			template = '" . $db->escape_string($vbulletin->GPC['template']) . "',
			template_un = '" . $db->escape_string($template_un) . "',
			dateline = " . TIMENOW . ",
			username = '" . $db->escape_string($vbulletin->userinfo['username']) . "',
			version = '" . $db->escape_string($full_product_info[$vbulletin->GPC['product']]['version']) . "',
			product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
		WHERE templateid = " . $vbulletin->GPC['templateid'] . "
	");

	if (strpos($vbulletin->GPC['title'], 'bbcode_') === 0)
	{
		// begins with bbcode_ - empty the post parsed cache
		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");
	}

	// update any customized templates to reflect a change of product id
	if ($old_template['styleid'] == -1 AND $vbulletin->GPC['product'] != $old_template['product'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "template
			SET product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
			WHERE title = '" . $db->escape_string($vbulletin->GPC['title']) . "'
				AND styleid <> -1
		");
	}

	if ($vbulletin->GPC['savehistory'])
	{
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "templatehistory
				(styleid, title, template, dateline, username, version, comment)
			VALUES
				($old_template[styleid],
				'" . $db->escape_string($vbulletin->GPC['title']) . "',
				'" . $db->escape_string($template_un) . "',
				" . TIMENOW . ",
				'" . $db->escape_string($vbulletin->userinfo['username']) . "',
				'" . $db->escape_string($full_product_info[$vbulletin->GPC['product']]['version']) . "',
				'" . $db->escape_string($vbulletin->GPC['histcomment']) . "')
		");
	}

	if ($vbulletin->GPC['return'])
	{
		$goto = "template.php?do=edit&amp;templateid=" . $vbulletin->GPC['templateid'] . "&amp;group=" . $vbulletin->GPC['group'] . "&amp;expandset=" . $vbulletin->GPC['expandset'] . "&amp;searchset=" . $vbulletin->GPC['searchset'] . "&amp;searchstring=" . urlencode($vbulletin->GPC['searchstring']);
	}
	else
	{
		$goto = "template.php?do=modify&amp;expandset=" . $vbulletin->GPC['expandset'] . "&amp;group=" . $vbulletin->GPC['group'] . "&amp;expandset=" . $vbulletin->GPC['expandset'] . "&amp;searchset=" . $vbulletin->GPC['searchset'] . "&amp;searchstring=" . urlencode($vbulletin->GPC['searchstring']) . "&amp;templateid=" . $vbulletin->GPC['templateid'];
	}

	if ($vbulletin->GPC['title'] == $vbulletin->GPC['oldtitle'])
	{
		if ($vbulletin->GPC['return'])
		{
			print_cp_redirect($goto);
		}
		else
		{
			$_REQUEST['do'] = 'modify';
			$vbulletin->GPC['expandset'] = $vbulletin->GPC['dostyleid'];
		}

		$vbulletin->GPC['searchstring'] = $string ? $string : $vbulletin->GPC['searchstring'];
		$vbulletin->GPC['searchset'] = $vbulletin->GPC['dostyleid'];

		//define('CP_REDIRECT', $goto);
		//print_stop_message('saved_template_x_successfully', $vbulletin->GPC['title']);
	}
	else
	{
		print_rebuild_style($vbulletin->GPC['dostyleid'], '', 0, 0, 0, 0);
		print_cp_redirect($goto, 1);
	}
}

// #############################################################################
// edit form for an existing template
if ($_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'group' => TYPE_STR,
		'searchstring' => TYPE_STR,
		'expandset' => TYPE_STR
	));

	$template = $db->query_first("
		SELECT template.*, style.title AS style
		FROM " . TABLE_PREFIX . "template AS template
		LEFT JOIN " . TABLE_PREFIX . "style AS style USING(styleid)
		WHERE templateid = " . $vbulletin->GPC['templateid'] . "
	");
	if ($template['styleid'] == -1)
	{
		$template['style'] = $vbphrase['global_templates'];
	}

	print_form_header('template', 'updatetemplate');
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['template'], $template['title'], $template['templateid']));
	construct_hidden_code('templateid', $template['templateid']);
	construct_hidden_code('group', $vbulletin->GPC['group']);
	construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
	construct_hidden_code('dostyleid', $template['styleid']);
	construct_hidden_code('expandset', $vbulletin->GPC['expandset']);
	construct_hidden_code('oldtitle', $template['title']);
	construct_hidden_code('lastedit', $template['dateline']);

	$backlink = "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;expandset=$template[styleid]&amp;group=" . $vbulletin->GPC['group'] . "&amp;templateid=" . $vbulletin->GPC['templateid'] . "&amp;searchstring=" . urlencode($vbulletin->GPC['searchstring']);

	$products = fetch_product_list();

	if ($template['styleid'] == -1)
	{
		print_select_row($vbphrase['product'], 'product', $products, $template['product']);
	}
	else
	{
		print_label_row($vbphrase['product'], $products[($template['product'] ? $template['product'] : 'vbulletin')]);
		construct_hidden_code('product', ($template['product'] ? $template['product'] : 'vbulletin'));
	}

	print_label_row($vbphrase['style'], "<a href=\"$backlink\" title=\"" . $vbphrase['edit_templates'] . "\"><b>$template[style]</b></a>");
	print_input_row(
		$vbphrase['title'] . ($template['styleid'] != -1 ? '<dfn>' .
			construct_link_code($vbphrase['view_history'], 'template.php?do=history&amp;dostyleid=' . $template['styleid'] . '&amp;title=' . urlencode($template['title']), 1) .
			'</dfn>' : ''),
		'title',
		$template['title']
	);
	print_textarea_row($vbphrase['template'] . '
			<br /><br />
			<span class="smallfont">' .
			iif($template['styleid'] != -1, construct_link_code($vbphrase['show_default'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=view&amp;title=$template[title]", 1) . '<br /><br />', '') .
			'<!--' . $vbphrase['wrap_text'] . '<input type="checkbox" unselectable="on" onclick="set_wordwrap(\'ta_template\', this.checked);" accesskey="w" checked="checked" />-->
			</span>',
		'template', $template['template_un'], 22, '75" style="width:100%', true, true, 'ltr', 'code');
	print_template_javascript();
	print_label_row($vbphrase['save_in_template_history'], '<label for="savehistory"><input type="checkbox" name="savehistory" id="savehistory" value="1" tabindex="1" />' . $vbphrase['yes'] . '</label><br /><span class="smallfont">' . $vbphrase['comment'] . '</span> <input type="text" name="histcomment" value="" tabindex="1" class="bginput" size="50" />');
	print_submit_row($vbphrase['save'], '_default_', 2, '', "<input type=\"submit\" class=\"button\" tabindex=\"1\" name=\"return\" value=\"$vbphrase[save_and_reload]\" accesskey=\"e\" />");
	?>
	<script type="text/javascript">
	<!--
	var initial_crc32 = crc32(YAHOO.util.Dom.get(textarea_id).value);
	var confirmUnload = true;
	YAHOO.util.Event.addListener('cpform', 'submit', function(e) { confirmUnload = false; });
	YAHOO.util.Event.addListener(window, 'beforeunload', function(e) {
		if (initial_crc32 != crc32(YAHOO.util.Dom.get(textarea_id).value) && confirmUnload) {
			e.returnValue = '<?php echo addslashes_js($vbphrase[unsaved_data_may_be_lost]); ?>';
		}
	});
	//-->
	</script>
	<?php
}

// #############################################################################
// kill a template and update template id caches for dependent styles
if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'group' => TYPE_STR,
	));

	$template = $db->query_first("SELECT styleid, title FROM " . TABLE_PREFIX . "template WHERE templateid = " . $vbulletin->GPC['templateid']);

	if ($template['styleid'])
	{
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "template WHERE templateid=" . $vbulletin->GPC['templateid']);
		print_rebuild_style($template['styleid'], '', 0, 0, 0, 0);
	}

	if (strpos($template['title'], 'bbcode_') === 0)
	{
		// begins with bbcode_ - empty the post parsed cache
		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");
	}

	?>
	<script type="text/javascript">
	<!--

	// refresh the opening window (used for the revert updated default templates action)
	if (window.opener && String(window.opener.location).indexOf("template.php?do=findupdates") != -1)
	{
		window.opener.window.location = window.opener.window.location;
	}

	//-->
	</script>
	<?php

	print_cp_redirect("template.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;expandset=$template[styleid]&amp;group=" . $vbulletin->GPC['group'], 1);

}

// #############################################################################
// confirmation for template deletion
if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'group' => TYPE_STR
	));

	$hidden = array();
	$hidden['group'] = $vbulletin->GPC['group'];
	print_delete_confirmation('template', $vbulletin->GPC['templateid'], 'template', 'kill', 'template', $hidden, $vbphrase['please_be_aware_template_is_inherited']);

}

// #############################################################################
// lets the user see the original template
if ($_REQUEST['do'] == 'view')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'title' => TYPE_STR
	));

	$template = $db->query_first("
		SELECT templateid, styleid, title, template_un
		FROM " . TABLE_PREFIX . "template
		WHERE styleid = -1 AND title = '" . $db->escape_string($vbulletin->GPC['title']) . "'
	");

	print_form_header('', '');
	print_table_header($vbphrase['show_default']);
	print_textarea_row($template['title'], '--[-ORIGINAL-TEMPLATE-]--', $template['template_un'], 20, 80, true, true, 'ltr', 'code');
	print_table_footer();
}


// #############################################################################
// update display order values
if ($_POST['do'] == 'dodisplayorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'displayorder' => TYPE_ARRAY_INT,
		'userselect'   => TYPE_ARRAY_INT,
	));

	$styles = $db->query_read("SELECT styleid, parentid, title, displayorder, userselect FROM " . TABLE_PREFIX . "style");
	if ($db->num_rows($styles))
	{
		while ($style = $db->fetch_array($styles))
		{
			$order = $vbulletin->GPC['displayorder']["{$style['styleid']}"];
			$uperm = intval($vbulletin->GPC['userselect']["{$style['styleid']}"]);
			if ($style['displayorder'] != $order OR $style['userselect'] != $uperm)
			{
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "style
					SET displayorder = $order,
					userselect = $uperm
					WHERE styleid = $style[styleid]
				");
			}
		}
	}

	$_REQUEST['do'] = "modify";

	build_style_datastore();

}

// #############################################################################
// main template list display
if ($_REQUEST['do'] == 'modify')
{

	$vbulletin->input->clean_array_gpc('r', array(
		'searchset'    => TYPE_INT,
		'expandset'    => TYPE_NOHTML,
		'searchstring' => TYPE_STR,
		'titlesonly'   => TYPE_BOOL,
		'group'        => TYPE_NOHTML,
	));

	// populate the stylecache
	cache_styles();

	// sort out parameters for searching
	if ($vbulletin->GPC['searchstring'])
	{
		$vbulletin->GPC['group'] = 'all';
		if ($vbulletin->GPC['searchset'] > 0)
		{
			$vbulletin->GPC['expandset'] =& $vbulletin->GPC['searchset'];
		}
		else
		{
			$parentlist = '-1';
			$vbulletin->GPC['expandset'] = 'all';
		}
	}
	else
	{
		$vbulletin->GPC['searchstring'] = '';
	}

	if (is_numeric($vbulletin->GPC['expandset']))
	{
		$style = $db->query_first("SELECT parentlist FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['expandset']);
		$parentlist = $style['parentlist'];
	}

	// all browsers now support the enhanced template editor
	if (true)
	{
		define('FORMTYPE', 1);
		$SHOWTEMPLATE = 'construct_template_option';
	}
	else
	{
		define('FORMTYPE', 0);
		$SHOWTEMPLATE = 'construct_template_link';
	}

	if ($vbulletin->debug)
	{
		$JS_STYLETITLES[] = "\"0\" : \"" . $vbphrase['master_style'] . "\"";
		$prepend = '--';
	}

	foreach($stylecache AS $style)
	{
		$JS_STYLETITLES[] = "\"$style[styleid]\" : \"" . addslashes_js($style['title'], '"') . "\"";
		$JS_STYLEPARENTS[] = "\"$style[styleid]\" : \"$style[parentid]\"";
	}

	$JS_MONTHS = array();
	$i = 0;
	$months = array('january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december');
	foreach($months AS $month)
	{
		$JS_MONTHS[] = "\"$i\" : \"" . $vbphrase["$month"] . "\"";
		$i++;
	}

	foreach (array(
		'click_the_expand_collapse_button',
		'this_template_has_been_customized_in_a_parent_style',
		'this_template_has_not_been_customized',
		'this_template_has_been_customized_in_this_style',
		'template_last_edited_js',
		'x_templates'
		) AS $phrasename)
	{
		$JS_PHRASES[] = "\"$phrasename\" : \"" . fetch_js_safe_string($vbphrase["$phrasename"]) . "\"";
	}

?>

<script type="text/javascript">
<!--
var SESSIONHASH = "<?php echo $vbulletin->session->vars['sessionhash']; ?>";
var EXPANDSET = "<?php echo $vbulletin->GPC['expandset']; ?>";
var GROUP = "<?php echo $vbulletin->GPC['group']; ?>";
var SEARCHSTRING = "<?php echo urlencode($vbulletin->GPC['searchstring']); ?>";
var STYLETITLE = { <?php echo implode(', ', $JS_STYLETITLES); ?> };
var STYLEPARENTS = { <?php echo implode(', ', $JS_STYLEPARENTS); ?> };
var MONTH = { <?php echo implode(', ', $JS_MONTHS); ?> };
var vbphrase = {
	<?php echo implode(",\r\n\t", $JS_PHRASES) . "\r\n"; ?>
};

// -->
</script>

<?php
if (!FORMTYPE)
{
	print_form_header('', '');
	print_table_header("$vbphrase[styles] &amp; $vbphrase[templates]");
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li><b>' . $vbphrase['color_key'] . '</b></li>
		<li class="col-g">' . $vbphrase['template_is_unchanged_from_the_default_style'] . '</li>
		<li class="col-i">' . $vbphrase['template_is_inherited_from_a_parent_style'] . '</li>
		<li class="col-c">' . $vbphrase['template_is_customized_in_this_style'] . '</li>
		</ul></div>
	');
	print_table_footer();
}
else
{
	echo "<br />\n";
}

if ($help = construct_help_button('', NULL, '', 1))
{
	$pagehelplink = "<div style=\"float:$stylevar[right]\">$help</div>";
}
else
{
	$pagehelplink = '';
}

?>

<form action="template.php?do=displayorder" method="post" tabindex="1" name="tform">
<input type="hidden" name="do" value="dodisplayorder" />
<input type="hidden" name="s" value="<?php echo $vbulletin->session->vars['sessionhash']; ?>" />
<input type="hidden" name="adminhash" value="<?php echo ADMINHASH; ?>" />
<input type="hidden" name="expandset" value="<?php echo $vbulletin->GPC['expandset']; ?>" />
<input type="hidden" name="group" value="<?php echo $vbulletin->GPC['group']; ?>" />
<div align="center">
<div class="tborder" style="width:90%; text-align:<?php echo $stylevar['left']; ?>">
<div class="tcat" style="padding:4px; text-align:center"><?php echo $pagehelplink; ?><b><?php echo $vbphrase['style_manager']; ?></b></div>
<div class="stylebg">

<?php

	if (!empty($vbulletin->GPC['expandset']))
	{
		DEVDEBUG("Querying master template ids");
		$masters = $db->query_read("
			SELECT templateid, title
			FROM " . TABLE_PREFIX . "template
			WHERE templatetype = 'template'
				AND styleid = -1
			ORDER BY title
		");
		while ($master = $db->fetch_array($masters))
		{
			$masterset["$master[title]"] = $master['templateid'];
		}
	}
	else
	{
		$masterset = array();
	}

	$LINKEXTRA = '';
	if (!empty($vbulletin->GPC['group']))
	{
		$LINKEXTRA .= "&amp;group=" . $vbulletin->GPC['group'];
	}
	if (!empty($vbulletin->GPC['searchstring']))
	{
		$LINKEXTRA .= "&amp;searchstring=" . urlencode($vbulletin->GPC['searchstring']) . "&amp;searchset=" . $vbulletin->GPC['searchset'];
	}

	if ($vbulletin->debug)
	{
		print_style(-1);
	}
	foreach($stylecache AS $styleid => $style)
	{
		print_style($styleid, $style);
	}

?>
</div>
<table cellpadding="2" cellspacing="0" border="0" width="100%" class="tborder" style="border: 0px">
<tr>
	<td class="tfoot" align="center">
		<input type="submit" class="button" tabindex="1" value="<?php echo $vbphrase['save_display_order']; ?>" />
		<input type="button" class="button" tabindex="1" value="<?php echo $vbphrase['search_in_templates']; ?>" onclick="window.location='template.php?<?php echo $vbulletin->session->vars['sessionurl_js']; ?>do=search';" />
	</td>
</tr>
</table>
</div>
</div>
</form>
<?php

	echo '<p align="center" class="smallfont">' .
		construct_link_code($vbphrase['add_new_style'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=addstyle");
	if ($vbulletin->debug)
	{
		echo construct_link_code($vbphrase['rebuild_all_styles'], "template.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuild&amp;goto=template.php?" . $vbulletin->session->vars['sessionurl']);
	}
	echo "</p>\n";


	// search only
	/*
	print_form_header('template', 'modify');
	print_table_header($vbphrase['search_templates']);
	construct_hidden_code('searchset', -1);
	construct_hidden_code('titlesonly', 0);
	print_input_row($vbphrase['search_for_text'], 'searchstring', $vbulletin->GPC['searchstring']);
	print_description_row('<input type="button" value="Submit with GET" onclick="window.location = (\'template.php?do=modify&amp;searchset=-1&amp;searchstring=\' + this.form.searchstring.value)" />');
	print_submit_row($vbphrase['find']);
	*/

}

// #############################################################################
// rebuilds all parent lists and id cache lists
if ($_REQUEST['do'] == 'rebuild')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'renumber' => TYPE_INT,
		'install'  => TYPE_INT
	));

	echo "<p>&nbsp;</p>";

	build_all_styles($vbulletin->GPC['renumber'], $vbulletin->GPC['install'], "template.php?" . $vbulletin->session->vars['sessionurl']);
}

// #############################################################################
// create template files

if ($_REQUEST['do'] == 'createfiles' AND $vbulletin->debug)
{
	// this action requires that a web-server writable folder called
	// 'template_dump' exists in the root of the vbulletin directory

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	if (function_exists('set_time_limit') AND !SAFEMODE)
	{
		@set_time_limit(1200);
	}

	chdir(DIR . '/template_dump');

	$templates = $db->query_read("
		SELECT title, templatetype, username, dateline, template_un AS template
		FROM " . TABLE_PREFIX . "template
		WHERE styleid = " . $vbulletin->GPC['dostyleid'] . "
			AND templatetype = 'template'
			" . iif($vbulletin->GPC['mode'] == 1, "AND templateid IN($templateids)") . "
		ORDER BY title
	");
	echo "<ol>\n";
	while ($template = $db->fetch_array($templates))
	{
		echo "<li><b class=\"col-c\">$template[title]</b>: Parsing... ";
		$text = str_replace("\r\n", "\n", $template['template']);
		$text = str_replace("\n", "\r\n", $text);
		echo 'Writing... ';
		$fp = fopen("./$template[title].htm", 'w+');
		fwrite($fp, $text);
		fclose($fp);
		echo "<span class=\"col-i\">Done</span></li>\n";
	}
	echo "</ol>\n";
}

// #############################################################################
// hex convertor
if ($_REQUEST['do'] == 'colorconverter')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'hex'    => TYPE_NOHTML,
		'rgb'    => TYPE_NOHTML,
		'hexdec' => TYPE_STR,
		'dechex' => TYPE_STR,
	));

	if ($vbulletin->GPC['dechex'])
	{
		$vbulletin->GPC['rgb'] = preg_split('#\s*,\s*#si', $vbulletin->GPC['rgb'], -1, PREG_SPLIT_NO_EMPTY);
		$vbulletin->GPC['hex'] = '#';
		foreach ($vbulletin->GPC['rgb'] AS $i => $value)
		{
			$vbulletin->GPC['hex'] .= strtoupper(str_pad(dechex($value), 2, '0', STR_PAD_LEFT));
		}
		$vbulletin->GPC['rgb'] = implode(',', $vbulletin->GPC['rgb']);
	}
	else if ($vbulletin->GPC['hexdec'])
	{
		if (preg_match('/#?([a-f0-9]{2})([a-f0-9]{2})([a-f0-9]{2})/siU', $vbulletin->GPC['hex'], $matches))
		{
			$vbulletin->GPC['rgb'] = array();
			for ($i = 1; $i <= 3; $i++)
			{
				$vbulletin->GPC['rgb'][] = hexdec($matches["$i"]);
			}
			$vbulletin->GPC['rgb'] = implode(',', $vbulletin->GPC['rgb']);
			$vbulletin->GPC['hex'] = strtoupper("#$matches[1]$matches[2]$matches[3]");
		}
	}

	print_form_header('template', 'colorconverter');
	print_table_header('Color Converter');
	print_label_row('Hexadecimal Color (#xxyyzz)', "<span style=\"padding:4px; background-color:" . $vbulletin->GPC['hex'] . "\"><input type=\"text\" class=\"bginput\" name=\"hex\" value=\"" . $vbulletin->GPC['hex'] . "\" size=\"20\" maxlength=\"7\" /> <input type=\"submit\" class=\"button\" name=\"hexdec\" value=\"Hex &raquo; RGB\" /></span>");
	print_label_row('RGB Color (r,g,b)', "<span style=\"padding:4px; background-color:rgb(" . $vbulletin->GPC['rgb'] . ")\"><input type=\"text\" class=\"bginput\" name=\"rgb\" value=\"" . $vbulletin->GPC['rgb'] . "\" size=\"20\" maxlength=\"11\" /> <input type=\"submit\" class=\"button\" name=\"dechex\" value=\"RGB &raquo; Hex\" /></span>");
	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| #
|| # CVS: $RCSfile$ - $Revision: 39862 $
|| ####################################################################
\*======================================================================*/
?>
