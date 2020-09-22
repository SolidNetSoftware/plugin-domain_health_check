<?php

class DomainHealthCheckTld extends DomainHealthCheckModel
{

    public function __construct()
    {
        parent::__construct();
    }

    public function performTldChecks($domain, $tld)
    {
        // Get Authorative Nameservers for TLD
        $auth_ns = $this->getAuthorativeNameservers($tld);
        if($auth_ns->getStatus() === 'bad')
            return [
                'tldNs' => $auth_ns
            ];

        // Get Nameservers for domain
        $domain_ns  = $this->getDomainNameservers($auth_ns->getRaw(), $domain);
        if($domain_ns->getStatus() === 'bad')
            return [
                'tldNs' => $auth_ns,
                'ns'    => $domain_ns
            ];

        // Check for glue records
        $glue       = $this->containsGlueRecords($domain_ns->getRaw());

        return [
            'tldNs' => $auth_ns,
            'ns'    => $domain_ns,
            'glue'  => $glue
        ];
    }

    public function getTopLevelDomain($domain)
    {
        $parser = $this->getDomainParser()->resolve($domain);
        $tld = $parser->getPublicSuffix();

        if(empty($tld))
            return $this->DomainResults::with('bad', 'dhc.test.domain.nameservers', sprintf(Language::_('dhc.message.nxtld', true), $tld));

        return $this->DomainResults::with('good', 'dhc.test.domain.nameservers', sprintf(Language::_('dhc.message.tld.exists', true), $tld), $tld);
    }

    public function getAuthorativeNameservers($tld)
    {
        // TODO FIXME: is it safe to use this machines local resolvers?
        $parent_nameserver = dns_get_record($tld, DNS_NS);
        if($parent_nameserver === FALSE)
            return $this->DomainResults::with('bad', 'dhc.test.authorative.nameservers', sprintf(Language::_('dhc.message.nxtldns', true), $tld));

        $parent_nameserver_pretty = sprintf(Language::_('dhc.message.authorative.nameservers', true), $tld);
        foreach($parent_nameserver as $key => $value)
            $parent_nameserver_pretty .= sprintf("%s<br />", $value['target']);

        return $this->DomainResults::with('info', 'dhc.test.authorative.nameservers', $parent_nameserver_pretty, $parent_nameserver);
    }

    public function getDomainNameservers(array $auth_ns, $domain)
    {
        $nameserver = $auth_ns[rand(0, count($auth_ns)-1)]['target'];
        $dns = $this->getNetDNS($this->nameToIP($nameserver)[0]['ip']);

        // Query Authorative Nameserver for registered nameservers
        try {
            $results = $dns->query($domain,'NS');
        } catch (Net_DNS2_Exception $e) {
            return $this->DomainResults::with('bad', 'dhc.test.domain.nameservers', sprintf(Language::_('dhc.message.nxdomain', true), $nameserver, $domain));
        }

        $results = $this->mergeAuthorityAndAdditional($results->authority, $results->additional);

        $pretty = sprintf(Language::_('dhc.message.authorative.response', true), $nameserver);
        foreach($results as $result)
            $pretty .= sprintf("%s<br />", $result['name']);

        return $this->DomainResults::with('good', 'dhc.test.domain.nameservers', $pretty, $results);
    }

    public function containsGlueRecords(array $results)
    {
        $found = '';
        $missing = '';
        foreach($results as $result)
            if(!array_key_exists('address', $result))
                $missing .= sprintf("%s<br />", $result['name']);
            else
                foreach($result['address'] as $record)
                    foreach($record as $address)
                        $found .= sprintf('%s &nbsp;&nbsp; [%s=%d] &nbsp;&nbsp; [%s]<br />', $result['name'], Language::_('dhc.message.ttl', true), $result['ttl'], $address['address']);

        if(!empty($missing))
            return $this->DomainResults::with('info', 'dhc.test.glue', sprintf(Language::_('dhc.message.glue.missing', true), $missing), $results);

        return $this->DomainResults::with('good', 'dhc.test.glue', sprintf(Language::_('dhc.message.glue.present', true), $found), $results);
    }

    public function mergeAuthorityAndAdditional($authority, $additional)
    {
        $results = [];
        foreach($authority as $auth_results)
        {
            $results[$auth_results->nsdname] = [
                'name'      => $auth_results->nsdname,
                'ttl'       => $auth_results->ttl
            ];
        }

        foreach($additional as $glue_results)
        {
            // glue_results->type is either A or AAAA records
            $results[$glue_results->name]['address'][$glue_results->type][] = [
                'address'   => $glue_results->address,
                'type'      => $glue_results->type
            ];
        }

        return $results;
    }
}
