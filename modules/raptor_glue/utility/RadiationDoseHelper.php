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

/**
 * This file contains radiation dose helper methods
 *
 * @author Frank Font of SAN Business Consultants
 */
class RadiationDoseHelper
{
    private static $dose_source_cd_term_map = array(
          'R'=>'Radioisotope'
        , 'E'=>'Equipment Other'
        , 'C'=>'CTDIvol'
        , 'D'=>'DLP'
        , 'Q'=>'Fluoro Air Kerma'
        , 'S'=>'Fluoro DAP'
        , 'T'=>'Fluoro Time'
        , 'H'=>'Fluoro Frame Rate'
        );

    private static $dose_source_cd_uom_map = array(
          'R'=>UOM_NORMALIZED_RADIOISOTOPE_RADIATION
        , 'E'=>UOM_NORMALIZED_EQUIPOTHER_RADIATION
        , 'C'=>UOM_NORMALIZED_CTDIVOL_RADIATION
        , 'D'=>UOM_NORMALIZED_DLP_RADIATION
        , 'Q'=>UOM_NORMALIZED_FLUORO_AIRKERMA
        , 'S'=>UOM_NORMALIZED_FLUORO_DAP
        , 'T'=>UOM_NORMALIZED_TIME_RADIATION
        , 'H'=>UOM_NORMALIZED_FREQ_RADIATION
        );

    private static $dose_source_cd_littlename_map = array(
          'R'=>'radioisotope'
        , 'E'=>'other'
        , 'C'=>'ctdivol'
        , 'D'=>'dlp'
        , 'Q'=>'fluoroQ'
        , 'S'=>'fluoroS'
        , 'T'=>'fluoroT'
        , 'H'=>'fluoroH'
        );
    
    private static $dose_type_cd_map = array(
          'E'=>'Estimated'
        , 'A'=>'Actual'
        , 'U'=>'Unknown Quality');

    public static function getDoseSourceLittlenameMap()
    {
        return self::$dose_source_cd_littlename_map;
    }
    
    public static function getDoseTypeTermMap()
    {
        return self::$dose_type_cd_map;
    }
    
    public static function getDoseSourceTermMap()
    {
        return self::$dose_source_cd_term_map;
    }

    public static function getDoseSourceDefaultUOMMap()
    {
        return self::$dose_source_cd_uom_map;
    }

    public static function getLittlenameForDoseSource($source_cd)
    {
        return self::$dose_source_cd_littlename_map[$source_cd];
    }
    
    public static function getDoseTypeTermForTypeCode($dose_type_cd,$wrapinparen=TRUE)
    {
        if($dose_type_cd == NULL || $dose_type_cd == '')
        {
            $dose_type_cd = 'U';
        }
        if(array_key_exists($dose_type_cd, self::$dose_type_cd_map))
        {
            if($wrapinparen)
            {
                return '('.self::$dose_type_cd_map[$dose_type_cd].')';
            } else {
                return self::$dose_type_cd_map[$dose_type_cd];
            }
        }
        return NULL;
    }
    
    public static function getDefaultTermForDoseSource($source_cd)
    {
        return self::$dose_source_cd_term_map[$source_cd];
    }
    
    public static function getDefaultUOMForDoseSource($source_cd)
    {
        return self::$dose_source_cd_uom_map[$source_cd];
    }
}