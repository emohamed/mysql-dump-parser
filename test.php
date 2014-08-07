<?php
include ("multi-import.php");
$start = microtime(true);
do_multi_sql("test.sql");
echo "Took: " . sprintf('%0.2f', microtime(true) - $start);