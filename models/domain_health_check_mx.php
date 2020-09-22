<?php

class DomainHealthCheckMx extends DomainHealthCheckModel
{

    public function __construct()
    {
        parent::__construct();
    }

    public function performMxChecks($domain, DomainResults $nameservers)
    {
        $nameserver_address = $this->getFirstNameserver($nameservers->getRaw());
        $dns = $this->getNetDNS($nameserver_address, true);

        $mx = $this->getMxRecord($dns, $domain);

        $spf = $this->checkSPFRecord($dns, $domain);
        $dmarc = $this->checkDMARCRecord($dns, $domain);

        return [
            'mx'    => $mx,
            'spf'   => $spf,
            'dmarc' => $dmarc
        ];
    }

    public function getMxRecord($dns, $domain)
    {
        try {
            // TODO FIXME: optimize this code and put into function
            $answer = $dns->query($domain, 'MX');
        } catch (Net_DNS2_Exception $e) {
            return $this->DomainResults::with('bad', 'dhc.test.mx.info', Language::_('dhc.message.mx.missing', true));
        }

        $result = '';
        foreach($this->mergeAnswerAndAdditional($answer->answer, $answer->additional) as $record)
            if(!array_key_exists('address', $record))
                $result .= sprintf('%d &nbsp;&nbsp; %s &nbsp;&nbsp; %s<br />', $record->preference, $record->exchange, '');
            else
                foreach($record->address as $address)
                    $result .= sprintf('%d &nbsp;&nbsp; %s &nbsp;&nbsp; %s<br />', $record->preference, $record->exchange, $address['address']);

        if(empty($result))
            return $this->DomainResults::with('bad', 'dhc.test.mx.info', Language::_('dhc.message.mx.missing', true));

        return $this->DomainResults::with('good', 'dhc.test.mx.info', sprintf(Language::_('dhc.message.mx.info', true), $result));
    }

    private function mergeAnswerAndAdditional($answer, $additional)
    {
        $results = [];
        foreach($answer as $record)
            $results[$record->exchange] = $record;

        foreach($additional as $record)
            $results[$record->name]->address[] = [ 'type' => $record->type, 'address' => $record->address];

        return $results;
    }

    public function checkSPFRecord($dns, $domain)
    {
        try {
            // TODO FIXME: optimize this code and put into function
            $answer = $dns->query($domain, 'TXT');
        } catch (Net_DNS2_Exception $e) {
            return $this->DomainResults::with('bad', 'dhc.test.mx.spf', Language::_('dhc.message.mx.spf.missing', true));
        }

        $spf_record = '';
        foreach($answer->answer as $record)
        {
            // TODO FIXME: text records might get extended to larger values and contain multiple 255 entries
            foreach($record->text as $text)
            {
                if(strlen($text) >= 6 && substr($text, 0, 6) === 'v=spf1')
                {
                    $spf_record = $text;
                    break;
                }
            }
        }

        if(empty($spf_record))
            return $this->DomainResults::with('bad', 'dhc.test.mx.spf', Language::_('dhc.message.mx.spf.missing', true));

        try {
            $record = $this->getSPF()->getRecordFromTXT($spf_record);
        } catch (\SPFLib\Exception $e) {
            // Problems decoding spf record (it's malformed).
            return $this->DomainResults::with('bad', 'dhc.test.mx.spf', sprintf(Language::_('dhc.message.mx.spf.error', true), $spf_record, $e->getMessage()));
        }


        $result = '';
        foreach ((new \SPFLib\SemanticValidator())->validate($record) as $issue)
            $result .= sprintf('%s<br /><br />', $issue->getDescription());

        if(!empty($result))
            return $this->DomainResults::with('bad', 'dhc.test.mx.spf', sprintf(Language::_('dhc.message.mx.spf.error', true), $spf_record, $result));

        return $this->DomainResults::with('good', 'dhc.test.mx.spf', sprintf(Language::_('dhc.message.mx.spf.info', true), $spf_record));
    }

    public function checkDMARCRecord($dns, $domain)
    {
        try {
            // TODO FIXME: optimize this code and put into function
            $answer = $dns->query('_dmarc.'.$domain, 'TXT');
        } catch (Net_DNS2_Exception $e) {
            return $this->DomainResults::with('bad', 'dhc.test.mx.dmarc', Language::_('dhc.message.mx.dmarc.missing', true));
        }

        $dmarc_record = '';
        foreach($answer->answer as $record)
        {
            // TODO FIXME: text records might get extended to larger values and contain multiple 255 entries
            foreach($record->text as $text)
            {
                if(strlen($text) >= 6 && substr($text, 0, 8) === 'v=DMARC1')
                {
                    $dmarc_record = $text;
                    break;
                }
            }
        }

        if(empty($dmarc_record))
            return $this->DomainResults::with('bad', 'dhc.test.mx.dmarc', Language::_('dhc.message.mx.dmarc.missing', true));

        return $this->DomainResults::with('good', 'dhc.test.mx.dmarc', sprintf(Language::_('dhc.message.mx.dmarc.info', true), $dmarc_record));
    }
}
