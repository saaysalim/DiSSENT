<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

$search = isset($_GET['q']) ? trim($_GET['q']) : "";
$found_report = null;

if($search !== ""){
    $found_report = find_verified_report($search);
}

// Also support ?report_id=X links coming from Media Center / Admin
if(isset($_GET['report_id']) && $search === ""){
    $found_report = find_verified_report($_GET['report_id']);
    $search = $_GET['report_id'];
}

// Title/journalist/category come from the fast MySQL join above, but the hash fields
// themselves are re-fetched from the ledger here so this detail page — whose whole
// purpose is proving the chain is authoritative — never shows stale/cached hashes.
if($found_report){
    $ledger_record = bc_get_verification($found_report['id']);
    if($ledger_record){
        $found_report['data_hash'] = $ledger_record['DataHash'];
        $found_report['prev_hash'] = $ledger_record['PrevHash'];
        $found_report['block_hash'] = $ledger_record['BlockHash'];
    }else{
        $found_report['block_hash'] = null;
    }
}

$stats = get_verification_stats();
$history = get_verification_history(10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verification - DiSSENT</title>
<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/verification.css">
</head>
<body>

<?php include('../includes/header.php'); ?>

<section class="verification-hero">
    <h1>Report Verification</h1>
    <p>
        Every verified DiSSENT report is sealed into a tamper-evident hash chain.
        Search below to confirm a report's authenticity and view its verification record.
    </p>
</section>

<section class="verification-search">

    <form method="GET" action="verification.php" class="verification-form">
        <input type="text" name="q" placeholder="Search by report ID, title, or journalist name" value="<?php echo e($search); ?>">
        <button type="submit">Verify</button>
    </form>

    <?php if($search !== ""){ ?>

        <?php if($found_report && $found_report['block_hash']){ ?>

            <div class="blockchain-section">
                <div class="blockchain-card">
                    <h2><?php echo e($found_report['title']); ?></h2>
                    <p><strong>Submitted by:</strong> <?php echo e($found_report['fullname']); ?></p>
                    <p><strong>Category:</strong> <?php echo e($found_report['category']); ?></p>
                    <p><strong>Status:</strong> <span class="verified-badge">✔ <?php echo e($found_report['status']); ?></span></p>
                    <p><strong>Verified on:</strong> <?php echo e(date('d M Y, g:i a', strtotime($found_report['verified_at']))); ?></p>

                    <p><strong>Data Hash</strong></p>
                    <div class="hash-box"><?php echo e($found_report['data_hash']); ?></div>

                    <p><strong>Previous Block Hash</strong></p>
                    <div class="hash-box"><?php echo e($found_report['prev_hash']); ?></div>

                    <p><strong>Block Hash</strong></p>
                    <div class="hash-box"><?php echo e($found_report['block_hash']); ?></div>
                </div>
            </div>

        <?php }else{ ?>

            <div class="blockchain-section">
                <div class="blockchain-card">
                    <p>No verified report found matching "<?php echo e($search); ?>". It may still be pending review, or the search didn't match anything.</p>
                </div>
            </div>

        <?php } ?>

    <?php } ?>

</section>

<section class="verification-stats">

    <div class="verification-card">
        <h2><?php echo (int)$stats['total_reports']; ?></h2>
        <p>Total Reports</p>
    </div>

    <div class="verification-card">
        <h2><?php echo (int)$stats['verified_count']; ?></h2>
        <p>Verified</p>
    </div>

    <div class="verification-card">
        <h2><?php echo (int)$stats['pending_count']; ?></h2>
        <p>Pending Review</p>
    </div>

    <?php
        if($stats['chain_intact'] === null){
            $chain_icon = '?';
            $chain_label = 'Unavailable';
        }elseif($stats['chain_intact']){
            $chain_icon = '✔';
            $chain_label = 'Intact';
        }else{
            $chain_icon = '⚠';
            $chain_label = 'BROKEN';
        }
    ?>
    <div class="verification-card">
        <h2><?php echo e($chain_icon); ?></h2>
        <p>Chain Integrity: <?php echo e($chain_label); ?></p>
    </div>

</section>

<section class="verification-history">

    <h2>Recent Verifications</h2>

    <?php if(empty($history)){ ?>

        <p style="text-align:center;">No reports have been verified yet.</p>

    <?php }else{ ?>

        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Journalist</th>
                    <th>Category</th>
                    <th>Verified On</th>
                    <th>Block Hash</th>
                    <th>Details</th>
                </tr>
            </thead>
        <tbody>
                <?php foreach($history as $h){ ?>
                    <tr>
                        <td data-label="Title"><?php echo e($h['title']); ?></td>
                        <td data-label="Journalist"><?php echo e($h['fullname']); ?></td>
                        <td data-label="Category"><?php echo e($h['category']); ?></td>
                        <td data-label="Verified On"><?php echo e(date('d M Y', strtotime($h['verified_at']))); ?></td>
                        <td data-label="Block Hash" style="font-family:monospace; font-size:12px;"><?php echo e(substr($h['block_hash'], 0, 16)); ?>&hellip;</td>
                        <td data-label="Details"><a href="verification.php?report_id=<?php echo (int)$h['id']; ?>">View</a></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

    <?php } ?>

</section>

<?php include('../includes/footer.php'); ?>

</body>
</html>