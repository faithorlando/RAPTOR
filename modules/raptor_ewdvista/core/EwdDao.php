<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2015
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, Alex Podlesny, et al
 * EWD Integration and VISTA collaboration: Joel Mewton, Rob Tweed
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

namespace raptor_ewdvista;

require_once 'IEwdDao.php';
require_once 'EwdUtils.php';
require_once 'WebServices.php';
require_once 'WorklistHelper.php';
require_once 'DashboardHelper.php';
require_once 'NotesHelper.php';
require_once 'VitalsHelper.php';
require_once 'MedicationHelper.php';
require_once 'LabsHelper.php';
require_once 'AllergyHelper.php';
require_once 'SurgeryReportHelper.php';
require_once 'ProblemsListHelper.php';
require_once 'PathologyReportHelper.php';
require_once 'RadiologyReportHelper.php';

defined('VERSION_INFO_RAPTOR_EWDDAO')
    or define('VERSION_INFO_RAPTOR_EWDDAO', 'EWD VISTA EHR Integration 20150929.3');

defined('REDAO_CACHE_NM_WORKLIST')
    or define('REDAO_CACHE_NM_WORKLIST', 'getWorklistDetailsMapData');
defined('REDAO_CACHE_NM_PENDINGORDERS')
    or define('REDAO_CACHE_NM_PENDINGORDERS', 'getPendingOrdersMapEWD');
defined('REDAO_CACHE_NM_SUFFIX_DASHBOARD')
    or define('REDAO_CACHE_NM_SUFFIX_DASHBOARD', '_getDashboardDetailsMapEWD');
defined('REDAO_CACHE_NM_SUFFIX_VITALS')
    or define('REDAO_CACHE_NM_SUFFIX_VITALS', '_getRawVitalSignsMapEWD');

/**
 * This is the primary interface implementation to VistA using EWDJS
 *
 * @author Frank Font of SAN Business Consultants
 */
class EwdDao implements \raptor_ewdvista\IEwdDao
{
    private $m_groupname = 'EwdDaoGroup';
    private $m_createdtimestamp = NULL;
    private $m_oWebServices = NULL;
    private $m_worklistHelper = NULL;
    private $m_dashboardHelper = NULL;
    private $m_info_message = NULL;
    private $m_session_key_prefix = NULL;
    private $userSiteId = NULL;
    
    public function __construct($siteCode, $session_key_prefix=NULL, $reset=FALSE)
    {
        if($session_key_prefix==NULL)
        {
            $session_key_prefix='EWDDAO';
        }
        $this->m_session_key_prefix = $session_key_prefix;
        $this->userSiteId = $siteCode;
        
        module_load_include('php', 'raptor_datalayer', 'core/Context');
        module_load_include('php', 'raptor_datalayer', 'core/RuntimeResultFlexCache');
        $this->m_createdtimestamp = microtime(TRUE);        
        $this->m_oWebServices = new \raptor_ewdvista\WebServices();
        $this->m_worklistHelper = new \raptor_ewdvista\WorklistHelper();
        $this->m_dashboardHelper = new \raptor_ewdvista\DashboardHelper();
        if($reset)
        {
            $this->initClient($siteCode);
        }
    }

    /**
     * Tell us the version of this DAO
     */
    public function getIntegrationInfo()
    {
        return VERSION_INFO_RAPTOR_EWDDAO;
    }

    /**
     * Set the instance info message.  
     */
    public function setCustomInfoMessage($msg)
    {
        $this->m_info_message = $msg;
    }
    
    /**
     * Get the instance info message.
     */
    public function getCustomInfoMessage()
    {
        return $this->m_info_message;
    }
    
    /**
     * We can only pre-cache order data if the DAO implementation is not statefully
     * remembering the last selected order as the current order.
     * 
     * Returns TRUE if critical functions support tracking ID override for precache purposes.
     */
    public function getSupportsPreCacheOrderData()
    {
        return TRUE;    //We have implemented an override for the tracking ID
    }
    
    /**
     * We can only pre-cache patient data if the DAO implementation is not statefully
     * remembering the last selected order as the current order.
     * 
     * Returns TRUE if critical functions support patientId override for precache purposes.
     */
    public function getSupportsPreCachePatientData()
    {
        return TRUE;    //We are implementing an override for the patientId
    }
    
    private function endsWith($string, $test) 
    {
        $strlen = strlen($string);
        $testlen = strlen($test);
        if ($testlen > $strlen) 
        {
            return FALSE;
        }
        return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
    }
    
    /**
     * Return the site specific fully qualified URL for the service.
     */
    private function getURL($servicename, $args=NULL)
    {
        try
        {
            $base_ewdfed_url = trim(EWDFED_BASE_URL);
            if(!$this->endsWith($base_ewdfed_url,'/'))
            {
               error_log("TUNING TIP: Add missing '/' at the end of the EWDFED_BASE_URL declaration (Currently declared as '$base_ewdfed_url')");
               $base_ewdfed_url .= '/';
            }
            if($args === NULL || $args === FALSE)
            {
                $theurl = $base_ewdfed_url . "$servicename";
            } else {
                if(!is_array($args))
                {
                    throw new \Exception("Expected arguments to be array instead of " . print_r($args,TRUE));
                }
                $argtext = '';
                foreach($args as $k=>$v)
                {
                    if($argtext > '')
                    {
                        $argtext .= '&';
                    }
                    if($v == '')    //Do NOT use strict check here!
                    {
                        $argtext .= "$k=";  //Just an empty string
                    } else { 
                        if(!is_string($v) && !is_numeric($v))
                        {
                            throw new \Exception("Expected arguments to be array of text/number values instead of value for $k of " 
                                    . print_r($v,TRUE) 
                                    . ' in ' 
                                    .print_r($args,TRUE));
                        }
                        $encoded = urlencode($v);
                        $argtext .= "$k=$encoded";
                    }
                }
                $theurl = $base_ewdfed_url . "$servicename?{$argtext}";
            }
            return $theurl;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Initialize the DAO client session
     */
    private function initClient($siteCode)
    {
        try
        {
            error_log('Starting EWD initClient at ' . microtime(TRUE));
            $this->disconnect();    //Clear all session variables
            $servicename = 'initiate';
            $url = $this->getURL($servicename);
            //$json_string = $this->m_oWebServices->callAPI($servicename, $url);
            $json_string = $this->m_oWebServices->callAPI('GET', $url);
            $json_array = json_decode($json_string, TRUE);
            $this->setSessionVariable('authorization',trim($json_array["Authorization"]));
            $this->setSessionVariable('init_key',trim($json_array["key"]));
            $authorization = $this->getSessionVariable('authorization');
            if($authorization == '')
            {
                throw new \Exception("Missing authorization value in result! [URL: $url]"
                        . "\n >>> array result=".print_r($json_array,TRUE) 
                        . "\n >>> raw JSON=".print_r($json_string,TRUE)
                        . "\n >>> urlencoded JSON=".  urlencode($json_string)
                        . "\n");    //So that the rest of the exception is not blanded into this line!
            }
            $init_key = $this->getSessionVariable('init_key');
            if($init_key == '')
            {
                throw new \Exception("Missing init key value in result! [URL: $url]"
                        . "\n >>> array result=".print_r($json_array,TRUE) 
                        . "\n >>> raw JSON=".print_r($json_string,TRUE)
                        . "\n >>> urlencoded JSON=".  urlencode($json_string)
                        . "\n");    //So that the rest of the exception is not blanded into this line!
            }
            error_log('EWD initClient is DONE at ' . microtime(TRUE));
        } catch (\Exception $ex) {
            throw new \Exception('Trouble in initClient because ' . $ex , 99876 , $ex);
        }
    }

    /**
     * Return TRUE if already authenticated and session is active
     */
    public function isAuthenticated() 
    {
        try
        {
            $userduz = $this->getSessionVariable('userduz');
            $has_localsession = ($userduz != NULL);
            if($has_localsession)
            {
                //Now check with EWD
                $args = array();
                $serviceName = 'isActiveSession';
                $rawresult = $this->getServiceRelatedData($serviceName, $args);
                if(!isset($rawresult['result']) || !$rawresult['result'])
                {
                    $has_ewd_session = FALSE;
                } else {
                    $has_ewd_session = TRUE;
                }
            }
            return ($has_localsession && $has_ewd_session);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    private function setSessionVariable($name,$value)
    {
        $fullname = "{$this->m_session_key_prefix}_$name";
        $_SESSION[$fullname] = $value;
    }

    private function getSessionVariable($name)
    {
        $fullname = "{$this->m_session_key_prefix}_$name";
        if(isset($_SESSION[$fullname]) 
                && $_SESSION[$fullname] > '')
        {
            return $_SESSION[$fullname];
        }
        return NULL;
    }
    
    /**
     * Disconnect this DAO from a session
     */
    public function disconnect() 
    {
        $this->setSessionVariable('userduz',NULL);
        $this->setSessionVariable('authorization',NULL);
        $this->setSessionVariable('init_key', NULL);
        $this->setSessionVariable('credentials', NULL);
        $this->setSessionVariable('dt', NULL);
        $this->setSessionVariable('displayname', NULL);
        $this->setSessionVariable('fullname', NULL);
        $this->setSessionVariable('greeting', NULL);
        $this->setSessionVariable('securitykeys', NULL);
        $this->setPatientID(NULL);
    }

    /**
     * Attempt to login and mark the user authenticated
     */
    public function connectAndLogin($siteCode, $username, $password) 
    {
        try
        {
            error_log('Starting EWD connectAndLogin at ' . microtime(TRUE));
            $errorMessage = "";
            
            //Are we already logged in?
            if($this->isAuthenticated())
            {
                //Log out before we try again!
                $this->disconnect();
            }
            
            //Have we already initialized the client?
            $authorization = $this->getSessionVariable('authorization');
            if($authorization == NULL)
            {
                //Initialize it now
                error_log("Calling init from connectAndLogin for $this");
                $this->initClient($siteCode);
                $authorization = $this->getSessionVariable('authorization');
            }
            $init_key = $this->getSessionVariable('init_key');
            if($init_key == NULL)
            {
                throw new \Exception("No initialization key has been set!");
            }
            module_load_include('php', 'raptor_ewdvista', 'core/Encryption');
            $encryption = new \raptor_ewdvista\Encryption();
            $credentials = $encryption->getEncryptedCredentials($init_key, $username, $password);
            $this->setSessionVariable('credentials', $credentials);
            $serviceName = 'login';
            //http://localhost:8081/RaptorEwdVista/raptor/login?credentials=
            $url = $this->getURL($serviceName) . '?credentials=' . $credentials;
            $header['Authorization']=$authorization;
            $json_string = $this->m_oWebServices->callAPI('GET', $url, FALSE, $header);            
            $json_array = json_decode($json_string, TRUE);
            
            if (array_key_exists('DUZ', $json_array))
            {
                $userduz = trim($json_array['DUZ']);
                $this->setSessionVariable('dt',trim($json_array['DT']));
                $this->setSessionVariable('userduz',$userduz);
                $this->setSessionVariable('displayname',trim($json_array['displayName']));
                $this->setSessionVariable('fullname',trim($json_array['username']));
                $this->setSessionVariable('greeting',trim($json_array['greeting']));
                $securitykeys = $this->getSecurityKeysForUser($userduz);
                $this->setSessionVariable('securitykeys',$securitykeys);
            } else {
                if(array_key_exists('code', $json_array))
                {
                    $errorMessage = 'Unable to LOGIN because ' . $json_array['code'] . ' in ' . print_r($json_array, TRUE);
                } else {
                    $errorMessage = 'Unable to LOGIN because missing DUZ in ' . print_r($json_array, TRUE);
                }
                throw new \Exception($errorMessage);
            }
        } catch (\Exception $ex) {
            $thecreds = $this->getSessionVariable('credentials');
            $this->disconnect();
            throw new \Exception("Trouble in connectAndLogin at $siteCode as $username with cred={$thecreds} because ".$ex,99876,$ex);
        }
    }

    /**
     * Return the raw result from the restful service.
     */
    private function getServiceRelatedData($serviceName, $args_ar=NULL, $methodtype='GET', $data_ar=FALSE)
    {
        try
        {
            $url = $this->getURL($serviceName, $args_ar);
//error_log("LOOK getServiceRelatedData $methodtype " . ($data_ar !== FALSE ? '(WITH Data Array!)' : '(no data array)' ) . " URL=$url");            
            $authorization = $this->getSessionVariable('authorization');
            if($authorization == NULL)
            {
                throw new \Exception("Missing the authorization string in call to $serviceName");
            }
            $header["Authorization"]=$authorization;
            
            $json_string = $this->m_oWebServices->callAPI($methodtype, $url, $data_ar, $header);            
//error_log("LOOK getServiceRelatedData $methodtype result=" . print_r($json_string,TRUE));            
            $php_array = json_decode($json_string, TRUE);
            
            return $php_array;
        } catch (\Exception $ex) {
            throw new \Exception("Trouble with $methodtype of $serviceName($args_ar) because $ex", 99876, $ex);;
        }
    }
    
    /**
    * http://stackoverflow.com/questions/190421/caller-function-in-php-5
    */
    private function getCallingFunctionName($completeTrace=FALSE)
    {
        try
        {
            $trace=debug_backtrace();
            $functionName = "";
            if($completeTrace)
            {
                $str = '';
                foreach($trace as $caller)
                {
                    //get the name, and we really interested in the last name in the wholepath 
                    $functionName = "".$caller['function'];
                    //get log information    
                    $str .= " -- Called by {$caller['function']}";
                    if (isset($caller['class']))
                    {
                        $str .= " From Class {$caller['class']}";
                    }
                }
            }
            else
            {
                //$caller=$trace[2];  20150812 Not safe to hardcode key as 2; does not always work!
                $breakatnext = FALSE;
                foreach($trace as $key=>$caller)
                {
                    $functionName = "".$caller['function'];
                    if($breakatnext)
                    {
                        break;
                    } else
                    if($functionName == 'getCallingFunctionName')
                    {
                        $breakatnext = TRUE;
                    }
                }
                if(!$breakatnext)
                {
                    throw new \Exception("Failed to find the calling function name in ".print_r($trace,TRUE));
                }
                $functionName = "".$caller['function'];
                $str = "Called by {$functionName}";
                if (isset($caller['class']))
                {
                    $str .= " From Class {$caller['class']}";
                }
            }
            return $functionName;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Returns array of arrays the way RAPTOR expects it.
     */
    public function getWorklistDetailsMap($max_rows_one_call = WORKLIST_MAXROWS_PER_QUERY, $start_with_IEN=NULL)
    {
        try
        {
            $args = array();
            $serviceName = $this->getCallingFunctionName();
            if($start_with_IEN === NULL)    //Force STRICT check here!!!!
            {
                $start_from_IEN = '';
            } else {
                if(!is_numeric($start_with_IEN))
                {
                    throw new \Exception("The starting IEN declaration must be numeric but instead we got ".print_r($start_with_IEN,TRUE));
                }
                $start_from_IEN = intval($start_with_IEN) + 1; //So we really start there
            }
            //$maxpages=1;
            //$pages=0;
            $matching_offset=NULL;
            //$getmorepages = TRUE;
            //$show_rows = array();
            $pending_orders_map = array();
            $args['max'] = $max_rows_one_call;
            $args['from'] = $start_from_IEN;    //VistA starts from this value -1!!!!!

            //Query several times
            $enough_rows_count=WORKLIST_ENOUGH_ROWS_COUNT;
            $max_loops = WORKLIST_MAX_QUERY_LOOPS;
            $iterations = 0;
            $all_worklist_rows_raw_text_ar = array();
            $rawdatarows = $this->getServiceRelatedData($serviceName, $args);
            $bundle = $this->m_worklistHelper->getFormatWorklistRows($rawdatarows);
            $rows_one_iteration = $bundle['all_rows'];
            while($iterations < $max_loops && count($all_worklist_rows_raw_text_ar) < $enough_rows_count)
            {
                
                $iterations++;
                $row_count = count($rows_one_iteration);
                if($row_count == 0)
                {
                    //No need to continue
                    break;
                }
                
                $row_ar = $rows_one_iteration[1];   //This is the first row
                $tracking_id = $row_ar[0];          //This is the oldest ID pulled so far
                $all_worklist_rows_raw_text_ar = array_merge($all_worklist_rows_raw_text_ar, $rows_one_iteration);
    
                //Query the next chunk
                if(OLDEST_WORKLIST_TICKET_ID && OLDEST_WORKLIST_TICKET_ID > $tracking_id)
                {
                    //We are done because we do not care about tickets older than OLDEST_WORKLIST_TICKET_ID
                    break;
                }
                $args['from'] = $tracking_id;    //VistA starts from this value -1!!!!!
                $rawdatarows = $this->getServiceRelatedData($serviceName, $args);
                $bundle = $this->m_worklistHelper->getFormatWorklistRows($rawdatarows);
                $rows_one_iteration = $bundle['all_rows'];
            }
            
            //Scanned enough to populate the pending orders?
            $show_rows = $all_worklist_rows_raw_text_ar;
            $scanned_rows = min($enough_rows_count, $max_rows_one_call * $max_loops);
            if($scanned_rows < WORKLIST_ENOUGH_ROWS_TO_FIND_DUPS)
            {
                $pending_orders_map = "NOT COMPUTED (only scanned $max_rows_one_call orders and our minimum is " . WORKLIST_ENOUGH_ROWS_TO_FIND_DUPS . ")";
            } else {
                //Put pending orders map into a cache
                $sThisPendingOrdersResultName = REDAO_CACHE_NM_PENDINGORDERS;
                $oContext = \raptor\Context::getInstance();
                $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
                if ($oRuntimeResultFlexCacheHandler != NULL)
                {
                    try 
                    {
                        $oRuntimeResultFlexCacheHandler->addToCache($sThisPendingOrdersResultName, $pending_orders_map, CACHE_AGE_LABS);
                    } catch (\Exception $ex) {
                        error_log("Failed to cache $sThisPendingOrdersResultName result because " . $ex->getMessage());
                    }
                }
            }
            
            $aResult = array('Pages'=>1
                            ,'Page'=>1
                            ,'RowsPerPage'=>count($show_rows)
                            ,'started_with'=>$start_with_IEN
                            ,'DataRows'=>$show_rows
                            ,'matching_offset' => $matching_offset
                            ,'pending_orders_map' => $pending_orders_map
                );
            //Done!
            return $aResult;
        } catch (\Exception $ex) {
            throw new \Exception("Failed to get worklist because $ex",99888,$ex);
        }
    }
    
    /**
     * Return array of valuse from the indicated action
     * This is good for developers to check results
     */
    function getPrivateValue($keynames)
    {
        try
        {
            if(!is_array($keynames))
            {
                $keynames_ar = array($keynames);
            } else {
                $keynames_ar = $keynames;
            }
            $result = array();
            foreach($keynames_ar as $keyname)
            {
                $varname = "m_{$keyname}";
                $result[$keyname] = $this->$varname;
            }
            return $result;
        } catch (\Exception $ex) {
            $msg = "Failed getting keynames because ".$ex;
            throw new \Exception($msg,99876,$ex);
        }
    }
    
    public function __toString()
    {
        try 
        {
            $infomsg = $this->getCustomInfoMessage();
            if($infomsg > '')
            {
                $infomsg_txt = "\n\tCustom info message=$infomsg";
            } else {
                $infomsg_txt = '';
            }
            $spid = $this->getSelectedPatientID();
            $is_authenticated = $this->isAuthenticated() ? 'YES' : 'NO';
            $displayname = $this->getSessionVariable('displayname');
            return 'EwdDao (site=' . $this->userSiteId . ') instance created at ' . $this->m_createdtimestamp
                    . ' isAuthenticated=[' . $is_authenticated . ']'
                    . ' selectedPatient=[' . $spid . ']'
                    . ' displayname=[' . $displayname . ']'
                    . $infomsg_txt;
        } catch (\Exception $ex) {
            return 'Cannot get toString of EwdDao because ' . $ex;
        }
    }

    public function getNotesDetailMap($override_patientId = NULL)
    {
        try
        {
            $myhelper = new \raptor_ewdvista\NotesHelper();
            $serviceName = $this->getCallingFunctionName();
            if($override_patientId != NULL)
            {
                $pid = $override_patientId;
            } else {
                $pid = $this->getSelectedPatientID();
            }
            if($pid == '')
            {
                throw new \Exception('Cannot get notes detail without a patient ID!');
            }

            //Get the notes data from EWD services
            $args = array();
            $args['patientId'] = $pid;
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $notesdetail = $myhelper->getFormattedNotes($rawresult, MAX_DEFAULT_NOTES_TO_SHOW);
            return $notesdetail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function setPatientID($sPatientID)
    {
        try
        {
            $this->setSessionVariable('selectedPatient',$sPatientID);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getEHRUserID($fail_if_missing = TRUE)
    {
        try
        {
            $userduz = $this->getSessionVariable('userduz');
            if($userduz == NULL && $fail_if_missing)
            {
                throw new \Exception('No user is currently authenticated!');
            }
            return $userduz;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function cancelRadiologyOrder($patientid, $orderFileIen, $providerDUZ, $locationthing, $reasonCode, $cancelesig)
    {
        try
        {
            $args = array();
            $args['patientId'] = $patientid;
            $args['orderId'] = $orderFileIen;
            $args['userId'] = $this->getSessionVariable('userduz');
            $args['providerId'] = $providerDUZ;
            $args['locationId'] = $locationthing;
            $args['reasonId'] = $reasonCode;
            $args['eSig'] = $cancelesig;
            $serviceName = $this->getCallingFunctionName();
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            if(!isset($rawresult['code']) && isset($rawresult['id']) && $rawresult['id'] > '')
            { 
                //Assume success
                $rawresult['cancelled_count'] = 1;
            } else {
                //We do NOT expect this when the cancel is succesful.
                $rawresult['cancelled_count'] = 0;
                if(!isset($rawresult['message']))
                {
                    $myerrmsg = print_r($rawresult,TRUE);
                } else {
                    $rawmessage = $rawresult['message'];
                    $strpos = strpos($rawmessage,'Error:');
                    if($strpos === FALSE)
                    {
                        $myerrmsg = print_r($rawresult,TRUE);
                    } else {
                        $myerrmsg = trim(substr($rawmessage, $strpos));
                    }
                }
                $rawresult['cancelled_errmsg'] = $myerrmsg;
                error_log("Failed cancelRadiologyOrder($patientid, $orderFileIen, $providerDUZ, $locationthing, $reasonCode, ***) because $myerrmsg");
            }
            return $rawresult;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function createNewRadiologyOrder($orderChecks, $args)
    {
        try
        {
            $serviceName = 'createNewRadiologyOrder';
            $userId = $this->getEHRUserID(); 
            $funnydatetime_startDateTime = EwdUtils::convertPhpDateTimeToFunnyText($args['startDateTime']);
            $funnydatetime_preOpDateTime = EwdUtils::convertPhpDateTimeToFunnyText($args['preOpDateTime']);
            if(!is_array($args['clinicalHx']))
            {
                $clinicHxText = '';
            } else {
                $clinicHxText = implode('|', $args['clinicalHx']);
            }
            if(!is_array($args['modifierIds']))
            {
                $modifiersText = '';
            } else {
                $modifiersText = implode('|', $args['modifierIds']);
            }

            $args_as_data = array();
            $args_as_data['patientId'] = $args['patientId'];
            $args_as_data['providerId'] = $args['requestingProviderDuz'];
            $args_as_data['userId'] = $userId;
            $args_as_data['eSig'] = $args['eSig'];
            $args_as_data['dialogId'] = $args['imagingTypeId'];
            $args_as_data['locationId'] = $args['locationIEN'];
            $args_as_data['orderableItemId'] = $args['orderableItemId'];
            $args_as_data['orderStartDateTime'] = $funnydatetime_startDateTime;
            $args_as_data['urgencyCode'] = $args['urgencyCode'];
            $args_as_data['modeCode'] = $args['modeCode'];
            $args_as_data['classCode'] = $args['classCode'];
            $args_as_data['submitTo'] = $args['submitTo'];
            $args_as_data['pregnant'] = $args['pregnant'];
            $args_as_data['isolation'] = $args['isolation'];
            $args_as_data['preOpDateTime'] = $funnydatetime_preOpDateTime;
            $args_as_data['reasonForStudy'] = $args['reasonForStudy'];
            $args_as_data['clinicHx'] = $clinicHxText;
            $args_as_data['orderCheckOverrideReason'] = $args['orderCheckOverrideReason'];
            $args_as_data['modifiers'] = $modifiersText;

            $rawresult = $this->getServiceRelatedData($serviceName, NULL, 'POST', $args_as_data);
            return $rawresult;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function createUnsignedRadiologyOrder($orderChecks, $args)
    {
        try
        {
            //Make sure we do NOT have an eSig.
            if(isset($args['eSig'])) 
            {
                 throw new \Exception("Cannot create an unsigned order and provide a signature!");
            }
            //Simply call the create orer function without an eSig.
            return $this->createNewRadiologyOrder($orderChecks, $args);
            
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Return a subset of hospital locations
     */
    public function getHospitalLocationsMap($startingitem)
    {
        try
        {
            $serviceName = 'getHospitalLocationsMap';   //Only gets 44 at a time
            $args = array();
            $args['target'] = $startingitem;
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $formatted = array();
            if(isset($rawresult['value']))
            {
                $rawdatarows = $rawresult['value'];
                foreach($rawdatarows as $key=>$onerow)
                {
                    $one_ar = explode('^',$onerow);
                    $newkey = $one_ar[0];
                    $formatted[$newkey] = $one_ar[1];
                }
            }
            return $formatted;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Return all the hospital locations
     */
    public function getAllHospitalLocationsMap()
    {
        try
        {
            $serviceName = 'getHospitalLocationsMap';   //Only gets 44 at a time
            $callservice = TRUE;
            $callcount=0;
            $maxcalls = 50;
            $prevend = ' ';
            $formatted = array();
            while($callservice)
            {
                $callcount++;
                $args = array();
                $args['target'] = $prevend;   //Start at the start
                $rawresult = $this->getServiceRelatedData($serviceName, $args);
                if(!isset($rawresult['value']))
                {
                    error_log("WARNING callcount=$callcount QUIT $serviceName ITERATIONS because NON-ARRAY RESULT prev=[$prevend] last=[$lastitem]"); 
                    $callservice = FALSE;
                } else {
                    $rawdatarows = $rawresult['value'];
                    $lastrawitem = end($rawdatarows);
                    $last_ar = explode('^',$lastrawitem);
                    $lastitem = $last_ar[1];
                    $moreformatted = array();
                    foreach($rawdatarows as $key=>$onerow)
                    {
                        $one_ar = explode('^',$onerow);
                        $newkey = $one_ar[0];
                        $moreformatted[$newkey] = $one_ar[1];
                    }
                    if(is_array($rawdatarows) && count($rawdatarows) > 0 && strcasecmp($prevend, $lastitem) < 0)
                    {
                        $prevend = $lastitem;
                        $callservice = TRUE;
                    } else {
                        $callservice = FALSE;
                    }
                    $formatted = $formatted + $moreformatted;
                }
                if($callcount >= $maxcalls)
                {
                    error_log("WARNING: TOO MANY ITERATIONS(hit $callcount with item $lastitem and max is $maxcalls) in getAllHospitalLocationsMap");
                    $formatted['GETMORE'] = "TOO MANY LOCATIONS";
                    $callservice = FALSE;
                }
            }
            return $formatted;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getAllergiesDetailMap($override_patientId = NULL)
    {
        try
        {
            if($override_patientId != NULL)
            {
                $pid = $override_patientId;
            } else {
                $pid = $this->getSelectedPatientID();
            }
            if($pid == '')
            {
                throw new \Exception('Cannot get allergy detail without a patient ID!');
            }
            $myhelper = new \raptor_ewdvista\AllergyHelper();
            $serviceName = $this->getCallingFunctionName();

            //Get the medication data from EWD services
            $args = array();
            $args['patientId'] = $pid;
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $formatted_detail = $myhelper->getFormattedAllergyDetail($rawresult);
            return $formatted_detail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getChemHemLabs($override_patientId = NULL)
    {
        try
        {
            $oContext = \raptor\Context::getInstance();
            if($override_patientId != NULL)
            {
                $pid = $override_patientId;
            } else {
                $pid = $this->getSelectedPatientID();
            }
            $myhelper = new \raptor_ewdvista\LabsHelper($oContext, $pid);
            $serviceName = $this->getCallingFunctionName();
            if($pid == '')
            {
                throw new \Exception('Cannot get chem labs detail without a patient ID!');
            }
            
            $args = array();
            $args['patientId'] = $pid;
            $args['fromDate'] = EwdUtils::getVistaDate(-1 * DEFAULT_GET_LABS_DAYS);
            $args['toDate'] = EwdUtils::getVistaDate(0);
            
            $rawresult_ar = $this->getServiceRelatedData($serviceName, $args);;
            $formatted_detail = $myhelper->getFormattedChemHemLabsDetail($rawresult_ar);
            return $formatted_detail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getEGFRDetailMap($override_patientId = NULL)
    {
        try
        {
            $oContext = \raptor\Context::getInstance();
            if($override_patientId != NULL)
            {
                $pid = $override_patientId;
            } else {
                $pid = $this->getSelectedPatientID();
            }
            if(trim($pid) == '')
            {
                throw new \Exception("Cannot get EGFR because no pid was provided!");
            }
            $myhelper = new \raptor_ewdvista\LabsHelper($oContext, $pid);
            $alldata = $myhelper->getLabsDetailData($pid);
            $clean_result = $alldata[1];
            return $clean_result;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getDiagnosticLabsDetailMap($override_patientId = NULL)
    {
        try
        {
            $oContext = \raptor\Context::getInstance();
            if($override_patientId != NULL)
            {
                $pid = $override_patientId;
            } else {
                $pid = $this->getSelectedPatientID();
            }
            if(trim($pid) == '')
            {
                throw new \Exception("Cannot get Diagnostic Labs because no pid was provided!");
            }
            $myhelper = new \raptor_ewdvista\LabsHelper($oContext, $pid);
            $alldata = $myhelper->getLabsDetailData($pid);
            $clean_result = $alldata[0];
            return $clean_result;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * If override_tracking_id is provided, then return dashboard for that order
     * instead of the currently selected order.
     */
    public function getDashboardDetailsMap($override_tracking_id = NULL)
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $oContext = \raptor\Context::getInstance();
            if ($override_tracking_id == NULL)
            {
                $tid = $oContext->getSelectedTrackingID();
            } else {
                $tid = trim($override_tracking_id);
            }
            if($tid == '')
            {
                throw new \Exception('Cannot get dashboard without a tracking ID!');
            }

            if ($oContext != NULL)
            {
                //Utilize the cache.
                $sThisResultName = "{$tid}" . REDAO_CACHE_NM_SUFFIX_DASHBOARD;
                $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
                if($oRuntimeResultFlexCacheHandler != NULL)
                {
                    $aCachedResult = $oRuntimeResultFlexCacheHandler->checkCache($sThisResultName);
                    if($aCachedResult !== NULL)
                    {
                        //Found it in the cache!
                        return $aCachedResult;
                    }
                }
            } else {
                $oRuntimeResultFlexCacheHandler = NULL;
            }

            //Get the dashboard data from EWD services
            $namedparts = $this->getTrackingIDNamedParts($tid);
            $order_IEN = $namedparts['ien'];
            $onerow = NULL; //We MUST declare it here, else not set after the try block
            $therow = array();
            try
            {
                $onerow = $this->getWorklistDetailsMap(1,$order_IEN);
                if(!is_array($onerow) || !isset($onerow['DataRows']))
                {
                    throw new \Exception("Failed to get worklist row for $order_IEN >>>" . print_r($onerow,TRUE));
                }
            } catch (\Exception $ex) {
                throw new \Exception("Failed to get worklist row for $order_IEN because $ex",99876,$ex);
            }
            $datarows = $onerow['DataRows'];
            if(count($datarows) < 1)    //Do NOT check for exactly 1 because result returns ONE extra row sometimes! (Thats okay)
            {
                $rownum = 0;
                $errmsg = "Expected 1 data row for $order_IEN (got ".count($datarows).")";
                foreach($datarows as $onedatarow)
                {
                    $rownum++;
                    $errmsg .= "\n\tData Row #$rownum) ".print_r($onedatarow,TRUE);
                }
                throw new \Exception($errmsg);
            }
            foreach($datarows as $key=>$therow)
            {
                break;  //Only want to get the first row.
            }
            $args = array();
            $args['ien'] = $order_IEN;
            $result = $this->getServiceRelatedData($serviceName, $args);
            if(!is_array($result['radiologyOrder']))
            {
                throw new \Exception("Did not find array of radiologyOrder in ".print_r($result,TRUE));
            }
            if(!is_array($result['order']))
            {
                throw new \Exception("Did not find array of order in ".print_r($result,TRUE));
            }
            $radiologyOrder = $result['radiologyOrder'];
            $orderFileRec = $result['order'];
            $pid = $therow[\raptor\WorklistColumnMap::WLIDX_PATIENTID];
            $oPatientData = $this->getPatientMap($pid);
            if($oPatientData == NULL)
            {
                $msg = 'Did not get patient data of pid='.$pid
                        .' for trackingID=['.$tid.']';
                error_log($msg.">>>instance details=".print_r($this, TRUE));
                throw new \Exception($msg);
            }
            $dashboard = $this->m_dashboardHelper->getFormatted($tid, $pid, $radiologyOrder, $orderFileRec, $therow, $oPatientData);

            //Put it into the cache if we one
            if ($oRuntimeResultFlexCacheHandler != NULL)
            {
                try 
                {
                    $oRuntimeResultFlexCacheHandler->addToCache($sThisResultName, $dashboard, CACHE_AGE_LABS);
                } catch (\Exception $ex) {
                    error_log("Failed to cache $sThisResultName result because " . $ex->getMessage());
                }
            }
            return $dashboard;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * A tracking ID can be an IEN or an SITE-IEN so
     * use this function instead of coding everywhere.
     */
    private function getTrackingIDNamedParts($tid)
    {
        $namedparts = array();
        $parts = explode('-',trim($tid));
        if(count($parts) == 1)
        {
            $namedparts['site'] = NULL; //Not specified in tid
            $namedparts['ien'] = trim($tid);
        } else {
            $namedparts['site'] = trim($parts[0]);
            $namedparts['ien'] = trim($parts[1]);
        }
        return $namedparts;
    }

    public function getImagingTypesMap()
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $rawresult = $this->getServiceRelatedData($serviceName);
            $rawdata = $rawresult['value'];
            if(!is_array($rawdata))
            {
                //Should only happen if site is not configured right.
                throw new \Exception("This site is not properly configured with image types!");
            }
            $formatted = array();
            foreach($rawdata as $key=>$onerow)
            {
                $one_ar = explode('^',$onerow);
                $newkey = $one_ar[3];
                $formatted[$newkey] = $one_ar[1];
            }
            return $formatted;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getMedicationsDetailMap($atriskmeds = NULL)
    {
        try
        {
            $myhelper = new \raptor_ewdvista\MedicationHelper();
            $serviceName = $this->getCallingFunctionName();
            $pid = $this->getSelectedPatientID();
            if($pid == '')
            {
                throw new \Exception('Cannot get medication detail without a patient ID!');
            }

            //Get the medication data from EWD services
            $args = array();
            $args['patientId'] = $pid;
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $formatted_detail = $myhelper->getFormattedMedicationsDetail($rawresult, $atriskmeds);
            return $formatted_detail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getOrderOverviewMap()
    {
        try
        {
            $dashboard_map = $this->getDashboardDetailsMap();
            $pid = $this->getSelectedPatientID();
            $patient_map = $this->getPatientMap($pid);
            $formatted_detail = array(
                     'RqstBy'=>$dashboard_map['RequestedBy'],
                     'RqstStdy'=>$dashboard_map['Procedure'],
                     'RsnStdy'=>$dashboard_map['ReasonForStudy'],
                     'PCP'=>$patient_map['teamPcpName'],
                     'AtP'=>$patient_map['teamAttendingName'],
                    );
            return $formatted_detail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getOrderableItems($imagingTypeId)
    {
        try
        {
            $args = array();
            $args['dialogId'] = $imagingTypeId;
            $serviceName = $this->getCallingFunctionName();
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $value_ar = $rawresult['value'];
            if(!is_array($value_ar))
            {
                error_log("ERROR DETECTED: Check full result of $this >>> " . print_r($rawresult,TRUE));
                throw new \Exception("Expected to find at least one orderable item for this site but found none!");
            }
            $formatted_detail = array();
            foreach($value_ar as $onerawrow)
            {
                $parts = explode('^',$onerawrow);
                if(count($parts)>1)
                {
                    $id = $parts[0];
                    $name = $parts[1];
                    if(count($parts)>3)
                    {
                        $requiresApproval = $parts[3];
                    } else {
                        $requiresApproval = '';
                    }
                    $formatted_detail[$id] = array('name'=>$name, 'requiresApproval'=>$requiresApproval);
                }
            }
            return $formatted_detail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getPathologyReportsDetailMap($override_patientId = NULL)
    {
        try
        {
            if($override_patientId != NULL)
            {
                $pid = $override_patientId;
            } else {
                $pid = $this->getSelectedPatientID();
            }
            if($pid == '')
            {
                throw new \Exception('Cannot get pathology detail without a patient ID!');
            }
            $myhelper = new \raptor_ewdvista\PathologyReportHelper();
            $serviceName = $this->getCallingFunctionName();
            $args = array();
            $args['patientId'] = $pid;
            $args['fromDate'] = EwdUtils::getVistaDate(-1 * DEFAULT_GET_LABS_DAYS);
            $args['toDate'] = EwdUtils::getVistaDate(0);
            $args['nRpts'] = 1000;
            $rawresult_ar = $this->getServiceRelatedData($serviceName, $args);
            $formatted_detail = $myhelper->getFormattedPathologyReportHelperDetail($rawresult_ar);
            return $formatted_detail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getPatientIDFromTrackingID($sTrackingID)
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $tid = trim($sTrackingID);
            if($tid == '')
            {
                throw new \Exception("Cannot get patient ID without a tracking ID! (param received='$sTrackingID')");
            }
            $namedparts = $this->getTrackingIDNamedParts($tid);
            $args['ien'] = $namedparts['ien'];
            $result = $this->getServiceRelatedData($serviceName, $args);
            if(!isset($result['result']))
            {
                throw new \Exception("Missing patient ID result from tracking ID value $sTrackingID: ".print_r($result,TRUE));
            }
            $patientID = $result['result'];
            return $patientID;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Return the set of orders that exist for one patient
     */
    public function getPendingOrdersMap($override_patientId = NULL)
    {
        try
        {
            if($override_patientId != NULL)
            {
                $pid = $override_patientId;
            } else {
                $pid = $this->getSelectedPatientID();
            }
            if($pid == '')
            {
                throw new \Exception('Cannot get pending orders detail without a patient ID!');
            }
            
            $sThisPendingOrdersResultName = REDAO_CACHE_NM_PENDINGORDERS;
            $oContext = \raptor\Context::getInstance();
            $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
            if($oRuntimeResultFlexCacheHandler != NULL)
            {
                //Note: This item is cached by the worklist function!
                $pending_orders_map = $oRuntimeResultFlexCacheHandler->checkCache($sThisPendingOrdersResultName);
                if($pending_orders_map == NULL)
                {
                    //Cache was empty; query it now.
                    $entire_worklist_bundle = $this->getWorklistDetailsMap();
                    $pending_orders_map = $entire_worklist_bundle['pending_orders_map'];
                }
            } else {
                //We have no caches, query it now.
                $entire_worklist_bundle = $this->getWorklistDetailsMap();
                $pending_orders_map = $entire_worklist_bundle['pending_orders_map'];
            }
            if(!is_array($pending_orders_map) || !isset($pending_orders_map[$pid]))
            {
                $themapping = array();
            } else {
                $themapping = $pending_orders_map[$pid];
            }
            return $themapping;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getProblemsListDetailMap()
    {
        try
        {
            $myhelper = new \raptor_ewdvista\ProblemsListHelper();
            $serviceName = $this->getCallingFunctionName();
            $pid = $this->getSelectedPatientID();
            if($pid == '')
            {
                throw new \Exception('Cannot get problems detail without a patient ID!');
            }

            //Get the medication data from EWD services
            $args = array();
            $args['patientId'] = $pid;
            $args['type'] = 'A';    //Only return the active ones
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $formatted_detail = $myhelper->getFormattedProblemsDetail($rawresult);
            return $formatted_detail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getProviders($neworderprovider_name)
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $args['target'] = $neworderprovider_name;
            $raw_result = $this->getServiceRelatedData($serviceName, $args);
            if(!isset($raw_result['value']))
            {
                error_log("Missing the expected 'value' key from target='" . $args['target'] . "' in this result>>>>" . print_r($raw_result,TRUE));
                throw new \Exception('Missing the expected value key!');
            }
            $values = $raw_result['value'];
            $formatted_ar = array();
            foreach($values as $oneprovider_raw)
            {
                $parts = explode('^', $oneprovider_raw);
                if(count($parts) > 1)
                {
                    $key = $parts[0];
                    $formatted_ar[$key] = $parts[1];
                }
            }
            return $formatted_ar;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getRadiologyCancellationReasons()
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $raw_result = $this->getServiceRelatedData($serviceName);
            if(!is_array($raw_result) || !isset($raw_result['value']))
            {
                error_log("Failed to get cancellation reasons in correct format; got this>>>>" . print_r($raw_result,TRUE));
                throw new \Exception("Did NOT get cancellation reasons in expected format!");
            }
            $value_ar = $raw_result['value'];
            $formatted = array();
            foreach($value_ar as $rawrow)
            {
                $parts = explode('^',$rawrow);
                if(count($parts) > 1)
                {
                    $rawkey = $parts[0];
                    if($rawkey[0] != 'i')
                    {
                        throw new \Exception("Expected i prefix on raw key but instead got row like this '$rawrow'");
                    }
                    $key = substr($rawkey,1);   //Skip the i prefix
                    $formatted[$key] = $parts[1];
                }
            }
            return $formatted;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getRadiologyOrderChecks($args)
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $service_args = array();
            $funnydatetime = EwdUtils::convertPhpDateTimeToFunnyText($args['startDateTime']);
            $service_args['patientId'] = $args['patientId'];
            $service_args['orderStartDateTime'] = $funnydatetime;
            $service_args['locationId'] = $args['locationIEN'];
            $service_args['orderableItemId'] = $args['orderableItemId'];
            $rawresult_ar = $this->getServiceRelatedData($serviceName, $service_args);

            //Now format our final result.
            $formatted = array();
            foreach($rawresult_ar as $item)
            {
                $id = $item['id'];
                $level = $item['level'];
                $name = $item['name'];
                $needsOverride = ($level == '1');
                $formatted[$id] = array(
                    'name'=>$name,
                    'level'=>$level,
                    'needsOverride'=>$needsOverride
                );
            }
            return $formatted;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getRadiologyOrderDialog($imagingTypeId, $patientId)
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $args = array();
            $args['patientId'] = $patientId;
            $args['dialogId'] = $imagingTypeId;
            $rawresult_ar = $this->getServiceRelatedData($serviceName, $args);

            $raw_commonProcedures = $rawresult_ar['commonProcedures'];
            $clean_commonProcedures = array();
            foreach($raw_commonProcedures as $onerawset)
            {
                $id = $onerawset['id'];
                $name = $onerawset['name'];
                $clean_commonProcedures[$id] = $name;
            }
            $rawresult_ar['commonProcedures'] = $clean_commonProcedures;
            
            $raw_contractOptions = $rawresult_ar['contractOptions'];
            $clean_contractOptions = array();
            foreach($raw_contractOptions as $onerawset)
            {
                $key = $onerawset['key'];
                $value = $onerawset['value'];
                $clean_contractOptions[$key] = $value;
            }
            $rawresult_ar['contractOptions'] = $clean_contractOptions;
            
            $raw_sharingOptions = $rawresult_ar['sharingOptions'];
            $clean_sharingOptions = array();
            foreach($raw_sharingOptions as $onerawset)
            {
                $key = $onerawset['key'];
                $value = $onerawset['value'];
                $clean_sharingOptions[$key] = $value;
            }
            $rawresult_ar['sharingOptions'] = $clean_sharingOptions;
            
            $raw_researchOptions = $rawresult_ar['researchOptions'];
            $clean_researchOptions = array();
            foreach($raw_researchOptions as $onerawset)
            {
                $key = $onerawset['key'];
                $value = $onerawset['value'];
                $clean_researchOptions[$key] = $value;
            }
            $rawresult_ar['researchOptions'] = $clean_researchOptions;  
            
            return $rawresult_ar;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getRadiologyReportsDetailMap($override_patientId = NULL)
    {
        try
        {
            if($override_patientId != NULL)
            {
                $pid = $override_patientId;
            } else {
                $pid = $this->getSelectedPatientID();
            }
            if($pid == '')
            {
                throw new \Exception('Cannot get Radiology Reports detail without a patient ID!');
            }
            $myhelper = new \raptor_ewdvista\RadiologyReportHelper();
            $serviceName = $this->getCallingFunctionName();
            $args = array();
            $args['patientId'] = $pid;
            $args['fromDate'] = EwdUtils::getVistaDate(-1 * DEFAULT_GET_LABS_DAYS);
            $args['toDate'] = EwdUtils::getVistaDate(0);
            $args['nRpts'] = 1000;
            $rawresult_ar = $this->getServiceRelatedData($serviceName, $args);
            
            $formatted_detail = $myhelper->getFormattedRadiologyReportHelperDetail($rawresult_ar);
            return $formatted_detail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getRawVitalSignsMap($override_patientId = NULL)
    {
        try
        {
            if($override_patientId != NULL)
            {
                $pid = $override_patientId;
            } else {
                $pid = $this->getSelectedPatientID();
            }
            if($pid == NULL)
            {
                throw new \Exception('Cannot return vitals when there is no selected patient!');
            }
            $oContext = \raptor\Context::getInstance();
            if ($oContext != NULL)
            {
                //Utilize the cache.
                $sThisResultName = "{$pid}" . REDAO_CACHE_NM_SUFFIX_VITALS;
                $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
                if ($oRuntimeResultFlexCacheHandler != NULL)
                {
                    $aCachedResult = $oRuntimeResultFlexCacheHandler->checkCache($sThisResultName);
                    if ($aCachedResult !== NULL)
                    {
                        //Found it in the cache!
                        return $aCachedResult;
                    }
                }
            } else {
                $oRuntimeResultFlexCacheHandler = NULL;
            }
            
            $myhelper = new \raptor_ewdvista\VitalsHelper();
            $serviceName = $this->getCallingFunctionName();
            $args = array();
            $args['patientId'] = $pid;
            $rawresult = array();
            $rawresult['result'] = $this->getServiceRelatedData($serviceName, $args);
            $bundle = $myhelper->getFormattedSuperset($rawresult);
            
            if ($oRuntimeResultFlexCacheHandler != NULL)
            {
                try 
                {
                    $oRuntimeResultFlexCacheHandler->addToCache($sThisResultName, $bundle, CACHE_AGE_LABS);
                } catch (\Exception $ex) {
                    error_log("Failed to cache $sThisResultName result because " . $ex->getMessage());
                }
            }
            return $bundle;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getSurgeryReportsDetailMap($override_patientId = NULL)
    {
        try
        {
            if($override_patientId != NULL)
            {
                $pid = $override_patientId;
            } else {
                $pid = $this->getSelectedPatientID();
            }
            if($pid == '')
            {
                throw new \Exception('Cannot get surgery detail without a patient ID!');
            }
            $myhelper = new \raptor_ewdvista\SurgeryReportHelper();
            $serviceName = $this->getCallingFunctionName();

            //Get the medication data from EWD services
            $args = array();
            $args['patientId'] = $pid;
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $formatted_detail = $myhelper->getFormattedSurgeryReportDetail($rawresult);
            return $formatted_detail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    private function getSecurityKeysForUser($userduz)
    {
        try
        {
            $serviceName = 'getUserSecurityKeys';
            $args = array();
            $args['uid'] = $userduz;
            $formatted_detail = array();
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $warnings = array();
            if(is_array($rawresult))
            {
                foreach($rawresult as $oneblock)
                {
                    $id = $oneblock['permissionId'];
                    $name = trim($oneblock['name']);
                    if($name > '')
                    {
                        $formatted_detail[$id] = $name;
                    } else {
                        $warnings[] = $id;
                    }
                }
            }
            if(count($warnings) > 0)
            {
                error_log("WARNING: For user DUZ=$userduz we did NOT find a security key name for the following IDs:" . implode(", ",$warnings));
            }
            return $formatted_detail;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    public function getUserSecurityKeys()
    {
        try
        {
            $securitykeys = $this->getSessionVariable('securitykeys');
            return $securitykeys;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getVisits()
    {
        try
        {
            $serviceName = $this->getCallingFunctionName();
            $args = array();
            $args['patientId'] = $this->getSelectedPatientID();
            $args['fromDate'] = EwdUtils::getVistaDate(-1 * DEFAULT_GET_VISIT_DAYS);
            $args['toDate'] = EwdUtils::getVistaDate(0);
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            if(!isset($rawresult['value']))
            { 
                //There are no visits.
                $aSorted = array();
            } else {
                //We have visits.
                $visitAry = $rawresult['value'];
                foreach ($visitAry as $visit) 
                {
                    $a = explode('^', $visit);
                    $l = explode(';', $a[0]); //first field is an array "type;visit timestamp;locationID"
                    $cleantimestamp = EwdUtils::convertVistaDateToYYYYMMDDtttt($l[1]);
                    $location = array(
                      'id'=>$l[2],
                      'name' => $a[2],
                    );
                    $visitTO = array(
                      'type'=>$l[0],
                      'location'=>$location,  
                      'timestamp'=>$cleantimestamp,
                      'status'=>(isset($a[3]) ? $a[3] : '')  
                    );
                    $aryItem = array(
                        'locationName' => $a[2],
                        'locationId' => $l[2],
                        'visitTimestamp' => $cleantimestamp,
                        'visitTO' => $visitTO
                    );
                    $result[] = $aryItem;   //Already acending
                }
                $aSorted = array_reverse($result); //Now this is descrnding.
            }
            return $aSorted;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getEncounterStringFromVisit($visitTO)
    {
        if($visitTO == NULL)
        {
            throw new \Exception('Cannot pass a NULL visitTo into getEncounterStringFromVisit!');
        }
        try
        {
            if(!isset($visitTO['location']) || !isset($visitTO['location']['id']))
            {
                throw new \Exception('Did not get a valid locationId from visit item '.print_r($visitTO,TRUE));
            }
            return $visitTO['location']['id'] . ';' . $visitTO['timestamp'] . ';' . $visitTO['type'];
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Return NULL if no problems.
     */
    public function getVistaAccountKeyProblems()
    {
        try
        {
            $missingkeys = array();
            $mykeys = $this->getSessionVariable('securitykeys');
            $has_superkey = in_array('XUPROGMODE', $mykeys);
            if(!$has_superkey)
            {
                $minSecondaryOptions = array('DVBA CAPRI GUI'); //'OR CPRS GUI CHART'
                foreach($minSecondaryOptions as $keyName)
                {
                    $haskey = in_array($keyName, $mykeys);
                    if(!$haskey)
                    {
                        $missingkeys[] = $keyName;
                    }
                }
            }
            $errormsg = NULL;
            if(count($missingkeys) > 0)
            {
               $keystext = implode(', ',$missingkeys);
               $missingkeycount = count($missingkeys);
               $errormsg = "The VistA user account does not have access to ($missingkeycount keys): $keystext!";
               error_log("PRIVILEGES WARNING: " . $errormsg . ' >>> ' . $this);
            }
            return $errormsg;            
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getVitalsDetailMap()
    {
        try
        {
            $vitalsbundle = $this->getRawVitalSignsMap();
            if(isset($vitalsbundle[0]))
            {
                return $vitalsbundle[0];
            }
            //Return an empty array.
            return array(); 
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getVitalsDetailOnlyLatestMap()
    {
        try
        {
            $vitalsbundle = $this->getRawVitalSignsMap();
            if(isset($vitalsbundle[2]))
            {
                return $vitalsbundle[2];
            }
            //Return an empty array.
            return array(); 
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getVitalsSummaryMap()
    {
        try
        {
            $vitalsbundle = $this->getRawVitalSignsMap();
            $myhelper = new \raptor_ewdvista\VitalsHelper();
            $summary = $myhelper->getVitalsSummary($vitalsbundle);
            return $summary;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function isProvider()
    {
        try
        {
            $securitykeys = $this->getSessionVariable('securitykeys');
            return in_array('PROVIDER', $securitykeys);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function signNote($newNoteIen, $eSig)
    {
        try
        {
            $args = array();
            $args['noteIEN'] = $newNoteIen;
            $args['eSig'] = $eSig;
            $serviceName = $this->getCallingFunctionName();
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            if(!is_array($rawresult))
            {
                throw new \Exception("Expected array result from signNote($newNoteIen,****) instead of " . print_r($rawresult,TRUE));
            }
            return $rawresult;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function userHasKeyOREMAS()
    {
        try
        {
            $securitykeys = $this->getSessionVariable('securitykeys');
            return in_array('OREMAS', $securitykeys);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function validateEsig($eSig)
    {
        try
        {
            $args = array();
            $args['eSig'] = $eSig;
            $serviceName = $this->getCallingFunctionName();
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            if(!isset($rawresult['result']))
            {
                throw new \Exception("The $serviceName result is corrupt!");
            }
            $isvalid = $rawresult['result'];
            return $isvalid;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function verifyNoteTitleMapping($checkVistaNoteIEN, $checkVistaNoteTitle)
    {
        try
        {
            $titlemap = $this->getNoteTitles($checkVistaNoteTitle);
            if(is_array($titlemap) && isset($titlemap[$checkVistaNoteIEN]))
            {
                foreach($titlemap[$checkVistaNoteIEN] as $onetitle)
                {
                    if($checkVistaNoteTitle == $onetitle)
                    {
                        return TRUE;
                    }
                }
            }
            return FALSE;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getNoteTitles($startingitem)
    {
        try
        {
            $args = array();
            $args['target'] = $startingitem;
            $serviceName = $this->getCallingFunctionName();
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            return $rawresult;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Call the web service to create the note and return new IEN
     */
    private function writeNote($titleIEN, $noteTextArray, $encounterString, $cosignerDUZ)
    {
        try
        {
            $formattedNoteText = implode("\n",$noteTextArray);
            $patientId = $this->getSelectedPatientID();
            $userId = $this->getEHRUserID(); 
            $authorDUZ = $userId;   //The author will ALWAYS be logged in user!
            if($patientId == '')
            {
                throw new \Exception('Did not find the patient ID for the note!');
            }
            if($authorDUZ == '')
            {
                throw new \Exception('Did not find the author DUZ for the note!');
            }
            $args_as_data = array();
            $args_as_data['patientId'] = $patientId;
            $args_as_data['titleIEN'] = $titleIEN;
            $args_as_data['authorDUZ'] = $authorDUZ;
            $args_as_data['cosignerDUZ'] = $cosignerDUZ;
            $args_as_data['userId'] = $userId;
            $args_as_data['encounterString'] = $encounterString;
            $args_as_data['text'] = $formattedNoteText;
            $serviceName = 'writeNote';
            $rawresult = $this->getServiceRelatedData($serviceName, NULL, 'POST', $args_as_data);
            if(!isset($rawresult['id']))
            {
                throw new \Exception("Expected result as array with id instead of this >>> " . print_r($rawresult,TRUE));
            }
            return $rawresult['id'];
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    public function writeRaptorGeneralNote($noteTextArray, $encounterString, $cosignerDUZ)
    {
        try
        {
            $titleIEN = VISTA_NOTEIEN_RAPTOR_GENERAL;
            return $this->writeNote($titleIEN, $noteTextArray, $encounterString, $cosignerDUZ);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function writeRaptorSafetyChecklist($aChecklistData, $encounterString, $cosignerDUZ)
    {
        try
        {
            $titleIEN = VISTA_NOTEIEN_RAPTOR_SAFETY_CKLST;
            return $this->writeNote($titleIEN, $aChecklistData, $encounterString, $cosignerDUZ);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function invalidateCacheForEverything()
    {
        try
        {
            $oContext = \raptor\Context::getInstance();
            $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
            if ($oRuntimeResultFlexCacheHandler != NULL)
            {
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheAllDataAndFlags();
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    public function invalidateCacheForOrder($tid)
    {
        try
        {
            $oContext = \raptor\Context::getInstance();
            $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
            if ($oRuntimeResultFlexCacheHandler != NULL)
            {
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheData("{$tid}" . REDAO_CACHE_NM_SUFFIX_DASHBOARD);
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheData(REDAO_CACHE_NM_WORKLIST);
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheData(REDAO_CACHE_NM_PENDINGORDERS);
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function invalidateCacheForPatient($pid)
    {
        try
        {
            $sThisResultName = "{$pid}" . REDAO_CACHE_NM_SUFFIX_VITALS;
            $oContext = \raptor\Context::getInstance();
            $oRuntimeResultFlexCacheHandler = $oContext->getRuntimeResultFlexCacheHandler($this->m_groupname);
            if ($oRuntimeResultFlexCacheHandler != NULL)
            {
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheData($sThisResultName);
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheData(REDAO_CACHE_NM_WORKLIST);
                $oRuntimeResultFlexCacheHandler->invalidateRaptorCacheData(REDAO_CACHE_NM_PENDINGORDERS);
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function getSelectedPatientID()
    {
        return $this->getSessionVariable('selectedPatient');
    }

    public function getPatientMap($sPatientID)
    {
        try
        {
            if($sPatientID == NULL)
            {
                throw new \Exception("Cannot get patient map without a patient ID!");
            }
            $serviceName = $this->getCallingFunctionName();
            $args = array();
            $args['patientId'] = $sPatientID;
            $rawresult = $this->getServiceRelatedData($serviceName, $args);
            $vista_dob = $rawresult['dob'];
            if($vista_dob > '')
            {
                $dob = \raptor_ewdvista\EwdUtils::convertVistaDateTimeToDate($vista_dob);
            } else {
                $dob = '';
            }
            if(isset($rawresult['admitTimestamp']) && trim($rawresult['admitTimestamp']) > '')
            {
                $admitTimestamp = date("m/d/Y h:i a", strtotime($rawresult['admitTimestamp']));
            } else {
                $admitTimestamp = ' ';
            }
            if(!isset($rawresult['location']) || !is_array($rawresult['location']))
            {
                $location_tx = '';
            } else {
                $raw_location = $rawresult['location'];
                $parts = array();
                if(isset($raw_location['room']))
                {
                    $parts[] = 'Room:' . $raw_location['room'];
                }
                if(isset($raw_location['bed']))
                {
                    $parts[] = 'Bed:' . $raw_location['bed'];
                }
                $location_tx = implode(' / ', $parts);
            }
            if(!isset($rawresult['team']) || !is_array($rawresult['team']))
            {
                $teamName = ' ';
                $teamPcpName = ' ';
                $teamAttendingName = ' ';
            } else {
                $raw_team = $rawresult['team'];
                $teamName = isset($raw_team['name']) ? $raw_team['name'] : ' ';
                $teamPcpName = isset($raw_team['pcpName']) ? $raw_team['pcpName'] : ' ';
                $teamAttendingName = isset($raw_team['attendingName']) ? $raw_team['attendingName'] : ' ';
            }
            if(!isset($rawresult['siteIds']) || !is_array($rawresult['siteIds']))
            {
                $sites =  ' ';
                $sitePids =  ' ';
            } else {
                $sites = $rawresult['siteIds'];
                $sitePids = array();    //Build this from the other structure
                foreach($sites as $key=>$detail)
                {
                    if(is_array($detail))
                    {
                        $sitePids[] = array(
                            $detail['id'],
                            $detail['name'],
                        );
                    }
                }
            }
            $result['patientName']  			= isset($rawresult['name']) ? $rawresult['name'] : ' ';
            $result['ssn']          			= isset($rawresult['ssn']) ? $rawresult['ssn'] : ' ';
            $result['gender']       			= isset($rawresult['gender']) ? $rawresult['gender'] : ' ';
            $result['dob']          			= $dob;
            $result['ethnicity']    			= isset($rawresult['ethnicity']) ? $rawresult['ethnicity'] : ' ';
            $result['age']          			= isset($rawresult['age']) ? $rawresult['age'] : ' ';
            $result['maritalStatus']			= isset($rawresult['maritalStatus']) ? $rawresult['maritalStatus'] : ' ';
            $result['mpiPid']       			= isset($rawresult['mpiPid']) ? $rawresult['mpiPid'] : ' ';
            $result['mpiChecksum']  			= isset($rawresult['mpiChecksum']) ? $rawresult['mpiChecksum'] : ' ';
            $result['cwad'] 				= isset($rawresult['cwad']) ? $rawresult['cwad'] : ' ';
            $result['restricted'] 			= isset($rawresult['isRestricted']) ? $rawresult['isRestricted'] : ' ';
            $result['serviceConnected']                 = isset($rawresult['isServiceConnected']) ? $rawresult['isServiceConnected'] : ' ';
            $result['scPercent'] 			= isset($rawresult['scPercent']) ? $rawresult['scPercent'] : ' ';
            $result['confidentiality'] 			= isset($rawresult['confidentiality']) ? $rawresult['confidentiality'] : ' ';
            $result['patientFlags'] 			= isset($rawresult['flags']) ? $rawresult['flags'] : ' ';
            $result['cmorSiteId']	 		= isset($rawresult['cmorSiteId']) ? $rawresult['cmorSiteId'] : ' ';
            $result['needsMeansTest'] 			= isset($rawresult['needsMeansTest']) ? $rawresult['needsMeansTest'] : ' ';
            $result['currentMeansStatus']               = isset($rawresult['meansTestStatus']) ? $rawresult['meansTestStatus'] : ' ';
            $result['patientType'] 			= isset($rawresult['type']) ? $rawresult['type'] : ' ';
            $result['isVeteran'] 			= isset($rawresult['isVeteran']) ? $rawresult['isVeteran'] : ' ';
            $result['mpiChecksum'] 			= isset($rawresult['mpiChecksum']) ? $rawresult['mpiChecksum'] : ' ';
            $result['admitTimestamp'] 			= $admitTimestamp;
            $result['location'] 			= $location_tx;
            $result['inpatient'] 			= isset($rawresult['isInpatient']) ? $rawresult['isInpatient'] : ' ';
            $result['deceasedDate'] 			= isset($rawresult['deceased']) ? $rawresult['deceased'] : ' ';
            $result['isTestPatient'] 			= isset($rawresult['isTestPatient']) ? $rawresult['isTestPatient'] : ' ';
            $result['isLocallyAssignedMpiPid']          = isset($rawresult['isLocallyAssignedMpiPid']) ? $rawresult['isLocallyAssignedMpiPid'] : ' ';
            $result['teamName'] 			= $teamName;
            $result['teamPcpName'] 			= $teamPcpName;
            $result['teamAttendingName']                = $teamAttendingName;
            $result['sites'] 				= $sites;
            $result['sitePids']     			= $sitePids;
            //deprecated 20150911 $result['localPid']     			= 'missing';
            //deprecated 20150911 $result['vendorPid']    			= 'missing';
            //deprecated 20150911 $result['preferredFacility']                  = 'missing';
            //deprecated 20150911 $result['teamID'] 				= 'missing'; //Did not find id as part of returned structure but looks like javascript has impl.
            //deprecated 20150911 $result['activeInsurance'] 			= 'missing expected text'; //isset($rawresult['activeInsurance']) ? $rawresult['activeInsurance'] : ' ';
            //deprecated 20150911 $result['hasInsurance'] 			= 'missing'; //expecting boolean
            return $result;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
}
