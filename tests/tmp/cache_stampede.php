<?php
/**
 * Created by: Muhammed Mamedov
 * Date: 5/15/16
 * Time: 7:53 PM
 * Project: pageCache
 */

/**
 *
 * (filemtime($file) < (time() - $this->cache_expire))
 *
 */

//set expiration to 20 minutes;
$expiration = 20 * 60;

//set last modification time of a cache file
$filemtime = time() - $expiration - 1;

if($filemtime < (time() - $expiration)){
    echo 'Expired'."\n";
    touch(__DIR__.'/newcachefile.txt');
}
else {
    echo 'Not Expired'."\n";
}

while(true) {
    $a = (time() - $expiration);
    $b = (time() - $expiration - log10(rand(10, 10000)));
    println($a-$b);
}


function println($str){
    echo $str."\n";
}