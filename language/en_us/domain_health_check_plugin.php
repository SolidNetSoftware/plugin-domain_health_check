<?php
$lang['dhc.name'] = 'Domain Health Check';
$lang['dhc.description'] = 'A plugin for checking the health of a domain that integrates with the cPanel module';

$lang['dhc.php.version'] = 'Domain Health Check plugin requires PHP version 7.2.0 or newer. PHP version: %s installed.';

// cron
$lang['dhc.cron.name'] = 'IANA Top Level Domains List';
$lang['dhc.cron.desc'] = 'Cron to download a weekly top level domain list from Internet Assigned Numbers Authority (IANA)';

// internal
$lang['dhc.client.tab.reseller'] = 'Select Domain to Check';
$lang['dhc.client.tab.results']  = 'Domain Health Check';
$lang['dhc.client.suspended']    = 'Suspended';
$lang['dhc.client.username']     = 'Username';
$lang['dhc.client.domain']       = 'Domain';
$lang['dhc.client.check']        = 'Check';

$lang['dhc.category']   = 'Category';
$lang['dhc.status']     = 'Status';
$lang['dhc.test']       = 'Test';
$lang['dhc.details']    = 'Details';

$lang['dhc.api.error']  = 'An internal error occurred, or the cPanel server did not respond to the request.';

// domain category
$lang['dhc.category.tld']   = 'Top Level Domain';
$lang['dhc.category.ns']    = 'Nameservers';
$lang['dhc.category.soa']   = 'Start of Authority';
$lang['dhc.category.mx']    = 'Mail';
$lang['dhc.category.a']     = 'A/AAAA';
$lang['dhc.category.caa']   = 'Certificate Authority';

// domain results
$lang['dhc.test.domain'] = 'Domain';
$lang['dhc.test.authorative.nameservers'] = 'Authorative Nameservers';
$lang['dhc.test.domain.nameservers'] = 'Domain Nameservers';
$lang['dhc.test.ra'] = 'Recursive Nameservers';
$lang['dhc.test.glue'] = 'Nameserver Glue';
$lang['dhc.test.nameservers.a'] = 'Nameserver A/AAAA Records';
$lang['dhc.test.nameservers.match'] = 'Nameserver Match';
$lang['dhc.test.nameservers.count'] = 'Nameserver Count';
$lang['dhc.test.ns.ip.info'] = 'IP Information';
$lang['dhc.test.soa.info'] = 'SOA Record';
$lang['dhc.test.soa.match'] = 'Same Serial';
$lang['dhc.test.soa.primary'] = 'Primary Nameserver';
$lang['dhc.test.mx.info'] = 'Mail Records';
$lang['dhc.test.mx.spf'] = 'SPF';
$lang['dhc.test.mx.dmarc'] = 'DMARC';
$lang['dhc.test.a'] = 'Reachable';
$lang['dhc.test.a.www'] = 'www';
$lang['dhc.test.caa'] = 'caa';

// domain messages
$lang['dhc.message.ttl'] = 'TTL';
$lang['dhc.message.nxtld'] = 'Top level domain %s does not exist';
$lang['dhc.message.nxtldns'] = 'Could not find authorative nameservers for tld: %s';
$lang['dhc.message.tld.exists'] = 'Top level domain is valid: %s';
$lang['dhc.message.nxdomain'] = 'Authoritative nameserver %s has no record for domain %s';
$lang['dhc.message.authorative.nameservers'] = 'Authoritative nameservers for top level domain: %s<br /><br />';
$lang['dhc.message.authorative.response'] = 'Authoritative response from: %s<br /><br />';
$lang['dhc.message.ra.enabled'] = 'The following nameservers support recursive queries which is not recommend:<br /><br />%s';
$lang['dhc.message.ra.disabled'] = 'All nameservers do not support recursive queries.';
$lang['dhc.message.glue.missing'] = 'The following nameservers do not have glue records:<br /><br />%s<br />This results in DNS clients requiring an extra lookup for A/AAAA nameserver resoluton.';
$lang['dhc.message.glue.present'] = 'Authoritative nameserver sent glue records for your nameservers.<br /><br />%s';
$lang['dhc.message.missing.a.nameservers'] = 'The following nameservers do not have any A or AAAA records.<br /><br />%s';
$lang['dhc.message.a.nameservers'] = 'Nameservers are reachable via A/AAAA records. Results:<br /><br />%s';
$lang['dhc.message.a.nameservers.match'] = 'Nameservers A/AAAA records match with glue records from authoritative nameservers.';
$lang['dhc.message.a.nameservers.mismatch'] = 'One or more of your nameservers A/AAAA records do not match that of the glue records provided by authoritative nameservers.';
$lang['dhc.message.ns.count.low'] = 'RFC 2182 recommends that at least two or more nameservers be used. Found: %d nameservers.';
$lang['dhc.message.ns.count.okay'] = 'Total of %d or more nameserver exists.';
$lang['dhc.message.ns.disconnect'] = 'Refused connection: %s &nbsp;&nbsp;[%s]<br />';
$lang['dhc.message.ns.ip.info'] = 'ASN: %d Prefix: %s<br />';
$lang['dhc.message.soa.mismatch'] = 'Not all nameservers agree on serial number. Expect %d but received:<br /><br />%s';
$lang['dhc.message.soa.match'] = 'All nameservers agree on serial number: %s';
$lang['dhc.message.soa.info'] = 'Primary nameserver: %s<br />Hostmaster contact: %s<br />Serial Number: %d<br />Refresh: %d<br />Retry: %d<br />Expire: %d<br />TTL: %d';
$lang['dhc.message.soa.primary'] = 'Primary nameserver as reported: %s';
$lang['dhc.message.mx.missing'] = 'Missing mail records';
$lang['dhc.message.mx.info'] = 'Valid MX records:<br /><br />%s';
$lang['dhc.message.mx.spf.info'] = 'Have valid SPF records: <br /><br />%s';
$lang['dhc.message.mx.spf.error'] = 'Found SPF record with errors:<br /><br />%s<br /><br />%s';
$lang['dhc.message.mx.spf.missing'] = 'No SPF records found. Mail can be spoofed and marked as spam.';
$lang['dhc.message.mx.dmarc.info'] = 'Found DMARC record: <br /><br />%s';
$lang['dhc.message.mx.dmarc.missing'] = 'No DMARC records found. Mail can be spoofed and marked as spam.';
$lang['dhc.message.a.missing'] = 'No A/AAAA records found';
$lang['dhc.message.a.found'] = 'A/AAAA records:<br /><br />%s';
$lang['dhc.message.a.www.missing'] = 'No www records found';
$lang['dhc.message.a.www.found'] = 'Found www records:<br /><br />%s';
$lang['dhc.message.caa.missing'] = 'No Certificate Authority Authorization (CAA) record found.';
$lang['dhc.message.caa'] = 'Domain: %s<br />TTL: %d<br />Tag: %s<br />Flags: %d<br />Value: %s';


