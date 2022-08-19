<?php
//
//spl_autoload_register(function ($className) {
//    if(strpos($className, 'MagentoSupport') !== 0) {
//        return;
//    }
//    $classFile = __DIR__ . '/' . str_replace('\\', '/', $className) . '.php';
//    //var_dump($classFile);
//    if (file_exists($classFile)) {
//        include $classFile;
//    }
//});