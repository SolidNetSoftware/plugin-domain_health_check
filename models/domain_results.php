<?php

class DomainResults
{

    private $status   = '';
    private $test     = '';
    private $message  = '';
    private $raw;

    public function __construct()
    {
    }

    public static function with($status, $test, $message, $raw = null)
    {
        $results = new DomainResults();
        return $results->load($status, $test, $message, $raw);
    }

    public function load($status, $test, $message, $raw = null)
    {
        $this->status   = $status;
        $this->test     = Language::_($test, true);
        $this->message  = $message;
        $this->raw      = $raw;
        return $this;
    }

    public function getIcon()
    {
        if      ($this->status === 'info')  return 'fa fa-info';
        else if ($this->status === 'good')  return 'fa fa-check';
        else                                return 'fa fa-ban';
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getTest()
    {
        return $this->test;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getRaw()
    {
        return $this->raw;
    }
}
