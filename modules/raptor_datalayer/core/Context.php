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

module_load_include('php', 'raptor_datalayer', 'config/ehr_integration');

require_once 'CustomKeywords.php';
require_once 'UserInfo.php';
require_once 'EhrDao.php';
require_once 'RuntimeResultFlexCache.php';

defined('CONST_NM_RAPTOR_CONTEXT')
    or define('CONST_NM_RAPTOR_CONTEXT', 'R150921B'.EHR_INT_MODULE_NAME);

defined('DISABLE_CONTEXT_DEBUG')
    or define('DISABLE_CONTEXT_DEBUG', TRUE);

/**
 * The context has all the details relevant to the user of the session and their
 * current activities.
 * 
 * @author Frank Font of SAN Business Consultants
 */
class Context
{
    private $m_oRuntimeResultFlexCacheHandler = array();    //20150715
    private $m_aLocalCache = array();
    private $m_oVixDao = NULL;      //20140718 
    private $m_oEhrDao = NULL;      //20150714 
    
    /**
     * Return user readable dump that hides passwords.
     */
    public static function safeArrayDump($myarray)
    {
        try
        {
            if(is_array($myarray))
            {
                $dump = array();
                foreach($myarray as $key=>$value)
                {
                    $uckey = strtoupper($key);
                    if($uckey == 'ESIG' || $uckey == 'PASSWORD' || $uckey == 'PSWD')
                    {
                        $dump[] = "$key => !!!VALUEMASKED!!!";
                    } else {
                        $dump[] = "$key => " . print_r($value,TRUE);
                    }
                }
                $keycount = count($dump);
                return "SAFE ARRAY DUMP ($keycount top level keys)...\n\t" . implode("\n\t",$dump);
            } else {
                error_log('Expected an array in safeDumpArray but instead got ' 
                        . $myarray);
                return "SAFE ARRAY DUMP non-array>>>".print_r($myarray,TRUE);
            }
        } catch (\Exception $ex) {
            error_log('Expected an array in safeDumpArray but instead got ' 
                    . $myarray." and error ".$ex->getMessage());
            return "SAFE ARRAY DUMP with exception>>>".print_r($myarray,TRUE);
        }
    }

    public static function getFastScrambled($cleartext)
    {
        try
        {
            $timetx = ''.time();
            $modifiedtext = substr($timetx,2,5) . "$cleartext";
            $unpacked = unpack('H*', $modifiedtext);
            $scrambedtext = 'ST'.array_shift( $unpacked );
            return $scrambedtext;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    public static function getFastUnscrambled($scrambedtext)
    {
        try 
        {
            $hex = substr($scrambedtext,2);
            $modifiedtext = pack('H*', $hex);
            $cleartext = substr($modifiedtext,5);
            return $cleartext;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Put value into session with special variable names
     */
    public static function saveSessionValue($name, $value, $encrypt=FALSE)
    {
        try
        {
            $fullname = CONST_NM_RAPTOR_CONTEXT.'_'.$name;
            $allnamesname = CONST_NM_RAPTOR_CONTEXT.'_allnames';
            if(!isset($_SESSION[$allnamesname]) || !is_array($_SESSION[$allnamesname]))
            {
                $_SESSION[$allnamesname] = array($allnamesname);
            }
            if($value != NULL)
            {
                $_SESSION[$allnamesname][$fullname] = microtime(TRUE);  //When the value got set
                if(!$encrypt)
                {
                    $_SESSION[$fullname] = $value;
                } else {
                    $scrambed = self::getFastScrambled($value);
                    $_SESSION[$fullname] = $scrambed;
                }
            } else {
                $_SESSION[$fullname] = NULL;
                unset($_SESSION[$allnamesname][$fullname]);
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Get the names of all the specially named session values
     */
    public static function getAllSessionValueNames()
    {
        try
        {
            $allnamesname = CONST_NM_RAPTOR_CONTEXT.'_allnames';
            if(!isset($_SESSION[$allnamesname]) || !is_array($_SESSION[$allnamesname]))
            {
                $_SESSION[$allnamesname] = array();
            }
            return $_SESSION[$allnamesname];
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Get value from session with special variable names
     */
    public static function getSessionValue($name, $value_if_missing=NULL, $decrypt=FALSE)
    {
        $fullname = CONST_NM_RAPTOR_CONTEXT.'_'.$name;
        if(isset($_SESSION[$fullname]))
        {
            if(!$decrypt)
            {
                $result = $_SESSION[$fullname];
            } else {
                $result = self::getFastUnscrambled($_SESSION[$fullname]);
            }
        } else {
            $result = $value_if_missing;
        }
        return $result;
    }

    /**
     * Check value from session with special variable names
     */
    public static function hasSessionValue($name, $null_is_sameasmissing = TRUE)
    {
        $fullname = CONST_NM_RAPTOR_CONTEXT.'_'.$name;
        if($null_is_sameasmissing)
        {
            return isset($_SESSION[$fullname]) && $_SESSION[$fullname] !== NULL;
        } else {
            return isset($_SESSION[$fullname]);
        }
    }
    
    /**
     * Quick access to a few things that have immutable relationships
     */
    private function checkLocalCache($sKey)
    {
        if(isset($this->m_aLocalCache[$sKey]))
        {
            //error_log('Successful hit on local cache for '.$sKey);
            $aItem = $this->m_aLocalCache[$sKey];
            $aItem['hit'] = microtime(TRUE);    //20150731
            return $aItem['value'];
        }
        return NULL;
    }
    
    /**
     * Important that you only map immutable relationships!
     */
    private function updateLocalCache($sKey,$oValue)
    {
        if(count($this->m_aLocalCache) > 1000)
        {
            //Leave evidence of possible tuning requirement.
            error_log("Administrator warning: The local cache size at $sKey is ".$this->m_aLocalCache);
        }
        $aItem['hit'] = 0;
        $aItem['value'] = $oValue;
        $this->m_aLocalCache[$sKey] = $aItem;
    }

    /**
     * Return a formatted string to help debug array content.
     */
    public static function pretty_debug_array($glue,$array,$indent='',$keystring='')
    {
        $result = '';
        foreach($array as $key=>$item)
        {
            $newkeystring = $keystring.'[\''.$key.'\']';
            if(strlen($key) > 80)
            {
                $padded = $newkeystring;
            } else {
                $padded = str_pad($newkeystring, 80);
            }
            $result .= $glue . '@' . $padded."=\t" . $indent;
            if(is_array($item))
            {
                $result .= 'ARRAY(count='.count($item).')' . $glue . Context::pretty_debug_array($glue, $item, $indent.'  ',$newkeystring);
            } else {
                $result .= print_r($item, TRUE);
            }
        }
        return $result;
    }
    
    public static function debugGetCallerInfo($nShowLevelCount=5,$nStartAncestry=1,$bReturnArray=FALSE)
    {
        $aResult = array();
        $aResult['StartAncestryLevel'] = $nStartAncestry;
        $aResult['ShowLevels'] = $nShowLevelCount;
        $trace=debug_backtrace();
        $nLastAncestry = $nStartAncestry + $nShowLevelCount - 1;
        for($nAncestry=$nStartAncestry; $nAncestry <= $nLastAncestry; $nAncestry++  )
        {
            if(isset($trace[$nAncestry]))
            {
                $caller=$trace[$nAncestry];
            } else {
                $caller=array('function'=>'NO CALLING FUNCTION');
                break;  //Get out now.
            }
            $aResult[$nAncestry]['function'] = $caller['function'];
            if (isset($caller['class']))
            {
                $aResult[$nAncestry]['class'] = $caller['class'];
            } else {
                $aResult[$nAncestry]['class']=NULL;
            }
        }
        if($bReturnArray)
        {
            return $aResult;
        } else {
                      
            $sTrace = '<ol>';
            foreach($aResult as $aItem)
            {
                $sTrace .= '<li>' . print_r($aItem,TRUE);
            }
            $sTrace .= '</ol>';
            return $sTrace;
        }
    }
    
    public static function debugDrupalMsg($message,$type='status')
    {
        if(!DISABLE_CONTEXT_DEBUG)
        {
            drupal_set_message('CONTEXT DEBUG>>>'.$message . '...CALLED BY>>>'.Context::debugGetCallerInfo(5), $type);
            error_log('CONTEXT DEBUG ['.$type.']>>>'.$message);
        }
    }
    
    private function __construct($nUID)
    {
        try
        {
            if(!is_numeric($nUID))
            {
                throw new \Exception("The UID passed into contructor of Context must be numeric, but instead got '$nUID'");
            }

            //Do we need to initialize some fundamental session variables?
            if(!self::hasSessionValue('UID'))
            {
                //We only do this IF user is not already in an active session
                self::saveSessionValue('UID', $nUID);
                self::saveSessionValue('InstanceTimestamp', microtime(TRUE));
                self::saveSessionValue('LastUpdateTimestamp', microtime(TRUE));
                self::saveSessionValue('InstanceUserActionTimestamp', time());
                self::saveSessionValue('InstanceSystemActionTimestamp', time());

                //Purge old cache contents now.
                RuntimeResultFlexCache::purgeOldItems();
            } else {
//error_log("LOOK in construct session>>>>" . print_r($_SESSION,TRUE));                
            }

        } catch (\Exception $ex) {
            throw $ex;
        }
    }    

    /**
     * Make it simpler to output details about this instance.
     * @return text
     */
    public function __toString()
    {
        try
        {
            $nUID = self::getSessionValue('UID','NONE');
            $rc = self::getSessionValue('REGENERATED_COUNT','UNKNOWN');
            $lct = self::getSessionValue('CREATED', 'UNKNOWN');
            $its = self::getSessionValue('InstanceTimestamp', 'UNKNOWN');
            $luts = self::getSessionValue('LastUpdateTimestamp', 'UNKNOWN');
            $ctid = self::getSessionValue('CurrentTicketID', 'UNKNOWN');
            $ehr_dao = $this->getEhrDao(FALSE);
            $info = 'Context of user ['.$nUID.']'
                    . ' instance created=['.$its . ']'
                    . ' last updated=['.$luts . ']'
                    . ' current tid=['.$ctid.']'
                    . " session regenerated $rc times last created $lct"
                    . "\n\tDAO=$ehr_dao";
            return $info;
        } catch (\Exception $ex) {
            return 'Cannot get toString of Context because '.$ex;
        }
    }
    
    public function getInstanceTimestamp()
    {
        return self::getSessionValue('InstanceTimestamp');
    }
    
    public function getLastUpdateTimestamp()
    {
        return self::getSessionValue('LastUpdateTimestamp');
    }
    
    /**
     * Tell us when this user last took an action.
     */
    public function getInstanceUserActionTimestamp()
    {
        try
        {
            $luats = self::getSessionValue('InstanceUserActionTimestamp');
            return $luats;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Tell us how long this user has been idle.
     */
    public function getUserIdleSeconds()
    {
        try
        {
            $luats = $this->getInstanceUserActionTimestamp();
            $now = time();
            $diff = $now - $luats;
            return $diff;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Return a value instead of NULL
     */
    public function valForNull($candidate, $altvalue=0)
    {
        if(!isset($candidate) || $candidate === NULL)
        {
            return $altvalue;
        }
        return $candidate;
    }

    /**
     * Return a value instead of NULL or missing
     */
    public function valForNullOrMissing($map, $key, $altvalue=0)
    {
        try
        {
            if(!isset($map[$key]))
            {
                return $altvalue;
            } else {
                $candidate = $map[$key];
                if(!isset($candidate) || $candidate === NULL)
                {
                    return $altvalue;
                }
                return $candidate;
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Only for extreeme situations where you need to clear the session contents
     */
    public static function forceClearUserSession()
    {
        global $user;
        $uid = $user->uid;
        error_log("About to clear session via forceClearUserSession for user $uid");
        unset($_SESSION['CREATED']);  
        unset($_SESSION[CONST_NM_RAPTOR_CONTEXT]);

        $all_literalnames = self::getAllSessionValueNames();
        foreach($all_literalnames as $onename=>$onevalue)
        {
            unset($_SESSION[$onename]);
        }
        error_log("Cleared session via forceClearUserSession for user $uid");
    }

    private static $m_singleton;

    public static function hasSingletonInstance()
    {
        return (NULL !== static::$m_singleton);
    }

    public static function getSingletonInstance($uid)
    {
        if (NULL === static::$m_singleton) {
            static::$m_singleton = new static($uid);
        }
        
        return static::$m_singleton;
    }
    
    public static function getInstance($forceReset=FALSE, $bSystemDrivenAction=FALSE)
    {
        try
        {
            if (session_status() == PHP_SESSION_NONE) 
            {
                error_log('CONTEXTgetInstance::Starting session');
                session_start();
                drupal_session_started(TRUE);       //If we dont do this we risk warning messages elsewhere.
            }        
            $currentpath = strtolower(current_path());
            $forceReset = ($currentpath == 'user/login' || $currentpath == 'user/logout');
            if(!self::hasSessionValue('CREATED')) 
            { 
                $startedtime = time();
                error_log('CONTEXTgetInstance::Setting CREATED value of session to '.$startedtime);
                $_SESSION['CREATED'] = $startedtime;
                $_SESSION['REGENERATED_COUNT'] = 0;
                self::saveSessionValue('CREATED', $startedtime);
                self::saveSessionValue('REGENERATED_COUNT', 0);
            } 
            global $user;
            if(user_is_logged_in())
            {
                $tempUID = $user->uid;
            } else {
                $tempUID = 0;
            }
            $candidate = self::getSingletonInstance($tempUID);
            if($tempUID != $candidate->getUID())
            {
                self::saveSessionValue('UID', $tempUID);
            }
            if($tempUID > 0)
            {
                $useridleseconds = intval($candidate->getUserIdleSeconds());
                $max_idle = USER_TIMEOUT_SECONDS 
                        + USER_TIMEOUT_GRACE_SECONDS 
                        + USER_ALIVE_INTERVAL_SECONDS
                        + KICKOUT_DIRTYPADDING;
                $bContextDetectIdleTooLong = ($useridleseconds > $max_idle);
                $bAccountConflictDetected = $candidate->hasAccountConflict($tempUID);
                if(!$forceReset && !$bContextDetectIdleTooLong && !$bAccountConflictDetected)
                {
                    if($bSystemDrivenAction)
                    {
                        //Just update this guy
                        self::saveSessionValue('InstanceSystemActionTimestamp', time());
                    } else {
                        //Update that the user is actively doing things
                        $candidate->updateUserActivityTracking($tempUID);
                    }
                } else {
                    //Now trigger logout if account conflict was detected.
                    $candidateVistaUserID = $candidate->getVistaUserID();
                    if($bAccountConflictDetected || $bContextDetectIdleTooLong)
                    {
                        //Don't kick out an administrator in a protected URL
                        $is_protected_adminuser = \raptor\UserInfo::is_protected_adminuser();
                        if(!$is_protected_adminuser)
                        {
                            //Don't get stuck in an infinite loop.
                            if(substr($candidateVistaUserID,0,8) !== 'kickout_')
                            {
                                //Prevent duplicate user messages.
                                $aForceLogoutReason = self::getSessionValue('ForceLogoutReason',NULL);
                                if($aForceLogoutReason == NULL)
                                {
                                    //Not already set, so set it now.
                                    if($bContextDetectIdleTooLong)
                                    {
                                        $useridleseconds = intval($candidate->getUserIdleSeconds());
                                        $usermsg = 'You are kicked out because context has detected excessive'
                                                . " idle time of $useridleseconds seconds";
                                        $errorcode = ERRORCODE_KICKOUT_TIMEOUT;
                                        $kickoutlabel = 'TIMEOUT';
                                    } else {
                                        if($candidateVistaUserID > '')
                                        {
                                            $usermsg = 'You are kicked out because another workstation has'
                                                    . ' logged in as the same'
                                                    . ' RAPTOR user account "'
                                                    . $candidateVistaUserID.'"';
                                            $errorcode = ERRORCODE_KICKOUT_ACCOUNTCONFLICT;
                                            $kickoutlabel = 'ACCOUNT CONFLICT';
                                        } else {
                                            //This can happen to a NON VISTA admin user for timeout and things like that.
                                            $usermsg = 'Your admin account has timed out';
                                            $errorcode = ERRORCODE_KICKOUT_TIMEOUT;
                                            $kickoutlabel = 'TIMEOUT CONFLICT';
                                        }
                                    }
                                    drupal_set_message($usermsg, 'error');
                                    $aForceLogoutReason = array();
                                    $aForceLogoutReason['code'] = $errorcode;
                                    $aForceLogoutReason['text'] = $usermsg;
                                    self::saveSessionValue('ForceLogoutReason', $aForceLogoutReason);
                                    self::saveSessionValue('VistaUserID', 'kickout_' . $candidateVistaUserID);
                                    self::saveSessionValue('VAPassword', NULL);
                                    try
                                    {
                                        $dao = $candidate->getEhrDao(TRUE);
                                        $dao->disconnect();
                                    } catch (\Exception $ex) {
                                        error_log("Failed to issue disconnect on dao because $ex");
                                    }
                                }

                                //$_SESSION[CONST_NM_RAPTOR_CONTEXT] = serialize($candidate); //Store this NOW!!!
                                error_log("CONTEXT KICKOUT $kickoutlabel DETECTED ON [" 
                                        . $candidateVistaUserID . '] >>> ' 
                                        . time() 
                                        . "\n\tSESSION>>>>" . print_r($_SESSION,TRUE));

                                $candidate->forceSessionRefresh(0);  //Invalidate any current form data now!
                            }
                        }
                    }
                }
            }
            $candidate->getEhrDao(TRUE);
            return $candidate;
        } catch (\Exception $ex) {
            error_log("Failed getInstance because $ex");
            throw $ex;
        }
    }    
    
    private function updateUserActivityTracking($tempUID)
    {
        try
        {
            //Log our activity.
            $stopwatchmoment = time();
            $updated_dt = date("Y-m-d H:i:s", $stopwatchmoment);
            db_insert('raptor_user_activity_tracking')
            ->fields(array(
                    'uid'=>$tempUID,
                    'action_cd' => UATC_GENERAL,
                    'ipaddress' => $_SERVER['REMOTE_ADDR'],
                    'sessionid' => session_id(),
                    'updated_dt'=>$updated_dt,
                ))
                ->execute();

            //Write the recent activity to the single record that tracks it too.
            db_merge('raptor_user_recent_activity_tracking')
            ->key(array('uid'=>$tempUID,
                ))
            ->fields(array(
                    'uid'=>$tempUID,
                    'ipaddress' => $_SERVER['REMOTE_ADDR'],
                    'sessionid' => session_id(),
                    'most_recent_action_dt'=>$updated_dt,
                    'most_recent_action_cd' => UATC_GENERAL,
                ))
                ->execute();

            $this->saveSessionValue('InstanceUserActionTimestamp', $stopwatchmoment);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    private function hasAccountConflict($tempUID)
    {
        try
        {
            //Update user action tracking in datatabase.
            $bAccountConflictDetected = FALSE;  //Assume everything is okay to start.
            $thismoment = time();
            $reftime = self::getSessionValue('InstanceUserActionTimestamp');
            if(is_numeric($reftime))
            {
                $nElapsedSeconds = $thismoment - $reftime; // m_nInstanceUserActionTimestamp;
            } else {
                $nElapsedSeconds = 0;
            }
            if(isset($tempUID) 
                    && $tempUID !== 0 && ($nElapsedSeconds > 10))
            {
                try
                {
                    //First make sure no one else is logged in as same UID
                    $mysessionid = session_id();
                    $other_or = db_or();
                    $other_or->condition('ipaddress', $_SERVER['REMOTE_ADDR'],'<>');
                    $other_or->condition('sessionid', $mysessionid ,'<>');
                    $resultOther = db_select('raptor_user_recent_activity_tracking','u')
                            ->fields('u')
                            ->condition('uid',$tempUID,'=')
                            ->condition($other_or)
                            ->orderBy('most_recent_action_dt','DESC')
                            ->execute();
                    if($resultOther->rowCount() > 0)
                    {
                        //There is always only one record in raptor_user_recent_activity_tracking
                        $resultMe = db_select('raptor_user_activity_tracking','u')
                                ->fields('u')
                                ->condition('uid',$tempUID,'=')
                                ->condition('ipaddress',$_SERVER['REMOTE_ADDR'],'=')
                                ->condition('sessionid', $mysessionid ,'=')
                                ->orderBy('updated_dt','DESC')
                                ->execute();
                        if($resultMe->rowCount() > 0)
                        {
                            $other = $resultOther->fetchAssoc();
                            $me = $resultMe->fetchAssoc();
                            $conflict_logic_info=array();
                            if($other['ipaddress'] == $me['ipaddress'])
                            {
                                //This is on same machine.
                                $nSesElapsedSeconds = (time() - $_SESSION['CREATED']);
                                $conflict_logic_info['same-machine-elapsed-seconds'] 
                                        = $nSesElapsedSeconds;
                                if (!isset($_SESSION['CREATED']) 
                                        || $nSesElapsedSeconds < CONFLICT_CHECK_DELAY_SECONDS)
                                {
                                    //Allow for possibility that the session ID has changed for a single user
                                    $bAccountConflictDetected = FALSE;
                                } else {
                                    //Possible the user has two browsers open with same account.
                                    $bAccountConflictDetected 
                                        = $other['most_recent_action_dt'] >= $me['updated_dt'];
                                }
                            } else {
                                //Simple check
                                $bAccountConflictDetected 
                                        = $other['most_recent_action_dt'] >= $me['updated_dt'];
                                $conflict_logic_info['simple date check'] = $bAccountConflictDetected;
                            }
                            if($bAccountConflictDetected)
                            {
                                error_log('CONTEXTgetInstance::Account conflict has '
                                        . 'been detected at '.$_SERVER['REMOTE_ADDR']
                                        . ' for UID=['.$tempUID.']'
                                        . ' this user at '.$me['ipaddress']
                                        . ' other user at '.$other['ipaddress'] 
                                        . ' user sessionid ['.$mysessionid.']'
                                        . ' other sessionid ['.$other['sessionid'].']' 
                                        . '>>> TIMES = other[' 
                                        . $other['most_recent_action_dt'] 
                                        . '] vs this['
                                        . $me['updated_dt'] 
                                        . '] logicinfo='
                                        .print_r($conflict_logic_info,TRUE));
                            } else {
                                error_log('CONTEXTgetInstance::No account conflict detected '
                                        . 'on check (es='.$nElapsedSeconds.') for UID=['.$tempUID.'] '
                                        . 'this user at '.$_SERVER['REMOTE_ADDR']
                                        . ' other user at '.$other['ipaddress'] 
                                        . '>>> TIMES = other[' 
                                        . $other['most_recent_action_dt'] 
                                        . '] vs this['
                                        . $me['updated_dt'] . ']');
                            }
                        }                    
                    }
                } catch (\Exception $ex) {
                    //Log this but keep going.
                    error_log('CONTEXT Trouble checking for account conflict >>>'.print_r($ex,TRUE));
                }
            }
            return $bAccountConflictDetected;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    public function hasForceLogoutReason()
    {
        $aForceLogoutReason = $this->getForceLogoutReason();
        return ($aForceLogoutReason !== NULL);
    }
    
    public function getForceLogoutReason()
    {
        return self::getSessionValue('ForceLogoutReason', NULL);
    }
    
    public function clearForceLogoutReason()
    {
        self::saveSessionValue('ForceLogoutReason', NULL);
    }
    
    /**
     * For the 2014 release, site ID is a constant for the entire installation.
     * @return the site ID of this installation
     */
    function getSiteID()
    {
        return VISTA_SITE;
    }
    
    function getFullyQualifiedTicketID($ticketIEN)
    {
        return VISTA_SITE . '-' . trim($ticketIEN);
    }
    
    function getUID()
    {
        $nUID = self::getSessionValue('UID',NULL);
        return $nUID;
        //return $this->m_nUID;
    }
    
    function getVistaUserID()
    {
        $sVUID = self::getSessionValue('VistaUserID',NULL);
        return $sVUID;
    }

    function getVixDao()
    {
        module_load_include('php', IMG_INT_MODULE_NAME, 'core/VixDao');
        if($this->m_oVixDao == NULL)
        {
            $sVistaUserID = self::getSessionValue('VistaUserID',NULL,TRUE);
            $sVAPassword = self::getSessionValue('VAPassword',NULL,TRUE);
            $this->m_oVixDao = new \raptor\VixDao($sVistaUserID, $sVAPassword);
        }
        return $this->m_oVixDao;
    }

    function getUserInfo($bFailIfNoUser=TRUE)
    {
        $nUID = self::getSessionValue('UID',NULL);
        if($bFailIfNoUser && ($nUID == NULL || $nUID < 1))
        {
            error_log('Did NOT find a valid UID!!!');
            global $base_url;
            die('<h1>Expired RAPTOR session</h1>'
                    . '<p>Did NOT find a valid user instance!<p>'
                    . '<p>TIP: <a href="'.$base_url.'/user/login">login</a></p>');
        }
        $oUserInfo = new \raptor\UserInfo($nUID);
        return $oUserInfo;
    }
    
    public function getWorklistMode()
    {
        $sWLM = self::getSessionValue('WorklistMode', NULL);
        if ($sWLM == NULL)
        {
            $sWLM = 'P';     //Default worklist mode
            self::saveSessionValue('WorklistMode', $sWLM);
        }
        return $sWLM;
    } 
    
    /**
     * P=Protocol *DEFAULT*
     * E=Examination
     * I=Interpretation
     * Q=QA
     * 
     * @param type $sWMODE 
     */
    public function setWorklistMode($sWMODE)
    {
        try
        {
            $nLastUpdateTimestamp = microtime(TRUE);
            self::saveSessionValue('LastUpdateTimestamp', $nLastUpdateTimestamp);
            $current_wlm = $this->getWorklistMode();
            if($current_wlm != $sWMODE)
            {
                if(!in_array($sWMODE, array('P', 'E', 'I', 'Q', 'S')))
                {
                    throw new \Exception("Invalid WorklistMode='$sWMODE'!");
                }
                self::saveSessionValue('WorklistMode', $sWMODE);
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    public function clearSelectedTrackingID($bSaveSession=TRUE)
    {
        try
        {
//error_log("LOOK clearSelectedTrackingID");            
            Context::debugDrupalMsg('called clearSelectedTrackingID');
            self::saveSessionValue('CurrentTicketID', NULL);
            $nLastUpdateTimestamp = microtime(TRUE);
            self::saveSessionValue('LastUpdateTimestamp', $nLastUpdateTimestamp);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * @return boolean TRUE if a tracking id is currently selected
     */
    public function hasSelectedTrackingID()
    {
        return $this->getSelectedTrackingID() !== NULL;
    }

    /**
     * @return NULL or the currently selected tracking ID
     */
    public function getSelectedTrackingID()
    {
        try 
        {
            $candidate = Context::getInstance();    //Important that we ALWAYS pull it from persistence layer here!
            self::saveSessionValue('PersonalBatchStackMessage', NULL);
            $sCurrentTicketID = self::getSessionValue('CurrentTicketID', NULL);
            if($sCurrentTicketID == NULL) //!isset($candidate->m_sCurrentTicketID) || $candidate->m_sCurrentTicketID == NULL)
            {
                //If there is anything in the personal batch stack, grab it now.
                $sCurrentTicketID = $candidate->popPersonalBatchStack();
                self::saveSessionValue('CurrentTicketID', $sCurrentTicketID);
                if($sCurrentTicketID !== NULL)
                {
                    $pbsize = $candidate->getPersonalBatchStackSize();
                    if($pbsize === 1)
                    {
                        $sPersonalBatchStackMessage = 'You have 1 remaining personal batch selection.';
                        self::saveSessionValue('PersonalBatchStackMessage', $sPersonalBatchStackMessage);
                    } else if($pbsize > 1){
                        $sPersonalBatchStackMessage = 'You have ' . $pbsize . ' remaining personal batch selections.';
                        self::saveSessionValue('PersonalBatchStackMessage', $sPersonalBatchStackMessage);
                    }
                }
            }
            return $sCurrentTicketID;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * @return type True if specified parameters, concatenated, are matching tracking ID store in the context
     */
    public function isDataMatchingTrackingID($key)
    {
        return $this->getSelectedTrackingID() == $key;  //20140604
    }

    /**
     * Critical interface that sets dependent selections and saves to the session.
     */
    function setSelectedTrackingID($sTrackingID, $bClearPersonalBatchStack=FALSE)
    {
        try
        {
            $prevtime = self::getSessionValue('LastUpdateTimestamp'); //$this->m_nLastUpdateTimestamp;
            $prevtid = self::getSessionValue('CurrentTicketID',NULL); //$this->m_nLastUpdateTimestamp;
            if($bClearPersonalBatchStack)   //20140619
            {
                $this->clearPersonalBatchStack();
            }
            $nLastUpdateTimestamp = microtime(TRUE);
            self::saveSessionValue('LastUpdateTimestamp', $nLastUpdateTimestamp);
            $aParts = explode('-',$sTrackingID);    //Allow for older type ticket tracking format
            if(count($aParts) == 1)
            {
                //Just IEN
                $nIEN = $aParts[0];
            } else {
                //Site-IEN
                $nIEN = $aParts[1];
            }
            //$this->m_sCurrentTicketID = $sTrackingID;
            self::saveSessionValue('CurrentTicketID', $sTrackingID);

            $oMC = $this->getEhrDao();
            $sPatientID = $this->checkLocalCache($sTrackingID);
            if($sPatientID == NULL)
            {
                $sPatientID = $oMC->getPatientIDFromTrackingID($sTrackingID);
                $this->updateLocalCache($sTrackingID, $sPatientID);
            }
            $prevpid = $oMC->getSelectedPatientID();
            $oMC->setPatientID($sPatientID);
            /*
            $logmsg = "Finished setSelectedTrackingID"
                    . " to tid=[$sTrackingID] and pid=[$sPatientID]"
                    . " (prevtid=[$prevtid] and prevpid=[$prevpid] from last update $prevtime)"
                    . "\n\tCurrent context>>> $this";
             */
        } catch (\Exception $ex) {
            throw new \Exception("Failed setSelectedTrackingID($sTrackingID, $bClearPersonalBatchStack) because $ex",99876,$ex);
        }
    }

    
    /**
     * @param array $aPBatch array representing stack of tracking IDs, last one in array is top of the stack
     */
    function setPersonalBatchStack($aPBatch)
    {
        try
        {
            self::saveSessionValue('LastUpdateTimestamp', microtime(TRUE));
            self::saveSessionValue('PersonalBatchStackMessage', NULL);
            self::saveSessionValue('PersonalBatchStack', $aPBatch);
            self::saveSessionValue('CurrentTicketID', NULL);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Clear the stack.
     */
    function clearPersonalBatchStack()
    {
        try
        {
            Context::debugDrupalMsg('<h1>called clearPersonalBatchStack</h1>');
            self::saveSessionValue('PersonalBatchStack', NULL);
            self::saveSessionValue('LastUpdateTimestamp', microtime(TRUE));
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * @return true if there are tickets in the stack
     */
    function hasPersonalBatchStack()
    {
        $pbs = self::getSessionValue('PersonalBatchStack');
        return is_array($pbs);
        //return (isset($this->m_aPersonalBatchStack) && is_array($this->m_aPersonalBatchStack));
    }

    function debugPersonalBatchStack()
    {
        $pbs = self::getSessionValue('PersonalBatchStack');
        return print_r($pbs, TRUE);
    }

    /**
     * @return NULL if nothing is on the stack.
     */
    function popPersonalBatchStack()
    {
        try
        {
            if(!$this->hasPersonalBatchStack())
            {
                Context::debugDrupalMsg("<h1>Popped nothing off the stack </h1>");
                $nTID = NULL;
            } else {
                $pbs = self::getSessionValue('PersonalBatchStack');
                $nTID = array_pop($pbs);
                self::saveSessionValue('PersonalBatchStack', $pbs);
                Context::debugDrupalMsg("<h1>Popped $nTID off the stack ". print_r($pbs, TRUE)  ."</h1>");
            }
            return $nTID;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    function getPersonalBatchStackSize()
    {
        try
        {
            if(!$this->hasPersonalBatchStack())
            {
                $thecount = 0;
            } else {
                $pbs = self::getSessionValue('PersonalBatchStack');
                $thecount = count($pbs);
            }
            return $thecount;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Show this to the user so they know they are in a personal batch mode
     * @return string or null
     */
    function getPersonalBatchStackMessage()
    {
        $pbsmsg = self::getSessionValue('PersonalBatchStackMessage');
        return $pbsmsg;
    }

    /**
     * Returns empty string if authenticated OK, else associative array with following keys: ERRNUM, ERRSUMMARY, ERRDETAIL 
     */
    public function authenticateSubsystems($sVistaUserID, $sVAPassword) 
    {
        try
        {
            self::saveSessionValue('VistaUserID', $sVistaUserID, TRUE);
            self::saveSessionValue('VAPassword', $sVAPassword, TRUE);
            $result = $this->authenticateEhrSubsystem($sVistaUserID, $sVAPassword);
            $updated_dt = date("Y-m-d H:i:s", time());
            global $user;
            $tempUID = $user->uid;  
            if($tempUID != NULL && $tempUID != 0)
            {
                try
                {
                    db_insert('raptor_user_activity_tracking')
                    ->fields(array(
                            'uid'=>$tempUID,
                            'action_cd' => UATC_LOGIN,
                            'ipaddress' => $_SERVER['REMOTE_ADDR'],
                            'sessionid' => session_id(),
                            'updated_dt'=>$updated_dt,
                        ))
                        ->execute();
                        //Write the recent activity to the single record that tracks it too.
                        db_merge('raptor_user_recent_activity_tracking')
                        ->key(array('uid'=>$tempUID,
                            ))
                        ->fields(array(
                                'uid'=>$tempUID,
                                'ipaddress' => $_SERVER['REMOTE_ADDR'],
                                'sessionid' => session_id(),
                                'most_recent_login_dt'=>$updated_dt,
                                'most_recent_action_dt'=>$updated_dt,
                                'most_recent_action_cd' => UATC_LOGIN,
                            ))
                            ->execute();
                } catch (\Exception $ex) {
                    error_log('Trouble updating raptor_user_activity_tracking>>>'
                            .print_r($ex,TRUE));
                    db_insert('raptor_user_activity_tracking')
                    ->fields(array(
                            'uid'=>$tempUID,
                            'action_cd' => ERRORCODE_AUTHENTICATION,
                            'ipaddress' => $_SERVER['REMOTE_ADDR'],
                            'sessionid' => session_id(),
                            'updated_dt'=>$updated_dt,
                        ))
                        ->execute();
                }
            }
            return $result;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    private function authenticateEhrSubsystem($sVistaUserID, $sVAPassword) 
    {
        try 
        {
            $its = self::getSessionValue('InstanceTimestamp');
            error_log('WORKFLOWDEBUG>>>Called authenticateEhrSubsystem for ' 
                    . $sVistaUserID . ' from '. $_SERVER['REMOTE_ADDR']  
                    . ' in ' . $its);
            
            // NOTE - hardcoded vista site per config.php->VISTA_SITE
            $loginResult = $this->getEhrDao()->connectAndLogin(VISTA_SITE, $sVistaUserID, $sVAPassword);
            $this->clearForceLogoutReason();    //Important that we clear it now otherwise can be stuck in kickout mode.
            return ''; // per data functions doc - return empty string on success
        }  catch (\Exception $ex) {
            error_log('Failed to log into EHR because '.$ex->getMessage());
            throw new \Exception('Failed to log into EHR',99765,$ex);
        }
    }
    
    /**
     * @return type TRUE/FALSE
     */
    public function isAuthenticatedInSubsystem() {
        return $this->isAuthenticatedInEhrSubsystem();
    }
    
    private function isAuthenticatedInEhrSubsystem() {
        return $this->getEhrDao()->isAuthenticated();
    }

    private function clearAllContext()
    {
        $this->logoutEhrSubsystem();
        self::saveSessionValue('InstanceClearedTimestamp', microtime(TRUE));
        self::saveSessionValue('UID', 0);        
        self::saveSessionValue('VistaUserID', NULL);        
        self::saveSessionValue('VAPassword', NULL);        
        self::saveSessionValue('CurrentTicketID', NULL);        
        self::saveSessionValue('PersonalBatchStack', NULL);        
        self::saveSessionValue('LastUpdateTimestamp', microtime(TRUE));        
        $all_literalnames = self::getAllSessionValueNames();
        foreach($all_literalnames as $onename)
        {
            unset($_SESSION[$onename]);
        }
        return '';
    }
    
    /**
     * Throws exception if fails
     */
    public function logoutSubsystems() 
    {
        try
        {
            $nUID = self::getSessionValue('UID',NULL);
            if($nUID != NULL && $nUID > 0)
            {
                $updated_dt = date("Y-m-d H:i:s", time());
                db_insert('raptor_user_activity_tracking')
                ->fields(array(
                        'uid'=>$nUID,
                        'action_cd' => UATC_LOGOUT,
                        'ipaddress' => $_SERVER['REMOTE_ADDR'],
                        'sessionid' => session_id(),
                        'updated_dt'=>$updated_dt,
                    ))
                    ->execute();
                //Write the recent activity to the single record that tracks it too.
                db_merge('raptor_user_recent_activity_tracking')
                    ->key(array('uid'=>$nUID))
                    ->fields(array(
                            'uid'=>$nUID,
                            'ipaddress' => $_SERVER['REMOTE_ADDR'],
                            'sessionid' => session_id(),
                            'most_recent_logout_dt'=>$updated_dt,
                            'most_recent_action_dt'=>$updated_dt,
                            'most_recent_action_cd' => UATC_LOGOUT,
                        ))
                        ->execute();
            }
            $this->clearAllContext();
        } catch (\Exception $ex) {
            error_log('Trouble updating raptor_user_activity_tracking>>>' . print_r($ex,TRUE));
        }
    }
    
    private function logoutEhrSubsystem() 
    {
        try {
            $this->getEhrDao()->disconnect();
            $_SESSION['CTX_EHRDAO_NEW_START'] = NULL;
            $_SESSION['CTX_EHRDAO_NEW_DONE'] = NULL;
            return '';
        } catch (\Exception $ex) {
            //Log it and continue
            error_log('Failed logout of EHR system because '.$ex);
        }
    }

    /**
     * When the session is refreshed all existing form data is invalid.
     * We need to refresh the session at least before the server invalidates it.
     * @param type $grace_seconds do not refresh if newer than this
     */
    public function forceSessionRefresh($grace_seconds=-1)
    {
        if(!user_is_logged_in() || $this->getUID() == 0)
        {
            //Never time out if no one is logged in anyways.
             $_SESSION['CREATED'] = time();  // update creation time
        } else {
            if($grace_seconds < 0)
            {
                //Use the configured default.
                $grace_seconds = SESSION_KEY_TIMEOUT_SECONDS;
            }
            if ((!isset($_SESSION['CREATED']) 
                || (time() - $_SESSION['CREATED']) > $grace_seconds))
            {
                $currentpath = current_path();
                // session started more than SESSION_REFRESH_DELAY seconds ago
                error_log('WORKFLOWDEBUG>>>Session key timeout of '
                        .$grace_seconds
                        .' seconds (grace seconds) reached so generated new key for uid=' . $this->getUID()
                        ."\nURL at key timeout = " . $currentpath);
                session_regenerate_id(FALSE);   // change session ID for the current session and invalidate old session ID
                if(!isset($_SESSION['REGENERATED_COUNT']))
                {
                    $_SESSION['REGENERATED_COUNT'] = 1;
                } else {
                    $rc = $_SESSION['REGENERATED_COUNT'];
                    $_SESSION['REGENERATED_COUNT'] = $rc + 1;
                }
                $_SESSION['CREATED'] = time();  // update creation time
            }
        }
    }

    /**
     * Interface to the EHR
     */
    public function getEhrDao($create_if_not_set=TRUE)
    {
        $mttag = microtime(TRUE);
        try
        {
            $ehrcorrupt = FALSE;
            $trycount = 0;
            if(!isset($this->m_oEhrDao))
            {
                //Lets create an instance or wait for one if already being created
                while(isset($_SESSION['CTX_EHRDAO_NEW_START']) != NULL && isset($_SESSION['CTX_EHRDAO_NEW_DONE']) == NULL)
                {
                    //Wait for the singleton process to complete at least once.
                    $trycount++;
        //error_log("LOOK DAO waited $trycount times for other process started at ".$_SESSION['CTX_EHRDAO_NEW_START']." to create the instance; will sleep and try again.");
                    sleep(2);
                    if(isset($_SESSION['CTX_EHRDAO_NEW_DONE']))
                    {
        //error_log("LOOK DAO waited $trycount times for other process to create the instance!");
                        break;
                    }
                    if($trycount > 15)
                    {
                        $startedinfo = $_SESSION['CTX_EHRDAO_NEW_START'];
                        $_SESSION['CTX_EHRDAO_NEW_START'] = NULL;   //Clear it for next time.
                        throw new \Exception("Did NOT get an EHRDAO that started at $startedinfo after $trycount tries!");
                    }
                }
                if(!isset($this->m_oEhrDao)) 
                {
                    if(!$create_if_not_set)
                    {
                        return NULL;
                    }
                    $_SESSION['CTX_EHRDAO_NEW_START'] = microtime(TRUE);
                    $this->m_oEhrDao = new \raptor\EhrDao($this->getSiteID());
                    $_SESSION['CTX_EHRDAO_NEW_DONE'] = microtime(TRUE);
        //error_log("LOOK DAO NEW1 getEhrDao($create_if_not_set)@$mttag");
                }
                try
                {
                    //If not corrupt, then we will get some nice info here.
                    $ehrinfo = $this->m_oEhrDao->getIntegrationInfo();
                    if($ehrinfo == '')
                    {
                        $ehrcorrupt = TRUE;
                    }
                } catch (\Exception $ex) {
                    $ehrcorrupt = TRUE;
                }
                if($ehrcorrupt)
                {
        //error_log("LOOK DAO WAS CORRUPT (tries=$trycount cdur=$duration) SO TRYING TO CREATE AGAIN!");
                    $_SESSION['CTX_EHRDAO_NEW_START'] = microtime(TRUE);
                    $this->m_oEhrDao = new \raptor\EhrDao($this->getSiteID());
                    $_SESSION['CTX_EHRDAO_NEW_DONE'] = microtime(TRUE);
        //error_log("LOOK NEW2 getEhrDao($create_if_not_set)@$mttag");
                }
            }
        //error_log("LOOK done getEhrDao($create_if_not_set)@$mttag");
            return $this->m_oEhrDao;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    /**
     * Returns NULL if no cache handler is available.
     */
    public function getRuntimeResultFlexCacheHandler($groupname,$embedUID=TRUE)
    {
        $handler = NULL;
        $uid = $this->getUID();
        if($uid > 0)
        {
            if($embedUID)
            {
                $groupname = "u:{$uid}_g:{$groupname}";
            }
            if(!isset($this->m_oRuntimeResultFlexCacheHandler[$groupname]))
            {
                $this->m_oRuntimeResultFlexCacheHandler[$groupname] = \raptor\RuntimeResultFlexCache::getInstance($groupname);
            }
            $handler = $this->m_oRuntimeResultFlexCacheHandler[$groupname];
        } else {
            if(!$embedUID)
            {
                if(isset($this->m_oRuntimeResultFlexCacheHandler[$groupname]))
                {
                    $handler = $this->m_oRuntimeResultFlexCacheHandler[$groupname];
                }
            }
        }
        return $handler;
    }
}
