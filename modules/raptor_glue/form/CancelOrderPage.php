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
 */ 

namespace raptor;

require_once 'FormHelper.php';

/**
 * Implementes the cancel order page.
 *
 * @author Frank Font of SAN Business Consultants
 */
class CancelOrderPage extends \raptor\ASimpleFormPage
{
    private $m_oContext = null;
    private $m_oTT = null;

    function __construct()
    {
        module_load_include('php', 'raptor_datalayer', 'config/Choices');
        $this->m_oContext = \raptor\Context::getInstance();
        $this->m_oTT = new \raptor\TicketTrackingData();
    }

    /**
     * Get the values to populate the form.
     * @return type result of the queries as an array
     */
    function getFieldValues()
    {
        try
        {
            $tid = $this->m_oContext->getSelectedTrackingID();
            if($tid == NULL || trim($tid) == '' || trim($tid) == 0)
            {
                throw new \Exception('Missing selected ticket number!  (If using direct, try overridetid.)');
            }
            $ehrDao = $this->m_oContext->getEhrDao();
            $aOneRow = $ehrDao->getDashboardDetailsMap($tid);
            //$nSiteID = $this->m_oContext->getSiteID();
            //$nIEN = $tid;
            //$nUID = $this->m_oContext->getUID();

            $myvalues = array();
            $myvalues['tid'] = $tid;
            $myvalues['procName'] = $aOneRow['Procedure'];
            $myvalues['reason'] = '';
            $myvalues['esig'] = '';
            $myvalues['providerDUZ'] = '';

            //$this->m_oContext = \raptor\Context::getInstance();
            //$myvalues['tid'] = $this->m_oContext->getSelectedTrackingID();
            $myvalues['OrderFileIen'] = $aOneRow['OrderFileIen'];
            $myvalues['PatientID'] = $aOneRow['PatientID'];

            return $myvalues;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Some checks to validate the data before we try to save it.
     * @param type $form
     * @param type $myvalues
     * @return TRUE or FALSE
     */
    function looksValid($form, $myvalues)
    {
        $is_good = TRUE;
        if(!isset($myvalues['reason']) || !is_numeric(($myvalues['reason'])))
        {
            form_set_error('reason','Did not find any a valid cancel reason');
            $is_good = FALSE;
        }
        return $is_good;
    }
    
    /**
     * Cancel the tickets.
     * Returns a success message string.
     */
    function updateDatabase($form, $myvalues)
    {
        try
        {
            //Try to create the record now
            $nSiteID = $this->m_oContext->getSiteID();
            $nIEN = $myvalues['tid'];
            $orderFileIen = $myvalues['OrderFileIen'];
            $nUID = $this->m_oContext->getUID();
            $sCWFS = $this->m_oTT->getTicketWorkflowState($nSiteID . '-' . $nIEN);
            $updated_dt = date("Y-m-d H:i:s", time());

            //$orderIEN = $nIEN;
            $reasonCode = $myvalues['reason'];
            //$cancelcomment = $myvalues['notes_tx'];
            $cancelesig = $myvalues['esig'];
            $providerDUZ = $myvalues['providerDUZ'];

            $canreallycancel = ($cancelesig > '');
            $real_cancel_success = FALSE; //Set to true if we are successful with the real cancel
            try
            {
                $oContext = \raptor\Context::getInstance();
                //$userinfo = $oContext->getUserInfo();
                $ehrDao = $oContext->getEhrDao();
                $results = $ehrDao->cancelRadiologyOrder( 
                        $myvalues['PatientID'],
                        $orderFileIen,
                        $providerDUZ,
                        'FakeLocation',
                        $reasonCode, 
                        $cancelesig);
                if(isset($results['cancelled_count']))
                {
                    $cc = $results['cancelled_count'];
                    if($cc > 0)
                    {
                        $real_cancel_success = TRUE;
                    } else {
                        //We ran into trouble
                        error_log("WARNING in ".VISTA_SITE." because have failed to cancel $nIEN");
                    }
                } else {
                    //Assume MDWS type of response
                    $cancelled_iens = $results['cancelled_iens'];
                    $failed_iens = $results['failed_iens'];
                    if(is_array($failed_iens) && count($failed_iens) > 0)
                    {
                        error_log("WARNING in ".VISTA_SITE." because have failed to cancel these IENs: " 
                                . print_r($failed_iens,TRUE)
                                . "\n\tCanceled these: " .print_r($cancelled_iens,TRUE) );
                    } else {
                        $real_cancel_success = TRUE;
                    }
                }
            } catch (\Exception $ex) {
                drupal_set_message('Failed cancel order ' . $myvalues['tid'] . ' (' . $myvalues['procName'] .')','error');
                error_log("Failed to cancel because ".$ex->getMessage()
                        ."\nValue details..." 
                        . Context::safeArrayDump($myvalues));
                throw $ex;
            }

            if(!$real_cancel_success)
            {
                $cancelMsg = "Failed to cancel $nIEN";
            } else {
                //Change the workflow state of this ticket.
                $sNewWFS = 'IA';
                $this->m_oTT->setTicketWorkflowState($nSiteID . '-' . $nIEN, $nUID, $sNewWFS, $sCWFS, $updated_dt);

                //Write success message
                if($canreallycancel)
                {
                    $cancelMsg = 'Canceled Order ' . $myvalues['tid'] . ' (' . $myvalues['procName'] .')';
                    //drupal_set_message($cancelMsg);
                } else {
                    $cancelMsg = 'Order ' . $myvalues['tid'] . ' (' . $myvalues['procName'] .') marked for discontinuation action';
                    //drupal_set_message($cancelMsg, 'warn');
                }
                error_log($cancelMsg);
            }
            return $cancelMsg;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    public function getForm($form, &$form_state, $disabled, $myvalues)
    {
        try
        {
            $form['data_entry_area1'] = array(
                '#prefix' => "\n<section class='user-profile-dataentry'>\n",
                '#suffix' => "\n</section>\n",
                '#disabled' => $disabled,
            );

            $myIEN = $myvalues['tid'];
            $ehrDao = $this->m_oContext->getEhrDao();
            $aOneRow = $ehrDao->getDashboardDetailsMap($myIEN);        
            //$sRequestedByName = $aOneRow['RequestedBy'];
            $canOrderBeDCd = $aOneRow['canOrderBeDCd'];
            //$orderFileStatus = $aOneRow['orderFileStatus'];

            if(!$canOrderBeDCd)
            {
                //This user cannot cancel/replace a ticket thus cannot replace.
                $form['data_entry_area1']['userrejected'] = array('#type' => 'item'
                        , '#markup' => '<h2>This order cannot be discontinued '
                        . 'from RAPTOR because of the current VISTA order status.</h2>',
                    );
                $form['data_entry_area1']['action_buttons']['cancel'] = array('#type' => 'item'
                        , '#markup' => '<input class="raptor-dialog-cancel" '
                            . 'type="button" value="Exit with No Changes">');
                return $form;
            }

            $myDuz = $ehrDao->getEHRUserID();
            $orginalProviderDuz = $aOneRow['orderingPhysicianDuz'];

            //Hidden values
            $form['hiddenthings']['tid'] = array('#type' => 'hidden'
                , '#value' => $myvalues['tid']);
            $form['hiddenthings']['procName'] = array('#type' => 'hidden'
                , '#value' => $myvalues['procName']);
            $form['hiddenthings']['OrderFileIen'] 
                    = array('#type' => 'hidden', '#value' => $myvalues['OrderFileIen']);
            $form['hiddenthings']['PatientID'] = array('#type' => 'hidden'
                , '#value' => $myvalues['PatientID']);
            $form['hiddenthings']['providerDUZ'] = array('#type' => 'hidden'
                , '#value' => $orginalProviderDuz);

            $needsESIG = FALSE;
            if($ehrDao->isProvider($myDuz))
            {
                //He is a provider, can only reallycancel if created the order
                if($myDuz == $orginalProviderDuz)
                {
                    $needsESIG = TRUE;
                    $form['data_entry_area1']['introblurb'] = array('#type' => 'item'
                            , '#markup' => '<h2>Your account created the '
                        . 'original order and will fully cancel '
                        . 'it by providing the electronic signature.</h2>');
                }
            } else if($ehrDao->userHasKeyOREMAS($myDuz)) {
                //They can cancel with signature on file feature
                $needsESIG = TRUE;
                $form['data_entry_area1']['introblurb'] = array('#type' => 'item'
                        , '#markup' => '<h2>Your account has the priviledge '
                    . 'to fully cancel using OREMAS key '
                    . 'by providing the electronic signature.</h2>');
            }

            if(!$needsESIG)
            {
                //They cannot fully cancel
                $form['data_entry_area1']['introblurb'] = array('#type' => 'item'
                        , '#markup' => '<h2>This order may continue to show up in'
                    . ' the worklist until the original order provider'
                    . ' signs the discontinuation action.</h2>');
            }

            //Provide the normal form.
            $aCancelOptions = $ehrDao->getRadiologyCancellationReasons();
            $form['data_entry_area1']['toppart']['reason'] = array(
                "#type" => "select",
                "#title" => t("Reason for Cancel"),
                "#options" => $aCancelOptions,
                "#description" => t("Select reason for canceling this order."),
                "#required" => TRUE,
                );        

            if(!$needsESIG)
            {
                $form['hiddenthings']['esig'] = array('#type' => 'hidden'
                    , '#value' => '');  //MUST BE EMPTY
            } else {
                $form['data_entry_area1']['toppart']['esig'] = array(
                    '#type'          => 'password',
                    '#title'         => t('Electronic Signature'),
                    '#disabled'      => $disabled,
                    '#size' => 55, 
                    '#maxlength' => 128, 
                    '#default_value' => '---',  //If we leave blank here then browser remembers last value
                    '#required' => TRUE,
                );
            }

            $form['data_entry_area1']['action_buttons'] = array(
                '#prefix' => "\n<section class='raptor-action-buttons'>\n",
                '#suffix' => "\n</section>\n",
                '#disabled' => $disabled,
            );
            $form['data_entry_area1']['action_buttons']['remove'] = array('#type' => 'submit'
                    , '#attributes' => array('class' => array('admin-action-button'))
                    , '#value' => t('Cancel this Imaging Order')
                    , '#disabled' => $disabled
            );

            $form['data_entry_area1']['action_buttons']['cancel'] = array('#type' => 'item'
                    , '#markup' => '<input class="raptor-dialog-cancel" '
                        . 'type="button" value="Exit with No Changes">');

            return $form;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
}

