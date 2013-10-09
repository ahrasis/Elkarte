<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains functions for dealing with topics. Low-level functions,
 * i.e. database operations needed to perform.
 * These functions do NOT make permissions checks. (they assume those were
 * already made).
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Retrieve all installed themes
 */
function installedThemes()
{
	$db = database();

	$request = $db->query('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({string:name}, {string:theme_dir}, {string:theme_url}, {string:images_url}, {string:theme_templates}, {string:theme_layers})
			AND id_member = {int:no_member}',
		array(
			'name' => 'name',
			'theme_dir' => 'theme_dir',
			'theme_url' => 'theme_url',
			'images_url' => 'images_url',
			'theme_templates' => 'theme_templates',
			'theme_layers' => 'theme_layers',
			'no_member' => 0,
		)
	);
	$themes = array();
	while ($row = $db->fetch_assoc($request))
	{
		if (!isset($themes[$row['id_theme']]))
			$themes[$row['id_theme']] = array(
				'id' => $row['id_theme'],
				'num_default_options' => 0,
				'num_members' => 0,
			);
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
	}
	$db->free_result($request);

	return $themes;
}

/**
 * Retrieve theme directory
 *
 * @param int $id_theme the id of the theme
 */
function themeDirectory($id_theme)
{
	$db = database();

	$request = $db->query('', '
		SELECT value
		FROM {db_prefix}themes
		WHERE variable = {string:theme_dir}
			AND id_theme = {int:current_theme}
		LIMIT 1',
		array(
			'current_theme' => $id_theme,
			'theme_dir' => 'theme_dir',
		)
	);
	list($themeDirectory) = $db->fetch_row($request);
	$db->free_result($request);

	return $themeDirectory;
}

/**
 * Retrieve theme URL
 *
 * @param int $id_theme id of the theme
 */
function themeUrl($id_theme)
{
	$db = database();

	$request = $db->query('', '
		SELECT value
		FROM {db_prefix}themes
		WHERE variable = {string:theme_url}
			AND id_theme = {int:current_theme}
		LIMIT 1',
		array(
			'current_theme' => $id_theme,
			'theme_url' => 'theme_url',
			)
		);

	list ($theme_url) = $db->fetch_row($request);
	$db->free_result($request);

	return $theme_url;
}

/**
 * validates a theme name
 *
 * @param string $indexes
 * @param array $value_data
 * @return type
 */
function validateThemeName($indexes, $value_data)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_theme, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND variable = {string:theme_dir}
			AND (' . implode(' OR ', $value_data['query']) . ')',
		array_merge($value_data['params'], array(
			'no_member' => 0,
			'theme_dir' => 'theme_dir',
			'index_compare_explode' => 'value LIKE \'%' . implode('\' OR value LIKE \'%', $indexes) . '\'',
		))
	);
	$themes = array();
	while ($row = $db->fetch_assoc($request))
	{
		// Find the right one.
		foreach ($indexes as $index)
			if (strpos($row['value'], $index) !== false)
				$themes[$row['id_theme']] = $index;
	}
	$db->free_result($request);

	return $themes;
}

/**
 * Get a basic list of themes
 *
 * @param array $themes
 * @return array
 */
function getBasicThemeInfos($themes)
{
	$db = database();

	$themelist = array();

	$request = $db->query('', '
		SELECT id_theme, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND variable = {string:name}
			AND id_theme IN ({array_int:theme_list})',
		array(
			'theme_list' => array_keys($themes),
			'no_member' => 0,
			'name' => 'name',
		)
	);
	while ($row = $db->fetch_assoc($request))
		$themelist[$themes[$row['id_theme']]] = $row['value'];

	$db->free_result($request);

	return $themelist;
}

/**
 * Gets a list of all themes from the database
 * @return array $themes
 */
function getCustomThemes()
{
	global $settings, $txt;

	$db = database();

	$request = $db->query('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
			AND id_member = {int:no_member}
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'default_theme' => 1,
			'no_member' => 0,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);

	// Manually add in the default
	$themes = array(
		1 => array(
			'name' => $txt['dvc_default'],
			'theme_dir' => $settings['default_theme_dir'],
		),
	);
	while ($row = $db->fetch_assoc($request))
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
	$db->free_result($request);

	return $themes;
}

/**
 * Returns all named and installed themes paths as an array of theme name => path
 *
 * @param array $theme_list
 */
function getThemesPathbyID($theme_list = array())
{
	global $modSettings;

	$db = database();

	// Nothing passed then we use the defaults
	if (empty($theme_list))
		$theme_list = explode(',', $modSettings['knownThemes']);

	if (!is_array($theme_list))
		$theme_list = array($theme_list);

	// Load up any themes we need the paths for
	$request = $db->query('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE (id_theme = {int:default_theme} OR id_theme IN ({array_int:known_theme_list}))
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'known_theme_list' => $theme_list,
			'default_theme' => 1,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);
	$theme_paths = array();
	while ($row = $db->fetch_assoc($request))
		$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];
	$db->free_result($request);

	return $theme_paths;
}

/**
 * Load the installed themes
 * (minimum data)
 *
 * @param array $knownThemes available themes
 */
function loadThemes($knownThemes)
{
	$db = database();

	// Load up all the themes.
	$request = $db->query('', '
		SELECT id_theme, value AS name
		FROM {db_prefix}themes
		WHERE variable = {string:name}
			AND id_member = {int:no_member}
		ORDER BY id_theme',
		array(
			'no_member' => 0,
			'name' => 'name',
		)
	);
	$themes = array();
	while ($row = $db->fetch_assoc($request))
		$themes[] = array(
			'id' => $row['id_theme'],
			'name' => $row['name'],
			'known' => in_array($row['id_theme'], $knownThemes),
		);
	$db->free_result($request);

	return $themes;
}

/**
 * Generates a file listing for a given directory
 *
 * @param type $path
 * @param type $relative
 * @return type
 */
function get_file_listing($path, $relative)
{
	global $scripturl, $txt, $context;

	// Is it even a directory?
	if (!is_dir($path))
		fatal_lang_error('error_invalid_dir', 'critical');

	$dir = dir($path);
	$entries = array();
	while ($entry = $dir->read())
		$entries[] = $entry;
	$dir->close();

	natcasesort($entries);

	$listing1 = array();
	$listing2 = array();

	foreach ($entries as $entry)
	{
		// Skip all dot files, including .htaccess.
		if (substr($entry, 0, 1) == '.' || $entry == 'CVS')
			continue;

		if (is_dir($path . '/' . $entry))
			$listing1[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => true,
				'is_template' => false,
				'is_image' => false,
				'is_editable' => false,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=browse;directory=' . $relative . $entry,
				'size' => '',
			);
		else
		{
			$size = filesize($path . '/' . $entry);
			if ($size > 2048 || $size == 1024)
				$size = comma_format($size / 1024) . ' ' . $txt['themeadmin_edit_kilobytes'];
			else
				$size = comma_format($size) . ' ' . $txt['themeadmin_edit_bytes'];

			$listing2[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => false,
				'is_template' => preg_match('~\.template\.php$~', $entry) != 0,
				'is_image' => preg_match('~\.(jpg|jpeg|gif|bmp|png)$~', $entry) != 0,
				'is_editable' => is_writable($path . '/' . $entry) && preg_match('~\.(php|pl|css|js|vbs|xml|xslt|txt|xsl|html|htm|shtm|shtml|asp|aspx|cgi|py)$~', $entry) != 0,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;filename=' . $relative . $entry,
				'size' => $size,
				'last_modified' => standardTime(filemtime($path . '/' . $entry)),
			);
		}
	}

	return array_merge($listing1, $listing2);
}

/**
 * Counts the theme options configured for guests
 * @return array
 */
function countConfiguredGuestOptions()
{
	$db = database();

	$themes = array();

	$request = $db->query('', '
		SELECT id_theme, COUNT(*) AS value
		FROM {db_prefix}themes
		WHERE id_member = {int:guest_member}
		GROUP BY id_theme',
		array(
			'guest_member' => -1,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$themes[] = $row;
	$db->free_result($request);

	return($themes);
}


/**
 * Counts the theme options configured for guests
 * @return array
 */
function availableThemes($current_theme, $current_member)
{
	global $modSettings, $settings, $user_info, $txt, $language;

	$db = database();

	$available_themes = array();
	if (!empty($modSettings['knownThemes']))
	{
		$request = $db->query('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:name}, {string:theme_url}, {string:theme_dir}, {string:images_url}, {string:disable_user_variant})' . (!allowedTo('admin_forum') ? '
				AND id_theme IN ({array_string:known_themes})' : '') . '
				AND id_theme != {int:default_theme}
				AND id_member = {int:no_member}',
			array(
				'default_theme' => 0,
				'name' => 'name',
				'no_member' => 0,
				'theme_url' => 'theme_url',
				'theme_dir' => 'theme_dir',
				'images_url' => 'images_url',
				'disable_user_variant' => 'disable_user_variant',
				'known_themes' => explode(',', $modSettings['knownThemes']),
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			if (!isset($available_themes[$row['id_theme']]))
				$available_themes[$row['id_theme']] = array(
					'id' => $row['id_theme'],
					'selected' => $current_theme == $row['id_theme'],
					'num_users' => 0
				);
			$available_themes[$row['id_theme']][$row['variable']] = $row['value'];
		}
		$db->free_result($request);
	}

	// Okay, this is a complicated problem: the default theme is 1, but they aren't allowed to access 1!
	if (!isset($available_themes[$modSettings['theme_guests']]))
	{
		$available_themes[0] = array(
			'num_users' => 0
		);
		$guest_theme = 0;
	}
	else
		$guest_theme = $modSettings['theme_guests'];

	$request = $db->query('', '
		SELECT id_theme, COUNT(*) AS the_count
		FROM {db_prefix}members
		GROUP BY id_theme
		ORDER BY id_theme DESC',
		array(
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		// Figure out which theme it is they are REALLY using.
		if (!empty($modSettings['knownThemes']) && !in_array($row['id_theme'], explode(',',$modSettings['knownThemes'])))
			$row['id_theme'] = $guest_theme;
		elseif (empty($modSettings['theme_allow']))
			$row['id_theme'] = $guest_theme;

		if (isset($available_themes[$row['id_theme']]))
			$available_themes[$row['id_theme']]['num_users'] += $row['the_count'];
		else
			$available_themes[$guest_theme]['num_users'] += $row['the_count'];
	}
	$db->free_result($request);

	// Get any member variant preferences.
	$variant_preferences = array();
	if ($current_member > 0)
	{
		$request = $db->query('', '
			SELECT id_theme, value
			FROM {db_prefix}themes
			WHERE variable = {string:theme_variant}
				AND id_member IN ({array_int:id_member})
			ORDER BY id_member ASC',
			array(
				'theme_variant' => 'theme_variant',
				'id_member' => isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'pick' ? array(-1, $current_member) : array(-1),
			)
		);
		while ($row = $db->fetch_assoc($request))
			$variant_preferences[$row['id_theme']] = $row['value'];
		$db->free_result($request);
	}

	// Save the setting first.
	$current_images_url = $settings['images_url'];
	$current_theme_variants = !empty($settings['theme_variants']) ? $settings['theme_variants'] : array();

	foreach ($available_themes as $id_theme => $theme_data)
	{
		// Don't try to load the forum or board default theme's data... it doesn't have any!
		if ($id_theme == 0)
			continue;

		// The thumbnail needs the correct path.
		$settings['images_url'] = &$theme_data['images_url'];

		if (file_exists($theme_data['theme_dir'] . '/languages/Settings.' . $user_info['language'] . '.php'))
			include($theme_data['theme_dir'] . '/languages/Settings.' . $user_info['language'] . '.php');
		elseif (file_exists($theme_data['theme_dir'] . '/languages/Settings.' . $language . '.php'))
			include($theme_data['theme_dir'] . '/languages/Settings.' . $language . '.php');
		else
		{
			$txt['theme_thumbnail_href'] = $theme_data['images_url'] . '/thumbnail.png';
			$txt['theme_description'] = '';
		}

		$available_themes[$id_theme]['thumbnail_href'] = $txt['theme_thumbnail_href'];
		$available_themes[$id_theme]['description'] = $txt['theme_description'];

		// Are there any variants?
		if (file_exists($theme_data['theme_dir'] . '/index.template.php') && (empty($theme_data['disable_user_variant']) || allowedTo('admin_forum')))
		{
			$file_contents = implode('', file($theme_data['theme_dir'] . '/index.template.php'));
			if (preg_match('~\$settings\[\'theme_variants\'\]\s*=(.+?);~', $file_contents, $matches))
			{
				$settings['theme_variants'] = array();

				// Fill settings up.
				eval('global $settings;' . $matches[0]);

				if (!empty($settings['theme_variants']))
				{
					loadLanguage('Settings');

					$available_themes[$id_theme]['variants'] = array();
					foreach ($settings['theme_variants'] as $variant)
						$available_themes[$id_theme]['variants'][$variant] = array(
							'label' => isset($txt['variant_' . $variant]) ? $txt['variant_' . $variant] : $variant,
							'thumbnail' => !file_exists($theme_data['theme_dir'] . '/images/thumbnail.png') || file_exists($theme_data['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? $theme_data['images_url'] . '/thumbnail_' . $variant . '.png' : ($theme_data['images_url'] . '/thumbnail.png'),
						);

					$available_themes[$id_theme]['selected_variant'] = isset($_GET['vrt']) ? $_GET['vrt'] : (!empty($variant_preferences[$id_theme]) ? $variant_preferences[$id_theme] : (!empty($settings['default_variant']) ? $settings['default_variant'] : $settings['theme_variants'][0]));
					if (!isset($available_themes[$id_theme]['variants'][$available_themes[$id_theme]['selected_variant']]['thumbnail']))
						$available_themes[$id_theme]['selected_variant'] = $settings['theme_variants'][0];

					$available_themes[$id_theme]['thumbnail_href'] = $available_themes[$id_theme]['variants'][$available_themes[$id_theme]['selected_variant']]['thumbnail'];
					// Allow themes to override the text.
					$available_themes[$id_theme]['pick_label'] = isset($txt['variant_pick']) ? $txt['variant_pick'] : $txt['theme_pick_variant'];
				}
			}
		}
	}

	// Then return it.
	$settings['images_url'] = $current_images_url;
	$settings['theme_variants'] = $current_theme_variants;

	return array($available_themes, $guest_theme);
}
/**
 * Counts the theme options configured for members
 * @return array
 */
function countConfiguredMemberOptions()
{
	$db = database();

	$themes = array();

	$request = $db->query('themes_count', '
		SELECT COUNT(DISTINCT id_member) AS value, id_theme
		FROM {db_prefix}themes
		WHERE id_member > {int:no_member}
		GROUP BY id_theme',
		array(
			'no_member' => 0,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$themes[] = $row;
	$db->free_result($request);

	return $themes;
}

/**
 * Deletes all outdated options from the themes table
 *
 * @param mixed $theme: if int to remove option from a specific theme,
 *              if string it can be:
 *               - 'default' => to remove from the default theme
 *               - 'custom' => to remove from all the custom themes
 *               - 'all' => to remove from both default and custom
 * @param mixed $membergroups: if int a specific member
 *              if string a "group" of members and it can assume the following values:
 *               - 'guests' => obviously guests,
 *               - 'members' => all members with custom settings (i.e. id_member > 0)
 *               - 'non_default' => guests and members with custom settings (i.e. id_member != 0)
 *               - 'all' => any record
 * @param mixed $old_settings can be a string or an array of strings. If empty deletes all settings.
 */
function removeThemeOptions($theme, $membergroups, $old_settings = array())
{
	$db = database();

	// The default theme is 1 (id_theme = 1)
	if ($theme === 'default')
		$query_param = array('theme_operator' => '=', 'theme' => 1);
	// All the themes that are not the default one (id_theme != 1)
	// @todo 'non_default' would be more esplicative, though it could be confused with the one in $membergroups
	elseif ($theme === 'custom')
		$query_param = array('theme_operator' => '!=', 'theme' => 1);
	// If numeric means a specific theme
	elseif (is_numeric($theme))
		$query_param = array('theme_operator' => '=', 'theme' => (int) $theme);

	// Guests means id_member = 1
	if ($membergroups === 'guests' )
		$query_param += array('member_operator' => '=', 'member' => -1);
	// Members means id_member > 0
	elseif ($membergroups === 'members')
		$query_param += array('member_operator' => '>', 'member' => 0);
	// Non default settings id_member != 0 (that is different from id_member > 0)
	elseif ($membergroups === 'non_default')
		$query_param += array('member_operator' => '!=', 'member' => 0);
	// all it's all
	elseif ($membergroups === 'all')
		$query_param += array('member_operator' => '', 'member' => 0);
	// If it is a number, then it means a specific member (id_member = (int))
	elseif (is_numeric($membergroups))
		$query_param += array('member_operator' => '=', 'member' => (int) $membergroups);

	// If array or string set up the query accordingly
	if (is_array($old_settings))
		$var = 'variable IN ({array_string:old_settings})';
	elseif (!empty($old_settings))
		$var = 'variable = {string:old_settings}';
	// If empty then means any setting
	else
		$var = '1=1';

	$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE ' . $var . ($membergroups === 'all' ? '' : '
			AND id_member {raw:member_operator} {int:member}') . ($theme === 'all' ? '' : '
			AND id_theme {raw:theme_operator} {int:theme}'),
		array_merge(
			$query_param,
			$old_settings
		)
	);
}

/**
 * Update the default options for our users.
 *
 * @param  array $setValues in the order: id_theme, id_member, variable name, value
 */
function updateThemeOptions($setValues)
{
	$db = database();

	$db->insert('replace',
		'{db_prefix}themes',
		array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		$setValues,
		array('id_theme', 'variable', 'id_member')
	);
}

/**
 * Add predefined options to the themes table.
 *
 * @param int $id_theme
 * @param string $options
 * @param mixed $value
 */
function addThemeOptions($id_theme, $options, $value)
{
	$db = database();

	$db->query('substring', '
		INSERT INTO {db_prefix}themes
			(id_member, id_theme, variable, value)
		SELECT id_member, {int:current_theme}, SUBSTRING({string:option}, 1, 255), SUBSTRING({string:value}, 1, 65534)
		FROM {db_prefix}members',
		array(
			'current_theme' => $id_theme,
			'option' => $options,
			'value' => (is_array($value) ? implode(',', $value) : $value),
		)
	);
}

/**
 * Deletes a theme from the database.
 *
 * @param int $id
 */
function deleteTheme($id)
{
	$db = database();

	// Make sure we never ever delete the default theme!
	if ($id === 1)
		fatal_lang_error('no_access', false);

	$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}',
		array(
			'current_theme' => $id,
		)
	);

	// Update the members ...
	$db->query('', '
		UPDATE {db_prefix}members
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $id,
		)
	);

	// ... and the boards table.
	$db->query('', '
		UPDATE {db_prefix}boards
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $id,
		)
	);
}

/**
 * Get the next free id for the theme.
 *
 * @return int
 */
function nextTheme()
{
	$db = database();

	// Find the newest id_theme.
	$result = $db->query('', '
		SELECT MAX(id_theme)
		FROM {db_prefix}themes',
		array(
		)
	);
	list ($id_theme) = $db->fetch_row($result);
	$db->free_result($result);

	// This will be theme number...
	$id_theme++;

	return $id_theme;
}

/**
 * Adds a new theme to the database.
 *
 * @param array $details
 */
function addTheme($details)
{
	$db = database();

	$db->insert('insert',
		'{db_prefix}themes',
		array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		$details,
		array('id_theme', 'variable')
	);
}

/**
 * Get the name of a theme
 *
 * @param int $id
 * @return string
 */
function getThemeName($id)
{
	$db = database();

	$result = $db->query('', '
		SELECT value
		FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}
			AND id_member = {int:no_member}
			AND variable = {string:name}
		LIMIT 1',
		array(
			'current_theme' => $id,
			'no_member' => 0,
			'name' => 'name',
		)
	);
	list ($theme_name) = $db->fetch_row($result);
	$db->free_result($result);

	return $theme_name;
}

/**
 * Deletes all variants from a given theme id.
 *
 * @param int $id
 */
function deleteVariants($id)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}
			AND variable = {string:theme_variant}',
		array(
			'current_theme' => $id,
			'theme_variant' => 'theme_variant',
		)
	);
}

function loadThemeOptionsInto($theme, $memID, $options = array(), $variables = array())
{
	$db = database();

	$variables = is_array($variables) ? $variables : array($variables);

	$request = $db->query('', '
		SELECT variable, value
		FROM {db_prefix}themes
		WHERE id_theme IN (1, {int:current_theme})
			AND id_member = {int:guest_member}' . (!empty($variables) ? '
			AND variable IN ({array_string:variables})' : ''),
		array(
			'current_theme' => $theme,
			'guest_member' => $memID,
			'variables' => $variables,
		)
	);

	while ($row = $db->fetch_assoc($request))
		$options[$row['variable']] = $row['value'];
	$db->free_result($request);

	return $options;
}

/**
 * Possibly the simplest and best example of how to use the template system.
 *  - allows the theme to take care of actions.
 *  - happens if $settings['catch_action'] is set and action isn't found
 *   in the action array.
 *  - can use a template, layers, sub_template, filename, and/or function.
 * @todo look at this
 */
function WrapAction()
{
	global $context, $settings;

	// Load any necessary template(s)?
	if (isset($settings['catch_action']['template']))
	{
		// Load both the template and language file. (but don't fret if the language file isn't there...)
		loadTemplate($settings['catch_action']['template']);
		loadLanguage($settings['catch_action']['template'], '', false);
	}

	// Any special layers?
	if (isset($settings['catch_action']['layers']))
	{
		$template_layers = Template_Layers::getInstance();
		foreach ($settings['catch_action']['layers'] as $layer)
			$template_layers->add($layer);
	}

	// Just call a function?
	if (isset($settings['catch_action']['function']))
	{
		if (isset($settings['catch_action']['filename']))
			template_include(SOURCEDIR . '/' . $settings['catch_action']['filename'], true);

		$settings['catch_action']['function']();
	}
	// And finally, the main sub template ;).
	elseif (isset($settings['catch_action']['sub_template']))
		$context['sub_template'] = $settings['catch_action']['sub_template'];
}