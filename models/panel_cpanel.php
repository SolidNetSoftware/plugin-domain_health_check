<?php

class PanelCpanel extends DomainHealthCheckModel
{
    public function __construct()
    {
        parent::__construct();
    }

    public function api($host, $user, $pass, $use_ssl = 'true')
    {
        // Reuse the Blesta cPanel API library
        Loader::load(COMPONENTDIR . DS . 'modules' . DS . 'cpanel' . DS . 'apis' . DS . 'cpanel_api.php');

        $api = new CpanelApi($host);
        $api->set_user($user);

        // Determine whether this is a token or a key based on length
        if (strlen($pass) > 32) {
            $api->set_hash($pass);
        } else {
            $api->set_token($pass);
        }

        $api->set_output('json');
        $api->set_port(($use_ssl === 'true' ? 2087 : 2086));
        $api->set_protocol('http' . ($use_ssl === 'true' ? 's' : ''));

        return $api;
    }

    public function parse($json)
    {
        $result = $this->parseJson($json);
        $success = true;

        // Only some API requests return status, so only use it if its available
        if (isset($result->status) && $result->status == 0) {
            $this->Input->setErrors(['api' => ['result' => $result->statusmsg]]);
            $success = false;
        } elseif (isset($result->result) && is_array($result->result)
            && isset($result->result[0]->status) && $result->result[0]->status == 0
        ) {
            $this->Input->setErrors(['api' => ['result' => $result->result[0]->statusmsg]]);
            $success = false;
        } elseif (isset($result->passwd) && is_array($result->passwd)
            && isset($result->passwd[0]->status) && $result->passwd[0]->status == 0
        ) {
            $this->Input->setErrors(['api' => ['result' => $result->passwd[0]->statusmsg]]);
            $success = false;
        } elseif (isset($result->cpanelresult) && !empty($result->cpanelresult->error)) {
            $this->Input->setErrors(
                [
                    'api' => [
                        'error' => (isset($result->cpanelresult->data->reason)
                            ? $result->cpanelresult->data->reason
                            : $result->cpanelresult->error
                        )
                    ]
                ]
            );
            $success = false;
        }

        $sensitive_data = ['/PassWord:.*?(\\\\n)/i'];
        $replacements = ['PassWord: *****${1}'];

        // Return if any errors encountered
        if (!$success) {
            return;
        }

        return $result;
    }
}
