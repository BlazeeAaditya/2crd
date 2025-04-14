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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

// display the credits table for use in admin/mod control panels

print_form_header('index', 'home');
print_table_header($vbphrase['vbulletin_developers_and_contributors']);
print_column_style_code(array('white-space: nowrap', ''));
print_label_row('<b>' . $vbphrase['software_developed_by'] . '</b>', '
	vBulletin Solutions, Inc.
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['product_manager'] . '</b>', '
	Kier Darby
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['business_development'] . '</b>', '
	James Limm,
	Ashley Busby
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['software_development'] . '</b>', '
	Kier Darby,
	Freddie Bingham,
	Scott MacVicar,
	Mike Sullivan,
	Jerry Hutchings,
	Darren Gordon
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['graphics_development'] . '</b>', '
	Kier Darby,
	Fabio Passaro
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['other_contributions_from'] . '</b>', '
	Jake Bunce,
	Doron Rosenberg,
	Overgrow,
	Kevin Schumacher,
	Chen Avinadav,
	Floris Fiedeldij Dop,
	Stephan \'pogo\' Pogodalla,
	Forum Scriptz Team,
	Michael \'Mystics\' K&ouml;nig,
	Martin Meredith,
	Torstein H&oslash;nsi,
	Mark James
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['copyright_enforcement_by'] . '</b>', '
	Pirate Reports
', '', 'top', NULL, false);
print_table_footer();

/*======================================================================*\
|| ####################################################################
|| #
|| # CVS: $RCSfile$ - $Revision: 39862 $
|| ####################################################################
\*======================================================================*/
?>
