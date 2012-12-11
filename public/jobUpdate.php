<?php
//
// Description
// -----------
// This method will update the details for a job, and add a followup note if specified.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:			The ID of the business the service belongs to.
// job_id:				The ID of the service to update.
// status:				(optional) The new status for the service, defaults to 10.
//	
//						10 - entered (default)
//						20 - started
//						30 - pending
//						40 - working
//						60 - completed
//						61 - skipped
//
// name:				(optional) The new name for this job, typically the year or some portion of the date.
// pstart_date:			(optional) The new date of the first day of the time period the job covers.
// pend_date:			(optional) The new date of the last day of the time period the job covers.
// service_date:		(optional) The new date of the job, or the last day of the time period the job covers.
// date_scheduled:		(optional) The new date the job is scheduled for.
// date_started:		(optional) The new date the job was started.
// date_due:			(optional) The new date the job is due to be finished by.
// date_completed:		(optional) The new date the job was completed on.
//
// note:				(optional) A note to attach to the thread of notes.
// 
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_services_jobUpdate($ciniki) {
    //  
    // Find all the required and optional arguments
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        'job_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Job'), 
		'status'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Status',
			'validlist'=>array('10','20','30','40','60','61')),
		'name'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Name'),
		'pstart_date'=>array('required'=>'no', 'blank'=>'no', 'type'=>'date', 'name'=>'Start Date'),
		'pend_date'=>array('required'=>'no', 'blank'=>'no', 'type'=>'date', 'name'=>'End Date'),
		'service_date'=>array('required'=>'no', 'blank'=>'no', 'type'=>'date', 'name'=>'Service Date'),
		'date_scheduled'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Date Scheduled'),
		'date_started'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Date Started'),
		'date_due'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Date Due'),
		'date_completed'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Date Completed'),
		'note'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Note'),
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];
    
    //  
    // Make sure this module is activated, and
    // check permission to run this function for this business
    //  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'services', 'private', 'checkAccess');
    $rc = ciniki_services_checkAccess($ciniki, $args['business_id'], 'ciniki.services.jobUpdate', $args['job_id'], 0); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

	//  
	// Turn off autocommit
	//  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.services');
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	//
	// Add the order to the database
	//
	$strsql = "UPDATE ciniki_service_jobs SET last_updated = UTC_TIMESTAMP()";

	//
	// Add all the fields to the change log
	//
	$changelog_fields = array(
		'name',
		'pstart_date',
		'pend_date',
		'service_date',
		'status',
		'date_scheduled',
		'date_started',
		'date_due',
		'date_completed',
		);
	foreach($changelog_fields as $field) {
		if( isset($args[$field]) ) {
			$strsql .= ", $field = '" . ciniki_core_dbQuote($ciniki, $args[$field]) . "' ";
			$rc = ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.services', 'ciniki_service_history', 
				$args['business_id'], 2, 'ciniki_service_jobs', $args['job_id'], $field, $args[$field]);
		}
	}
	$strsql .= "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $args['job_id']) . "' ";
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.services');
	if( $rc['stat'] != 'ok' ) { 
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.services');
		return $rc;
	}
	if( !isset($rc['num_affected_rows']) || $rc['num_affected_rows'] != 1 ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.services');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'863', 'msg'=>'Unable to update job'));
	}

	//
	// Check if there's a note to add
	//
	if( isset($args['note']) && $args['note'] != '' ) {
		ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'threadAddFollowup');
		$rc = ciniki_core_threadAddFollowup($ciniki, 'ciniki.services', 'ciniki_service_job_notes', 'job', $args['job_id'], array(
			'user_id'=>$ciniki['session']['user']['id'],
			'job_id'=>$args['job_id'],
			'content'=>$args['note'],
			));
		if( $rc['stat'] != 'ok' ) {
			ciniki_core_dbTransactionRollback($ciniki, 'ciniki.services');
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'865', 'msg'=>'Unable to update job'));
		}
	}

	//
	// Commit the database changes
	//
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.services');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'services');

	return array('stat'=>'ok');
}
?>
