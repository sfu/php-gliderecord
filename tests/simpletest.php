<?php 

use SFU\GlideAccess;
use SFU\GlideRecord;


/**
 *  PHP GlideRecord: Testing
 *  
 *  Very simple tests just to start things off. This will improve in future versions.
 *  
 *  Note that these are destructive to data, so only use a dev instance for this!!
 */
require("testheader.php");

// Check for GlideAccess
$ga = GlideAccess::getInstance();
print ("Got instance of GlideAccess pointing at ".$ga->getBaseURI());

// Query incidents
$incident = new GlideRecord("incident");
$incident->addEncodedQuery("active=true");
$incident->orderBy("sys_updated_on");
$incident->setLimit(5);

$incident->query();

foreach ($incident as $i) {
	print("\n\n Incident: ".$incident->number.": ".$incident->short_description);
	$incident->short_description = "Changed for ".$incident->number;
}

foreach ($incident as $i) {
	print("\n\n Updating Incident: ".$incident->number.": ".$incident->short_description);
	$incident->update();
}

for ($i = 0; $i < 2; $i++) {
	try {
		$incident[$i]->deleteRecord();
	}
	catch (SFU\GlideAuthorizationException $g) {
		print("\n Couldn't delete incident: ".$g->getMessage());
	}

}

// Create a new one
$incident->initialize();

$incident->short_description = "foo";
$incident->assignment_group = "ITS CS IT Service Management";
$incident->assigned_to = "Mike Sollanych [msollany]";

$sys_id = $incident->insert();
print("\n\nInserted incident ".$incident->number.", sys_id $sys_id");
