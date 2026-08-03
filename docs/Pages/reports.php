<?php
session_start();
include('../backend/db.php');
include('../backend/content.php');

require_login();

$message = "";
$user_id = $_SESSION['user_id'];

if($_SERVER["REQUEST_METHOD"] == "POST"){

    verify_csrf();

    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $evidence = trim($_POST['evidence']);

    $stmt = mysqli_prepare($conn, "
        INSERT INTO reports (user_id, title, category, description, location, evidence, status)
        VALUES (?, ?, ?, ?, ?, ?, 'Pending')
    ");
    mysqli_stmt_bind_param($stmt, 'isssss', $user_id, $title, $category, $description, $location, $evidence);

    if(mysqli_stmt_execute($stmt)){
        $message = "Report submitted successfully.";
    }else{
        $message = "Submission failed. Please try again.";
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$reports = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports - DiSSENT</title>

<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/reports.css">
</head>

<body>

<?php include('../includes/header.php'); ?>

<!-- HERO -->

<section class="reports-hero">

    <h1>Reports Centre</h1>

    <p>
        Submit reports and track verification status
        through the DiSSENT platform.
    </p>

</section>

<!-- MESSAGE -->

<?php if($message != "") { ?>

<div style="
max-width:900px;
margin:20px auto;
padding:15px;
background:#f4f4f4;
border-radius:8px;
text-align:center;
font-weight:bold;">

<?php echo e($message); ?>

</div>

<?php } ?>

<!-- REPORT SUBMISSION -->

<section class="reports-section">

<div class="report-card">

<h2>Submit New Report</h2>

<form method="POST">

<?php echo csrf_field(); ?>

<input
type="text"
name="title"
placeholder="Report Title"
required>

<br><br>

<input
type="text"
name="category"
placeholder="Category"
required>

<br><br>

<input
type="text"
name="location"
placeholder="Location">

<br><br>

<input
type="text"
name="evidence"
placeholder="Evidence Link or Filename">

<br><br>

<textarea
name="description"
rows="6"
placeholder="Report Description"
required></textarea>

<br><br>

<button type="submit">
Submit Report
</button>

</form>

</div>

</section>

<!-- MY REPORTS -->

<section class="reports-section">

<h2>My Reports</h2>

<div class="reports-grid">

<?php

if(mysqli_num_rows($reports) > 0){

while($row = mysqli_fetch_assoc($reports)){

?>

<div class="report-card">

<h3>
<?php echo e($row['title']); ?>
</h3>

<p>
<?php echo e($row['description']); ?>
</p>

<p>
<strong>Category:</strong>
<?php echo e($row['category']); ?>
</p>

<p>
<strong>Location:</strong>
<?php echo e($row['location']); ?>
</p>

<p>
<strong>Evidence:</strong>
<?php echo e($row['evidence']); ?>
</p>

<p>

<strong>Status:</strong>

<?php

if($row['status']=="Verified"){

echo "<span style='color:green;font-weight:bold;'>Verified</span>";

}
elseif($row['status']=="Rejected"){

echo "<span style='color:red;font-weight:bold;'>Rejected</span>";

}
else{

echo "<span style='color:orange;font-weight:bold;'>Pending</span>";

}

?>

</p>

<p>

<small>

Submitted:
<?php echo e($row['created_at']); ?>

</small>

</p>

</div>

<?php

}

}else{

?>

<div class="report-card">

<p>No reports submitted yet.</p>

</div>

<?php

}

?>

</div>

</section>

<?php include('../includes/footer.php'); ?>

</body>
</html>