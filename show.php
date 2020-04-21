<?php
$contents = file_get_contents($_GET['url']);

if( substr($contents, 0, 5) == '<?xml' ) {
    header('Content-Type: text/xml');
}
echo $contents;
?>