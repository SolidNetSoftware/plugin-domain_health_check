<?php

class DomainHealthCheckA extends DomainHealthCheckModel
{

    public function __construct()
    {
        parent::__construct();
    }

    public function performAChecks($domain, DomainResults $nameservers)
    {
        $nameserver_address = $this->getFirstNameserver($nameservers->getRaw());

        $parser = $this->getDomainParser()->resolve($domain);
        $domain = $parser->getRegistrableDomain(); // removes www and subdomains

        // Print all A/AAAA Records
        $a = $this->getA($domain, $nameserver_address);

        // Print all www records
        $www = $this->getWWW($domain, $nameserver_address);

        return [
            'a'     => $a,
            'www'   => $www
        ];
    }

    public function getA($domain, $nameserver)
    {
        $result = $this->getRecord($domain, $nameserver, 'A');

        if(empty($result))
            return $this->DomainResults::with('bad', 'dhc.test.a', Language::_('dhc.message.a.missing', true));

        return $this->DomainResults::with('good', 'dhc.test.a', sprintf(Language::_('dhc.message.a.found', true), $result));
    }

    public function getWWW($domain, $nameserver)
    {
        $result = $this->getRecord(sprintf('www.%s', $domain), $nameserver, 'CNAME');

        if(empty($result))
            return $this->DomainResults::with('bad', 'dhc.test.a.www', Language::_('dhc.message.a.www.missing', true));

        return $this->DomainResults::with('good', 'dhc.test.a.www', sprintf(Language::_('dhc.message.a.www.found', true), $result));
    }

    protected function getRecord($domain, $nameserver, $format)
    {
        if($format === 'CNAME')
            $answers[] = $this->dnsQuery($domain, 'CNAME', $nameserver);
        else
            $answers = $this->dnsQueryIPAll($domain, $nameserver);

        $result = '';
        foreach($answers as $record)
            if(get_class($record) === 'Net_DNS2_RR_A' || get_class($record) === 'Net_DNS2_RR_AAAA' || get_class($record) === 'Net_DNS2_RR_CNAME')
                if($format === 'A')
                    $result .= sprintf('%s [%s]<br />', $domain, $record->address);
                else if ($format === 'CNAME')
                    $result .= sprintf('%s &nbsp;&nbsp; %s &nbsp;&nbsp; [%s]<br />', $record->name, $record->type, (get_class($record) === 'Net_DNS2_RR_CNAME') ? $record->cname : $record->address);

        return $result;
    }
}
