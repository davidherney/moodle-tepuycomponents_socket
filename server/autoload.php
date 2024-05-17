<?php

function APP_autoload($className){
    //Core Classes
    $className = str_replace('\\', '/', $className);
    $fileName = APP_SOURCE_PATH."/".$className.".class.php";

    if(file_exists($fileName)){
        require_once($fileName);
        return;
    }
    //Core interfaces
    $fileName = APP_SOURCE_PATH."/".$className.".interface.php";
    if(file_exists($fileName)){
        require_once($fileName);
        return;
    }
}

spl_autoload_register('APP_autoload');
