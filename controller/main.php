<?php
/**
 *
 * Top Poster Of The Month. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2005,2017, 3Di
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace threedi\tpotm\controller;

/**
 * Top Poster Of The Month main controller.
 */
class main
{
	/* @var \phpbb\auth\auth */
	protected $auth;

	/* @var \phpbb\cache\service */
	protected $cache;

	/* @var \phpbb\db\driver\driver_interface */
	protected $db;

	/* @var \phpbb\config\config */
	protected $config;

	/* @var \phpbb\extension\manager */
	protected $ext_manager;

	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbb\path_helper */
	protected $path_helper;

	/* @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\user */
	protected $user;

	/* @var \threedi\tpotm\core\tpotm */
	protected $tpotm;

	/* @var string phpBB root path */
	protected $root_path;

	/**
	 * Constructor
	 *
	 * @param \phpbb\auth\auth					$auth			Authentication object
	 * @param \phpbb\cache\service				$cache
	 * @param \phpbb\db\driver\driver_interface	$db				Database object
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\extension\manager			$ext_manager
	 * @param \phpbb\controller\helper			$helper
	 * @param \phpbb\path_helper				$path_helper
	 * @param \phpbb\template\template			$template
	 * @param \phpbb\user						$user
	 * @param threedi\tpotm\core\tpotm			$tpotm			Methods to be used by Class
	 * @var string phpBB root path				$root_path
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\cache\service $cache, \phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, \phpbb\extension\manager $ext_manager, \phpbb\controller\helper $helper, \phpbb\path_helper $path_helper, \phpbb\template\template $template, \phpbb\user $user, \threedi\tpotm\core\tpotm $tpotm, $root_path)
	{
		$this->auth			= $auth;
		$this->cache		= $cache;
		$this->db			= $db;
		$this->config		= $config;
		$this->ext_manager	= $ext_manager;
		$this->helper		= $helper;
		$this->path_helper	= $path_helper;
		$this->template		= $template;
		$this->user			= $user;
		$this->tpotm		= $tpotm;
		$this->root_path	= $root_path;
	}

	/**
	 * Controller for route /tpotm/{name}
	 *
	 * @param string $name
	 *
	 * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
	 */
	public function handle($name)
	{
		if (!$this->tpotm->is_authed())
		{
			throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED_TPOTM__HALL');
		}

		if (!$this->tpotm->is_hall())
		{
			throw new \phpbb\exception\http_exception(404, 'TPOTM__HALL_DISABLED');
		}

		/**
		 * Check permissions prior to run the code
		 */
		if ($this->tpotm->is_authed() && $this->tpotm->is_hall())
		{
			$l_message = $this->user->lang('DEMO_HELLO');
			$this->template->assign_var(
				'DEMO_MESSAGE', $this->user->lang($l_message, $name)
				);

			/**
			 * Gives an avatar as default if missing.
			 * For the sake of the layout
			 */
			$no_avatar = '<img src="' . ($this->path_helper->get_web_root_path() . 'ext/threedi/tpotm/styles/' . rawurlencode($this->user->style['style_path']) . '/theme/images/tpotm_badge.png') . '" />';
			/* Starting point in time */
			$board_start = (int) $this->config['board_startdate']; // UNIX
			/**
			 * if the current month is 01 (January) date() will decrement the year by one
			 * and wrap the month back round to 12
			 */
			$now = time(); // UNIX
			$date_today = gmdate("Y-m", $now);
			list($year_cur, $month_cur) = explode('-', $date_today);
			$month = (int) $month_cur -1;
			$year = (int) $year_cur;
			/* top_posters_ever (minus the present month - Thx Steve) */
			$max_days =  date('t', gmmktime(23, 59, 59, $month, 1, $year));
			$end_last_month = gmmktime(23, 59, 59, $month, $max_days, $year); // UNIX
			/*
			 * top_posters_ever
			 * Show the top posters ever sorted by total posts DESC
			 * If same tot posts and same exact post time then the post ID rules
			 * Empty arrays SQL errors eated by setting the fourth parm as true within "sql_in_set"
			*/
			$sql = 'SELECT u.username, u.user_id, u.user_colour, u.user_avatar, u.user_avatar_type, u.user_avatar_width, u.user_avatar_height, MAX(u.user_type), p.poster_id, MAX(p.post_time), COUNT(p.post_id) AS total_posts
				FROM ' . USERS_TABLE . ' u, ' . POSTS_TABLE . ' p
				WHERE u.user_id <> ' . ANONYMOUS . '
					AND u.user_id = p.poster_id
					AND ' . $this->db->sql_in_set('u.user_id', $this->tpotm->auth_admin_mody_ary(), true, true) . '
					AND ' . $this->db->sql_in_set('u.user_id', $this->tpotm->banned_users_ids(), true, true) . '
					AND (u.user_type <> ' . USER_FOUNDER . ')
					AND p.post_visibility = ' . ITEM_APPROVED . '
					AND p.post_time BETWEEN ' . (int) $board_start . ' AND ' . (int) $end_last_month . '
				GROUP BY u.user_id
				ORDER BY total_posts DESC';
			$result = $this->db->sql_query($sql, (int) $this->tpotm->config_time_cache());

			while ($row = $this->db->sql_fetchrow($result))
			{
				/* Map arguments for  phpbb_get_avatar() */
				$row_avatar = array(
					'avatar'		=> $row['user_avatar'],
					'avatar_type'	=> $row['user_avatar_type'],
					'avatar_width'	=> (int) $row['user_avatar_width'],
					'avatar_height'	=> (int) $row['user_avatar_height'],
				);

				$this->template->assign_block_vars('tpotm_ever', array(
					'USER_AVATAR'	=> (!empty($row['user_avatar'])) ? phpbb_get_avatar($row_avatar, $alt = $this->user->lang('USER_AVATAR')) : $no_avatar,
					'USERNAME'		=> ($this->auth->acl_get('u_viewprofile')) ? get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']) : get_username_string('no_profile', $row['user_id'], $row['username'], $row['user_colour']),
					'TOTAL_POSTS'	=> (int) $row['total_posts'],
				//	'YEAR'			=> (!empty($row['year'])) ? (int) $row['year'] : '',
				//	'MONTH'			=> $this->user->lang['tpotm_months'][$row['month']], // Do not cast to INT!
					'POST_TIME'		=> $this->user->format_date((int) $row['MAX(p.post_time)'])
				));
			}
			$this->db->sql_freeresult($result);

			/* Data range */
			$data_begin = $this->user->format_date((int) $this->config['board_startdate']);
			$data_end = $this->user->format_date((int) $end_last_month);

			$template_vars = array(
				'L_TPOTM_EXPLAIN_HALL'	=> $this->user->lang('TPOTM_EXPLAIN', $data_begin, $data_end),
			);

			/* You know.. template stuff */
			$this->template->assign_vars($template_vars);

			return $this->helper->render('tpotm_hall.html', $name);
		}
	}
}
