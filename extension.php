<?php

require_once 'extension.civix.php';
/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function extension_civicrm_config(&$config) {
  _extension_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function extension_civicrm_xmlMenu(&$files) {
  _extension_civix_civicrm_xmlMenu($files);
}


# Implementation of hook_civicrm_install
function extension_civicrm_install(){
	# Copy attributes from 'Attended' status type to create 'Attended With Friend'
	$result = civicrm_api3('ParticipantStatusType', 'get', array(
		'sequential' => 1,
		'name' => "attended",
    ));
	$class=$result['values'][0]['class'];
	$isReserved=$result['values'][0]['is_reserved'];
	$isCounted=$result['values'][0]['is_counted'];
	$weight=(int)($result['values'][0]['weight']) + 1;
	$visibilityId=$result['values'][0]['visibility_id'];
	$params=array(
		'class' => $class, 
		'is_reserved' => $isReserved,
		'is_active' => '1',
		'is_counted' => $isCounted,
		'weight' => $weight,
		'visibility_id' => $visibilityId
	);
	# Check if 'Attended With Friend' status exists
	$result = civicrm_api3('ParticipantStatusType', 'get', array('sequential' => 1,'name' => "attended with friend",));
	# If status doesn't exist, pass name and label. Otherwise, pass ID so it updates the existing status
	if ($result['count'] == 0){
		$params['name'] = 'Attended With Friend';
		$params['label'] = 'Attended With Friend';
	} else {
		$params['id'] = $result['values']['0']['id'];
	}
	$result = civicrm_api3('ParticipantStatusType', 'create', $params);
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function extension_civicrm_enable() {
  _extension_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function extension_civicrm_disable() {
  _extension_civix_civicrm_disable();
}


# Implementation of hook_civicrm_pre
function extension_civicrm_pre( $op, $objectName, $objectId, &$params ) {	

	# CREATE PARTICIPANT
	if ($objectName == 'Participant' && $op == 'create'){
		$eventId		= $params['event_id'];
		$contactId		= $params['contact_id'];
		$status			= mpk_lookupParticipantStatus($params['status_id']);
		$eventType		= mpk_getEventType($eventId);
		$contactName	= mpk_getContactField($contactId,'display_name');
		$agentTotalField = mpk_getCustomFieldLabel("Total","Agent_Details");
		
		# When creating new participant.. if Agent is GlowKid, make their role GlowKid, otherwise, make their role Agent
		if (mpk_isSubType($contactId,'Agent')){
			$agentTypeLabel	= mpk_getCustomFieldLabel('Type','Agent_Details');
			$agentTypeOptionGroup = mpk_getCustomFieldOptionGroup('Type','Agent_Details');
			$result = civicrm_api3('Contact', 'get', array(
				'sequential' => 1,
				'id' => $contactId,
				'return' => $agentTypeLabel,
			));
			$agentType=mpk_getOptionValueLabel($result['values']['0'][$agentTypeLabel],$agentTypeOptionGroup);
			if (strtolower($agentType)=='glowkids'){
				# set participant role to GlowKid
				$agentRoleId=mpk_getOptionValueId('GlowKid','participant_role');
			} else {
				# set participant role to Agent
				$agentRoleId=mpk_getOptionValueId('Agent','participant_role');
			}
			if ($agentRoleId != -1){
				$params['role_id']=$agentRoleId;
			}
		}
		
		$roleId	= $params['role_id'];
		if (is_array($roleId)) $roleId=$roleId[0]; 
		$roleName = mpk_getOptionValueLabel($roleId,'participant_role');
		
		# Update Agent's total points
		if (mpk_isSubType($contactId,'Agent') && strtolower($roleName)=='agent' ){
			$currentTotal	= mpk_getContactField($contactId,$agentTotalField);
			$newTotal		= $currentTotal;
			# Calculate Agent's Total
			if (strtolower($status)=='attended')				$newTotal=$newTotal+1;
			if (strtolower($status)=='attended with friend')	$newTotal=$newTotal+2;
			
			if ($newTotal!=$currentTotal){
				$returnMsg = "New total for $contactName = $newTotal";
				CRM_Core_Session::setStatus($returnMsg,'Agent total updated','success', array('expires'=>'20000'));
				mpk_setContactField($contactId,$agentTotalField,$newTotal);
			}
		}
	}
	
	# EDIT PARTICIPANT
    if ($objectName == 'Participant' && $op == 'edit') {
		$status 		= mpk_lookupParticipantStatus($params['status_id']);
		$participantId	= $objectId;
		# Get current participant record by looking up participantId
		$result 		= civicrm_api3('Participant', 'get', array(
							'sequential' => 1,
							'id' => $participantId,
							'return' => 'contact_id,event_id,participant_status,participant_role_id'
		));
		$contactId		= $result['values']['0']['contact_id'];
		$eventId		= $result['values']['0']['event_id'];
		$oldStatus		= $result['values']['0']['participant_status'];
		$roleId			= $result['values']['0']['participant_role_id'];
		if (is_array($roleId)) $roleId=$roleId[0]; 
		$roleName		= mpk_getOptionValueLabel($roleId,'participant_role');
		$eventType		= mpk_getEventType($eventId);
		$contactName	= mpk_getContactField($contactId,'display_name');
		$agentTotalField = mpk_getCustomFieldLabel("Total","Agent_Details");
		
		if (mpk_isSubType($contactId,'Agent') && strtolower($roleName)=='agent'){
			$currentTotal	= mpk_getContactField($contactId,$agentTotalField);
			$newTotal 		= $currentTotal;
			# Calculate Agent's Total
			if (strtolower($oldStatus) 	== 'attended')				$newTotal=$newTotal-1;
			if (strtolower($oldStatus) 	== 'attended with friend')	$newTotal=$newTotal-2;
			if (strtolower($status) 	== 'attended')				$newTotal=$newTotal+1;
			if (strtolower($status) 	== 'attended with friend')	$newTotal=$newTotal+2;
			if ($newTotal<0) $newTotal=0;
			
			if ($newTotal!=$currentTotal){
				mpk_setContactField($contactId,$agentTotalField,$newTotal);
				$returnMsg = "New total for $contactName = $newTotal";
				CRM_Core_Session::setStatus($returnMsg,'Agent total updated','success', array('expires'=>'20000'));
			}
		}
	}
	
	# DELETE PARTICIPANT
	if ($objectName == 'Participant' && $op == 'delete'){
		$participantId = $objectId;
		# Get current participant record by looking up participantId
		$result 		= civicrm_api3('Participant', 'get', array(
							'sequential' => 1,
							'id' => $participantId,
							'return' => 'contact_id,event_id,participant_status,participant_role_id'
		));
		$contactId		= $result['values']['0']['contact_id'];
		$eventId		= $result['values']['0']['event_id'];
		$oldStatus		= $result['values']['0']['participant_status'];
		$roleId			= $result['values']['0']['participant_role_id'];
		if (is_array($roleId)) $roleId=$roleId[0]; 
		$roleName		= mpk_getOptionValueLabel($roleId,'participant_role');
		$eventType		= mpk_getEventType($eventId);
		$contactName	= mpk_getContactField($contactId,'display_name');
		$agentTotalField = mpk_getCustomFieldLabel("Total","Agent_Details");
		
		if (mpk_isSubType($contactId,'Agent') && strtolower($roleName)=='agent'){
			$currentTotal	= mpk_getContactField($contactId,$agentTotalField);
			$newTotal		= $currentTotal;
			# Calculate Agent's Total
			if (strtolower($oldStatus)=='attended')				$newTotal=$newTotal-1;
			if (strtolower($oldStatus)=='attended with friend')	$newTotal=$newTotal-2;
			if ($newTotal<0) $newTotal=0;
			
			if ($newTotal!=$currentTotal){
				mpk_setContactField($contactId,$agentTotalField,$newTotal);
				$returnMsg = "New total for $contactName = $newTotal";
				CRM_Core_Session::setStatus($returnMsg,'Agent total updated','success', array('expires'=>'20000'));
			}
		}
		
	}
	
}

# Implementation of hook civicrm_pageRun 
function extension_civicrm_pageRun(&$page){              
	# Get User currently logged in
    $session 	= CRM_Core_Session::singleton();
	$userId 	= $session->get('userID');       
    $pageName 	= $page->getVar('_name');			
				
	# Get associated chapter id (if applicable)
	$userChapter = mpk_getChildChapterId($userId);
	$userEmail = mpk_getContactField($userId,'email');
                
	if ($pageName == 'CRM_Extension_Page_AgentPromotion' && $userChapter!=-1){
		$chapterName=mpk_getContactField($userChapter,"display_name");
		$agentTotalField=mpk_getCustomFieldLabel("Total","Agent_Details");
		$agentTitleField=mpk_getCustomFieldLabel("Agent_Title","Agent_Details");
		
		$agentList = mpk_getChapterAgents($userChapter);
		$emailMsg = "";
		
		foreach ($agentList as &$agentId) {
			$agentName = mpk_getContactField($agentId,'display_name');
			$currentTotal = mpk_getContactField($agentId,$agentTotalField);
			# get existing title
			$result = civicrm_api3('Contact', 'get', array(
				'sequential' => 1,
				'id' => $agentId,
				'return' => $agentTitleField,
			));
			$oldTitle	= $result['values']['0'][$agentTitleField];
			$newTitle 	= mpk_lookupAgentTitle($currentTotal);
			# Set Agent Title
			if ($oldTitle != $newTitle){
				$result = civicrm_api3('Contact', 'create', array(
					'sequential' => 1,
					'id' => $agentId,
					$agentTitleField => $newTitle,
				));
				$msg="$agentName has been promoted to $newTitle!";
				$emailMsg.=$msg."\n";
				CRM_Core_Session::setStatus("$msg",'Agent Promotion','success',array('expires'=>'20000'));
			}
		}
		
		CRM_Core_Session::setStatus("Promotion rules complete for Chapter: $chapterName",'Success','success',array('expires'=>'20000'));
		#mail($userEmail,"Agent Promotion Report",$emailMsg);
	}
	
}

# Implementation of hook civicrm_post
function extension_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {	
	# Get User currently logged in
    $session = CRM_Core_Session::singleton();
	$userId = $session->get('userID');       
	$userName = mpk_getContactField($userId,'display_name');
	
	# Get associated chapter id (if applicable)
	$userChapter = mpk_getChildChapterId($userId);

	
    # CREATE EVENT
    if ($objectName == "Event" && $op == "create" && $userChapter != -1 ) {
		$eventName		= $objectRef->title;			# event title
		$eventId		= $objectId;					# Civi object id of the event
		$eventType		= mpk_getEventType($eventId);	# event type
		$chapterName 	= mpk_getContactField($userChapter,'display_name');	# Chapter name

		if ($eventType!='Mission'){
			return;
		}
		
		# Get list of agent IDs under leader's chapter
		$agentList = mpk_getChapterAgents($userChapter);
		
		
		# Register each agent under the leader's chapter as a participant of the new event
		$agentString="Auto Registered the following agents: ";
		foreach ($agentList as &$agentId) {
			$agentName = mpk_getContactField($agentId,'display_name');
			$agentString = $agentString."[".$agentName."] ";
			mpk_addParticipant($eventId,$agentId);
		}
		
		CRM_Core_Session::setStatus("$agentString",'Auto Registration','success',array('expires'=>'20000'));
		
	}

	# CREATE AGENT
    if ($objectName == "Individual" && $op == "create" && $userChapter != -1 && mpk_isSubType($objectId,"Agent") ) {
		$agentId		= $objectId;									# Civi object id of the new agent
		$agentName      = mpk_getContactField($agentId,'display_name');		# Contact display name
		$chapterName 	= mpk_getContactField($userChapter,'display_name');	# chapter name
		
		# Add agent to the chapter leader's chapter
		$relationshipId = mpk_getRelationshipId('agent member of');
		if ($relationshipId==-1){
			# display message
			return;
		} else {
			mpk_addRelationship($agentId,$userChapter,$relationshipId);
		}
	}
	
	# CREATE PARENT
	if ($objectName == "Individual" && $op == "create" && mpk_isSubType($objectId,"Parent") ) {
		$parentName = mpk_getContactField($objectId,"display_name");
		# Add parent to Parents Group
		$parentsGroupId = mpk_getGroupId("Parents Group");
		$result = mpk_addContactToGroup($objectId,$parentsGroupId);
		if ($result != -1){
			CRM_Core_Session::setStatus("Added $parentName to Parents Group",'Success','success',array('expires'=>'20000'));
		}
	}

	# CREATE CHAPTER
	if ($objectName == "Organization" && $op == "create" && mpk_isSubType($objectId,"Chapter") ) {
		$chapterName 			= mpk_getContactField($objectId,'display_name');
		$chapterLeaderGroupName = trim($chapterName)." Leader Group";
		$chapterMemberGroupName = trim($chapterName)." Member Group";
		$chapterLeaderRoleName = trim($chapterName)." Leader Role";
		
		# Create a new Custom Group of format "Chapter Name Leader Group" and provide Access Control through grooup_type.
 		$resultNewGroup = civicrm_api3('Group', 'create', array(
		  'sequential' => 1,
		  'title' => $chapterLeaderGroupName,
		  'description' => "Leader Group for ".$chapterName,
		  'extends' => "",
 		  'group_type' => [
                "1","2",
            	  ]
		));

		#Get the Leader Group Details
		$result = civicrm_api3('Group', 'get', array(
  		'sequential' => 1,
  		'title' => $chapterLeaderGroupName,
		));
		$customLeaderGroupId = $result['values']['0']['id'];
		
		#Create a new custom group for the Chapter Members
		$customMemberGroup = civicrm_api3('Group', 'create', array(
		  'sequential' => 1,
		  'title' => $chapterMemberGroupName,
		  'description' => "Member Group for ".$chapterName,
		  'extends' => "",
		  'extends' => "",
 		  'group_type' => [
                "1","2",
            	  ]
		));
		
		#Get the Member Group details
		$result = civicrm_api3('Group', 'get', array(
  		'sequential' => 1,
  		'title' => $chapterMemberGroupName,
		));
		$customMemberGroupId = $result['values']['0']['id'];
		$customMemberGroupName= $result['values']['0']['title'];
		

		# Get Option Group for Acl_role and Create Option Value as the respective Chapter Lead Role.
		$AclRole = civicrm_api3('OptionGroup', 'get', array(
			'sequential' => 1,
			'name' => "acl_role",
			'api.OptionValue.create' => array('label' => $chapterLeaderRoleName),
        ));	
		
		$result = civicrm_api3('OptionGroup', 'get', array(
  		'sequential' => 1,
  		'id' => 8,
  		'api.OptionValue.getcount' => array(),
		));
		$LatestCountOfAclRoles = $result['values']['0']['api.OptionValue.getcount'];
		$count = $LatestCountOfAclRoles -1;

		$result = civicrm_api3('OptionGroup', 'get', array(
  			'sequential' => 1,
  			'id' => 8,
  			'api.OptionValue.get' => array('label' => $chapterLeaderRoleName),
		));
		$AclRoleValue = $result['values']['0']['api.OptionValue.get']['values']['0']['value'];

		# Assign ACL role to each Chapter Leader group.
		$assignAclRoleToChapterLeaderGroup = civicrm_api3('AclEntityRole', 'create', array(
  			'sequential' => 1,
  			'acl_role_id' => $AclRoleValue,
  			'entity_table' => "civicrm_group",
  			'entity_id' => $customLeaderGroupId,
  			'is_active' => 1,
		));
		
		# Assign Edit Permission to the new Group to Acl Role.
		$EditAccessChapterMembersGroup = civicrm_api3('Acl', 'create', array(
  			'sequential' => 1,
  			'name' => $customMemberGroupName,
  			'entity_table' => "civicrm_acl_role",
  			'entity_id' => $AclRoleValue,
  			'operation' => "Edit",
  			'object_table' => "civicrm_saved_search",
  			'object_id' => $customMemberGroupId,
  			'is_active' => 1,
		));	
	}
	
	# CREATE RELATIONSHIP
	if ($objectName == "Relationship" && $op == "create" ) {
		$relationshipId		= $objectId;
		$relationshipTypeId	= $objectRef->relationship_type_id;
		$contactIdA 		= $objectRef->contact_id_a;
		$contactIdB			= $objectRef->contact_id_b;
		
		if ($relationshipTypeId==mpk_getRelationshipId('chapter leader of')){
			# Check if contact already has 'chapter leader relationship'
			$result = civicrm_api3('Relationship', 'get', array(
				'sequential' => 1,
				'relationship_type_id' => $relationshipTypeId,
				'contact_id_a' => $contactIdA,
			));
			if ($result['count']>1){
				$result = civicrm_api3('Relationship', 'delete', array(
					'sequential' => 1,
					'id' => $relationshipId,
				));
				$errorMsg="Cannot have more than 1 'chapter leader of' relationship.";
				CRM_Core_Session::setStatus($errorMsg,'Relationship not saved','error',array('expires'=>'20000'));
				return;
			}
			
			# Add contact to chapter leader group
			$chapterName			= mpk_getContactField($contactIdB,'display_name');
			$leaderName				= mpk_getContactField($contactIdA,'display_name');
			$chapterLeaderGroup		= trim($chapterName)." Leader Group";
			$chapterLeaderGroupId 	= mpk_getGroupId($chapterLeaderGroup);
			if ($chapterLeaderGroupId == -1){
				return;
			} else {
				mpk_addContactToGroup($contactIdA,$chapterLeaderGroupId);
				$msg="Added $leaderName to $chapterLeaderGroup";
				CRM_Core_Session::setStatus($msg,'Success','success',array('expires'=>'20000'));
			}
			
			# Add contact to Leaders group (for drupal access sync to work)
			$leadersGroupName = "Chapter Leaders Group";
			$leadersGroupId	= mpk_getGroupId($leadersGroupName);
			if ($leadersGroupId == -1){
				return;
			} else {
				mpk_addContactToGroup($contactIdA,$leadersGroupId);
				$msg="Added $leaderName to '$leadersGroupName' group";
				CRM_Core_Session::setStatus($msg,'Success','success',array('expires'=>'20000'));
			}
			
		}
		
		if ($relationshipTypeId==mpk_getRelationshipId('agent member of')){
			# Add contact to chapter members group
			$chapterName		= mpk_getContactField($contactIdB,'display_name');
			$agentName			= mpk_getContactField($contactIdA,'display_name');
			
			$chapterMemberGroup	= trim($chapterName)." Member Group";
			$memberGroupId	= mpk_getGroupId($chapterMemberGroup);
			if ($memberGroupId == -1 || $memberGroupId == ""){
				return;
			} else {
				$result=mpk_addContactToGroup($contactIdA,$memberGroupId);
				$msg="Added $agentName to $chapterMemberGroup";
				CRM_Core_Session::setStatus($msg,'Success','success',array('expires'=>'20000'));
			}
		}
		
	}
	
}


# Helper function mpk_to get Title for any number of points
function mpk_lookupAgentTitle($totalPoints){
	$title="";
	if ($totalPoints >= 0 && $totalPoints < 6) $title = "Special Agent";
	if ($totalPoints >= 6 && $totalPoints < 12) $title = "Special Agent 1st Class";
	if ($totalPoints >= 12 && $totalPoints < 18) $title = "Master Agent";
	if ($totalPoints >= 18 && $totalPoints < 24) $title = "Master Agent 1st Class";
	if ($totalPoints >= 24 && $totalPoints < 30) $title = "Special Forces Officer";
	if ($totalPoints >= 30 && $totalPoints < 36) $title = "Special Forces Officer 1st Class";
	if ($totalPoints >= 36 && $totalPoints < 42) $title = "Secret Intelligence Officer";
	if ($totalPoints >= 42 && $totalPoints < 48) $title = "Chief Officer";
	if ($totalPoints >= 48 && $totalPoints < 54) $title = "Ambassador";
	if ($totalPoints >= 54 && $totalPoints < 60) $title = "Deputy Commander";
	if ($totalPoints >= 60 && $totalPoints < 66) $title = "Commander";
	if ($totalPoints >= 66 && $totalPoints < 72) $title = "Assistant Director";
	if ($totalPoints >= 72 && $totalPoints < 82) $title = "Deputy Assistant Director";
	if ($totalPoints >= 82 && $totalPoints < 92) $title = "Director";
	if ($totalPoints >= 92 && $totalPoints < 102) $title = "Director, Silver";
	if ($totalPoints >= 102) $title = "Director, Gold";
	return $title;
}

# Helper function mpk_to lookup participant status from status id
function mpk_lookupParticipantStatus($statusId){
	if ($statusId=="" || !is_numeric($statusId)){
		return -1;
	} 
	$result = civicrm_api3('ParticipantStatusType', 'get', array(
		'sequential' => 1,
		'id' => $statusId,
    ));
	if ($result['count']==1){
		return $result['values']['0']['name'];
	} else {
		return -1;
	}
}

# Helper function mpk_to get custom field label. 
function mpk_getCustomFieldLabel($customFieldName,$customGroupName){
	if ($customFieldName=="" || $customGroupName==""){
		return -1;
	}
	$result = civicrm_api3('CustomField', 'get', array(
		'sequential' => 1,
		'custom_group_id' => $customGroupName,
		'name' => $customFieldName,
	));

	if ($result['count']==1){
		$customFieldId=$result['values']['0']['id'];
		$customFieldLabel="custom_$customFieldId";
		return $customFieldLabel;
	} else {
		return -1;
	}
}

# Helper function mpk_to get custom field option group
function mpk_getCustomFieldOptionGroup($customFieldName,$customGroupName){
	if ($customFieldName=="" || $customGroupName==""){
		return -1;
	}
	$result = civicrm_api3('CustomField', 'get', array(
		'sequential' => 1,
		'custom_group_id' => $customGroupName,
		'name' => $customFieldName,
	));

	if ($result['count']==1){
		return $customFieldId=$result['values']['0']['option_group_id'];
	} else {
		return -1;
	}
}

# Helper function mpk_to get Contact field
function mpk_getContactField($contactId, $fieldName){
	if ($fieldName=='' || !is_numeric($contactId)){
		return -1;
	}
	$result = civicrm_api3('Contact', 'get', array(
		'sequential' => 1,
		'id' => $contactId,
		'return' => $fieldName,
	));
	if ($result['count']==1){
		return $result['values']['0'][$fieldName];
	} else {
		return -1;
	}
}

# Helper function mpk_to set Contact field
function mpk_setContactField($contactId,$fieldName,$fieldValue){
	if ($fieldName=='' || !is_numeric($contactId)){
		return -1;
	}
	$result = civicrm_api3('Contact', 'create', array(
		'sequential' => 1,
		'id' => $contactId,
		$fieldName => $fieldValue,
	));
	if ($result['is_error']=='0'){
		return 0;
	} else {
		return -1;
	}
}

# Helper funciton to get Event type
function mpk_getEventType($eventId){
	if (!is_numeric($eventId)) return -1;
	$result = civicrm_api3('Event', 'get', array(
		'sequential' => 1,
		'id' => $eventId,
    ));
	if ($result['count']!=1) return -1;
	$eventTypeId=$result['values']['0']['event_type_id'];
	$result = civicrm_api3('OptionValue', 'get', array(
		'sequential' => 1,
		'option_group_id' => "event_type",
		'value' => $eventTypeId,
    ));
	if ($result['count']!=1) {
		return -1;
	}
	else {
		return $result['values']['0']['name'];
	}
}

# Helper function mpk_to get OptionValue label given id and group name
function mpk_getOptionValueLabel($optionValueId, $optionGroup){
	$result = civicrm_api3('OptionValue', 'get', array(
		'sequential' => 1,
		'option_group_id' => $optionGroup,
		'value' => $optionValueId,
    ));
	if ($result['count']==0) {
		return -1;
	}
	else {
		return $result['values']['0']['label'];
	}
}

# Helper function mpk_to get OptionValue id given label and group name
function mpk_getOptionValueId($optionValueLabel, $optionGroup){
	$result = civicrm_api3('OptionValue', 'get', array(
		'sequential' => 1,
		'option_group_id' => $optionGroup,
		'label' => $optionValueLabel,
    ));
	if ($result['count']==0) {
		return -1;
	}
	else {
		return $result['values']['0']['value'];
	}
}

# Helper function mpk_to get child chapter from "Chapter leader of" relationship_type_id
function mpk_getChildChapterId($contactId){
	if (!is_numeric($contactId)){
		return -1;
	}
	$relationshipId=mpk_getRelationshipId('chapter leader of');
	if ($relationshipId==-1){
		return -1;
	}
	$result = civicrm_api3('Relationship', 'get', array(
	  'sequential' => 1,
	  'contact_id_a' => $contactId,
	  'relationship_type_id' => $relationshipId,
	));
	if ($result['count'] == 1){
		return $result['values']['0']['contact_id_b'];
	} else {
		return -1;
	}
}

# Helper function mpk_to get all agents for a chapter (returns an array)
function mpk_getChapterAgents($contactId){
	if (!is_numeric($contactId)){
		return -1;
	}
	$relationshipId=mpk_getRelationshipId('agent member of');
	if ($relationshipId==-1){
		return -1;
	}
	$result = civicrm_api3('Relationship', 'get', array(
	  'sequential' => 1,
	  'contact_id_b' => $contactId,
	  'relationship_type_id' => $relationshipId,
	));
	if ($result['count']==0 || $result['is_error']!=0){
		return -1;
	}
	$agentList = array();
	foreach ($result['values'] as &$agent){
		array_push($agentList,$agent['contact_id_a']);
	}
	return $agentList;
}

# Helper function mpk_to check if a contact is certain subtype
function mpk_isSubType($contactId,$subType){
	if (!is_numeric($contactId)){
		return false;
	}
	# Get subtype name based on label
	$result = civicrm_api3('ContactType', 'get', array(
		'sequential' => 1,
		'label' => $subType,
	));
	if ($result['count'] != 1){
		return false;
	}
	$subTypeName = $result['values']['0']['name'];
	
	# Get contact's subtype
	$result = civicrm_api3('Contact', 'get', array(
		'sequential' => 1,
		'id' => $contactId,
	));
	$subTypeArray = $result['values']['0']['contact_sub_type'];
	if ($subTypeArray == ""){
		return false;
	}
	foreach ($subTypeArray as $mySubType){
		if ($mySubType == $subTypeName) return true;
	}
	return false;
}

# Helper function mpk_to add a contact as an event participant
function mpk_addParticipant($eventId,$contactId){
	if (!is_numeric($eventId)){
		return -1;
	}
	if (!is_numeric($contactId)){
		return -1;
	}
	$result = civicrm_api3('Participant', 'create', array(
	  'sequential' => 1,
	  'event_id' => $eventId,
	  'contact_id' => $contactId,
	  'role_id' => 'Agent'
	));
	if ($result['is_error'] == 0){
		return 0;
	} else {
		return -1;
	}
}

# Helper function mpk_to add a relationship
function mpk_addRelationship($contactA,$contactB,$relationshipId){
	if (!is_numeric($contactA)){
		return -1;
	}
	if (!is_numeric($contactB)){
		return -1;
	}
	if (!is_numeric($relationshipId)){
		return -1;
	}
	$result = civicrm_api3('Relationship', 'create', array(
		'sequential' => 1,
		'contact_id_a' => $contactA,
		'contact_id_b' => $contactB,
		'relationship_type_id' => $relationshipId,
	));
	if ($result['is_error'] == 0){
		return 0;
	} else {
		return -1;
	}
}

# Helper function mpk_to get relationship id given a to b relationship label
function mpk_getRelationshipId($labelAB){
	$result = civicrm_api3('RelationshipType', 'get', array(
		'sequential' => 1,
		'label_a_b' => $labelAB,
	));
	if ($result['count'] == 1){
		return $result['values']['0']['id'];
	} else {
		return -1;
	}
}

# Helper function to get group id by name
function mpk_getGroupId($groupName){
	$result = civicrm_api3('Group', 'get', array(
		'sequential' => 1,
		'title' => $groupName,
	));
	if ($result['count']==0){
		$errorMsg="$groupName not found";
		CRM_Core_Session::setStatus($errorMsg,'Error','error',array('expires'=>'20000'));
		return -1;
	}
	if ($result['count']>1){
		$errorMsg="Multiple records found for $groupName";
		CRM_Core_Session::setStatus($errorMsg,'Error','error',array('expires'=>'20000'));
		return -1;
	}
	return $result['values']['0']['id'];
}

# Helper function to add a contact to a group
function mpk_addContactToGroup($contactId,$groupId){
	if (!is_numeric($contactId)){
		return -1;
	} 
	if (!is_numeric($groupId)){
		return -1;
	}
	$result = civicrm_api3('GroupContact', 'create' , array(
		'contact_id' => $contactId, 
		'group_id' => $groupId,
	));
}

