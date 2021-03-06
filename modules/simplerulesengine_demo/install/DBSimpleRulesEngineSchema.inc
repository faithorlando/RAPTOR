<?php
/*
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

/**
 * This class is for database schema content
 *
 * @author Frank Font
 */
class DBSimpleRulesEngineSchema
{
    /**
     * Add all the schema content for the contra indication tables
     */
    public function addToSchema(&$schema)
    {
        $loaded = module_load_include('inc','simplerulesengine_core','install/DBSimpleRulesEngineSchema');
        if(!$loaded)
        {
            throw new \Exception('Failed to load the simplerulesengine_core DBSimpleRulesEngineSchema class');
        }
        $aRefSchema = array();
        $oReferenceSchema = new \simplerulesengine\DBSimpleRulesEngineSchema();
        $oReferenceSchema->addToSchema($aRefSchema);
        $schema['simplerulesengine_demo_rule'] = $aRefSchema['simplerulesengine_rule'];
        $schema['simplerulesengine_demo_measure'] = $aRefSchema['simplerulesengine_measure'];
    }    
}
    