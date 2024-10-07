<?php

class LibreSignEndpoint
{
    public $groupid;
    public $display_name;
    public $quota;
    public $apps;
    public $authorization;

    public function __construct($groupid, $display_name, $quota, $apps, $authorization) 
    {
        $this->groupid = $groupid;
        $this->display_name = $display_name;
        $this->quota = $quota;
        $this->apps = $apps;
        $this->authorization = $authorization;

        $this->triggerAPI();
    }

    public function triggerAPI()
    {
        // Here goes the API call
     
    }

  


    
  


}
    