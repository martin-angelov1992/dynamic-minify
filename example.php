<?php
include("Minify.php");
$content = file_get_contents("example.html");
file_put_contents("minified.html", Minify::mixed($content));
?>