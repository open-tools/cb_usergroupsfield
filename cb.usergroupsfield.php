<?php
/**
* Joomla Community Builder User groups Field Type Plugin: plug_cbusergroupsfield
* Lets the administrator change the user's joomla groups in the CB profile in the FE.
* Based on the CBfield_userparams class of the cb.core.php plugin, copyright (C) Beat, JoomlaJoe, www.joomlapolis.com and various
* @version $Id$
* @package plug_cbusergroupsfield
* @subpackage cb.usergroupsfield.php
* @author Reinhold Kainhofer, Open Tools
* @copyright (C) 2014 www.open-tools.net
* @license Limited http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2 or later
* @final 1.2
*/

/** ensure this file is being included by a parent file */
if ( ! ( defined( '_VALID_CB' ) || defined( '_JEXEC' ) || defined( '_VALID_MOS' ) ) ) { die( 'Direct Access to this location is not allowed.' ); }

global $_PLUGINS;
$_PLUGINS->loadPluginGroup( 'user', array( (int) 1 ) );
$_PLUGINS->registerUserFieldTypes( array( 'usergroupsfield' => 'CBfield_usergroups' ) );
$_PLUGINS->registerUserFieldParams();

class CBfield_usergroups extends cbFieldHandler {
	/**
	 * Initializer:
	 * Puts the default value of $field into $user (for registration or new user in backend)
	 *
	 * @param  moscomprofilerFields  $field
	 * @param  moscomprofilerUser    $user
	 * @param  string                $reason      'profile' for user profile view, 'edit' for profile edit, 'register' for registration, 'list' for user-lists
	 */
	function initFieldToDefault( &$field, &$user, $reason ) {
	}
	
	/** Copied from cb.params.php: */
	public function control_name( $control_name, $name ) {
		if ( $control_name ) {
			return $control_name .'['. $name .']';
		} else {
			return $name;
		}
	}
	function control_id( $control_name, $name ) {
		return moscomprofilerHTML::htmlId( $this->control_name( $control_name, $name ) );
	}
	/**
	 * Helper function to display a user groups select box with multiple entries and 
	 * multiple selection for the plugin's param form (the default usergroup type only 
	 * uses a combobox and does not allow multi-selection)
	 */
	function getUsergroups($name,$value,$control_name) {
		// All lists are stored as val1|*|val2|*|val3|*|val4, so we need to explode them to a list
		if ( $value != '' ) {
			$value			=	$this->_explodeCBvalues( $value );
		} else {
			$value			=	array();
		}
	
		$gtree = cbGetAllUsergroupsBelowMe();
		$content	=	moscomprofilerHTML::selectList( $gtree, $this->control_name( $control_name, $name )."[]", 'class="inputbox" multiple="multiple" id="' . $this->control_id( $control_name, $name ) . '" size="8"', 'value', 'text', $value, 2, false, false );
		return $content;
	}
	
	/**
	 * Get a list of the user groups.
	 *
	 * @return	array
	 * @since	1.6
	 */
	protected function getUserGroupsMap() {
		// Initialise variables.
		$db	= JFactory::getDBO();
		$query = $db->getQuery(true)
				->select('a.id AS value, a.title AS text')
				->from('#__usergroups AS a')
				->order('a.lft ASC');

		$db->setQuery($query);
		$options = $db->loadObjectList();
		$map = array();
		foreach ($options as $o) {
			$map[$o->value] = $o->text;
		}

		return $map;
	}
	protected function groupIDsToNames($groupids, $map) {
		$result=array();
		foreach ($groupids as $g) {
			$result[] = $map[$g];
		}
		return $result;
	}


	/**
	 * Accessor:
	 * Returns a field in specified format
	 *
	 * @param  moscomprofilerField   $field
	 * @param  moscomprofilerUser    $user
	 * @param  string                $output  'html', 'xml', 'json', 'php', 'csvheader', 'csv', 'rss', 'fieldslist', 'htmledit'
	 * @param  string                $reason  'profile' for user profile view, 'edit' for profile edit, 'register' for registration, 'list' for user-lists
	 * @param  int                   $list_compare_types   IF reason == 'search' : 0 : simple 'is' search, 1 : advanced search with modes, 2 : simple 'any' search
	 * @return mixed                
	 */
	function getField( &$field, &$user, $output, $reason, $list_compare_types ) {
		global $_CB_framework;
		if ( class_exists( 'JFactory' ) ) {
				$lang						=	JFactory::getLanguage();
				$lang->load( 'com_users' );
		}
		$gids					=	$user->gids;
		$allgroups				=	$this->getUserGroupsMap();
		$restrictedgroups		=	$this->_explodeCBvalues($field->params->get('usergroups', ''));
		$restrict				=	$field->params->get('restrict_display_selected', 1);
		$authorized_groups		=	$this->_explodeCBvalues($field->params->get('moderator_usergroups', ''));
		
		// If display is restricted, filter out only the allowed groups
		if ($restrict) {
			$gids = array_intersect($gids, $restrictedgroups);
			$allgroups = array_intersect_key($allgroups, array_flip($restrictedgroups)); // Intersects on KEYs!
		}

		switch ( $output ) {
			case 'html':
			case 'rss':
				$groupnames 		=	$this->groupIDsToNames($gids, $allgroups);
				$class				=	trim( $field->params->get( 'field_display_class' ) );
				$displayStyle		=	$field->params->get( 'field_display_style' );
				$listType			=	( $displayStyle == 1 ? 'ul' : ( $displayStyle == 2 ? 'ol' : ', ' ) );
				for( $i = 0, $n = count( $groupnames ); $i < $n; $i++ ) {
	   				$groupnames[$i]	=	getLangDefinition( $groupnames[$i] );
				}
				return $this->_arrayToFormat( $field, $groupnames, $output, $listType, $class );
				break;
			
			case 'htmledit':
				if ($reason=='search')
					return null;

				$i_am_super_admin				=	$_CB_framework->acl->amIaSuperAdmin();
				$canModifyUser					=	CBuser::getMyInstance()->authoriseAction( 'core.edit', 'com_users' )
													|| CBuser::getMyInstance()->authoriseAction( 'core.edit.state', 'com_users' );

				$my_groups						= $_CB_framework->acl->getGroupIds($_CB_framework->myId());
				$i_am_authorized				= count(array_intersect($my_groups, $authorized_groups))>0;

				if ( $i_am_super_admin || ($canModifyUser && $i_am_authorized)) {

					// TODO: ensure user can't add group higher than themselves
					$gtree = array();
					foreach ($allgroups as $id=>$g) {
						$gtree[] = (object)array('value'=>"$id", 'text'=>"$g", 'disable'=>in_array($id, $restrictedgroups));
					}

					$disabled				=	'';
					$strgids				=	array_map( 'strval', $gids );
					$size					=	min(11, count($gtree));
					return moscomprofilerHTML::selectList( $gtree, 'gid[]', 'class="inputbox" size="'.$size.'" multiple="multiple"' . $disabled, 'value', 'text', $strgids, 2, false );
				} else {
					return "<div class=\"error_msg cb_usergroups_error\">(You are not authorized to modify the user's group memberships.)</div>";
				}
				break;

			case 'xml':
			case 'json':
			case 'php':
			case 'csv':
				return $this->_arrayToFormat( $field, $gids, $output );

			case 'csvheader':
			case 'fieldslist':
			default:
				return parent::getField( $field, $user, $output, $reason, $list_compare_types );
				break;
		}
		return '';
	}
	/**
	 * Prepares field data for saving to database (safe transfer from $postdata to $user)
	 * Override
	 *
	 * @param  moscomprofilerFields  $field
	 * @param  moscomprofilerUser    $user      RETURNED populated: touch only variables related to saving this field (also when not validating for showing re-edit)
	 * @param  array                 $postdata  Typically $_POST (but not necessarily), filtering required.
	 * @param  string                $reason    'edit' for save profile edit, 'register' for registration, 'search' for searches
	 */
	function prepareFieldDataSave( &$field, &$user, &$postdata, $reason ) {
		$this->_prepareFieldMetaSave( $field, $user, $postdata, $reason );

		global $_CB_framework;

		$i_am_super_admin		=	$_CB_framework->acl->amIaSuperAdmin();
		$canModifyUser			=	CBuser::getMyInstance()->authoriseAction( 'core.edit', 'com_users' )
									|| CBuser::getMyInstance()->authoriseAction( 'core.edit.state', 'com_users' );

		$my_groups				=	$_CB_framework->acl->getGroupIds($_CB_framework->myId());
		$authorized_groups		=	$this->_explodeCBvalues($field->params->get('moderator_usergroups', ''));
		$i_am_authorized		=	count(array_intersect($my_groups, $authorized_groups))>0;

		if ( $i_am_super_admin || ($canModifyUser && $i_am_authorized)) {
			$usergids				=	$user->gids;
			$restrictedgroups		=	$this->_explodeCBvalues($field->params->get('usergroups', ''));
			$setgids				=	cbGetParam( $postdata, 'gid', array( 0 ) );
			// Only modify the groups selected in the field config; I.e. first remove all configured and then only add the selected
			$usergids				=	array_diff ($usergids, $restrictedgroups);
			$usergids				=	array_merge($usergids, $setgids);

			$user->gids				=	$usergids;
			$user->gid				=	(int) $_CB_framework->acl->getBackwardsCompatibleGid( $user->gids );
		} else {
			JFactory::getApplication()->enqueueMessage(JText::_("You are not authorized to modify the user's groups. No changes were done."), 'error');
		}
 	}
}


?>