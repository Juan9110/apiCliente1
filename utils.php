<?php
/**
 * Abrir la conexión con la base de datos
 */

 function connect($db){

     try{
        $conn = new PDO("mysql:host={$db['host']};
        dbname={$db['db']};
        charset=utf8",
        $db['username'],
        $db['password']);

        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;

     }catch(PDOException $exception){
        exit($exception->getMessage());
     }
     
 }
 //agragado a github
© 2021 GitHub, Inc.
