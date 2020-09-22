<?php

class DomainHealthCheckCaa extends DomainHealthCheckModel
{

    public function __construct()
    {
        parent::__construct();
    }

    public function performCaaChecks($domain, DomainResults $nameservers)
    {
        $nameserver_address = $this->getFirstNameserver($nameservers->getRaw());

        // Print all A/AAAA Records
        $caa = $this->getCAA($domain, $nameserver_address);

        return [
            'caa'     => $caa
        ];
    }

    public function getCaa($domain, $nameserver)
    {
        $answer = $this->dnsQuery($domain, 'CAA', $nameserver);

        $result = '';
        foreach($answer->answer as $record)
            $result .= sprintf(Language::_('dhc.message.caa', true), $record->name, $record->ttl, $record->tag, $record->flags, $record->value);

        if(empty($result))
            return $this->DomainResults::with('bad', 'dhc.test.caa', Language::_('dhc.message.caa.missing', true));

        return $this->DomainResults::with('good', 'dhc.test.caa', $result);
    }
}
