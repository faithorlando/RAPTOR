<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, Alex Podlesny, et al
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 * 
 */ 

namespace raptor_ewdvista;

/**
 * Helper for returning worklist content
 *
 * @author Frank Font of SAN Business Consultants
 */
class WorklistHelper
{
    
    //Worklist EWD vista result value keys
    const WLVFO_IEN             = 'IEN';                    // 0;
    const WLVFO_PatientID             = 'PatientID';	                       // 1;
    const WLVFO_PatientName           = 'PatientName';	                      // 2;
    const WLVFO_ExamCategory          = 'ExamCategory';	                      // 3;
    const WLVFO_RequestingPhysician   = 'RequestingPhysician';	             // 4;
    const WLVFO_OrderedDate           = 'OrderedDate';	                     // 5;
    const WLVFO_Procedure             = 'Procedure';	                           // 6;
    const WLVFO_ImageType             = 'ImageType';	                           // 7;
    const WLVFO_ExamLocation          = 'ExamLocation';	                           // 8;
    const WLVFO_Urgency               = 'Urgency';	                               // 9;
    const WLVFO_Nature                = 'Nature';	                               // 10;
    const WLVFO_Transport             = 'Transport';	                           // 11;
    const WLVFO_DesiredDate           = 'DesiredDate';	                          // 12;
    const WLVFO_OrderFileIen          = 'OrderFileIen';	                          // 13;
    const WLVFO_RadOrderStatus        = 'RadOrderStatus';                  // 14;
    
    
    private function getScheduleMarkupArray($sqlScheduleTrackRow)
    {
        try
        {
            // Pull schedule from raptor_schedule_track
            if($sqlScheduleTrackRow != NULL)
            {
                //If a record exists, then there is something to see.
                $showText = '';
                if(isset($sqlScheduleTrackRow->scheduled_dt))
                {
                    $phpdate = strtotime( $sqlScheduleTrackRow->scheduled_dt );
                    $sdt = date( 'Y-m-d H:i', $phpdate ); //Remove the seconds
                    if(isset($sqlScheduleTrackRow->confirmed_by_patient_dt))
                    {
                        if($showText > '')
                        {
                           $showText .= '<br>'; 
                        }
                        $showText .= 'Confirmed '.$sqlScheduleTrackRow->confirmed_by_patient_dt; 
                    }
                    if($showText > '')
                    {
                       $showText .= '<br>'; 
                    }
                    $showText .= 'For '. $sdt ;//$sqlScheduleTrackRow->scheduled_dt; 
                    if(isset($sqlScheduleTrackRow->location_tx))
                    {
                        if($showText > '')
                        {
                           $showText .= '<br>'; 
                        }
                        $showText .= 'In ' . $sqlScheduleTrackRow->location_tx; 
                    }
                }
                if(isset($sqlScheduleTrackRow->canceled_dt))
                {
                    //If we are here, clear everything before.
                    $showText = 'Cancel requested '.$sqlScheduleTrackRow->canceled_dt; 
                }
                if(trim($sqlScheduleTrackRow->notes_tx) > '')
                {
                    if($showText > '')
                    {
                       $showText .= '<br>'; 
                    }
                    $showText .= 'See Notes...'; 
                }
                if($showText == '')
                {
                    //Indicate there is someting to see in the form.
                    $showText = 'See details...';
                }
                $markup_ar = array(
                    'EventDT' => $sqlScheduleTrackRow->scheduled_dt,
                    'LocationTx' => $sqlScheduleTrackRow->location_tx,
                    'ConfirmedDT' => $sqlScheduleTrackRow->confirmed_by_patient_dt,
                    'CanceledDT' => $sqlScheduleTrackRow->canceled_dt,
                    'ShowTx' => $showText
                );
                //print_r($sqlScheduleTrackRow, TRUE);
            } else {
                //No record exists yet.
                $markup_ar = array(
                    'EventDT' => NULL,
                    'LocationTx' => NULL,
                    'ConfirmedDT' => NULL,
                    'CanceledDT' => NULL,
                    'ShowTx' => 'Unknown'
                );
            }
            return $markup_ar;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Return the rows formatted the way RAPTOR expects to parse them.
     */
    public function getFormatWorklistRows($rawdatarows)
    {
        try
        {
            if(!is_array($rawdatarows))
            {
                throw new \Exception("Cannot parse worklist content from ".print_r($rawdatarows,TRUE));
            }
            if(!array_key_exists('data',$rawdatarows))
            {
                throw new \Exception("Missing the 'data' key in worklist result ".print_r($rawdatarows,TRUE));
            }

            module_load_include('php', 'raptor_formulas', 'core/LanguageInference');
            module_load_include('php', 'raptor_formulas', 'core/MatchOrderToUser');
            module_load_include('php', 'raptor_datalayer', 'core/TicketTrackingData');
            $oTT = new \raptor\TicketTrackingData();
            $ticketTrackingDict = $oTT->getConsolidatedWorklistTracking();
            $ticketTrackingRslt = $ticketTrackingDict['raptor_ticket_tracking'];
            $ticketCollabRslt = $ticketTrackingDict['raptor_ticket_collaboration'];
            $scheduleTrackRslt = $ticketTrackingDict['raptor_schedule_track'];

            $aPatientPendingOrderCount = array();
            $aPatientPendingOrderMap = array();
            $nOffsetMatchIEN = NULL;
            
            $oContext = \raptor\Context::getInstance();
            $userinfo = $oContext->getUserInfo();

            
            $match_order_to_user = new \raptor_formulas\MatchOrderToUser($userinfo);
            $language_infer = new \raptor_formulas\LanguageInference();
            $unformatted_datarows = $rawdatarows['data'];
            //error_log("LOOK raw data for worklist>>>".print_r($unformatted_datarows,TRUE));
            if(is_array($unformatted_datarows))
            {
                $rowcount = count($unformatted_datarows);
            } else {
                $rowcount = 0;
            }
            //error_log("LOOK TODO implement reformat of $rowcount raw data rows");
            $formatted_datarows = array();
            $rownum = 0;
            $last_ien = NULL;
            foreach($unformatted_datarows as $onerow)
            {
                if(isset($onerow['PatientID']) && isset($onerow['Procedure']))
                {
                    $ienKey = $onerow[self::WLVFO_IEN];
                    $patientID = $onerow['PatientID'];
                    $sqlTicketTrackRow = array_key_exists($ienKey, $ticketTrackingRslt) ? $ticketTrackingRslt[$ienKey] : NULL;
                    $sqlTicketCollaborationRow = array_key_exists($ienKey, $ticketCollabRslt) ? $ticketCollabRslt[$ienKey] : NULL;
                    $sqlScheduleTrackRow = array_key_exists($ienKey, $scheduleTrackRslt) ? $scheduleTrackRslt[$ienKey] : NULL;
                    $workflowstatus = (isset($sqlTicketTrackRow) ? $sqlTicketTrackRow->workflow_state : 'AC');
                    $studyname = $onerow['Procedure'];
                    $imagetype = $onerow['ImageType'];
                    $modality = $language_infer->inferModalityFromPhrase($imagetype);
                    if($modality == NULL)
                    {
                        $modality = $language_infer->inferModalityFromPhrase($studyname);
                    }
                    //Add the clean row to our collection of rows.
                    if($modality > '')
                    {
                        //We have usable data for this one.
                        $rownum++;
                        $cleanrow = array();
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_TRACKINGID] = $ienKey;
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_PATIENTID] = $patientID;
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_PATIENTNAME] = $onerow[self::WLVFO_PatientName];
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_DATETIMEDESIRED] = $onerow[self::WLVFO_DesiredDate];
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_DATEORDERED] = $onerow[self::WLVFO_OrderedDate];
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_MODALITY] = $modality;
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_STUDY] = $studyname;
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_URGENCY] = $onerow[self::WLVFO_Urgency];
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_TRANSPORT] = $onerow[self::WLVFO_Transport];
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_PATIENTCATEGORYLOCATION ] = $onerow[self::WLVFO_ExamCategory];
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_ANATOMYIMAGESUBSPEC] = 'TODO ANATOMY';   //Placeholder for anatomy keywords
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_WORKFLOWSTATUS] = $workflowstatus;
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_ORDERSTATUS] = '?ORDER STATUS?';   //Placeholder for Order Status
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_EDITINGUSER]  = '';   //Placeholder for UID of user that is currently editing the record, if any. (check local database)
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_CPRSCODE] = '';   //Placeholder for the CPRS code associated with this ticket
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_IMAGETYPE] = $imagetype;
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_SCHEDINFO] = $this->getScheduleMarkupArray($sqlScheduleTrackRow);

                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_COUNTPENDINGORDERSSAMEPATIENT] = 'todo123';
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_MAPPENDINGORDERSSAMEPATIENT] = 'todo20'; 
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_EXAMLOCATION] = $onerow[self::WLVFO_ExamLocation];
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_REQUESTINGPHYSICIAN] = $onerow[self::WLVFO_RequestingPhysician];
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = 23;
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_ORDERFILEIEN] = 24;
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_RADIOLOGYORDERSTATUS] = 25;
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_ISO8601_DATETIMEDESIRED] = 26;
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_ISO8601_DATEORDERED] = 27;
                        $rfs = trim($onerow[self::WLVFO_Nature]);
                        switch ($rfs)
                        {
                            case 'w' :
                                $cleanrow[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "WRITTEN";
                                break;
                            case 'v' :
                                $cleanrow[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "VERBAL";
                                break;
                            case 'p' :
                                $cleanrow[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "TELEPHONED";
                                break;
                            case 's' :
                                $cleanrow[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "SERVICE CORRECTION";
                                break;
                            case 'i' :
                                $cleanrow[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "POLICY";
                                break;
                            case 'e' :
                                $cleanrow[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "PHYSICIAN ENTERED";
                                break;
                            default :
                                if(strlen($rfs)==0)
                                {
                                    $cleanrow[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = "NOT ENTERED";
                                } else {
                                    $cleanrow[\raptor\WorklistColumnMap::WLIDX_NATUREOFORDERACTIVITY] = $rfs;
                                }
                                break;
                        }

                        //Only show an assignment if ticket has not yet moved downstream in the workflow.
                        if($workflowstatus == 'AC' 
                            || $workflowstatus == 'CO' 
                            || $workflowstatus == 'RV')
                        {
                            $aPatientPendingOrderMap[$patientID][$ienKey] 
                                    = array($ienKey,$modality,$studyname);
                            if(isset($aPatientPendingOrderCount[$patientID]))
                            {
                                $aPatientPendingOrderCount[$patientID] +=  1; 
                            } else {
                                $aPatientPendingOrderCount[$patientID] =  1; 
                            }
                            $cleanrow[\raptor\WorklistColumnMap::WLIDX_ASSIGNEDUSER] = (isset($sqlTicketCollaborationRow) ? array(
                                                                      'uid'=>$sqlTicketCollaborationRow->collaborator_uid
                                                                    , 'requester_notes_tx'=>$sqlTicketCollaborationRow->requester_notes_tx
                                                                    , 'requested_dt'=>$sqlTicketCollaborationRow->requested_dt
                                                                    , 'username'=>$sqlTicketCollaborationRow->username
                                                                    , 'fullname'=>trim($sqlTicketCollaborationRow->usernametitle 
                                                                            . ' ' .$sqlTicketCollaborationRow->firstname
                                                                            . ' ' .$sqlTicketCollaborationRow->lastname. ' ' .$sqlTicketCollaborationRow->suffix )
                                                                ) : NULL);    
                        } else {
                            $cleanrow[\raptor\WorklistColumnMap::WLIDX_ASSIGNEDUSER] = '';
                        }

                        // Pull schedule from raptor_schedule_track
                        if($sqlScheduleTrackRow != null)
                        {
                            //If a record exists, then there is something to see.
                            $showText = '';
                            if(isset($sqlScheduleTrackRow->scheduled_dt))
                            {
                                $phpdate = strtotime( $sqlScheduleTrackRow->scheduled_dt );
                                $sdt = date( 'Y-m-d H:i', $phpdate ); //Remove the seconds
                                if(isset($sqlScheduleTrackRow->confirmed_by_patient_dt))
                                {
                                    if($showText > '')
                                    {
                                       $showText .= '<br>'; 
                                    }
                                    $showText .= 'Confirmed '.$sqlScheduleTrackRow->confirmed_by_patient_dt; 
                                }
                                if($showText > '')
                                {
                                   $showText .= '<br>'; 
                                }
                                $showText .= 'For '. $sdt ;//$sqlScheduleTrackRow->scheduled_dt; 
                                if(isset($sqlScheduleTrackRow->location_tx))
                                {
                                    if($showText > '')
                                    {
                                       $showText .= '<br>'; 
                                    }
                                    $showText .= 'In ' . $sqlScheduleTrackRow->location_tx; 
                                }
                            }
                            if(isset($sqlScheduleTrackRow->canceled_dt))
                            {
                                //If we are here, clear everything before.
                                $showText = 'Cancel requested '.$sqlScheduleTrackRow->canceled_dt; 
                            }
                            if(trim($sqlScheduleTrackRow->notes_tx) > '')
                            {
                                if($showText > '')
                                {
                                   $showText .= '<br>'; 
                                }
                                $showText .= 'See Notes...'; 
                            }
                            if($showText == '')
                            {
                                //Indicate there is someting to see in the form.
                                $showText = 'See details...';
                            }
                            $cleanrow[\raptor\WorklistColumnMap::WLIDX_SCHEDINFO] = array(
                                'EventDT' => $sqlScheduleTrackRow->scheduled_dt,
                                'LocationTx' => $sqlScheduleTrackRow->location_tx,
                                'ConfirmedDT' => $sqlScheduleTrackRow->confirmed_by_patient_dt,
                                'CanceledDT' => $sqlScheduleTrackRow->canceled_dt,
                                'ShowTx' => $showText
                            );
                            print_r($sqlScheduleTrackRow, TRUE);
                        } else {
                            //No record exists yet.
                            $cleanrow[\raptor\WorklistColumnMap::WLIDX_SCHEDINFO] = array(
                                'EventDT' => NULL,
                                'LocationTx' => NULL,
                                'ConfirmedDT' => NULL,
                                'CanceledDT' => NULL,
                                'ShowTx' => 'Unknown'
                            );
                        }
                        
                        //Compute the score AFTER all the other columns are set.
                        $rankscore = $match_order_to_user->getTicketRelevance($cleanrow);
                        $cleanrow[\raptor\WorklistColumnMap::WLIDX_RANKSCORE] = $rankscore;
                        
                        //Add the row to our collection
                        $formatted_datarows[$rownum] = $cleanrow;
                    }
                }
            }
            //Now walk through all the clean rows to update the pending order reference information
            for($i=0;$i<count($formatted_datarows);$i++)
            {
                $t = &$formatted_datarows[$i];
                if($i == 0 || $t[0] < $last_ien)    //Smallest is last
                {
                    $last_ien = $t[0];
                }
                if(is_array($t))
                {
                    //Yes, this is a real row.
                    $patientID = $t[\raptor\WorklistColumnMap::WLIDX_PATIENTID];
                    if(isset($aPatientPendingOrderMap[$patientID]))
                    {
                        $t[\raptor\WorklistColumnMap::WLIDX_MAPPENDINGORDERSSAMEPATIENT] 
                                = $aPatientPendingOrderMap[$patientID];
                        $t[\raptor\WorklistColumnMap::WLIDX_COUNTPENDINGORDERSSAMEPATIENT] 
                                = $aPatientPendingOrderCount[$patientID];
                    } else {
                        //Found no pending orders for this IEN
                        $t[\raptor\WorklistColumnMap::WLIDX_MAPPENDINGORDERSSAMEPATIENT] = array();;
                        $t[\raptor\WorklistColumnMap::WLIDX_COUNTPENDINGORDERSSAMEPATIENT] = 0;
                    }
                }
            }
            
            //return $formatted_datarows;
            $bundle = array( 'pending_orders_map'=>&$aPatientPendingOrderMap
                            ,'matching_offset'=>$nOffsetMatchIEN
                            ,'last_ien'=>$last_ien
                            ,'all_rows'=>&$formatted_datarows);
error_log("LOOK bundle >>>".print_r($bundle,TRUE));
            return $bundle;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    
}