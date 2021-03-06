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

require_once 'AReport.php';

/**
 * This class returns the user activity report
 *
 * @author Frank Font of SAN Business Consultants
 */
class ViewReportUserActivity extends AReport
{
    private static $reqprivs = array('VREP2'=>1);
    private static $menukey = 'raptor/showuseractivity';
    private static $reportname = 'User Activity';

    function __construct()
    {
        parent::__construct(self::$reqprivs, self::$menukey, self::$reportname);
    }

    public function getDescription() 
    {
        return 'Shows user login and logout activity times in the system.';
    }
    
    private function getFormattedDuration($seconds)
    {
        try
        {
            $wholeseconds = ceil($seconds);
            $dtF = new \DateTime("@0");
            $dtT = new \DateTime("@$wholeseconds");
            $dateinstance = $dtF->diff($dtT);
            $portioned = $dateinstance->format('%a;%h;%i;%s');
            if($wholeseconds >= 86400)  //Days
            {
                $formatted = $dateinstance->format('%a days %h hours %i minutes and %s seconds');
            } else 
            if($wholeseconds >= 3600)   //Hours
            {
                $formatted = $dateinstance->format('%h hours %i minutes and %s seconds');
            } else 
            if($wholeseconds >= 60)    //Minutes
            {
                $formatted = $dateinstance->format('%i minutes and %s seconds');
            } else {
                $formatted = $dateinstance->format('%s seconds');
            }
            //$formatted = $dateinstance->format('%a days %h hours, %i minutes and %s seconds');
            return $formatted;
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues)
    {
        $form['data_entry_area1'] = array(
            '#prefix' => "\n<section class='user-admin raptor-dialog-table'>\n",
            '#suffix' => "\n</section>\n",
        );
        $form['data_entry_area1']['table_container'] = array(
            '#type' => 'item', 
            '#prefix' => '<div class="raptor-dialog-table-container">',
            '#suffix' => '</div>', 
            '#tree' => TRUE,
        );

        $rows = "\n";
        
        $query = db_select('raptor_user_profile', 'n');
        $query->join('users', 'u', 'n.uid = u.uid'); 
        $query->fields('n');
        $query->fields('u');
        $query->orderBy('access', 'DESC');
        $result = $query->execute();        
        $now = time();
        $MININ4WEEKS=4*10080;
        foreach($result as $item) 
        {
            $nowseconds = time();
            $secondssincelastaction = ($item->access > 0 ? round(($nowseconds - $item->access )) : NULL);
            $minutessincelastaction = ($secondssincelastaction !== NULL ? round($secondssincelastaction / 60) : NULL);
            if($minutessincelastaction !== NULL) // && $minutessincelastaction <= $MININ4WEEKS)   //Only include users that have logged in recently
            {
                $hourssincelastaction = round($minutessincelastaction / 60);
                $fullname = trim($item->usernametitle . ' ' . $item->lastname . ', ' . $item->firstname);
                $username = $item->username;
                $rolename = $item->role_nm;
                $lastactivity = date('m/d/Y H:i:s',$item->access);
                $lastlogin = date('m/d/Y H:i:s',$item->login);
                
                $query = db_select('raptor_user_activity_tracking', 'n');
                $query->fields('n')
                    ->condition('uid',$item->uid,'=')
                    ->condition('action_cd',3,'=')
                    ->orderBy('updated_dt','DESC')
                    ->range(0,1);
                $logoutresut = $query->execute();
                if($logoutresut->rowCount() == 1)
                {
                    $a = $logoutresut->fetchAssoc();
                    $lastlogout = $a['updated_dt'];
                } else {
                    $lastlogout = '*Never*';  
                }
    
                $formatted_duration = $this->getFormattedDuration($secondssincelastaction);
                $rows   .= "\n".'<tr>'
                        . '<td>'.$username.'</td>'
                        . '<td>'.$fullname.'</td>'
                        . '<td>'.$rolename.'</td>'
                        . '<td>'.$lastlogin.'</td>'
                        . '<td>'.$lastlogout.'</td>'
                        . '<td>'.$lastactivity.'</td>'
                        . '<td '.($hourssincelastaction > 0 ? 'title="about '.$hourssincelastaction.' hours"' : 'title="less than 1 hour"' ).'>'.$formatted_duration.'</td>'
                        .'</tr>';
            }
        }

        $form['data_entry_area1']['table_container']['users'] = array('#type' => 'item',
                 '#markup' => '<table id="my-raptor-dialog-table" class="raptor-dialog-table dataTable">'
                            . '<thead><tr>'
                            . '<th>Login name</th>'
                            . '<th>Full name</th>'
                            . '<th>Role</th>'
                            . '<th>Last Login</th>'
                            . '<th>Last Logout</th>'
                            . '<th>Last Activity</th>'
                            . '<th>Time Since Last Activity</th>'
                            . '</tr>'
                            . '</thead>'
                            . '<tbody>'
                            . $rows
                            .  '</tbody>'
                            . '</table>');

        
        $form['data_entry_area1']['action_buttons'] = array(
            '#type' => 'item', 
            '#prefix' => '<div class="raptor-action-buttons">',
            '#suffix' => '</div>', 
            '#tree' => TRUE,
        );
       
        $form['data_entry_area1']['action_buttons']['refresh'] = array('#type' => 'submit'
                , '#attributes' => array('class' => array('admin-action-button'), 'id' => 'refresh-report')
                , '#value' => t('Refresh Report'));
        
        global $base_url;
        $goback = $base_url . '/raptor/viewReports';
        $form['data_entry_area1']['action_buttons']['cancel'] = $this->getExitButtonMarkup($goback);
        return $form;
    }
}
