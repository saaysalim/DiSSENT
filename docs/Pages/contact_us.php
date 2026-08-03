<?php
include('../backend/db.php');

$message = "";
$message_type = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject']));
    $body = mysqli_real_escape_string($conn, trim($_POST['message']));

    if($full_name != "" && $email != "" && $body != ""){

        $sql = "INSERT INTO contact_messages
        (full_name,email,subject,message)
        VALUES
        ('$full_name','$email','$subject','$body')";

        if(mysqli_query($conn,$sql)){
            $message = "Thank you, your message has been sent. We'll get back to you soon.";
            $message_type = "success";
        }else{
            $message = "Something went wrong. Please try again later.";
            $message_type = "error";
        }

    }else{
        $message = "Please fill in your name, email and message.";
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - DiSSENT</title>

    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/contact_us.css">
</head>

<body>

<?php include('../includes/header.php'); ?>

<!-- CONTACT HERO -->

<section class="contact-hero">

    <div class="contact-hero-content">

        <h1>Contact DiSSENT</h1>

        <p>
            We'd love to hear from you. Contact our team for support,
            collaboration opportunities, or project inquiries.
        </p>

    </div>

</section>

<!-- SUBMISSION MESSAGE -->

<?php if($message != "") { ?>

<div style="
max-width:900px;
margin:20px auto;
padding:15px;
background:<?php echo $message_type == "success" ? "#e6f4ea" : "#fdecea"; ?>;
color:<?php echo $message_type == "success" ? "#1e7e34" : "#a12622"; ?>;
border-radius:8px;
text-align:center;
font-weight:bold;">

<?php echo htmlspecialchars($message); ?>

</div>

<?php } ?>

<!-- CONTACT SECTION -->

<section class="contact-section">

    <div class="contact-container">

        <div class="contact-info">

            <h2>Get In Touch</h2>

            <p>
                DiSSENT supports secure communication between journalists,
                media organisations and trusted partners across borders.
            </p>

            <div class="contact-details">

                <p><strong>Email:</strong> support@dissent.org</p>

                <p><strong>Phone:</strong> +353 000 000 000</p>

                <p><strong>Location:</strong> Limerick, Ireland</p>

            </div>

        </div>

        <div class="contact-form-container">

            <h2>Send a Message</h2>

            <form method="POST" class="contact-form">

                <input type="text"
                    name="full_name"
                    placeholder="Full Name"
                    value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ""; ?>"
                    required>

                <input type="email"
                    name="email"
                    placeholder="Email Address"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ""; ?>"
                    required>

                <input type="text"
                    name="subject"
                    placeholder="Subject"
                    value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ""; ?>">

                <textarea
                    name="message"
                    rows="6"
                    placeholder="Write your message here..."
                    required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ""; ?></textarea>

                <button type="submit"
                        class="contact-btn">
                    Send Message
                </button>

            </form>

        </div>

    </div>

</section>

<?php include('../includes/footer.php'); ?>

</body>

</html>
