<?php


class LibreSignEndpoint
{
    function __construct()
    {
        add_action("init", 'test');
      
    }

    public function test(){
        echo "Hello World";
    }

   

}




/* 

class LibreSignEndpoint {
    private $groupid;
    private $display_name;
    private $quota;
    private $apps;
    private $authorization;

    public function __construct($groupid, $display_name, $quota, $apps, $authorization) {
        $this->groupid = $groupid;
        $this->display_name = $display_name;
        $this->quota = $quota;
        $this->apps = $apps;
        $this->authorization = $authorization;
    }

    public function triggerAPI() {
        $url = 'http://localhost/ocs/v2.php/apps/admin_group_manager/api/v1/admin-group';
        
        $body = [
            'groupid'     => $this->groupid,
            'displayname' => $this->display_name,
            'quota'       => $this->quota,
            'apps'        => $this->apps
        ];

        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($this->authorization),
            'Content-Type'  => 'application/json',
            'OCS-APIRequest' => 'true',
        ];

        $response = wp_remote_post($url, [
            'body'    => json_encode($body),
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            return 'Erro: ' . $response->get_error_message();
        }

        return wp_remote_retrieve_body($response);
    }
}

*/
