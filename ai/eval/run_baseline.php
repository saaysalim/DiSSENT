<?php
/*
 * Baseline evaluation (step 1 of the research plan): runs the current rule-based
 * compliance_check() from docs/backend/compliance.php on every message in
 * ai/eval/test_messages.jsonl and writes results_baseline.json and results_baseline.md.
 *
 * Command line only. ai/ is outside the web root (docs/), so the website cannot run it.
 * There is no PHP on the host, so run it in a temporary container made from the
 * DiSSENT web image (PowerShell or bash, from DiSSENT-clean):
 *
 *   docker compose run --rm --no-deps -v "${PWD}/ai:/var/www/ai" web php /var/www/ai/eval/run_baseline.php
 *
 * Positive class = REVIEW (a message that should be held).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

// Always the rules, even if AI_ENABLED=1 is set: this is the baseline.
putenv('AI_ENABLED=0');

$compliance = $argv[1] ?? '/var/www/html/backend/compliance.php';
require $compliance;

$dir = __DIR__;
$messages = array();

foreach (file($dir . '/test_messages.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $messages[] = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
}

$results = array();

foreach ($messages as $m) {
    $attachment = $m['has_attachment']
        ? array('mime' => 'application/pdf', 'original_name' => 'attachment.pdf', 'path' => '')
        : null;
    $sender = array('id' => 0, 'role' => 'user', 'organisation' => '', 'country' => $m['sender_country']);
    $receiver = array('id' => 0, 'role' => 'user', 'organisation' => '', 'country' => $m['receiver_country']);

    $start = hrtime(true);
    $check = compliance_check($m['text'], $attachment, $sender, $receiver);
    $ms = (hrtime(true) - $start) / 1e6;

    $results[] = array(
        'id' => $m['id'],
        'category' => $m['category'],
        'expected' => $m['expected'],
        'predicted' => $check['decision'],
        'correct' => $check['decision'] === $m['expected'],
        'reasons' => $check['reasons'],
        'citations' => 0,
        'latency_ms' => round($ms, 3),
    );
}

$metrics = eval_metrics($results);

file_put_contents($dir . '/results_baseline.json', json_encode(array(
    'system' => 'Baseline: rule-based compliance_check() (' . COMPLIANCE_CHECKER . ')',
    'test_set' => 'ai/eval/test_messages.jsonl',
    'run_at' => gmdate('Y-m-d H:i:s') . ' UTC',
    'metrics' => $metrics,
    'results' => $results,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

file_put_contents($dir . '/results_baseline.md', eval_markdown(
    'Baseline: current rule-based check (' . COMPLIANCE_CHECKER . ')',
    'Every message in `test_messages.jsonl` was checked by `compliance_check()` from `docs/backend/compliance.php` with `AI_ENABLED=0`.',
    $metrics,
    $results
));

printf("Baseline: %d messages, accuracy %.1f%%, precision %.1f%%, recall %.1f%%, false alarms %.1f%%, missed %d\n",
    $metrics['n'], 100 * $metrics['accuracy'], 100 * $metrics['precision'], 100 * $metrics['recall'],
    100 * $metrics['false_alarm_rate'], $metrics['fn']);


/* ---------- Measures (the same as ai/eval/run_ai.py) ---------- */

function eval_metrics($results)
{
    $tp = $fp = $tn = $fn = 0;
    $categories = array();

    foreach ($results as $r) {
        $expected_review = $r['expected'] === 'REVIEW';
        $predicted_review = $r['predicted'] === 'REVIEW';

        if ($expected_review && $predicted_review) $tp++;
        elseif (!$expected_review && $predicted_review) $fp++;
        elseif (!$expected_review && !$predicted_review) $tn++;
        else $fn++;

        $c = $r['category'];
        $categories[$c] = $categories[$c] ?? array('n' => 0, 'correct' => 0, 'held' => 0);
        $categories[$c]['n']++;
        $categories[$c]['correct'] += $r['correct'] ? 1 : 0;
        $categories[$c]['held'] += $predicted_review ? 1 : 0;
    }

    $n = count($results);
    $ratio = static function ($a, $b) { return $b > 0 ? $a / $b : 0.0; };

    return array(
        'n' => $n,
        'tp' => $tp, 'fp' => $fp, 'tn' => $tn, 'fn' => $fn,
        'accuracy' => $ratio($tp + $tn, $n),
        'precision' => $ratio($tp, $tp + $fp),
        'recall' => $ratio($tp, $tp + $fn),
        'false_alarm_rate' => $ratio($fp, $fp + $tn),
        'missed_risks' => array_values(array_map(static function ($r) { return $r['id']; },
            array_filter($results, static function ($r) { return $r['expected'] === 'REVIEW' && $r['predicted'] !== 'REVIEW'; }))),
        'false_alarms' => array_values(array_map(static function ($r) { return $r['id']; },
            array_filter($results, static function ($r) { return $r['expected'] === 'ALLOW' && $r['predicted'] === 'REVIEW'; }))),
        'with_citation' => $ratio(count(array_filter($results, static function ($r) { return $r['citations'] > 0; })), $n),
        'avg_latency_ms' => $ratio(array_sum(array_column($results, 'latency_ms')), $n),
        'per_category' => $categories,
    );
}


function eval_markdown($title, $intro, $m, $results)
{
    $pct = static function ($x) { return number_format(100 * $x, 1) . ' %'; };
    $order = array('safe', 'personal_data', 'source_identity', 'location', 'financial', 'security_risk', 'borderline');

    $md = "# $title\n\n$intro\n\nRun: " . gmdate('d/m/Y H:i') . " UTC. Positive class = REVIEW (the message should be held).\n\n";
    $md .= "| Measure | Value |\n|---|---|\n";
    $md .= '| Messages | ' . $m['n'] . " |\n";
    $md .= '| Accuracy | ' . $pct($m['accuracy']) . " |\n";
    $md .= '| Precision (REVIEW) | ' . $pct($m['precision']) . " |\n";
    $md .= '| Recall (REVIEW) | ' . $pct($m['recall']) . " |\n";
    $md .= '| False alarm rate (safe messages held) | ' . $pct($m['false_alarm_rate']) . " |\n";
    $md .= '| Missed risks (should be held, were allowed) | ' . $m['fn'] . " |\n";
    $md .= '| Decisions with at least one citation | ' . $pct($m['with_citation']) . " |\n";
    $md .= '| Average time per message | ' . number_format($m['avg_latency_ms'], 2) . " ms |\n\n";
    $md .= "Confusion matrix: TP " . $m['tp'] . ', FP ' . $m['fp'] . ', TN ' . $m['tn'] . ', FN ' . $m['fn'] . ".\n\n";
    $md .= "## Per category\n\n| Category | Messages | Correct | Held (REVIEW) |\n|---|---|---|---|\n";

    foreach ($order as $c) {
        if (isset($m['per_category'][$c])) {
            $x = $m['per_category'][$c];
            $md .= "| $c | {$x['n']} | {$x['correct']} (" . $pct($x['correct'] / $x['n']) . ") | {$x['held']} |\n";
        }
    }

    $md .= "\n## Missed risks\n\n" . ($m['missed_risks'] === array() ? "None.\n" : '');
    $byid = array_column($results, null, 'id');
    foreach ($m['missed_risks'] as $id) {
        $md .= "- $id ({$byid[$id]['category']})\n";
    }
    $md .= "\n## False alarms\n\n" . ($m['false_alarms'] === array() ? "None.\n" : '');
    foreach ($m['false_alarms'] as $id) {
        $md .= "- $id ({$byid[$id]['category']}): " . implode(', ', $byid[$id]['reasons']) . "\n";
    }

    return $md;
}
