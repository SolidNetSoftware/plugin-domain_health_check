<?php

class DomainHealthCheckNs extends DomainHealthCheckModel
{

    public function __construct()
    {
        parent::__construct();
    }

    public function performNsChecks($domain, DomainResults $nameservers)
    {
        // Do nameservers have A/AAAA records
        $a = $this->getNsARecords($nameservers->getRaw());

        // Do they match with glue records
        $match = $this->hasMatchingGlue($nameservers, $a->getRaw());

        // Count the number of nameservers
        $count = $this->nameserverCount($nameservers->getRaw());

        // Determine if Recursion Avaiable header is set for all nameservers
        $ra = $this->isRecusionEnabled($domain, $a->getRaw());

        // Nameserver IPs on different /24 subnets
        $ip_info = $this->getIpInfo($a);

        return [
            'a'     => $a,
            'match' => $match,
            'count' => $count,
            'ra'    => $ra,
            'ip'    => $ip_info
        ];
    }

    public function getNsARecords(array $nameservers)
    {
        $missing = '';
        $records = '';
        $results = [];
        foreach($nameservers as $nameserver)
        {
            // Check for any A records
            $a = $this->parseDNSRecords($nameserver['name'], DNS_A, $missing, $records);
            if(!empty($a)) $results[$nameserver['name']]['A'] = $a;

            if(Configure::get('dhc.enable.ipv6'))
            {
                // Check for AAAA records
                $aaaa = $this->parseDNSRecords($nameserver['name'], DNS_AAAA, $missing, $records);
                if(!empty($aaaa)) $results[$nameserver['name']]['AAAA'] = $aaaa;
            }
        }

        if(!empty($missing))
            return $this->DomainResults::with('bad', 'dhc.test.nameservers.a', sprintf(Language::_('dhc.message.missing.a.nameservers', true), $missing));

        return $this->DomainResults::with('good', 'dhc.test.nameservers.a', sprintf(Language::_('dhc.message.a.nameservers', true), $records), $results);
    }

    private function parseDNSRecords($nameserver, $type, &$missing /* RET */, &$found /* RET */)
    {
        $dns_records = $this->nameToIp($nameserver, $type);
        if($dns_records === false)
        {
            $missing .= sprintf('%s<br />', $nameserver['name']);
            return [];
        }

        $results = [];
        foreach($dns_records as $dns)
        {
            $found .= sprintf('%s &nbsp;&nbsp; [%s=%d] &nbsp;&nbsp; [%s]<br />', $nameserver, Language::_('dhc.message.ttl', true), $dns['ttl'], ($type === DNS_A) ? $dns['ip'] : $dns['ipv6']);
            $results[($type === DNS_A) ? $dns['ip'] : inet_pton($dns['ipv6'])] = $dns;
        }

        return $results;
    }

    public function hasMatchingGlue(DomainResults $nameservers, array $a)
    {
        if($nameservers->getStatus() !== 'good')
            return $this->DomainResults::with('bad', 'dhc.test.nameservers.match', Language::_('dhc.message.a.nameservers.mismatch', true));

        foreach($nameservers->getRaw() as $nameserver)
        {
            foreach($nameserver['address'] as $ipTypes)
            {
                foreach($ipTypes as $record)
                {
                    if(array_key_exists($nameserver['name'], $a) && array_key_exists($record['type'], $a[$nameserver['name']]))
                    {
                        if($record['type'] === 'AAAA')
                            $ip = inet_pton($record['address']);
                        else
                            $ip = $record['address'];

                        if(array_key_exists($ip, $a[$nameserver['name']][$record['type']]))
                            continue;
                        else
                            return $this->DomainResults::with('bad', 'dhc.test.nameservers.match', Language::_('dhc.message.a.nameservers.mismatch', true));
                    }
                }
            }
        }

        return $this->DomainResults::with('good', 'dhc.test.nameservers.match', Language::_('dhc.message.a.nameservers.match', true));
    }

    public function nameserverCount(array $nameservers)
    {
        $count = count($nameservers);

        if($count >= 2)
            return $this->DomainResults::with('good', 'dhc.test.nameservers.count', sprintf(Language::_('dhc.message.ns.count.okay', true), $count));

        return $this->DomainResults::with('bad', 'dhc.test.nameservers.count', Language::_('dhc.message.ns.count.low', true), $count);
    }

    public function isRecusionEnabled($domain, array $nameservers)
    {
        $result = '';
        $soa = [];
        foreach($nameservers as $type)
            foreach($type as $records)
                foreach($records as $record)
                {
                    $address = ($record['type'] === 'AAAA') ? $record['ipv6'] : $record['ip'];
                    $query = $this->getNetDNS($address, true);

                    // Run a 'dumb' query to see if the RA header bit is set for each nameserver
                    // To increase performance lets grab SOA records for future checks
                    try {
                        // TODO FIXME: optimize this code and put into function
                        $dns = $query->query($domain, 'SOA');
                    } catch (Net_DNS2_Exception $e) {
                        $result .= sprintf(Language::_('dhc.message.ns.disconnect', true), $record['host'], $address);
                        continue;
                    }

                    $soa[$address] = $dns;
                    if($dns->header->ra === 1)
                        $result .= sprintf('%s &nbsp;&nbsp;[%s]<br />', $record['host'], $address);
                }

        if(!empty($result))
            return $this->DomainResults::with('bad', 'dhc.test.ra', sprintf(Language::_('dhc.message.ra.enabled', true), $result));

        return $this->DomainResults::with('good', 'dhc.test.ra', Language::_('dhc.message.ra.disabled', true), $soa);
    }

    public function getIpInfo(DomainResults $nameservers_results)
    {
        if($nameservers_results->getStatus() === 'bad')
            return $this->DomainResults::with('bad', 'dhc.test.ns.ip.info', 'MISSING');

        $result = '';
        $seen_prefix = [];
        foreach($nameservers_results->getRaw() as $type)
        {
            foreach($type as $records)
            {
                foreach($records as $record)
                {
                    if($record['type'] === 'AAAA')
                        $address = \IPLib\Address\IPv6::fromString($record['ipv6']);
                    else if($record['type'] === 'A')
                        $address = \IPLib\Address\IPv4::fromString($record['ip']);

                    // Convert to little endian/host byte order
                    $little_endian = $address->getReverseDNSLookupName();
                    $ptr = $this->formatHost($little_endian, $record['type']);
                    $txt = $this->nameToIP($ptr, DNS_TXT);

                    if($txt === false)
                        continue;

                    foreach($txt as $entry)
                    {
                        list($asnum, $prefix, $country, $rir, $alloc_date) = explode('|', trim($entry['txt'], " \"\t\n\r\0\x0B"));
                        if(array_key_exists($prefix, $seen_prefix))
                            continue;

                        $seen_prefix[$prefix] = $asnum;
                        $result .= sprintf(Language::_('dhc.message.ns.ip.info', true), $asnum, $prefix);
                    }
                }
            }
        }

        return $this->DomainResults::with('info', 'dhc.test.ns.ip.info', $result);
    }

    private function formatHost($ip, $type)
    {
        if($type === 'AAAA')
            return str_replace('.ip6.arpa', '.origin6.asn.cymru.com', $ip);
        else
            return str_replace('.in-addr.arpa', '.origin.asn.cymru.com', $ip);
    }

}
