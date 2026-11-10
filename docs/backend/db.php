<?php

mysqli_report(MYSQLI_REPORT_OFF);

// Inside Docker these come from docker-compose.yml.
// Running PHP directly on your laptop, the defaults point to the Docker MySQL on port 3307.
$host     = getenv('DB_HOST')     ?: '127.0.0.1';
$port     = (int) (getenv('DB_PORT') ?: 3307);
$user     = getenv('DB_USER')     ?: 'dissent';
$password = getenv('DB_PASSWORD') ?: 'dissent_dev';
$database = getenv('DB_NAME')     ?: 'dissent_local';

$conn = @mysqli_connect($host, $user, $password, $database, $port);

$db_error = "";

if(!$conn){
    $db_error = mysqli_connect_error();
}else{
    mysqli_set_charset($conn, "utf8mb4");
}

// securecomm (System1) — report verification ledger + secure message sending.
define('BLOCKCHAIN_API_URL', getenv('BLOCKCHAIN_API_URL') ?: 'http://localhost:8081');
define('BLOCKCHAIN_API_KEY', 'dev-only-change-me');

// securecomm2 (System2) — secure message verification/decryption status.
define('MESSAGING_API_URL', getenv('MESSAGING_API_URL') ?: 'http://localhost:8082');
define('MESSAGING_API_KEY', 'dev-only-change-me');
?>