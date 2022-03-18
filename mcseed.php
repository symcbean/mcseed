<?php
/**
 * Seed an mcache instance from a running instance
 *
 * Colin McKinnon, 2022
 *
 * requires at least the name of the machine to pull from
 * default write host is localhost
 * default ports are 11211 at both ends
 *
 * Usage: mcseed.php sourcehost [ sourceport [ desthost [ destport ]]]
 */

// ---------------- main ---------------
define("DEBUG", false);
list($seedhost, $seedport, $desthost, $destport)=get_params();

$lru_list=get_lru_list($seedhost, $seedport);
$src=new Memcache();
$src->addServer($seedhost, $seedport);
$dest=new Memcache();
$dest->addServer($desthost, $destport);

$line='';
$recs=0;
while (('END' != $line = trim(fgets($lru_list))) && !feof($lru_list)) {
	if (DEBUG) print "> $line\n";
	$rec=parse_record($line);
	if (!$rec) {
		continue;
	}
	$val=$src->get($rec['key']);
	if (!$dest->set($rec['key'], $val, 0, $rec['exp'])) {
		fputs(STDERR, "Failed to add key $rec[key] - aborting\n");
		exit (2);
	}
	++$recs;
}	
if ('END'==$line) {
	fputs(STDERR, "Seeding completed successfully - $recs keys\n");
	exit (0);
}
fputs(STDERR, "Lost connection to seed host\n");
exit (2);

// ---------------- functions -----------------

// Convert the read data into a structured record
function parse_record($l)
{
	$parts=explode(" ", $l);
	$out=array();
	foreach ($parts as $p) {
		list($k, $v)=explode("=", $p);
		$out[$k]=$v;
	}
	if (isset($out['key']) && isset($out['exp'])) {
		if (DEBUG) print "found $out[key]\n";
		return $out;
	}
	if (DEBUG) print "No data\n";
	return false;
}

// Read comand line arguments / apply defaults
function get_params()
{
	global $argv;
	$params=array(null, "11211", '127.0.0.1', '11211');
	$arglist=$argv;
	array_shift($arglist);
	if (1>=count($arglist)) {
		usage();
		exit (1);
	}
	foreach ($arglist as $i=>$a) {
		$params[$i]=$a;
	}
	$error=false;
	if (!is_host($params[0]) || !is_host($params[2])) {
		fputs(STDERR, "$params[0] or $params[2] is not a host\n");
		usage();
		exit (1);
	}
	if (!(integer)$params[1] || !(integer)$params[3]) {
		fputs(STDERR, "$params[1] or $params[3] is not a port number\n");
		usage();
		exit (1);
	}
	if (DEBUG) print_r($params);
	return $params;
}

// data validation routine used by gate_params()
function is_host($name)
{
	if (filter_var($name, FILTER_VALIDATE_IP)) {
		return true;
	}
	if ($name != gethostbyname($name)) {
		return true;
	}
	return false;
}

// something went wrong - send usage info 
function usage()
{
	global $argv;
	fputs(STDERR, "Usage: $argv[0] <seedhost> [ <seedport> [ <desthost> [ <destport> ]]]\n");
}

// get the list of keys from the source
// Note that this uses a raw socket - neither PHPs
// memcache nor memcached extensions implement this
function get_lru_list($seedhost, $seedport)
{
	$err=0;
	$msg='';
	$timeout=1;
	$handle=fsockopen($seedhost, $seedport, $err, $msg, $timeout);
	if (!$handle) {
		fputs(STDERR, "Failed to connect to $seedhost $seedport\n");
		exit (2);
	}
	fputs($handle, "lru_crawler metadump all\r\n");
	return $handle;
}
