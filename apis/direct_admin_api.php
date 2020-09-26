<?php

class DirectAdminApi
{

    public $response_header = [];

    private $url        = '';
    private $port       = 2222;
    private $username   = '';
    private $password   = '';

    public function __construct($url, $username, $password, $port = 2222)
    {
        $this->url = sprintf('%s:%d', rtrim($url, "/"), $port);
        $this->username = $username;
        $this->password = $password;
    }

    public function get($endpoint, array $query_params = null)
    {
        return $this->connect('GET', $endpoint, $query_params);
    }

    protected function connect($verb, $endpoint, array $payload = null)
    {
        $curl = curl_init();
        $verb = strtoupper($verb);

        // HTTP verb-specific options
        switch($verb)
        {
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;

            case 'GET':
                break;

            case 'PATCH':
                $header[] = 'Content-Length: ' .  strlen($payload);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                break;

            case 'PUT':
                $header[] = 'Content-Length: ' .  strlen($payload);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                break;

            case 'POST':
                $header[] = 'Content-Length: ' .  strlen($payload);
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                break;
        }

		curl_setopt($curl, CURLOPT_URL, sprintf('%s/%s%s', $this->url, $endpoint, $this->createQueryString($payload)));
		curl_setopt($curl, CURLOPT_USERPWD, sprintf('%s:%s', $this->username, $this->password));
		curl_setopt($curl, CURLOPT_HTTPAUTH,CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);

        $result = curl_exec($curl);
        $this->response_header = curl_getinfo($curl);
        curl_close($curl);

        return $result;
    }

    protected function createQueryString(array $payload)
    {
        if(is_null($payload) || !is_array($payload))
            return "";

        $query_string = "?";
        foreach($payload as $key => $value)
            $query_string .= sprintf('%s=%s&', $key, rawurlencode($value));

        return rtrim($query_string, '&');
    }

}
?>
