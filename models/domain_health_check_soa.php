<?php

class DomainHealthCheckSoa extends DomainHealthCheckModel
{

    public function __construct()
    {
        parent::__construct();
    }

    public function performSoaChecks(DomainResults $nameservers)
    {
        // Print all SOA information
        $soa = $this->getSoa($nameservers->getRaw());

        // Check to see if Serials match
        $serial = $this->doSerialsMatch($nameservers->getRaw());

        // Check minimum time to live
        $primary = $this->primaryNS($soa);

        return [
            'soa'       => $soa,
            'match'     => $serial,
            'primary'   => $primary
        ];
    }

    public function getSoa(array $nameservers)
    {
        $result = '';
        $answer = [];
        foreach($nameservers as $nameserver)
        {
            $answer = $nameserver->answer[0];
            $result = sprintf(Language::_('dhc.message.soa.info', true), $answer->mname, $answer->rname, $answer->serial, $answer->refresh, $answer->retry, $answer->expire, $answer->ttl);
            break;
        }

        return $this->DomainResults::with('info', 'dhc.test.soa.info', $result, $answer);
    }

    public function primaryNS(DomainResults $soa)
    {
        $answer = $soa->getRaw()->mname;
        return $this->DomainResults::with('info', 'dhc.test.soa.primary', sprintf(Language::_('dhc.message.soa.primary', true), $answer));
    }

    public function doSerialsMatch(array $nameservers)
    {
        $serial = null;
        $result = '';
        foreach($nameservers as $dns)
        {
            foreach($dns->answer as $response)
            {
                if(is_null($serial)) $serial = $response->serial;

                if($serial != $response->serial)
                    $result .= sprintf('%s &nbsp;&nbsp;->&nbsp;&nbsp; %s<br />', $dns->answer_from, $response->serial);
            }
        }

        if(!empty($result))
            return $this->DomainResults::with('bad', 'dhc.test.soa.match', sprintf(Language::_('dhc.message.soa.mismatch', true), $serial, $result));

        return $this->DomainResults::with('good', 'dhc.test.soa.match', sprintf(Language::_('dhc.message.soa.match', true), $serial));
    }
}
