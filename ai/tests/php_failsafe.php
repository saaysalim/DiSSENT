<?php
/*
 * Fail-safe tests for the PHP side of the AI check (compliance.php). Command line only.
 * Run inside the web container (it starts a fake AI service on 127.0.0.1:9911):
 *   docker cp ai/tests/. dissent-web:/tmp/aitests/ ; docker exec dissent-web php /tmp/aitests/php_failsafe.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require '/var/www/html/backend/compliance.php';

$log = tempnam(sys_get_temp_dir(), 'ailog');
ini_set('error_log', $log);
ini_set('log_errors', '1');

$server = proc_open(array('php', '-S', '127.0.0.1:9911', __DIR__ . '/fake_ai_router.php'), array(array('pipe', 'r'), array('file', '/dev/null', 'w'), array('file', '/dev/null', 'w')), $pipes);
usleep(600000);

$passed = 0;
$total = 0;
function ok($name, $cond, $detail = '')
{
    global $passed, $total;
    $total++;
    $passed += $cond ? 1 : 0;
    echo ($cond ? 'PASS ' : 'FAIL ') . $name . ($cond ? '' : '  -> ' . json_encode($detail)) . "\n";
}

$secret = 'Secret text with passport P0000000';
$people = array('id' => 1, 'role' => 'user', 'organisation' => 'Journalist');

function with_ai($url, $timeout = 1)
{
    putenv('AI_ENABLED=1');
    putenv('AI_SERVICE_URL=' . $url);
    putenv('AI_TIMEOUT_SECONDS=' . $timeout);
}

$cases = array(
    'service down (nothing listening)' => array('http://127.0.0.1:9', 'could not be reached'),
    'HTTP 500' => array('http://127.0.0.1:9911/http500', 'HTTP 500'),
    'not JSON' => array('http://127.0.0.1:9911/notjson', 'not valid'),
    'JSON with the wrong shape' => array('http://127.0.0.1:9911/badshape', 'not valid'),
    'REVIEW without a reason' => array('http://127.0.0.1:9911/reviewnoreason', 'not valid'),
    'a citation with a bad country code' => array('http://127.0.0.1:9911/badcitation', 'not valid'),
    // Last: the fake server answers one request at a time, and this one keeps it busy for 3 s.
    'too slow (3 s, limit 1 s)' => array('http://127.0.0.1:9911/slow', 'no answer within 1 seconds'),
);

foreach ($cases as $name => list($url, $why)) {
    with_ai($url);
    $t = microtime(true);
    $r = compliance_run($secret, null, $people, $people);
    $s = microtime(true) - $t;
    ok('AI ' . $name . ': message held (REVIEW, check_failed), the admin is told why',
        $r['decision'] === 'REVIEW' && $r['reasons'] === array('check_failed') && ($r['ai']['status'] ?? '') === 'failed'
        && strpos($r['ai']['reasons'][0] ?? '', $why) !== false && $s < 3, array($r, round($s, 2)));
}

// Let the fake server finish the slow request before the next cases.
sleep(3);

with_ai('http://127.0.0.1:9911/good');
$r = compliance_run('Thanks for the edits', null, $people, $people);
ok('a valid ALLOW from the AI is used', $r['decision'] === 'ALLOW' && $r['reasons'] === array() && $r['ai']['status'] === 'ok' && $r['ai']['model_name'] === 'fake', $r);

with_ai('http://127.0.0.1:9911/goodreview');
$r = compliance_run('Our source is X', null, $people, $people);
ok('a valid REVIEW from the AI holds the message with reason "ai_review" and keeps the citation', $r['decision'] === 'REVIEW' && $r['reasons'] === array('ai_review') && $r['ai']['citations'][0]['passage_id'] === 'IE-SRC-1', $r);
ok('the checker name is "ai-rag-react-v1" while AI_ENABLED=1', compliance_checker_name() === 'ai-rag-react-v1');

putenv('AI_ENABLED=0');
$same = true;
foreach (array('Call me on 087 123 4567', 'Hello, see you tomorrow', 'IBAN IE29 AIBK 9311 5212 3456 78', 'write a.b@example.org', 'Passport P1234567') as $text) {
    $same = $same && compliance_run($text, null, $people, $people) === compliance_check_rules($text, null, $people, $people)
        && !isset(compliance_run($text, null, $people, $people)['ai']);
}
ok('AI_ENABLED=0: exactly the rules, no AI call, no "ai" part', $same);
ok('the checker name is "rules-v1" while AI_ENABLED=0', compliance_checker_name() === 'rules-v1');
$r = compliance_run('Hello', array('mime' => 'image/png'), $people, $people);
ok('AI_ENABLED=0: an attachment is still held by the rules', $r['decision'] === 'REVIEW' && $r['reasons'] === array('attachment'), $r);

$logged = (string) file_get_contents($log);
ok('the error log names the problem but never the message text', strpos($logged, 'AI compliance check failed') !== false && strpos($logged, 'P0000000') === false && strpos($logged, 'Secret text') === false, $logged);

proc_terminate($server);
@unlink($log);
echo "$passed/$total passed\n";
exit($passed === $total ? 0 : 1);
