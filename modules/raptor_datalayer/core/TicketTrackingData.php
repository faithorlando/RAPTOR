<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, et al
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 * Copyright 2015 SAN Business Consultants, a Maryland USA company (sanbusinessconsultants.com)
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ------------------------------------------------------------------------------------
 * 
 */ 

namespace raptor;

require_once 'UserInfo.php';

/**
 * This class is used to manage the ticket tracking information.
 *
 * @author Frank Font of SAN Business Consultants
 */
class TicketTrackingData 
{

    private $m_oWF = NULL;
    
    function __construct($tid = NULL)
    {
        $loaded = module_load_include('php', 'raptor_workflow', 'core/Transitions');
        if(!$loaded)
        {
            $msg = 'Failed to load the Workflow Engine';
            throw new \Exception($msg);      //This is fatal, so stop everything now.
        }
        $this->m_oWF = new \raptor\Transitions();
    }    
    
    /**
     * Generate a unique tracking ID from immutable elements of one record from data store
     * The system can use this ID to then find one and only one record in the data store
     * @return string
     */
    public function getTrackingID($nSiteID, $nIEN)
    {
        return $nSiteID . '-' . $nIEN;
    }
    
    /**
     * Tell us what the ticket type is from the tracking ID
     * @return string
     */
    public function getTicketProcessingMode($sTrackingID)
    {
        $sCWFS = $this->getTicketWorkflowState($sTrackingID);
        return $this->m_oWF->getTicketProcessingModeCodeFromWFS($sCWFS);
    }
    
    /**
     * Expand the code into a phrase.
     */
    public static function getTicketPhraseFromWorflowState($sCWFS)
    {
        return \raptor\Transitions::getTicketPhraseFromWorflowState($sCWFS);
    }

    /**
     * Return the workflow state of a ticket.
     * @param type $sTrackingID must be "SiteID-IEN"
     * @return string
     */
    public function getTicketWorkflowState($sTrackingID)
    {
        try
        {
            $aParts = $this->getTrackingIDParts($sTrackingID);
            $nSiteID = $aParts[0];
            $nIEN = $aParts[1];
            $aWorkflowStateRecord = db_select('raptor_ticket_tracking', 'n')
                ->fields('n')
                ->condition('siteid', $nSiteID,'=')
                ->condition('IEN', $nIEN,'=')
                ->execute()
                ->fetchAssoc();       
            if(!isset($aWorkflowStateRecord['workflow_state']) || $aWorkflowStateRecord['workflow_state'] == '')
            {
                $sCWFS = 'AC';
            } else {
                $sCWFS = $aWorkflowStateRecord['workflow_state'];
            } 
            return $sCWFS;        
        } catch (\Exception $ex) {
            error_log("FAILED getTicketWorkflowState ".$ex->getMessage());
            throw $ex;
        }
    }
    
    /**
     * Returns array if ticket assignment info if ticket is in collaborate mode.
     * Use is_array to see if in collaborate mode.
     * @param type $sTrackingID
     */
    public function getCollaborationInfo($sTrackingID)
    {
        try
        {
            $return = NULL;
            $aParts = $this->getTrackingIDParts($sTrackingID);
            $nSiteID = $aParts[0];
            $nIEN = $aParts[1];
            $result = db_select('raptor_ticket_collaboration','p')
                    ->fields('p')
                    ->condition('siteid',$nSiteID,'=')
                    ->condition('IEN',$nIEN,'=')
                    ->execute();
            if($result == NULL)
            {
                $nRows = 0;
            } else {
                $nRows = $result->rowCount();
            }
            if($nRows > 0)
            {
                //Return the fields of the found record.
                $return = $result->fetchAssoc();
            }
            return $return;
        } catch (\Exception $ex) {
            error_log("FAILED getCollaborationInfo ".$ex->getMessage());
            throw $ex;
        }
    }
    
    /**
     * Set or clear the collaboration status of a ticket.
     * @param type $sCWFS current workflow status
     */
    public function setCollaborationUser($sTrackingID, $nRequesterUID
            , $sRequesterNote, $nCollaboratorUID
            , $sCWFS=NULL
            , $updated_dt=NULL)
    {
        $successMsg = NULL;
        if($sCWFS == NULL)
        {
            $sCWFS = $this->getTicketWorkflowState($sTrackingID);
        }
        if($updated_dt == NULL)
        {
            $updated_dt = date("Y-m-d H:i:s", time());
        }
        $aParts = $this->getTrackingIDParts($sTrackingID);
        $nSiteID = $aParts[0];
        $nIEN = $aParts[1];

        //Make sure we are okay to reserve this ticket.
        if($sCWFS !== 'AC' && $sCWFS !== 'AP' && $sCWFS !== 'CO' && $sCWFS !== 'RV')
        {
            $msg = 'Only tickets in the active or approved or collaborate status can be reserved!  Ticket ' 
                    . $sTrackingID . ' is in the [' .$sCWFS. '] state!';
            error_log($msg);
            throw new \Exception($msg);
        }
        
        try
        {
            //If we are here, make sure we end up with a raptor_ticket_tracking record too.
            db_merge('raptor_ticket_tracking')
                ->key(
                        array('siteid'=>$nSiteID
                                ,'IEN' => $nIEN,
                    ))
                ->fields(array(
                        'updated_dt'=>$updated_dt,
                    ))
                ->execute();         
            
        } catch (\Exception $ex) {
            throw $ex;
        }

        //Create the raptor_ticket_collaboration record now
        try
        {
            if($nCollaboratorUID == NULL)
            {
                //Simply delete the existing collaboration record if it exists.
                $num_deleted = db_delete('raptor_ticket_collaboration')
                    ->condition('siteid',$nSiteID,'=')
                    ->condition('IEN',$nIEN,'=')
                    ->execute();
                $successMsg = 'Ticket '.$sTrackingID.' is longer assigned to anyone.';
            } else {
                $result = db_select('raptor_ticket_collaboration','p')
                        ->fields('p')
                        ->condition('siteid',$nSiteID,'=')
                        ->condition('IEN',$nIEN,'=')
                        ->condition('collaborator_uid',$nCollaboratorUID,'=')
                        ->execute();
                if($result == NULL)
                {
                    $nRows = 0;
                } else {
                    $nRows = $result->rowCount();
                }
                if($nRows > 0)
                {
                    $successMsg = 'Already assigned ' . $sTrackingID;
                } else {
                    $result = db_select('raptor_ticket_collaboration','p')
                            ->fields('p')
                            ->condition('siteid',$nSiteID,'=')
                            ->condition('IEN',$nIEN,'=')
                            ->condition('collaborator_uid',$nCollaboratorUID,'<>')
                            ->execute();
                    if($result == NULL)
                    {
                        $nRows = 0;
                    } else {
                        $nRows = $result->rowCount();
                    }
                    $oInsert = db_merge('raptor_ticket_collaboration')
                            ->key(array('siteid' => $nSiteID,'IEN' => $nIEN,))
                            ->fields(array(
                                'siteid' => $nSiteID,
                                'IEN' => $nIEN,
                                'requester_uid' => $nRequesterUID,
                                'requested_dt' => $updated_dt,
                                'requester_notes_tx' => $sRequesterNote,
                                'collaborator_uid' => $nCollaboratorUID,
                                'active_yn' => 1,
                            ))
                            ->execute();
                    if($nRows > 0)
                    {
                        $successMsg = 'Replaced other user assignment ' . $sTrackingID;
                    } else {
                        $successMsg = 'Assigned other user assignment ' . $sTrackingID;
                    }
                }
            }
        }
        catch(\Exception $e)
        {
            error_log('Failed to create raptor_ticket_collaboration: ' 
                    . $e 
                    . "\nDetails..." . print_r($oInsert,TRUE));
            throw new \Exception('Failed to reserve ['.$sTrackingID.'] ticket!',99123,$e);
        }

        /* DO NOT USE CO AS OF 20150702
        //Did we collaborate or remove collaboration?
        $sNewWFS = ''; 
        if($sCWFS == 'CO' && $nCollaboratorUID == NULL)
        {
            //AC is the non-collaboration equivalent of CO
            $sNewWFS = 'AC'; 
        } else if($sCWFS == 'AC' && $nCollaboratorUID !== NULL) {
            //CO is the collaboration equivalent of AC
            $sNewWFS = 'CO'; 
        }
        if($sNewWFS > '')
        {
            //Changing the WFS value
            $this->setTicketWorkflowState($sTrackingID, $nRequesterUID
                , $sNewWFS, $sCWFS, $updated_dt);
        }
        */
        
        return $successMsg;
    }
    
    /**
     * Update the database with ticket state
     */
    public function setTicketWorkflowState($sTrackingID, $nUID, $sNewWFS
            , $sCWFS=NULL, $updated_dt=NULL)
    {
        if($sCWFS == NULL)
        {
            $sCWFS = $this->getTicketWorkflowState($sTrackingID);
        }
        if($updated_dt == NULL)
        {
            $updated_dt = date("Y-m-d H:i:s", time());
        }
        $aParts = $this->getTrackingIDParts($sTrackingID);
        $nSiteID = $aParts[0];
        $nIEN = $aParts[1];
        
        //Try to create the raptor_ticket_tracking record now
        try
        {
            $aFields = array(
                        'siteid' => $nSiteID,
                        'IEN' => $nIEN,
                        'workflow_state' => $sNewWFS,
                        'updated_dt' => $updated_dt,
                );
            if($sNewWFS == 'AP')
            {
                $aFields['approved_dt'] = $updated_dt;
            }
            if($sNewWFS == 'PA')
            {
                $aFields['acknowledged_dt'] = $updated_dt;
            }
            if($sNewWFS == 'EC')
            {
                $aFields['exam_completed_dt'] = $updated_dt;
            }
            if($sCWFS !== 'QA' && $sNewWFS == 'QA') //20140808
            {
                $aFields['interpret_completed_dt'] = $updated_dt;
            }
            if($sCWFS === 'QA' && $sNewWFS == 'QA') //20140808
            {
                $aFields['qa_completed_dt'] = $updated_dt;
            }
            if($sNewWFS == 'IA')
            {
                $aFields['suspended_dt'] = $updated_dt;
            }
            $oInsert = db_merge('raptor_ticket_tracking')
                    ->key(array('siteid'=>$nSiteID, 'IEN'=>$nIEN))
                    ->fields($aFields)
                    ->execute();
        }
        catch(\Exception $ex)
        {
            throw new \Exception("Failed to update raptor_ticket_tracking for $sTrackingID, $nUID, $sNewWFS, $sCWFS", 99123, $ex);
        }

        //Create the raptor_ticket_workflow_history record now
        try
        {
            $oInsert = db_insert('raptor_ticket_workflow_history')
                    ->fields(array(
                        'siteid' => $nSiteID,
                        'IEN' => $nIEN,
                        'initiating_uid' => $nUID,
                        'old_workflow_state' => $sCWFS,
                        'new_workflow_state' => $sNewWFS,
                        'created_dt' => $updated_dt,
                    ))
                    ->execute();
        }
        catch(\Exception $e)
        {
            throw new \Exception("Failed to update "
                    . "raptor_ticket_workflow_history for $sTrackingID, $nUID, $sNewWFS, $sCWFS" 
                    , 99123, $ex);
        }
    }

    /**
     * Update the database
     * @return int Description 0 for failed, 1 for success
     */
    public function setTicketAsReplaced($currentTID, $newTID, $nUID, $updated_dt=NULL)
    {
        $sNewWFS = 'IA';
        if($updated_dt == NULL)
        {
            $updated_dt = date("Y-m-d H:i:s", time());
        }
        $aParts = $this->getTrackingIDParts($currentTID);
        $nSiteID = $aParts[0];
        $current_IEN = $aParts[1];

        $aParts = $this->getTrackingIDParts($newTID);
        $new_IEN = $aParts[1];
        
        //Try to create the raptor_ticket_tracking record now
        try
        {
            $aWorkflowStateRecord = db_select('raptor_ticket_tracking', 'n')
                ->fields('n')
                ->condition('siteid', $nSiteID,'=')
                ->condition('IEN', $current_IEN,'=')
                ->execute()
                ->fetchAssoc();       
            
            //Make sure we have real values.
            if(!isset($aWorkflowStateRecord['workflow_state']) || $aWorkflowStateRecord['workflow_state'] == '')
            {
                $sCWFS = 'AC';
                $the_date = $updated_dt;
            } else {
                $sCWFS = $aWorkflowStateRecord['workflow_state'];
                $the_date = $aWorkflowStateRecord['updated_dt'];;
            } 
        
            //Create the new record
            $num = db_insert('raptor_ticket_tracking')
                    ->fields(array(
                        'siteid' => $nSiteID,
                        'IEN' => $new_IEN,
                        'workflow_state' => $sCWFS,
                        'suspended_dt' => $aWorkflowStateRecord['suspended_dt'],
                        'approved_dt' => $aWorkflowStateRecord['approved_dt'],
                        'exam_completed_dt' => $aWorkflowStateRecord['exam_completed_dt'],
                        'interpret_completed_dt' => $aWorkflowStateRecord['interpret_completed_dt'],
                        'qa_completed_dt' => $aWorkflowStateRecord['qa_completed_dt'],
                        'exam_details_committed_dt' => $aWorkflowStateRecord['exam_details_committed_dt'],
                        'updated_dt' => $the_date,
                    ))
                    ->execute();            

            //Move any collaboration records
            $nUpdated = db_update('raptor_ticket_collaboration')
                    -> fields(array(
                        'siteid' => $nSiteID,
                        'IEN' => $new_IEN,
                    ))
            ->condition('IEN',$current_IEN,'=')
            ->execute();

            //Move any contraindication acknowledgement records
            $nUpdated = db_update('raptor_ticket_contraindication')
                    -> fields(array(
                        'siteid' => $nSiteID,
                        'IEN' => $new_IEN,
                    ))
            ->condition('IEN',$current_IEN,'=')
            ->execute();
            
            //Move any checklist records
            $nUpdated = db_update('raptor_ticket_checklist')
                    -> fields(array(
                        'siteid' => $nSiteID,
                        'IEN' => $new_IEN,
                    ))
            ->condition('IEN',$current_IEN,'=')
            ->execute();

            //Move any schedule records
            $nUpdated = db_update('raptor_schedule_track')
                    -> fields(array(
                        'siteid' => $nSiteID,
                        'IEN' => $new_IEN,
                    ))
            ->condition('IEN',$current_IEN,'=')
            ->execute();
            
            //Move the raptor_ticket_protocol_settings record now
            try
            {
                $nUpdated = db_update('raptor_ticket_protocol_settings')
                        -> fields(array(
                            'siteid' => $nSiteID,
                            'IEN' => $new_IEN,
                        ))
                ->condition('IEN',$current_IEN,'=')
                ->execute();
            }
            catch(\Exception $ex)
            {
                error_log('Failed to create raptor_ticket_protocol_settings: ' . print_r($ex,TRUE));
                drupal_set_message('Failed to save information for this ticket because ' . $ex->getMessage(),'error');
                throw($ex);
            }

            //Move all the existing notes
            try
            {
                $nUpdated = db_update('raptor_ticket_protocol_notes')
                        -> fields(array(
                            'siteid' => $nSiteID,
                            'IEN' => $new_IEN,
                        ))
                ->condition('IEN',$current_IEN,'=')
                ->execute();
            } catch (\Exception $ex) {
                error_log('Failed to copy existing raptor_ticket_protocol_notes: ' . $e);
                drupal_set_message('Failed to copy existing notes to the '.$new_IEN.' ticket!','error');
                throw($ex);
            }
            
            //Create a new raptor_ticket_protocol_notes record now
            try
            {
                $oInsert = db_insert('raptor_ticket_protocol_notes')
                    ->fields(array(
                        'siteid' => $nSiteID,
                        'IEN' => $new_IEN,
                        'notes_tx' => "Replaced VISTA order $currentTID with order $newTID",
                        'author_uid' => $nUID,
                        'created_dt' => $updated_dt,
                    ))
                    ->execute();
            }
            catch(\Exception $e)
            {
                error_log('Failed to create raptor_ticket_protocol_notes: ' . $e);
                drupal_set_message('Failed to save notes for the '.$targetTID.' ticket!','error');
                throw($ex);
            }
            
            //Now change the state of the original record
            $this->setTicketWorkflowState($currentTID, $nUID, $sNewWFS, $sCWFS, $updated_dt);
        }
        catch(\Exception $e)
        {
            error_log('Failed to update raptor_ticket_tracking for replacement: ' . $e->getMessage());
            drupal_set_message('Failed to change workflow status of this ticket!','error');
            throw $e;
        }
    }
    
    /**
     * Delete all lock records for a user.
     */
    public function deleteAllUserTicketLocks($nUID, $entire_delete_reason=NULL)
    {
        if($entire_delete_reason != NULL)
        {
            error_log($entire_delete_reason);
        }
        if($nUID == '')
        {
            throw new \Exception('Cannot deleteAllUserTicketLocks because no UID was provided!');
        }
        try
        {
            $query = db_delete('raptor_ticket_lock_tracking')
                ->condition('locked_by_uid', $nUID,'=')
                    ->execute();
        } catch (\Exception $ex) {
            error_log("FAILED deleteAllUserTicketLocks ".$ex->getMessage());
            throw $ex;
        }
    }

    private function deleteUserTicketLocks($nSiteID, $locked_by_uid, $locktype='E')
    {
        try
        {
            $query = db_delete('raptor_ticket_lock_tracking')
                ->condition('siteid', $nSiteID,'=');
            $query->condition('locked_by_uid', $locked_by_uid, '=');
            if($locktype !== NULL)
            {
                $query->condition('locked_type_cd', $locktype, '=');
            }
            $deleted = $query->execute();            
        } catch (\Exception $ex) {
            error_log('Failed to delete locks for user '.$locked_by_uid.' because '.$ex->getMessage());
            throw $ex;
        }
    }
    
    /**
     * Delete any kind of lock record
     */
    private function deleteTicketLock($sTrackingID, $locked_by_uid=NULL
            , $entire_delete_reason=NULL
            , $locktype=NULL, $filteroperator='=')
    {
        $aParts = $this->getTrackingIDParts($sTrackingID);
        $nSiteID = $aParts[0];
        $nIEN = $aParts[1];
        $deleted = 0;
        try
        {
            $query = db_delete('raptor_ticket_lock_tracking')
                ->condition('siteid', $nSiteID,'=')
                ->condition('IEN', $nIEN,'=');
            if($locked_by_uid != NULL)
            {
                $query->condition('locked_by_uid', $locked_by_uid, '=');
            }
            if($locktype != NULL)
            {
                $query->condition('locked_type_cd', $locktype, $filteroperator);
            }
            $deleted = $query->execute();            
        } catch (\Exception $ex) {
            error_log('Failed to delete '.$sTrackingID.' because '.$ex->getMessage());
            throw $ex;
        }
        if($deleted == 1)
        {
            if($entire_delete_reason != NULL)
            {
                error_log($entire_delete_reason);
            }
        } else {
            //Don't bother if there was nothing to delete.
            if($deleted != 0)
            {
                error_log('Expected to delete one ticket="'.$sTrackingID.'"'
                        . ' but instead deleted '.$deleted.' records!'
                        . ' >>>query='.print_r($query,TRUE)
                        );
            }
        }
        return $deleted;
    }
    
    /**
     * Delete an edit lock record
     */
    public function deleteTicketEditLock($sTrackingID, $uid=NULL, $delete_reason=NULL)
    {
        $aParts = $this->getTrackingIDParts($sTrackingID);
        $nSiteID = $aParts[0];
        $nIEN = $aParts[1];
        if($delete_reason != NULL)
        {
            $entire_delete_reason = 'Deleting edit lock for '.$sTrackingID.' because '.$delete_reason;
        } else {
            $entire_delete_reason = NULL;
        }
        return $this->deleteTicketLock($sTrackingID, $uid, $entire_delete_reason, 'E', '=');
    }

    /**
     * Delete a non-edit lock record
     */
    public function deleteTicketNonEditLock($sTrackingID, $delete_reason=NULL)
    {
        $aParts = $this->getTrackingIDParts($sTrackingID);
        $nSiteID = $aParts[0];
        $nIEN = $aParts[1];
        if($delete_reason != NULL)
        {
            $entire_delete_reason = 'Deleting non-edit lock for '.$sTrackingID.' because '.$delete_reason;
        } else {
            $entire_delete_reason = NULL;
        }
        $this->deleteTicketLock($sTrackingID, NULL, $entire_delete_reason, 'E', '<>');
    }

    /**
     * Get details for all current record locks
     *   details[tickets][tid] <-- lookup the ticket here
     *   details[users][uid] <-- lookup the fullname here
     */
    public function getAllTicketLockDetails()
    {
        try
        {
            $details = array();
            $details['tickets'] = array();
            $details['users'] = array();

            $this->deleteAllStaleTicketLocks(VISTA_SITE);

            $query = db_select('raptor_ticket_lock_tracking', 'n');
            $query->join('raptor_user_profile', 'u', 'n.locked_by_uid = u.uid');
            $query->fields('n');
            $query->fields('u', array('username','usernametitle','firstname','lastname','suffix'))
                ->orderBy('IEN');
            $result = $query->execute();
            while($record = $result->fetchAssoc())
            {
                $oneticket = array();
                $oneticket['siteid'] = $record['siteid'];
                $oneticket['IEN'] = $record['IEN'];
                $oneticket['locked_type_cd'] = $record['locked_type_cd'];
                $oneticket['lock_started_dt'] = $record['lock_started_dt'];
                $oneticket['lock_refreshed_dt'] = $record['lock_refreshed_dt'];
                $lbuid = $record['locked_by_uid'];
                $oneticket['locked_by_uid'] = $lbuid;
                $sTID = $record['siteid'].'-'.$record['IEN'];
                $details['tickets'][$sTID] = $oneticket;
                if(!isset($details['users'][$lbuid]))
                {
                    //Add this user to the lookup information.
                    $fullname = trim($record['usernametitle'] . ' ' 
                            . $record['firstname'] . ' ' 
                            . $record['lastname'] . ' ' 
                            . $record['suffix']);
                    $oneuser = array();
                    $oneuser['uid'] = $lbuid;
                    $oneuser['fullname'] = $fullname;
                    $details['users'][$lbuid] = $oneuser;
                }
            }
            return $details;
        } catch (\Exception $ex) {
            error_log("FAILED getAllTicketLockDetails because ".$ex->getMessage());
            throw $ex;
        }
    }
    
    
    /**
     * Get details for one or more lock records of a ticket
     */
    public function getTicketLockDetails($sTrackingID
            ,$locktypefilter='E'
            ,$uidfilter=NULL
            ,$limit_one_rec=TRUE)
    {
        try
        {
            $aParts = $this->getTrackingIDParts($sTrackingID);
            $nSiteID = $aParts[0];
            $nIEN = $aParts[1];
            $query = db_select('raptor_ticket_lock_tracking', 'n')
                ->fields('n')
                ->condition('siteid', $nSiteID,'=')
                ->condition('IEN', $nIEN,'=');
            if($locktypefilter !== NULL)
            {
                //Apply the filter.
                $query->condition('locked_type_cd', $locktypefilter,'=');
            }
            if($uidfilter !== NULL)
            {
                //Apply the filter.
                $query->condition('locked_by_uid', $uidfilter,'=');
            }
            $result = $query->execute();
            if($result != NULL && $result->rowCount() > 0)
            {
                if($limit_one_rec && $result->rowCount() > 1)
                {
                    throw new \Exception('Too many edit lock records ('
                            .$result->rowCount().') found for '
                            .$sTrackingID.'>>>'.print_r($result,TRUE));
                }
                //Return the lock record details as an array.
                return $result->fetchAssoc();       
            }
            //No lock record found.
            return NULL;
        } catch (\Exception $ex) {
            error_log("FAILED getTicketLockDetails because ".$ex->getMessage());
            throw $ex;
        }
    }
    
    /**
     * Delete all the stale records
     * Logic: If user has not accessed the site in more than USER_EDITLOCK_TIMEOUT_SECONDS
     *        the the edit lock is removed.  
     */
    public function deleteAllStaleTicketLocks($nSiteID, $extralogmessage=NULL)
    {
        try
        {
            //error_log('STARTING deleteAllStaleTicketLocks');
            $maxage = USER_EDITLOCK_TIMEOUT_SECONDS;
            $maxloginage = USER_EDITLOCK_TIMEOUT_SECONDS * 10;  //Failsafe based on login time
            $oldestallowed_ts = time() - $maxage;
            $oldestallowed_dt = date("Y-m-d H:i:s", $oldestallowed_ts);
            $oldestlogin_ts = time() - $maxloginage;
            //First check the raptor table
            $query = db_select('raptor_ticket_lock_tracking', 'n');
            $query->leftJoin('users', 'u', 'n.locked_by_uid = u.uid');
            //too many rows $query->leftJoin('sessions', 's', 'n.locked_by_uid = s.uid');
            $query->fields('n');
            //$query->fields('s', array('uid'));
            $query->fields('u', array('uid','access'));
            $query->condition('n.siteid', $nSiteID,'=');
            //$db_or = db_or();
            //$db_or->condition('n.lock_refreshed_dt', $oldestallowed_dt,'<');
            //$db_or->isNull('s.uid');
            //$query->condition($db_or);
            $result = $query->execute();
            $mycount=0;
            $mydeleted=0;
            foreach($result as $row)
            {
                $mycount++;
                $sTrackingID = $nSiteID.'-'.$row->IEN;
                $currently_locked_by_uid = $row->locked_by_uid;
                $delete = FALSE;
                if($row->uid  == NULL || !UserInfo::isUserOnline($row->uid))
                {
                    //Not logged in.
                    $delete = TRUE;
                    $entire_delete_reason = 'Deleted stale lock on '.$sTrackingID
                            .' because '.$currently_locked_by_uid
                            .' user is not logged in >>> '.print_r($row,TRUE);
                } else {
                    $lock_refreshed_ts = strtotime($row->lock_refreshed_dt);    //Because DATE is not a timestamp!!!
                    if($lock_refreshed_ts < $oldestallowed_ts) {
                        //Locked ticket is too old from raptor lock check.
                        $diff = $lock_refreshed_ts - $oldestallowed_ts;
                        $delete = TRUE;
                        $entire_delete_reason = 'Deleted stale lock on '.$sTrackingID
                                .' because lock refresh '.$lock_refreshed_ts
                                .' ('.$row->lock_refreshed_dt.')'
                                .' is older than '.$oldestallowed_ts
                                .' ('.$oldestallowed_dt.')'
                                .' diff='.$diff
                                .' >>> '.print_r($row,TRUE);
                    } else if($row->access < $oldestlogin_ts) {
                        //Locked ticket is too old from core table check.
                        $oldestlogin_dt = date("Y-m-d H:i:s", $oldestlogin_ts);
                        $diff = $row->access - $oldestallowed_ts;
                        $delete = TRUE;
                        $entire_delete_reason = 'Deleted stale lock on '.$sTrackingID
                                .' because access '.$row->access
                                .' is older than '.$oldestlogin_ts
                                .' (login access '.$oldestlogin_dt.')'
                                .' diff='.$diff
                                .' >>> '.print_r($row,TRUE);
                    }
                }
                if($delete)
                {
                    if($extralogmessage != NULL)
                    {
                        $entire_delete_reason .= ' ('.$extralogmessage.')';
                    }
                    $mydeleted++;
                    $this->deleteTicketLock($sTrackingID, $currently_locked_by_uid, $entire_delete_reason);
                }
            }
            if($mydeleted>0)
            {
                error_log("Deleted $mydeleted stale locks from existing list of $mycount locks found in database.");
            }
        } catch (\Exception $ex) {
            error_log("FAILED deleteAllStaleTicketLocks because ".$ex->getMessage());
            throw $ex;
        }
    }
    
    /**
     * Queries the database to see if ticket is locked by other user for editing
     * @return boolean
     */
    public function isTicketEditLockedByOtherUser($sTrackingID, $nUID)
    {
        try
        {
            $lockrec = $this->getTicketLockDetails($sTrackingID);
            if($lockrec == NULL)
            {
                //No edit lock exists.
                return FALSE;
            }

            //See if lock is still valid.
            $nowtime = time();
            $lockage = $nowtime - $lockrec['lock_refreshed_dt'];
            if($lockage > USER_TIMEOUT_SECONDS + USER_TIMEOUT_GRACE_SECONDS)
            {
                //Ticket is too old, kill it!
                $delete_reason = 'expired lock record: '.print_r($lockrec,TRUE);
                $this->deleteTicketEditLock($sTrackingID, NULL, $delete_reason);
                //No lock exists now.
                return FALSE;
            }

            //Locked by another user?
            return ($lockrec['locked_by_uid'] != $nUID);
        } catch (\Exception $ex) {
            error_log("FAILED isTicketEditLockedByOtherUser because ".$ex->getMessage());
            throw $ex;
        }
    }
    
    /**
     * Update the timestamp on an existing ticket lock record
     */
    public function updateTicketEditLock($sTrackingID, $nUID, $updated_dt = NULL)
    {
        try
        {
            $aParts = $this->getTrackingIDParts($sTrackingID);
            $nSiteID = $aParts[0];
            $nIEN = $aParts[1];
            if($updated_dt == NULL)
            {
                $updated_dt = date("Y-m-d H:i:s", time());
            }
            $num_updated  = db_update('raptor_ticket_lock_tracking')
                    ->fields(array(
                        'lock_refreshed_dt' => $updated_dt,
                    ))
                    ->condition('siteid',$nSiteID,'=')
                    ->condition('IEN', $nIEN,'=')
                    ->condition('locked_type_cd','E','=')
                    ->condition('locked_by_uid',$nUID,'=')
                    ->execute();
            //Only throw an exception if we updated MORE than one.  Leave zero alone.
            if($num_updated > 1)
            {
                throw new \Exception('Expected to update one ticket edit lock for '.$sTrackingID.' but instead updated '.$num_updated);
            }
        } catch (\Exception $ex) {
            error_log('Failed to update edit lock for '.$sTrackingID.' because '.print_r($ex,TRUE));
            throw $ex;
        }
    }
    
    /**
     * Return parts of the tracking ID and throw exception if anything missing
     */
    private function getTrackingIDParts($sTrackingID)
    {
        $aParts = explode('-',$sTrackingID);
        if(count($aParts) !== 2)
        {
            throw new \Exception('Invalid format for tracking ID "'
                    .$sTrackingID.'"!');
        }
        return $aParts;
    }
    
    /**
     * Update the ticket as locked for editing
     * Release other edit locks (only one allowed at a time)
     */
    public function markTicketEditLocked($sTrackingID, $nUID, $updated_dt = NULL)
    {
        $lockrec = $this->getTicketLockDetails($sTrackingID);
        if($lockrec != NULL)
        {
            if($lockrec['locked_by_uid'] != $nUID)
            {
                throw new \Exception('Cannot mark '.$sTrackingID
                        .' with edit lock because already locked '
                        .print_r($lockrec,TRUE));
            }
            //Already locked.
            return;
        }
        $aParts = $this->getTrackingIDParts($sTrackingID);
        $nSiteID = $aParts[0];
        $nIEN = $aParts[1];
        if($updated_dt == NULL)
        {
            $updated_dt = date("Y-m-d H:i:s", time());
        }
        $this->deleteUserTicketLocks($nSiteID,$nUID);   //Delete all other existing Edit locks now
        try
        {
            db_insert('raptor_ticket_lock_tracking')
                ->fields(array(
                    'siteid' => $nSiteID,
                    'IEN' => $nIEN,
                    'locked_by_uid' => $nUID,
                    'locked_type_cd' => 'E',
                    'lock_started_dt' => $updated_dt,
                    'lock_refreshed_dt' => $updated_dt,
                ))
                ->execute();
        } catch (\Exception $ex) {
            error_log('Failed to insert edit lock for '.$sTrackingID
                    .' because '.print_r($ex,TRUE));
            throw $ex;
        }
    }

    /**
     * Verify no other user has the lock and then delete it
     */
    public function markTicketUnlocked($sTrackingID, $nUID)
    {
        try
        {
            //Check for an existing edit lock first.
            $lockrec = $this->getTicketLockDetails($sTrackingID,'E');
            if($lockrec != NULL)
            {
                //Found an edit lock.
                if($lockrec['locked_by_uid'] != $nUID)
                {
                    //Try again after killing all stale tickets
                    $this->deleteAllStaleTicketLocks(VISTA_SITE);   
                    $lockrec = $this->getTicketLockDetails($sTrackingID,'E');
                    if($lockrec['locked_by_uid'] != $nUID)
                    {
                        //Hmm, was not a stale ticket.
                        throw new \Exception('User '.$nUID
                           .' cannot delete edit lock on '
                           .$sTrackingID
                           .' because it belongs to '
                           .$lockrec['locked_by_uid']);
                    }
                }
                $this->deleteTicketEditLock($sTrackingID);
            } else {
                //No edit lock, but there may be other kinds. (e.g., View)
                $lockrec = $this->getTicketLockDetails($sTrackingID,NULL,$nUID);
                if($lockrec != NULL)
                {
                    $this->deleteTicketNonEditLock($sTrackingID);
                }
            }
        } catch (\Exception $ex) {
            error_log("FAILED markTicketUnlocked because ".$ex->getMessage());
            throw $ex;
        }
    }
    
    public function getTicketModalityMap()
    {
        $map = array();
        try
        {
            $sql = 'select t1.ien, t2.protocol_shortname, t2.modality_abbr ' 
               . ' from raptor_ticket_protocol_settings t1 '
               . ' left join raptor_protocol_lib t2 '
               . ' on t1.primary_protocol_shortname = t2.protocol_shortname '
               . ' order by t1.ien';
            $result = db_query($sql);
            if($result->rowCount() > 0)
            {
                while($record = $result->fetchAssoc())
                {
                    $ien = $record['ien'];
                    $map["TID$ien"] = $record;
                }
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
        return $map;
    }
    
    
    /**
    /* simply create a "dictionary" organized by key field IEN
     */
    private function createMapOnIEN($sqlResult) 
    {
        try
        {
            $result = array();

            if ($sqlResult->rowCount() === 0) {
                return $result;
            }

            foreach ($sqlResult as $row) 
            {
                $key = $row->IEN;
                if(!isset($row->workflow_state) || $row->workflow_state == NULL)
                {
                    $row->workflow_state = 'AC';    //Because NULL means AC.
                }
                $result[$key] = $row;
            }

            return $result;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    
    /**
     * Return consolidated information of all the tickets
     */
    public function getConsolidatedWorklistTracking() 
    {
        try
        {
            $sql = "SELECT * FROM raptor_ticket_tracking";
            $sqlResult = db_query($sql);
            $ticketTrackingResult = $this->createMapOnIEN($sqlResult);

            $sql = "SELECT c.IEN, c.collaborator_uid, c.requester_notes_tx, c.requested_dt, u.username, u.usernametitle, u.firstname, u.lastname, u.suffix FROM raptor_ticket_collaboration c left join raptor_user_profile u on c.collaborator_uid=u.uid WHERE active_yn";
            $sqlResult = db_query($sql);
            $ticketCollaborationResult = $this->createMapOnIEN($sqlResult);

            $sql = "SELECT * FROM raptor_schedule_track";
            $sqlResult = db_query($sql);
            $scheduleTrackResult = $this->createMapOnIEN($sqlResult);

            return array(
                "raptor_ticket_tracking" => $ticketTrackingResult,
                "raptor_ticket_collaboration" => $ticketCollaborationResult,
                "raptor_schedule_track" => $scheduleTrackResult);
        } catch (\Exception $ex) {
            error_log("FAILED getConsolidatedWorklistTracking because $ex");
            throw $ex;
        }
    }
}
