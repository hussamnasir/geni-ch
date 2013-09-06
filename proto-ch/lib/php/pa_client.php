<?php
//----------------------------------------------------------------------
// Copyright (c) 2012-2013 Raytheon BBN Technologies
//
// Permission is hereby granted, free of charge, to any person obtaining
// a copy of this software and/or hardware specification (the "Work") to
// deal in the Work without restriction, including without limitation the
// rights to use, copy, modify, merge, publish, distribute, sublicense,
// and/or sell copies of the Work, and to permit persons to whom the Work
// is furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Work.
//
// THE WORK IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
// HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE WORK OR THE USE OR OTHER DEALINGS
// IN THE WORK.
//----------------------------------------------------------------------

// Client-side interface to GENI Clearinghouse Project Authority (PA)
// Consists of these methods:
//   project_id <= create_project(sa_url, project_name, lead_id, lead_email, purpose, expiration);
//   project_ids <= get_projects(sa_url);
//   [project_name, lead_id, project_email, project_purpose] <= lookup_project(project_id);
//   update_project(sa_url, project_name, project_id, project_email, project_purpose, expiration);
//   change_lead(sa_url, project_id, previous_lead_id, new_lead_id);
//   get_project_members(sa_url, project_id, role=null) // null => Any
//   get_projects_for_member(sa_url, member_id, is_member, role=null)
//   lookup_project_details(sa_url, project_uuids)
//   modify_project_membership(sa_url, signer, project_id, 
//			 members_to_add, members_to_change_role, members_to_remove)
//   add_project_member(sa_url, project_id, member_id, role)
//   remove_project_member(sa_url, project_id, member_id)
//   change_member_role(sa_url, project_id, member_id, role)
//   lookup_project_attributes(sa_url, project_id)
//   add_project_attribute(sa_url, signer, project_id, name, value)


require_once('pa_constants.php');
require_once 'chapi.php';

// A cache of a user's detailed info indexed by member_id
if(!isset($project_cache)) {
  //  error_log("SETTING PROJECT_CACHE");
  $project_cache = array();
}

// Create a project with given name, lead_id (UUID of lead member), email to contact on all 
// matters related to project, and documentation purpose of project
function create_project($sa_url, $signer, $project_name, $lead_id, $project_purpose, $expiration)
{
  $client = new XMLRPCClient($sa_url, $signer);

  if (! $expiration) {
    $expiration = "";
  }

  $fields = array('PROJECT_NAME'          => $project_name,
		  '_GENI_PROJECT_LEAD_ID' => $lead_id,
		  '_GENI_PROJECT_PURPOSE' => $project_purpose,
		  'PROJECT_EXPIRATION'    => $project_expiration);
  $results = $client->create_project($client->get_credentials(), array('fields'=>$fields));
  $project_id = $results['PROJECT_UID'];

  return $project_id;
}

// return list of project ids
function get_projects($sa_url, $signer)
{
  $client = new XMLRPCClient($sa_url, $signer);
  $options = array('match'=>array(),
		   'filter'=>array('PROJECT_UID'));
  $res = $client->lookup_projects($client->get_credentials(), $options);
  return array_map(function($x) { return $x['PROJECT_UID']; }, $slices);
}

// return list of project ids
function get_projects_by_lead($sa_url, $signer, $lead_id)
{
  $client = new XMLRPCClient($sa_url, $signer);
  $options = array('match'=>array('_GENI_PROJECT_LEAD'=>$lead_id),
		   'filter'=>array('PROJECT_UID'));
  $res = $client->lookup_projects($client->get_credentials(), $options);
  return array_map(function($x) { return $x['PROJECT_UID']; }, $slices);
}

$PACHAPI2PORTAL = array('PROJECT_UID'=>PA_PROJECT_TABLE_FIELDNAME::PROJECT_ID,
			'PROJECT_URN'=>PA_PROJECT_TABLE_FIELDNAME::PROJECT_URN,
			'PROJECT_NAME'=>PA_PROJECT_TABLE_FIELDNAME::PROJECT_NAME,
			'_GENI_PROJECT_OWNER'=>PA_PROJECT_TABLE_FIELDNAME::LEAD_ID,
			'_GENI_PROJECT_EMAIL'=>PA_PROJECT_TABLE_FIELDNAME::PROJECT_EMAIL,
			'PROJECT_CREATION'=>PA_PROJECT_TABLE_FIELDNAME::CREATION,
			'PROJECT_DESCRIPTION'=>PA_PROJECT_TABLE_FIELDNAME::PROJECT_PURPOSE,
			'PROJECT_EXPIRATION'=>PA_PROJECT_TABLE_FIELDNAME::EXPIRATION,
			'_GENI_PROJECT_EXPIRED'=>PA_PROJECT_TABLE_FIELDNAME::EXPIRED);

$DETAILSKEYS = array('PROJECT_UID',
		     'PROJECT_URN',
		     'PROJECT_NAME',
		     '_GENI_PROJECT_OWNER',
		     '_GENI_PROJECT_EMAIL',
		     'PROJECT_CREATION',
		     'PROJECT_DESCRIPTION',
		     'PROJECT_EXPIRATION',
		     '_GENI_PROJECT_EXPIRED');

function details_chapi2portal($row)
{
  global $PACHAPI2PORTAL;
  $nrow = array();
  foreach ($row as $k=>$v) {
    $nrow[$PACHAPI2PORTAL[$k]] = $v;
  }
  return $nrow;
}

// Return project details
function lookup_projects($sa_url, $signer, $lead_id=null)
{
  $client = new XMLRPCClient($sa_url, $signer);
  $match = array();
  if ($lead_id <> null) {
    $match['_GENI_PROJECT_LEAD']=$lead_id;
  }
  global $DETAILSKEYS;
  $options = array('match'=>$match,
		   'filter'=>$DETAILSKEYS);
  $res = $client->lookup_projects($client->get_credentials(), $options);
  $results = array();

  foreach ($res as $row) {
    $results[] = details_chapi2portal($row);
  }

  return $results;
}

function get_project_urn($sa_url, $signer, $project_id) {
  $details = lookup_project($sa_url, $signer, $project_id);
  return $details[PA_PROJECT_TABLE_FIELDNAME::PROJECT_URN];
}

// Return project details
function lookup_project($sa_url, $signer, $project_id)
{
  global $project_cache;
  if (! is_object($signer)) {
    throw new InvalidArgumentException('Null signer');
  }

  if (array_key_exists($project_id, $project_cache)) {
    //    error_log("CACHE HIT lookup_project " . $project_id);
    return $project_cache[$project_id];
  }

  $client = new XMLRPCClient($sa_url, $signer);
  $options = array('match'=>array('PROJECT_UID'=>$project_id),
		   'filter'=>$DETAILSKEYS);
  $res = $client->lookup_projects($client->get_credentials(), $options);
  $details = array();

  foreach ($res as $row) {
    $details[] = details_chapi2portal($row);
  }
  //  error_log("LP.end " . $project_id . " " . time());
  // return null if empty
  if (sizeof($details)==0) {
    return null;
  }

  // FIXME: Could be >1? 
  $details = $details[0];  // just take the first match
  $project_cache[$project_id] = $details;
  
  return $details;
}

// Return project details
function lookup_project_by_name($sa_url, $signer, $project_name)
{
  global $project_cache;
  if (! is_object($signer)) {
    throw new InvalidArgumentException('Null signer');
  }

  $client = new XMLRPCClient($sa_url, $signer);
  $options = array('match'=>array('PROJECT_NAME'=>$project_name),
		   'filter'=>$DETAILSKEYS);
  $res = $client->lookup_projects($client->get_credentials(), $options);
  $details = array();

  foreach ($res as $row) {
    $details[] = details_chapi2portal($row);
  }
  //  error_log("LP.end " . $project_id . " " . time());
  // return null if empty
  if (sizeof($details)==0) {
    return null;
  }

  // FIXME: Could be >1? 
  $details = $details[0];  // just take the first match
  
  return $details;
}

// FIXME: lookup_projects_member(sa_url, member_id, is_member, role)
// FIXME: lookup_projects_ids(sa_url, project_ids_list)

function update_project($sa_url, $signer, $project_id, $project_name,
			$project_purpose, $expiration)
{
  $project_urn = get_project_urn($sa_url, $signer, $project_id);

  $client = new XMLRPCClient($sa_url, $signer);
  $options = array('fields'=>array('PROJECT_NAME'=>$project_name,
				   'PROJECT_DESCRIPTION'=>$project_purpose,
				   'PROJECT_EXPIRATION'=>$project_expiration));

  $res = $client->update_projects($project_urn, $client->get_credentials(), $options);
  $results = array();
  return $results;
}


function _conv_mid2urn($sa_url, $signer, $alist)
{
  return array_map(function ($mid) { return get_member_urn(sa_to_ma_url($sa_url), $signer, $mid); }, $alist);
}

function _conv_mid2urn_map($sa_url, $signer, $amap)
{
  $nmap = array();
  foreach ($amap as $mid => $v) {
    $murn = get_member_urn(sa_to_ma_url($sa_url), $signer, $mid);
    $nmap[$murn] = $v;
  }
  return $nmap;
}

// Modify project membership according to given lists to add/change_role/remove
// $members_to_add and $members_to_change role are both
//     dictionaries of {member_id => role, ....}
// $members_to_delete is a list of member_ids
function modify_project_membership($sa_url, $signer, $project_id, 
				   $members_to_add, 
				   $members_to_change, 
				   $members_to_remove)
{
  $members_to_add = _conv_mid2urn_map($sa_url, $signer, $members_to_add);
  $members_to_change = _conv_mid2urn_map($sa_url, $signer, $members_to_change);
  $members_to_remove = _conv_mid2urn($sa_url, $signer, $members_to_remove);
  
  $options = array();
  if (sizeof($members_to_add)>0)    { $options['members_to_add']    = $members_to_add; }
  if (sizeof($members_to_change)>0) { $options['members_to_change'] = $members_to_change; }
  if (sizeof($members_to_remove)>0) { $options['members_to_remove'] = $members_to_remove; }
  modify_project_membership($project_urn, $client->get_credentials(), $options);
}


// mik was here

// Modify project lead, make previous into an admin
// Assumes lead is already a member
function change_lead($sa_url, $signer, $project_id, $prev_lead_id, $new_lead_id)
{
  $members_to_change = array($prev_lead_id => CS_ATTRIBUTE_TYPE::ADMIN, 
			     $new_lead_id => CS_ATTRIBUTE_TYPE::LEAD);
  $result = modify_project_membership($sa_url, $signer, $project_id,
				      array(), $members_to_change, array());
  return $result;
}

// Add a member of given role to given project
function add_project_member($sa_url, $signer, $project_id, $member_id, $role)
{
  $member_roles = array($member_id => $role);
  $result = modify_project_membership($sa_url, $signer, $project_id, 
				    $member_roles, array(), array());
  return $result;
}

// Remove a member from given project
function remove_project_member($sa_url, $signer, $project_id, $member_id)
{
  $member_to_remove = array($member_id);
  $result = modify_project_membership($sa_url, $signer, $project_id, 
				    array(), array(), $member_to_remove);
  return $result;
}

// Change role of given member in given project
function change_member_role($sa_url, $signer, $project_id, $member_id, $role)
{
  $member_roles = array($member_id => $role);
  $result = modify_project_membership($sa_url, $signer, $project_id, 
				    array(), $member_roles, array());
  return $result;
}

// Return list of member ID's and roles associated with given project
// If role is provided, filter to members of given role
function get_project_members($sa_url, $signer, $project_id, $role=null) 
{
  $get_project_members_message['operation'] = 'get_project_members';
  $get_project_members_message[PA_ARGUMENT::PROJECT_ID] = $project_id;
  $get_project_members_message[PA_ARGUMENT::ROLE_TYPE] = $role;
  $results = put_message($sa_url, $get_project_members_message, 
			 $signer->certificate(), $signer->privateKey());
  return $results;
}

// Return list of project ID's for given member_id
// If is_member is true, return projects for which member is a member
// If is_member is false, return projects for which member is NOT a member
// If role is provided, filter on projects 
//    for which member has given role (is_member = true)
//    for which member does NOT have given role (is_member = false)
function get_projects_for_member($sa_url, $signer, $member_id, $is_member, $role=null)
{
  if (! is_object($signer)) {
    throw new InvalidArgumentException('Null signer');
  }

  //  $cert = $signer->certificate();
  //  $key = $signer->privateKey();
  //  $get_projects_message['operation'] = 'get_projects_for_member';
  //  $get_projects_message[PA_ARGUMENT::MEMBER_ID] = $member_id;
  //  $get_projects_message[PA_ARGUMENT::IS_MEMBER] = $is_member;
  //  $get_projects_message[PA_ARGUMENT::ROLE_TYPE] = $role;
  //  $results = put_message($sa_url, $get_projects_message,
  //			 $cert, $key, 
  //			 $signer->certificate(), $signer->privateKey());

  global $user;
  $options = array('_dummy' => null);
  $client = new XMLRPCClient($sa_url, $signer);
  $member_urn = $user->urn; 
  $rows = $client->lookup_projects_for_member($member_urn, $client->get_credentials(), $options);
  $project_uuids = array();
  foreach($rows as $row) {
    $project_uuid = $row['PROJECT_UID'];
    array_push($project_uuids, $project_uuid);
  }
  return $project_uuids;
}

// Convert slice details from external (CH API compliant) names
// to internal (database field) names
function convert_project_to_internal($project)
{
  return array(
	       PA_PROJECT_TABLE_FIELDNAME::PROJECT_ID => $project['PROJECT_UID'],
	       PA_PROJECT_TABLE_FIELDNAME::PROJECT_NAME => $project['PROJECT_NAME'],
	       PA_PROJECT_TABLE_FIELDNAME::PROJECT_PURPOSE => $project['PROJECT_DESCRIPTION'],
	       PA_PROJECT_TABLE_FIELDNAME::EXPIRATION => $project['PROJECT_EXPIRATION'],
	       PA_PROJECT_TABLE_FIELDNAME::EXPIRED => $project['PROJECT_EXPIRED'],
	       PA_PROJECT_TABLE_FIELDNAME::CREATION => $project['PROJECT_EXPIRED'],
	       PA_PROJECT_TABLE_FIELDNAME::PROJECT_EMAIL => $project['_GENI_PROJECT_EMAIL'],
	       PA_PROJECT_TABLE_FIELDNAME::LEAD_ID => $project['_GENI_PROJECT_OWNER']);
	       
}



function lookup_project_details($sa_url, $signer, $project_uuids)
{
  //  $cert = $signer->certificate;
  //  $key = $signer->privateKey();
  //  $get_projects_message['operation'] = 'lookup_project_details';
  //  $get_projects_message[PA_ARGUMENT::PROJECT_UUIDS] = $project_uuids;
  //  $results = put_message($sa_url, $get_projects_message,
  //			 $cert, $key, 
  //			 $signer->certificate(), $signer->privateKey());
  //			 
  //  $results2 = array();
  //  foreach ($results as $project) {
  //  	  $results2[ $project['project_id'] ] = $project;
  //  }			 
  //  return $results2;

  //  error_log("PIDS = " . print_r($project_uuids, true));

  global $user;
  $client = new XMLRPCClient($sa_url, $signer);
  $options = array('match' => array('PROJECT_UID' => $project_uuids));
  $results = $client->lookup_projects($client->get_credentials(), $options);
  //  error_log("LPD.RESULTS = " . print_r($results, true));
  $converted_projects = array();
  foreach($results as $project) {
    $converted_projects[] = convert_project_to_internal($project);
  }
  $results = $converted_projects;
  //  error_log("LPD.RESULTS = " . print_r($results, true));

  return $results;

}

// Routines to invite and accept invivations for members to projects

// Generate an invitation for a (not yet identified) member
// to join a project
// return the invitation ID and expiration, `
function invite_member($sa_url, $signer, $project_id, $role)
{
  $invite_member_message['operation'] = 'invite_member';
  $invite_member_message[PA_ARGUMENT::PROJECT_ID] = $project_id;
  $invite_member_message[PA_ARGUMENT::ROLE_TYPE] = $role;
  $result = put_message($sa_url, $invite_member_message, 
		       $signer->certificate(), $signer->privateKey());
  return $result;
}

// Accept an invitation
function accept_invitation($sa_url, $signer, $invitation_id)
{
  global $user;
  $accept_invitation_message['operation'] = 'accept_invitation';
  $accept_invitation_message[PA_ARGUMENT::INVITATION_ID] = $invitation_id;
  $accept_invitation_message[PA_ARGUMENT::MEMBER_ID] = $user->account_id;
  $result = put_message($sa_url, $accept_invitation_message, 
			$signer->certificate(), $signer->privateKey());
  return $result;
}

// Look up all attributes of a given project
function lookup_project_attributes($sa_url, $signer, $project_id)
{
  global $user;
  $lookup_project_attributes_message['operation'] = 'lookup_project_attributes';
  $lookup_project_attributes_message[PA_ARGUMENT::PROJECT_ID] = $project_id;
  $results = put_message($sa_url, $lookup_project_attributes_message, 
			 $signer->certificate(), $signer->privateKey());
  return $results;
}

// Add attribute (name/value pair) to a given project
function add_project_attribute($sa_url, $signer, $project_id, $name, $value)
{
  global $user;
  $add_project_attribute_message['operation'] = 'add_project_attribute';
  $add_project_attribute_message[PA_ARGUMENT::PROJECT_ID] = $project_id;
  $add_project_attribute_message[PA_ATTRIBUTE::NAME] = $name;
  $add_project_attribute_message[PA_ATTRIBUTE::VALUE] = $value;
  $results = put_message($sa_url, $add_project_attribute_message, 
			 $signer->certificate(), $signer->privateKey());
  return $results;
}




?>
