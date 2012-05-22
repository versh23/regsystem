<?php
/**
 * <pre>
 * Invision Power Services
 * IP.Board v3.1.4
 * Profile View
 * Last Updated: $Date: 2010-10-21 07:08:38 -0400 (Thu, 21 Oct 2010) $
 * </pre>
 *
 * @author 		$Author: ips_terabyte $
 * @copyright	(c) 2001 - 2009 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/community/board/license.html
 * @package		IP.Board
 * @subpackage	Members
 * @link		http://www.invisionpower.com
 * @since		20th February 2002
 * @version		$Revision: 7007 $
 */

if ( ! defined( 'IN_IPB' ) )
{
	print "<h1>Incorrect access</h1>You cannot access this file directly. If you have recently upgraded, make sure you upgraded all the relevant files.";
	exit();
}

class public_members_profile_view extends ipsCommand
{
	/**
	 * Custom fields object
	 *
	 * @var		object
	 */
	public $custom_fields;
	
	/**
	 * Temporary stored output HTML
	 *
	 * @var		string
	 */
	public $output;
	
	/**
	 * Member name
	 *
	 * @var		string
	 */
	protected $member_name;

	/**
	 * Class entry point
	 *
	 * @param	object		Registry reference
	 * @return	void		[Outputs to screen/redirects]
	 */
	public function doExecute( ipsRegistry $registry )
	{
		//-----------------------------------------
		// Get HTML and skin
		//-----------------------------------------

		$this->registry->class_localization->loadLanguageFile( array( 'public_profile' ), 'members' );
		$this->registry->class_localization->loadLanguageFile( array( 'public_online' ), 'members' );

		//-----------------------------------------
		// Can we access?
		//-----------------------------------------
		
		if ( !$this->memberData['g_mem_info'] )
 		{
 			$this->registry->output->showError( 'profiles_off', 10245, null, null, 403 );
		}

		$this->_viewModern();

		//-----------------------------------------
		// Push to print handler
		//-----------------------------------------
		
		$this->registry->output->addContent( $this->output );
		$this->registry->output->setTitle( $this->member_name . ' - ' . $this->lang->words['page_title_pp'] . ' - ' . ipsRegistry::$settings['board_name'] );
		$this->registry->output->addNavigation( $this->lang->words['page_title_pp'] . ': ' . $this->member_name, '' );
		$this->registry->output->sendOutput();
 	}

	/**
	 * Modern profile
	 *
	 * @return	void		[Outputs to screen]
	 */
 	protected function _viewModern()
 	{
 		//-----------------------------------------
 		// INIT
 		//-----------------------------------------
		
		$member_id			= intval( $this->request['id'] ) ? intval( $this->request['id'] ) : intval( $this->request['MID'] );
		$member_id			= $member_id ? $member_id : $this->memberData['member_id'];
		$tab				= substr( IPSText::alphanumericalClean( str_replace( '..', '', trim( $this->request['tab'] ) ) ), 0, 20 );
		$firsttab			= '';
		$member				= array();
		$comments			= array();
		$comments_html		= "";
		$friends			= array();
		$visitors			= array();
		$comment_perpage	= 5;
		$pips				= 0;
		$default_tab        = '';
		$tabs				= array();
		$_tabs				= array();
		$_positions			= array( 0 => 0 );
		$_member_ids		= array();
		$sql_extra			= '';
		$pass				= 0;
		$mod				= 0;
		$_todays_date		= getdate();
		$_rCustom			= intval( $this->request['removeCustomization'] );
		$_dCustom			= intval( $this->request['disableCustomization'] );
		$time_adjust		= $this->settings['time_adjust'] == "" ? 0 : $this->settings['time_adjust'];
		$board_posts		= $this->caches['stats']['total_topics'] + $this->caches['stats']['total_replies'];
		$seenFiles			= array();
		
 		//-----------------------------------------
		// Check input..
		//-----------------------------------------

		if ( ! $member_id )
		{
			$this->registry->output->silentRedirect( $this->settings['base_url'] );
		}

		/* Removing customization? */
		if ( $_rCustom AND ( $member_id == $this->memberData['member_id'] OR $this->memberData['g_is_supmod'] ) AND $this->request['secure_key'] == $this->member->form_hash )
		{
			IPSMember::save( $member_id, array( 'extendedProfile' => array( 'pp_customization' => serialize( array() ) ) ) );
		}
		
		/* Disable? */
		if ( $_dCustom AND $this->memberData['g_is_supmod'] AND $this->request['secure_key'] == $this->member->form_hash )
		{
			IPSMember::save( $member_id, array( 'core' => array( 'bw_disable_customization' => 1 ) ) );
		}
		
		//-----------------------------------------
		// Configure tabs
		//-----------------------------------------
		
		foreach( ipsRegistry::$applications as $app )
		{
			/* Skip if disabled */
			if( ! $app['app_enabled'] )
			{
				continue;
			}
			
			/* Path to tabs */
			$custom_path = IPSLib::getAppDir( $app['app_directory'] ) . '/extensions/profileTabs';
			
		if ( is_dir( $custom_path ) )
		{
			foreach( new DirectoryIterator( $custom_path ) as $f )
			{
				if ( ! $f->isDot() && ! $f->isDir() )
				{
					$file = $f->getFileName();
					
					if( $file[0] == '.' )
					{
						continue;
					}
								
					if ( preg_match( "#\.conf\.php$#i", $file ) )
					{
						$classname = str_replace( ".conf.php", "", $file );
						
							$CONFIG = array();
						require( $custom_path . '/' . $file );
						
						//-------------------------------
						// Allowed to use?
						//-------------------------------
					
						if ( $CONFIG['plugin_enabled'] )
						{
							$CONFIG['app']				= $app['app_directory'];
								
							$_position					= $this->_getTabPosition( $_positions, $CONFIG['plugin_order'] );
							$_tabs[ $_position ]		= $CONFIG;
							$_positions[]				= $_position;
						}
					}
				}
			}			
		}
		}
		
		ksort( $_tabs );
		
		foreach( $_tabs as $_pos => $data )
		{
			if( !$firsttab )
			{
				$firsttab = $data['plugin_key'];
			}
			
			$data['_lang']					= isset($this->lang->words[ $data['plugin_lang_bit'] ]) ? $this->lang->words[ $data['plugin_lang_bit'] ] : $data['plugin_name'];
			$tabs[ $data['plugin_key'] ]	= $data;
		}
		
		if ( ! $tab OR ( $tab != 'comments' AND $tab != 'settings' AND ! @file_exists( IPSLib::getAppDir( $tabs[ $tab ]['app'] ) . '/extensions/profileTabs/' . $tab . '.php' ) ) )
		{
			$tab         = $firsttab;
			$default_tab = $tabs[ $tab ]['app'] . ':' . $tab;
		}

		//-----------------------------------------
		// Grab all data...
		//-----------------------------------------
		
		$member = IPSMember::load( $member_id, 'profile_portal,pfields_content,sessions,groups', 'id' );

		if ( !$member['member_id'] )
		{
			$this->registry->output->showError( 'profiles_no_member', 10246, null, null, 404 );
		}
		
		// Cycle redirect on member profile for ID FURL Fix
		if ( IPB_USE_ONLY_ID_FURL )
		{
			 $member['members_seo_name'] = $member['member_id'];
		}
		else
		{
			 $member['members_seo_name'] = ( $member['members_seo_name'] ) ? $member['members_seo_name'] : IPSText::makeSeoTitle( $member['members_display_name'] );
		}
		
		/* Check USER permalink... */
		$this->registry->getClass('output')->checkPermalink( $member['members_seo_name'] );
		
		/* Build data */
		$member = IPSMember::buildDisplayData( $member, array( 'customFields' => 1, 'cfSkinGroup' => 'profile', 'checkFormat' => 1, 'cfGetGroupData' => 1, 'signature' => 1, 'spamStatus' => 1 ) );

		//-----------------------------------------
		// Recent visitor?
		//-----------------------------------------
		
		if ( $member['member_id'] != $this->memberData['member_id'] )
		{
			list( $be_anon, $loggedin ) = explode( '&', $this->memberData['login_anonymous'] );
			
			if ( ! $be_anon )
			{
				$this->_addRecentVisitor( $member, $this->memberData['member_id'] );
			}
		}

		//-----------------------------------------
		// DST?
		//-----------------------------------------
		
		if ( $member['dst_in_use'] == 1 )
		{
			$member['time_offset'] += 1;
		}

		//-----------------------------------------
		// Format extra user data
		//-----------------------------------------
		
		$member['_age']			 = ( $member['bday_year'] ) ? date( 'Y' ) - $member['bday_year'] : 0;
		
		if( $member['bday_month'] > date( 'n' ) )
		{
			$member['_age'] -= 1;
		}
		else if( $member['bday_month'] == date( 'n' ) )
		{
			if( $member['bday_day'] > date( 'j' ) )
			{
				$member['_age'] -= 1;
			}
		}

		$member['_local_time']		= $member['time_offset'] != "" ? gmstrftime( $this->settings['clock_long'], time() + ($member['time_offset']*3600) + ($time_adjust * 60) ) : '';
		$member['g_title']			= IPSLib::makeNameFormatted( $member['g_title'], $member['g_id'], $member['prefix'], $member['suffix'] );
		$member['_posts_day']		= 0;
		$member['_total_pct']		= 0;
		$member['_bday_month']		= $member['bday_month'] ? $this->lang->words['M_' . $member['bday_month'] ] : 0;

		$posts	= $this->DB->buildAndFetch( array(
												'select'	=> "COUNT(*) as total_posts",
												'from'		=> "posts",
												'where'		=> "author_id=" . $member['member_id'],
											)		);

		$member['posts']	= $posts['total_posts'];

		//-----------------------------------------
		// Total posts
		//-----------------------------------------
		
		if ( $member['posts'] and $board_posts  )
		{
			$member['_posts_day'] = round( $member['posts'] / ( ( time() - $member['joined']) / 86400 ), 2 );
	
			# Fix the issue when there is less than one day
			$member['_posts_day'] = ( $member['_posts_day'] > $member['posts'] ) ? $member['posts'] : $member['_posts_day'];
			$member['_total_pct'] = sprintf( '%.2f', ( $member['posts'] / $board_posts * 100 ) );
		}
		
		$member['_posts_day'] = floatval($member['_posts_day']);
			
		if( ! $this->settings['disable_profile_stats'] )
		{
			//-----------------------------------------
			// Most active in
			//-----------------------------------------
			
			/* Get list of good forum IDs */
			$forumIdsOk = $this->registry->class_forums->fetchSearchableForumIds();
			$faves		= array();
			$top		= 0;
			
			if( is_array($forumIdsOk) AND count($forumIdsOk) )
			{
				$favorite = $this->DB->buildAndFetch( array('select'	=> 'COUNT(p.author_id) as f_posts',
															'from'		=> array( 'posts' => 'p' ),
															'where'		=> 'p.author_id=' . $member['member_id'] . ' AND t.forum_id IN (' . implode( ",", $forumIdsOk ) . ") ",
															'group'		=> 't.forum_id',
															'order'		=> 'f_posts DESC',
															'limit'		=> array( 0, 1 ),
															'add_join'	=> array( array( 'select'	=> 't.forum_id',
																						 'from'		=> array( 'topics' => 't' ),
																						 'where'	=> 't.tid=p.topic_id' ) ) )	);
			}
			else
			{
				$favorite	= array( 'forum_id' => 0, 'f_posts' => 0 );
			}

			$member['favorite_id']	= $favorite['forum_id'];
			$member['_fav_posts']	= $favorite['f_posts'];
			
			if( $member['posts'] )
			{
				$member['_fav_percent']	= round( $favorite['f_posts'] / $member['posts'] * 100 );
			}
		}

		//-----------------------------------------
		// Comments
		//-----------------------------------------
		
		if( $member['pp_setting_count_comments'] )
		{
			$classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( 'members' ) . '/sources/comments.php', 'profileCommentsLib', 'members' );
			$comment_lib	= new $classToLoad( $this->registry );
			$comment_html	= $comment_lib->buildComments( $member );
		}

		//-----------------------------------------
		// Visitors
		//-----------------------------------------
		
		if ( $member['pp_setting_count_visitors'] )
		{
			$_pp_last_visitors	= unserialize( $member['pp_last_visitors'] );
			$_visitor_info		= array();
			$_count				= 1;
		
			if ( is_array( $_pp_last_visitors ) )
			{
				krsort( $_pp_last_visitors );
			
				$_members = IPSMember::load( array_values( $_pp_last_visitors ), 'extendedProfile' );
	
				foreach( $_members as $_id => $_member )
				{ 
					$_visitor_info[ $_id ] = IPSMember::buildDisplayData( $_member, array( 'reputation' => 0, 'avatar' => 0, 'warn' => 0 ) );
				}
				
				foreach( $_pp_last_visitors as $_time => $_id )
				{
					if ( $_count > $member['pp_setting_count_visitors'] )
					{
						break;
					}
				
					$_count++;
				
					if( !$_visitor_info[ $_id ]['members_display_name_short'] )
					{
						$_visitor_info[ $_id ] = IPSMember::buildDisplayData( IPSMember::setUpGuest(), array( 'reputation' => 0, 'avatar' => 0, 'warn' => 0 ) );
					}
					
					$_visitor_info[ $_id ]['_visited_date'] 				= ipsRegistry::getClass( 'class_localization')->getDate( $_time, 'TINY' );
					$_visitor_info[ $_id ]['members_display_name_short']	= $_visitor_info[ $_id ]['members_display_name_short'] ? $_visitor_info[ $_id ]['members_display_name_short'] : $this->lang->words['global_guestname'];

					$visitors[] = $_visitor_info[ $_id ];
				}
			}
		}

		//-----------------------------------------
		// Friends
		//-----------------------------------------
		
		# Get random number from member's friend cache... grab 10 random. array_rand( array, no.)
		# also fall back on last 10 if no cache
		
		if ( $member['pp_setting_count_friends'] > 0 && $this->settings['friends_enabled'] )
		{
			$member['_cache'] = IPSMember::unpackMemberCache( $member['members_cache'] );
		
			if ( is_array( $member['_cache']['friends'] ) AND count( $member['_cache']['friends'] ) )
			{
				foreach( $member['_cache']['friends'] as $id => $approved )
				{
					$id = intval( $id );
				
					if ( $approved AND $id )
					{
						$_member_ids[] = $id;
					}
				}
				
				$member['_total_approved_friends']	= count( $_member_ids );
				$member['_showing_friend_count']	= $member['_total_approved_friends'] > 50 ? 50 : $member['_total_approved_friends'];

				if ( is_array( $_member_ids ) AND $member['_total_approved_friends'] )
				{
					$_max		= $member['_total_approved_friends'] > 50 ? 50 : $member['_total_approved_friends'];
					$_rand		= array_rand( $_member_ids, $_max );
					$_final		= array();
					
					# If viewing member is in list, let's show em
					if( in_array( $this->memberData[ 'member_id' ], $_member_ids ) )
					{						
						$_final[]	= $this->memberData[ 'member_id' ];
						
						$new_mids	= array();
						
						foreach( $_member_ids as $mid )
						{
							if( $mid == $this->memberData[ 'member_id' ] )
							{
								continue;
							}
							
							$new_mids[] = $mid;
						}
												
						$_member_ids = $new_mids;
						unset( $new_mids );
						
						if( is_array( $_rand ) )
						{
							if( count( $_rand ) >= 50 )
							{
								array_pop( $_rand );
							}
						}
					}
				
					if ( is_array( $_rand ) AND count( $_rand ) )
					{
						foreach( $_rand as $_id )
						{
							$_final[] = $_member_ids[ $_id ];
						}
					}
				
					if ( count( $_final ) )
					{
						$sql_extra = ' AND friends_friend_id IN (' . IPSText::cleanPermString( implode( ',', $_final ) ) . ')';
					}
				}
			}
			
			/* Fetch friends */
			$_memberIds = array();
			$_members   = array();
			$_friends   = array();
			
			$this->DB->build( array('select'   => '*',
									'from'	   => 'profile_friends',
									'where'	   => 'friends_member_id=' . $member_id . ' AND friends_approved=1' . $sql_extra,
									'limit'	   => array( 0, 50 ),
									'order'    => 'friends_approved DESC' )	);
																
			$this->DB->execute();
			
			while( $row = $this->DB->fetch() )
			{
				$_memberIds[] = $row['friends_friend_id'];
				$_friends[]   = $row;
			}
			
			/* Got members? */
			$_members = IPSMember::load( $_memberIds, 'core,extendedProfile' );
			$member['_total_displayed_friends'] = 0;
			
			foreach( $_friends as $row )
			{
				if( ! isset( $_members[ $row['friends_friend_id'] ] ) )
				{
					continue;
				}
				
				if ( $row['friends_friend_id'] && isset( $_members[ $row['friends_friend_id'] ] ) )
				{
					$row = array_merge( $row, $_members[ $row['friends_friend_id'] ] );
				}
				
				$row['_friends_added']		= ipsRegistry::getClass( 'class_localization')->getDate( $row['friends_added'], 'SHORT' );
			
				$row = IPSMember::buildProfilePhoto( $row );
			
				$row['members_display_name_short'] = IPSText::truncate( $row['members_display_name'], 13 );
				
				$friends[] = $row;
				
				$member['_total_displayed_friends']++;
			}
		}
		
		//-----------------------------------------
		// Online location
		//-----------------------------------------
		
		$member = IPSMember::getLocation( $member );
		
		//-----------------------------------------
		// Add profile view
		//-----------------------------------------
		
		$this->DB->insert( 'profile_portal_views', array( 'views_member_id' => $member['member_id'] ), true );
		
		//-----------------------------------------
		// Grab default tab...
		//-----------------------------------------
		
		$tab_html = '';
		
		if ( $tab != 'comments' AND $tab != 'settings' )
		{
			if( file_exists( IPSLib::getAppDir( $tabs[ $tab ]['app'] ) . '/extensions/profileTabs/' . $tab . '.php' ) )
			{
				require( IPSLib::getAppDir( 'members' ) . '/sources/tabs/pluginParentClass.php' );
				$classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir( $tabs[ $tab ]['app'] ) . '/extensions/profileTabs/' . $tab . '.php', 'profile_' . $tab, $tabs[ $tab ]['app'] );
				$plugin      = new $classToLoad( $this->registry );
				$tab_html    = $plugin->return_html_block( $member );
			}
		}
		
		//-----------------------------------------
		// Set description tag
		//-----------------------------------------
		
		$_desc = ( $member['pp_about_me'] ) ? $member['pp_about_me'] : $member['signature'];
		
		if ( $_desc )
		{
			$this->registry->output->addMetaTag( 'description', $member['members_display_name'] . ': ' . IPSText::getTextClass('bbcode')->stripAllTags( $_desc ) );
		}
		
		/* Reputation */
		if ( $this->settings['reputation_protected_groups'] )
		{
			if ( in_array( $member['member_group_id'], explode( ",", $this->settings['reputation_protected_groups'] ) ) )
			{
				$this->settings['reputation_show_profile'] = false;
			}
		}
		
		//-----------------------------------------
		// Try to "fix" empty custom field groups
		//-----------------------------------------
		
		foreach( $member['custom_fields'] as $group => $mdata )
		{
			if( $group != 'profile_info' AND $group != 'contact' )
			{
				if( is_array( $member['custom_fields'][ $group ] ) AND count( $member['custom_fields'][ $group ] ) )
				{
					$_count	= 0;
					
					foreach( $member['custom_fields'][ $group ] as $key => $value )
					{
						if( $value )
						{
							$_count++;
						}
					}
					
					if( !$_count )
					{
						unset($member['custom_fields'][ $group ]);
					}
				}
			}
		}

		//-----------------------------------------
		// Add to output
		//-----------------------------------------
//herelee
	$this->DB->build( array( 'select' => 'h.hddsn',
											'from' => array( 'srr_hdd' => 'h' ),
											'where' => 'h.forum_id='.$member['member_id'],
											'order'		=> 's.date DESC',
											'add_join'	=> array( array( 'select'	=> 's.steam,s.date',
																		 'from'		=> array( 'srr_steam' => 's' ),
																		 'where'	=> 'h.hddsn=s.hddsn'))));
	$this->DB->execute();
	while($t = $this->DB->fetch())
{
	$dt[]=$t;
}	

$link = mysql_connect('localhost', 'login', 'pass');
if($dt)
{
//тут проверим hddsn
//die($dt[0]['hddsn']);
mysql_select_db('web913612_bans', $link);	
	//прошарим на активные баны
	$result = mysql_query("SELECT bid FROM amx_bans WHERE hwid='".$dt[0]['hddsn']."' ORDER BY ban_created DESC LIMIT 1",$link) or die(mysql_error());

	if(mysql_num_rows($result)==0)
	{	
		//оок, ща он не забанен..тогда прошарим в истории банов ban_history
		$result = mysql_query("SELECT bhid FROM amx_banhistory WHERE hwid='".$dt[0]['hddsn']."' ORDER BY ban_created DESC LIMIT 1",$link) or die(mysql_error());
		if(mysql_num_rows($result)==0)
		{
			$dt[0]['ban_hdd'] = '<span class="not_banned">В бан листе не найден</span>';		
		}else{
		$row = mysql_fetch_assoc($result);
		$dt[0]['ban_hdd']="<a class=\"was_banned\" href='http://stalin-server.ru/bah/ban_details.php?bhid=".$row['bhid']."'>Не забанен. Есть история</a>";
		}
			
	}else{
	//ок, забанен ща	
	$row = mysql_fetch_assoc($result);
	
		$dt[0]['ban_hdd']="<a class=\"banned\" href='http://stalin-server.ru/bah/ban_details.php?bid=".$row['bid']."'>Игрок забанен</a>";

}

foreach ($dt as &$value) {
	mysql_select_db('web913612_ps', $link);
	$result = mysql_query("SELECT plrid FROM ps_plr_ids_worldid WHERE worldid='".$value['steam']."'",$link) or die(mysql_error());
	(mysql_num_rows($result)==0) ? $value['statid']='<span class="nothing_stat">Не найдено</span>' : $value['statid']="<a href='http://stalin-server.ru/psychostats/player.php?id=".mysql_result($result,0,0)."'>Перейти</a>";
	
	//преобразуем вид для скринов
	$value['screen']= "<a href='http://77.247.239.97/screengalery/index.php?filtrdb=".str_replace ( ':', '_' , $value['steam']."&stype=1'>Найти</a>");
	mysql_select_db('web913612_bans', $link);	
	//прошарим на активные баны
	$result = mysql_query("SELECT bid FROM amx_bans WHERE player_id='".$value['steam']."' ORDER BY ban_created DESC LIMIT 1",$link) or die(mysql_error());

	if(mysql_num_rows($result)==0)
	{	
		//оок, ща он не забанен..тогда прошарим в истории банов ban_history
		$result = mysql_query("SELECT bhid FROM amx_banhistory WHERE player_id='".$value['steam']."' ORDER BY ban_created DESC LIMIT 1",$link) or die(mysql_error());
		if(mysql_num_rows($result)==0)
		{
			$value['ban'] = '<span class="not_banned">В бан листе не найден</span>';	
		}else{
		$row = mysql_fetch_assoc($result);
		$value['ban']="<a class=\"was_banned\" href='http://stalin-server.ru/bah/ban_details.php?bhid=".$row['bhid']."'>Не забанен. Есть история</a>";
		}
			
	}else{
	//ок, забанен ща	
	$row = mysql_fetch_assoc($result);
	
		$value['ban']="<a class=\"banned\" href='http://stalin-server.ru/bah/ban_details.php?bid=".$row['bid']."'>Игрок забанен</a>";

}

	
	
}}
	
	$this->member_name	= $member['members_display_name'];
	$this->output		= $this->registry->getClass('output')->getTemplate('profile')->profileModern( $dt, $tabs, $member, $comment_html, $friends, $visitors, $default_tab, $tab_html);
	
}
	
	/**
	 * Determines where to put custom profile tabs
	 *
	 * @param	array 		$takenPositions		Array of positions that have been used
	 * @param	integer		$requestedPosition	Position to check
	 * @return	integer
	 */
	protected function _getTabPosition( $takenPositions, $requestedPosition )
	{
		if( in_array( $requestedPosition, $takenPositions ) )
		{
			$requestedPosition++;
			$requestedPosition = $this->_getTabPosition( $takenPositions, $requestedPosition );
		}
		
		return $requestedPosition;
	}
 	
 	/**
	 * Adds a recent visitor to ones profile
	 *
	 * @param	array 				Member information
	 * @param	integer				Member id to add
	 * @return	boolean
	 * @since	IPB 2.2.0.2006-7-31
	 */
 	protected function _addRecentVisitor( $member=array(), $member_id_to_add=0 )
 	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$member_id_to_add	= intval( $member_id_to_add );
		$found				= 0;
		$_recent_visitors	= array();
		
		//-----------------------------------------
		// Check...
		//-----------------------------------------
		
		if ( ! $member_id_to_add )
		{
			return false;
		}
		
		//-----------------------------------------
		// Sort out data...
		//-----------------------------------------
		
		$recent_visitors = unserialize( $member['pp_last_visitors'] );
		
		if ( ! is_array( $recent_visitors ) OR ! count( $recent_visitors ) )
		{
			$recent_visitors = array();
		}
		
		foreach( $recent_visitors as $_time => $_id )
		{
			if ( $_id == $member_id_to_add )
			{
				$found  = 1;
				continue;
			}
			else
			{
				$_recent_visitors[ $_time ] = $_id;
			}
		}
		
		$recent_visitors = $_recent_visitors;
	
		krsort( $recent_visitors );
	
		//-----------------------------------------
		// No more than 10
		//-----------------------------------------
	
		if ( ! $found )
		{
			if ( count( $recent_visitors ) > 10 )
			{
				$_tmp = array_pop( $recent_visitors );
			}
		}
		
		//-----------------------------------------
		// Add the visit
		//-----------------------------------------
			
		$recent_visitors[ time() ] = $member_id_to_add;
		
		krsort( $recent_visitors );
		
		//-----------------------------------------
		// Update profile...
		//-----------------------------------------
	
		if ( $member['pp_member_id'] )
		{
			$this->DB->update( 'profile_portal ', array( 'pp_last_visitors' => serialize( $recent_visitors ) ), 'pp_member_id=' . $member['member_id'], true );
		}
		else
		{
			$this->DB->insert( 'profile_portal ', array( 'pp_member_id'		=> $member['member_id'],
															'pp_last_visitors'	=> serialize( $recent_visitors ) 
								), true					);
		}
		
		return true;
	}
}
