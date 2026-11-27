<?php
// A fake AI service for the fail-safe tests (php -S ... fake_ai_router.php). Test use only.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$mode = explode('/', trim($path, '/'))[0] ?? '';
$good = array('decision' => 'ALLOW', 'risk_level' => 'none', 'reasons' => array('Nothing risky found.'),
    'citations' => array(), 'steps' => array(array('thought' => 't', 'action' => 'final_answer', 'observation' => 'ALLOW')),
    'model_name' => 'fake', 'latency_ms' => 1);

header('Content-Type: application/json');
switch ($mode) {
    case 'good':
        echo json_encode($good);
        break;
    case 'goodreview':
        echo json_encode(array_merge($good, array('decision' => 'REVIEW', 'risk_level' => 'high', 'reasons' => array('A source may be identified (see IE-SRC-1).'),
            'citations' => array(array('source' => 'SAMPLE', 'country' => 'IE', 'passage_id' => 'IE-SRC-1', 'quote' => 'SAMPLE summary')))));
        break;
    case 'slow':
        sleep(3);
        echo json_encode($good);
        break;
    case 'http500':
        http_response_code(500);
        echo json_encode($good);
        break;
    case 'notjson':
        header('Content-Type: text/html');
        echo '<html>not json</html>';
        break;
    case 'badshape':
        echo json_encode(array('decision' => 'MAYBE'));
        break;
    case 'reviewnoreason':
        echo json_encode(array_merge($good, array('decision' => 'REVIEW', 'reasons' => array())));
        break;
    case 'badcitation':
        echo json_encode(array_merge($good, array('decision' => 'REVIEW', 'reasons' => array('x'), 'citations' => array(array('source' => 's', 'country' => 'Ireland', 'passage_id' => 'p', 'quote' => 'q')))));
        break;
    default:
        http_response_code(404);
}
