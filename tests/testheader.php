<?php

/**
 *  PHP GlideRecord testing harness header
 *  
 *  @author Mike Sollanych
 *  @package php-gliderecord
 */
 
require("../vendor/autoload.php");
require("../src/SFU/GlideRecord/GlideAccess.php");
require("../src/SFU/GlideRecord/GlideRecord.php");
require("../src/SFU/GlideRecord/GlideUtil.php");
require("../src/SFU/GlideRecord/GlideAccessException.php");
require("../src/SFU/GlideRecord/GlideValidationException.php");
require("../src/SFU/GlideRecord/GlideAuthorizationException.php");
require("../src/SFU/GlideRecord/GlideRecordException.php");

$options = getopt('', ["username:", "password:", "instance:"]);
if (!$options) {
	
	// Print usage information.
	echo <<<EOF
PHP GlideRecord Test Script
Usage: simpletest.php --username [username] --password [password] --instance [instance]

The Instance name will automatically be postfixed with 'service-now.com'

EOF;
	exit(1);
}

// Intialize GlideAccess
SFU\GlideAccess::init($options["instance"].".service-now.com", $options["username"], $options["password"]);
