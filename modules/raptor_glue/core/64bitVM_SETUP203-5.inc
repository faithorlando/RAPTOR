<?php

defined("RAPTOR_CONFIG_ID")
    or define("RAPTOR_CONFIG_ID", "20150317.1 for TEST 203 AppServer2");

defined("RAPTOR_ROOT_URL")
    or define("RAPTOR_ROOT_URL", "http://10.203.12.5/RAPTOR/");

defined("EMRSERVICE_URL")
    or define("EMRSERVICE_URL", "http://localhost/mdws2.5/emrsvc.asmx");
defined("QUERYSERVICE_URL")
    or define("QUERYSERVICE_URL", "http://localhost/mdws2.5/querysvc.asmx");

defined("EMRSERVICE_LOCAL_FILE")
    or define("EMRSERVICE_LOCAL_FILE", "C:\\inetpub\\wwwroot\\RAPTOR\\sites\\all\\modules\\raptor_datalayer\\core\\emrsvc.wsdl");
defined("QUERYSERVICE_LOCAL_FILE")
    or define("QUERYSERVICE_LOCAL_FILE", "C:\\inetpub\\wwwroot\\RAPTOR\\sites\\all\\modules\\raptor_datalayer\\core\\querysvc.wsdl");

//Point to HYDRA server
defined("VIX_HTML_VIEWER_URL")
    or define("VIX_HTML_VIEWER_URL", "http://10.203.12.5:9000/hydra/viewer");

//Point to VIX server
defined("VIX_STUDIES_URL")
    or define("VIX_STUDIES_URL", "http://10.203.12.3:8090/RaptorWebApp/secure/restservices/raptor/studies/");
defined("VIX_THUMBNAIL_URL")
    or define("VIX_THUMBNAIL_URL", "http://10.203.12.3:8090/RaptorWebApp/token/thumbnail");