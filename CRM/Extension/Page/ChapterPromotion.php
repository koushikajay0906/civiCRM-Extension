<?php

require_once 'CRM/Core/Page.php';

class CRM_Extension_Page_ChapterPromotion extends CRM_Core_Page {
  function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(ts('ChapterPromotion'));

    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));
     print("<td> 
    
        
        
	  <button onclick=\"window.location.href='http://npc4npo.org/civicrm/agent-promotion'\">Click Here to Promote Agents in your Chapter</button>  </td>");

    parent::run();
  }
}
