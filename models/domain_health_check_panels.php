<?php

class DomainHealthCheckPanels extends DomainHealthCheckModel
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_direct_admin_accounts(stdClass $meta, array $direct_admin_info)
    {
        Loader::loadModels($this, ['DomainHealthCheck.PanelDirectAdmin']);

        $domains = [];
        $direct_admin = $this->PanelDirectAdmin->api($meta->host_name, $meta->user_name, $meta->password, $meta->port, $meta->use_ssl);
        $accounts = $this->PanelDirectAdmin->parse($direct_admin->get('CMD_ALL_USER_SHOW', ['json' => 'yes']));

        if(empty($accounts))
            return $domains[$direct_admin_info['domain']] = (object) ['domain' => $direct_admin_info['domain'], 'user' => $direct_admin_info['user'], 'suspended' => false];

        foreach($accounts as $key => $account)
        {
            if(!is_numeric($key))
                continue;

            // Only return accounts owned by the reseller and the reseller account itself
            if($account['creator'] !== $direct_admin_info['user'] && $account['username']['value'] !== $direct_admin_info['user'])
                continue;

            foreach($account['domains'] as $domain => $noop)
                $domains[$domain] = (object) ['domain' => $domain, 'user' => $account['username']['value'], 'suspended' => ($account['suspended']['value'] === 'yes') ? true : false ];
        }

        return $domains;
    }

    public function get_cpanel_accounts(stdClass $meta, array $cpanel_info)
    {
        Loader::loadModels($this, ['DomainHealthCheck.PanelCpanel']);

        $domains = [];
        $cpanel = $this->PanelCpanel->api($meta->host_name, $meta->user_name, $meta->key, $meta->use_ssl);
        $accounts = $this->PanelCpanel->parse($cpanel->listaccts('owner', $cpanel_info['user']));

        if(empty($accounts))
            return $domains[$cpanel_info['domain']] = (object) ['domain' => $cpanel_info['domain'], 'user' => $cpanel_info['user'], 'suspended' => false];

        foreach($accounts->acct as $account)
            $domains[$account->domain] = (object) ['domain' => $account->domain, 'user' => $account->user, 'suspended' => $account->suspended];

        ksort($domains, SORT_STRING); // cPanel does not have a deterministic way returning the data
        return $domains;
    }

}
