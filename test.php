<?php
include ("multi-import.php");

$start = microtime(true);

ShuttleSQLDumpFileSeparator::separate_queries("test.sql", function ($query) {
	echo "--- $query --- \n\n";
});

echo "Took: " . sprintf('%0.2f', microtime(true) - $start);