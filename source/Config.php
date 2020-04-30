<?php
// SITE CONFIG
define("SITE",[
    "name" => "Auth MVC PHP",
    "desc" => "Aplicacao de autenticacao MVC PHP",
    "domain" => "localhost.com",
    "locale" => "pt_BR",
    "root" => "https://localhost/loginproject"
]);

// SITE MINIFY
if($_SERVER["SERVER_NAME"] == "localhost") {
    require __DIR__ . "/Minify.php";
}

// DATABASE CONNECT
define("DATA_LAYER_CONFIG", [
    "driver" => "mysql",
    "host" => "localhost",
    "port" => "3306",
    "dbname" => "auth",
    "username" => "root",
    "passwd" => "",
    "options" => [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_CASE => PDO::CASE_NATURAL
    ]
]);

// SOCIAL CONFIG
define("SOCIAL", [
   "facebook_page" => "rodrigoteixeirastudio",
   "facebook_author" => "rodrigoteixeira97",
   "facebook_appId" => "",
   "twitter_creator" => "",
   "twitter_site" => "",
]);

// MAIL CONNECT
define("MAIL", [
    "host" => "smtp.sendgrid.net",
    "port" => "587",
    "user" => "apikey",
    "passwd" => "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",
    "from_name" => "Rodrigo Teixeira",
    "from_email" => "contato@rodrigoteixeiradesign.com.br",
]);

// SOCIAL LOGIN FACEBOOK
define("FACEBOOK_LOGIN", [
    "clientId" => "786444211762855",
    "clientSecret" => "b8439d267dcdd9212b899a012d541f91",
    "redirectUri" => "/facebook",
    "graphApiVersion" => "v4.0",
]);

// SOCIAL LOGIN GOOGLE
define("GOOGLE_LOGIN", [
    "clientId" => "676962639280-q5shbuiu7md8j66grn01hueormogqrqj.apps.googleusercontent.com",
    "clientSecret" => "tYIf4S85yKfXJFSBdaO7sRar",
    "redirectUri" => "/google"
]);