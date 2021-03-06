<?php
/**
 * @file
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants
 * Designed and implemented by Frank Font (ffont@sanbusinessconsultants.com)
 * In collaboration with Andrew Casertano (acasertano@sanbusinessconsultants.com)
 * Open source enhancements to this module are welcome!  Contact SAN to share updates.
 *
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
 * This is a simple decision support engine module for Drupal.
 */


namespace simplerulesengine_demo;

module_load_include('php','simplerulesengine_ui','form/EditMeasurePage');
module_load_include('inc','simplerulesengine_demo','core/EvalEngine');

/**
 * This class implements the Edit Measure page
 *
 * @author Frank Font of SAN Business Consultants
 */
class DemoEditMeasurePage extends \simplerulesengine\EditMeasurePage
{
    public function __construct($measure_nm)
    {
        parent::__construct(
                    $measure_nm
                ,   new \simplerulesengine_demo\EvalEngine(NULL)
                ,   array('return'=>'simplerulesengine_demo/managemeasures')
                );
    }
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues, $html_classname_overrides=NULL)
    {
        $form = parent::getForm($form, $form_state, $disabled, $myvalues, $html_classname_overrides);
        return $form;
    }
}
