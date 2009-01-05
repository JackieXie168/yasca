<?php

$a = microtime();
$z=0;
for ($i=0; $i<1000000; $i++) {
    $z++;
}

$b = microtime();

print ($b-$a);

?>