<?php

$str=file_get_contents("/tmp/a.json");

print_r(json_decode($str,true));

?>
