<?php

require_once 'CRM/Core/Page.php';

class CRM_Extension_Page_AgentPromotion extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('AgentPromotion'));

    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));
    print("<td> 
    <form action=\"agent-promotion\" method=\"post\">

        
        
    
    </form> </td>");


    parent::run();
  }
}
