<?php

class PanelDirectAdmin extends DomainHealthCheckModel
{
    public function __construct()
    {
        parent::__construct();
    }

    public function api($host, $user, $pass, $port, $use_ssl = 'true')
    {
        // Can not reuse Blesta Direct Admin library since it does not
        // support all the endpoints and the methods are private
        Loader::load(PLUGINDIR . 'domain_health_check' . DS . 'apis' . DS . 'direct_admin_api.php');

        $url = sprintf('http%s://%s', ($use_ssl === 'true' ? 's' : ''), $host);
        return new DirectAdminApi($url, $user, $pass, $port);
    }

    public function parse($json)
    {
        return $this->parseJson($json, true);
    }
}
