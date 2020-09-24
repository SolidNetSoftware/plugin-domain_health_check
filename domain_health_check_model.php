<?php

class DomainHealthCheckModel extends AppModel {

    private $_domainParser = null;
    private $_domainData = null;
    private $_netDns = null;
    private $_spf = null;

    public function __construct()
    {
        parent::__construct();

        Loader::loadModels($this, ['DomainHealthCheck.DomainResults']);

        Loader::load(dirname(__FILE__) . DS . 'vendors' . DS . 'spf-lib'  . DS . 'vendor' . DS . 'autoload.php');
        Loader::load(dirname(__FILE__) . DS . 'vendors' . DS . 'net_dns2'  . DS . 'vendor' . DS . 'autoload.php');
        Loader::load(dirname(__FILE__) . DS . 'vendors' . DS . 'php-domain-parser'  . DS . 'vendor' . DS . 'autoload.php');

        $this->_domainData = $this->getUploadDirectory() . 'tlds-alpha-by-domain.txt';

        Configure::load('domain_health_check', dirname(__FILE__) . DS . 'config' . DS);
    }

    private function getUploadDirectory()
    {
        Loader::loadComponents($this, ['SettingsCollection']);

        // Get the uploads directory
        $uploads_dir = $this->SettingsCollection->fetchSetting(
            null,
            Configure::get('Blesta.company_id'),
            'uploads_dir'
        );
        $uploads_dir = isset($uploads_dir['value']) ? $uploads_dir['value'] : PLUGINDIR . DS . 'domain_health_check' . DS . 'uploads' . DS;

        return $uploads_dir . Configure::get('Blesta.company_id') . DS . 'domain_health_check' . DS;
    }

    public function getSPF()
    {
        if(empty($this->_spf)) $this->createSPF();

        return $this->_spf;
    }

    public function getNetDNS($nameserver, $force = false)
    {
        if(empty($this->_netDns) || $force)  $this->createNetDNS($nameserver);

        return $this->_netDns;
    }

    public function getDomainParser()
    {
        if(empty($this->_domainParser)) $this->createDomainParser();

        return $this->_domainParser;
    }

    private function createNetDNS($nameserver)
    {
        $this->_netDns = new Net_DNS2_Resolver(['nameservers' => [$nameserver]]);
    }

    private function createDomainParser()
    {
        $this->_domainParser = Pdp\TopLevelDomains::createFromPath($this->_domainData);
    }

    private function createSPF()
    {
        $this->_spf = new \SPFLib\Decoder();
    }

    public function performHealthCheck($domain)
    {
        Loader::loadModels($this, ['DomainHealthCheck.DomainHealthCheckTld',
            'DomainHealthCheck.DomainHealthCheckNs',
            'DomainHealthCheck.DomainHealthCheckSoa',
            'DomainHealthCheck.DomainHealthCheckMx',
            'DomainHealthCheck.DomainHealthCheckA',
            'DomainHealthCheck.DomainHealthCheckCaa'
        ]);
        $data = [
            'tld' => [
                'domain'    => $this->DomainResults::with('info', 'dhc.test.domain', $domain),
                'tld'       => $this->DomainHealthCheckTld->getTopLevelDomain($domain)
            ]
        ];

        // Does TLD Exist?
        if($data['tld']['tld']->getStatus() !== 'good')
            return $data;

        // Check Top Level Domain
        $tld = $this->DomainHealthCheckTld->performTldChecks($domain, $data['tld']['tld']->getRaw());
        $data['tld'] = array_merge($data['tld'], $tld);

        if(!array_key_exists('ns', $data['tld']) || $data['tld']['ns']->getStatus() === 'bad')
            return $data;

        // Check Nameserver Records
        $ns = $this->DomainHealthCheckNs->performNsChecks($domain, $data['tld']['glue']);
        $data['ns'] = $ns;

        if(!array_key_exists('ra', $data['ns']) || $data['ns']['ra']->getStatus() === 'bad')
            return $data;

        // Check Start of Authority Records
        $soa = $this->DomainHealthCheckSoa->performSoaChecks($data['ns']['ra']);
        $data['soa'] = $soa;

        // Check MX Records
        $mx = $this->DomainHealthCheckMx->performMxChecks($domain, $data['ns']['ra']);
        $data['mx'] = $mx;

        // Check A/AAAA Records
        $a = $this->DomainHealthCheckA->performAChecks($domain, $data['ns']['ra']);
        $data['a'] = $a;

        // Check CAA Records
        $caa = $this->DomainHealthCheckCaa->performCaaChecks($domain, $data['ns']['ra']);
        $data['caa'] = $caa;

        return $data;
    }

    protected function nameToIP($domain, $type = DNS_A, $count = 'ALL')
    {
        $results = dns_get_record($domain, $type);
        $results_size = count($results);

        if(is_numeric($count) && $count < $results_size)
            return array_slice($results, 0, $count);
        return $results;
    }

    protected function getFirstNameserver(array $nameservers)
    {
        foreach($nameservers as $nameserver)
            return $nameserver->answer_from;
    }

    protected function dnsQuery($record, $query, $nameserver)
    {
        if($query === 'AAAA' && Configure::get('dhc.enable.ipv6') === false)
            return $this->DomainResults::with('exception', '', '');

        try {
            $dns = $this->getNetDNS($nameserver, true);
            return $dns->query($record, $query);
        } catch (Net_DNS2_Exception $e) {
            return $this->DomainResults::with('exception', '', '', $e);
        }
    }

    protected function dnsQueryAll($record, $nameserver, $query)
    {
        $ret = [];

        foreach($query as $type) {
            $results = $this->dnsQuery($record, $type, $nameserver);
            $ret = $this->mergeAnswers($ret, $results);
        }

        return $ret;
    }

    protected function mergeAnswers($a1, $a2)
    {
        if(get_class($a2) === 'Net_DNS2_Packet_Response')
            return array_merge($a1, $a2->answer);

        return $a1;
    }

}
