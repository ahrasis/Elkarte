<?php

/**
 * The main purpose of this file is to show a list of all badbehavior entries
 * and allow filtering and deleting them.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Class to show a list of all badbehavior log entries
 *
 * @package BadBehavior
 */
class BadBehavior_Controller extends Action_Controller
{
	/**
	 * Call the appropriate action method.
	 */
	public function action_index()
	{
		// all we know how to do is...
		$this->action_log();
	}

	/**
	 * View the forum's badbehavior log.
	 *
	 * What it does:
	 * - This function sets all the context up to show the badbehavior log for review.
	 * - It requires the maintain_forum permission.
	 * - It is accessed from ?action=admin;area=logs;sa=badbehaviorlog.
	 *
	 * @uses the BadBehavior template and badbehavior_log sub template.
	 */
	public function action_log()
	{
		global $scripturl, $txt, $context, $modSettings;

		// Check for the administrative permission to do this.
		isAllowedTo('admin_forum');

		// Templates, etc...
		loadLanguage('BadBehaviorlog');
		loadTemplate('BadBehavior');

		// Functions we will need
		require_once(SUBSDIR . '/BadBehavior.subs.php');

		// Set up the filtering...
		$filter = array();
		if (isset($_GET['value'], $_GET['filter']))
		{
			$filter = $this->_setFilter();
		}

		if (empty($filter))
		{
			// Bad filter or something else going on, back to the start you go
			unset($_GET['filter'], $_GET['value']);
			redirectexit('action=admin;area=logs;sa=badbehaviorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));
		}

		// Deleting or just doing a little weeding?
		if (isset($_POST['delall']) || isset($_POST['delete']))
			$this->_action_delete($filter);

		// Just how many entries are there?
		$num_errors = getBadBehaviorLogEntryCount($filter);

		// If this filter turns up empty, just return
		if (empty($num_errors) && !empty($filter))
			redirectexit('action=admin;area=logs;sa=badbehaviorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));

		// Clean up start.
		$start = (!isset($_GET['start']) || $_GET['start'] < 0) ? 0 : (int) $_GET['start'];

		// Do we want to reverse the listing?
		$sort = isset($_REQUEST['desc']) ? 'down' : 'up';

		// Set the page listing up.
		$context['page_index'] = constructPageIndex($scripturl . '?action=admin;area=logs;sa=badbehaviorlog' . ($sort == 'down' ? ';desc' : '') . (!empty($filter) ? $filter['href'] : ''), $start, $num_errors, $modSettings['defaultMaxMessages']);

		// Find and sort out the log entries.
		$context['bb_entries'] = getBadBehaviorLogEntries($start, $modSettings['defaultMaxMessages'], $sort, $filter);

		$members = array();
		foreach ($context['bb_entries'] as $member)
			$members[] = $member['member']['id'];

		// Load the member data so we have more information available
		if (!empty($members))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$members = getBasicMemberData($members, array('add_guest' => true));

			// Go through each entry and add the member data.
			foreach ($context['bb_entries'] as $id => $dummy)
			{
				$memID = $context['bb_entries'][$id]['member']['id'];
				$context['bb_entries'][$id]['member']['username'] = $members[$memID]['member_name'];
				$context['bb_entries'][$id]['member']['name'] = $members[$memID]['real_name'];
				$context['bb_entries'][$id]['member']['href'] = empty($memID) ? '' : $scripturl . '?action=profile;u=' . $memID;
				$context['bb_entries'][$id]['member']['link'] = empty($memID) ? $txt['guest_title'] : '<a href="' . $scripturl . '?action=profile;u=' . $memID . '">' . $context['bb_entries'][$id]['member']['name'] . '</a>';
			}
		}

		// Filtering?
		if (!empty($filter))
			$this->_prepareFilters($filter);

		// And the standard template goodies
		$context['page_title'] = $txt['badbehaviorlog_log'];
		$context['has_filter'] = !empty($filter);
		$context['sub_template'] = 'badbehavior_log';
		$context['sort_direction'] = $sort;
		$context['start'] = $start;

		createToken('admin-bbl');
	}

	/**
	 * Prepares the filter index of the $context variable
	 *
	 * @param string[] $filter - an array describing the current filter
	 */
	protected function _prepareFilters($filter)
	{
		global $context, $scripturl, $user_profile;

		$context['filter'] = $filter;

		// Set the filtering context.
		if ($filter['variable'] === 'id_member')
		{
			$id = $filter['value']['sql'];
			loadMemberData($id, false, 'minimal');
			$context['filter']['value']['html'] = '<a href="' . $scripturl . '?action=profile;u=' . $id . '">' . $user_profile[$id]['real_name'] . '</a>';
		}
		elseif ($filter['variable'] === 'url')
		{
			$context['filter']['value']['html'] = '\'' . strtr(htmlspecialchars((substr($filter['value']['sql'], 0, 1) === '?' ? $scripturl : '') . $filter['value']['sql'], ENT_COMPAT, 'UTF-8'), array('\_' => '_')) . '\'';
		}
		elseif ($filter['variable'] === 'headers')
		{
			$context['filter']['value']['html'] = '\'' . strtr(htmlspecialchars($filter['value']['sql'], ENT_COMPAT, 'UTF-8'), array("\n" => '<br />', '&lt;br /&gt;' => '<br />', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\')) . '\'';
			$context['filter']['value']['html'] = preg_replace('~&amp;lt;span class=&amp;quot;remove&amp;quot;&amp;gt;(.+?)&amp;lt;/span&amp;gt;~', '$1', $context['filter']['value']['html']);
		}
		else
			$context['filter']['value']['html'] = $filter['value']['sql'];
	}

	/**
	 * Populates the $filter array with data from $_GET
	 */
	protected function _setFilter()
	{
		global $txt;

		$db = database();

		// You can filter by any of the following columns:
		$filters = array(
			'id_member' => $txt['badbehaviorlog_username'],
			'ip' => $txt['badbehaviorlog_ip'],
			'session' => $txt['badbehaviorlog_session'],
			'valid' => $txt['badbehaviorlog_key'],
			'request_uri' => $txt['badbehaviorlog_request'],
			'user_agent' => $txt['badbehaviorlog_agent'],
		);

		if (!isset($filters[$_GET['filter']]))
			return false;

		return array(
			'variable' => $_GET['filter'] == 'useragent' ? 'user_agent' : $_GET['filter'],
			'value' => array(
				'sql' => in_array($_GET['filter'], array('request_uri', 'user_agent')) ? base64_decode(strtr($_GET['value'], array(' ' => '+'))) : $db->escape_wildcard_string($_GET['value']),
			),
			'href' => ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'],
			'entity' => $filters[$_GET['filter']]
		);
	}

	/**
	 * Performs the removal of one or multiple log entries
	 *
	 * @param string[] $filter - an array describing the current filter
	 */
	protected function _action_delete($filter)
	{
		$type = isset($_POST['delall']) ? 'delall' : 'delete';
		// Make sure the session exists and the token is correct
		checkSession();
		validateToken('admin-bbl');

		$redirect = deleteBadBehavior($type, $filter);
		$redirect_path = 'action=admin;area=logs;sa=badbehaviorlog' . (isset($_REQUEST['desc']) ? ';desc' : '');

		if ($redirect === 'delete')
		{
			$start = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
			// Go back to where we were.
			redirectexit($redirect_path . ';start=' . $start . (!empty($filter) ? $filter['href'] : ''));
		}
		redirectexit($redirect_path);
	}

}