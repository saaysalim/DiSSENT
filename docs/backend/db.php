<?php

mysqli_report(MYSQLI_REPORT_OFF);

$host = "127.0.0.1";
$user = "root";
$password = "";
$database = "dissent_local";

$conn = @mysqli_connect(
    $host,
    $user,
    $password,
    $database
);

$db_error = "";

if(!$conn){
    $db_error = mysqli_connect_error();
}else{
    mysqli_set_charset($conn, "utf8mb4");
}

// securecomm (System1) — report verification ledger + secure message sending.
// Point these at wherever the Java API is actually running: http://localhost:8081
// for local testing, or the deployed HTTPS URL once a server is available.
define('BLOCKCHAIN_API_URL', 'http://localhost:8081');
define('BLOCKCHAIN_API_KEY', 'dev-only-change-me');

// securecomm2 (System2) — secure message verification/decryption status.
define('MESSAGING_API_URL', 'http://localhost:8082');
define('MESSAGING_API_KEY', 'dev-only-change-me');
?>