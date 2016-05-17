<?php
/**
 * Created by: Muhammed Mamedov
 * Date: 5/15/16
 * Time: 10:16 PM
 * Project: pageCache
 */


/**
 * fopen "c" from the manual:
 * Open the file for writing only. If the file does not exist, it is created.
 * If it exists, it is neither truncated (as opposed to 'w'), nor the call to this function fails (as is the case with 'x').
 * The file pointer is positioned on the beginning of the file.
 * This may be useful if it's desired to get an advisory lock (see flock()) before attempting to modify the file,
 * as using 'w' could truncate the file before the lock was obtained
 * (if truncation is desired, ftruncate() can be used after the lock is requested).
 */
$fp = fopen(__DIR__."/nofileexisting", "c");


/**
 * LOCK_EX to acquire an exclusive lock (writer).
 * LOCK_NB - prevents flock() from blocking while locking (so that others could still read (but not write) while lock is active)
 */
if (flock($fp, LOCK_EX | LOCK_NB)) {

    echo "Got it! \n";
  //  sleep(30);
    echo 'Wrtigint..sleep over ';

    /**
     * since "c" was used with fopen file is not truncated. Truncate manually.
     */
    ftruncate($fp, 0);
    if(fwrite($fp, "Write something here\n")!==false){
        echo 'Writing successful ';
    }
    else {
        echo 'Writing error inside lock; ';
    }

    flock($fp, LOCK_UN);

} else {

    $b=readfile(__DIR__."/nofileexisting");
    var_dump($b);
    echo "Lock failed \n";
}

fclose($fp);