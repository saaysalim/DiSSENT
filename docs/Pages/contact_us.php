<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

$message = '';
$message_type = '';

$fields = array(
    'full_name' => '',
    'email' => '',
    'subject' => '',
    'message' => ''
);

$contact_escape = static function ($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$contact_length = static function ($value) {
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
};

// Show the success message once after redirecting.
if (isset($_SESSION['contact_success'])) {
    $message = 'Thank you. Your message has been received.';
    $message_type = 'success';

    unset($_SESSION['contact_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $message = '';
    $message_type = 'error';
    $invalid_input = false;

    foreach ($fields as $field => $default_value) {
        $value = $_POST[$field] ?? '';

        if (!is_string($value)) {
            $invalid_input = true;
            continue;
        }

        $fields[$field] = trim($value);
    }

    if ($invalid_input) {
        $message = 'Please enter valid text in each field.';
    } elseif (
        $fields['full_name'] === '' ||
        $fields['email'] === '' ||
        $fields['message'] === ''
    ) {
        $message = 'Please fill in your name, email and message.';
    } elseif ($contact_length($fields['full_name']) > 100) {
        $message = 'Please keep your name within 100 characters.';
    } elseif (
        strlen($fields['email']) > 254 ||
        !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)
    ) {
        $message = 'Please enter a valid email address.';
    } elseif ($contact_length($fields['subject']) > 150) {
        $message = 'Please keep the subject within 150 characters.';
    } elseif ($contact_length($fields['message']) > 5000) {
        $message = 'Please keep your message within 5,000 characters.';
    } else {
        $statement = null;
        $saved = false;

        try {
            $statement = mysqli_prepare(
                $conn,
                'INSERT INTO contact_messages
                    (full_name, email, subject, message)
                VALUES (?, ?, ?, ?)'
            );

            if (!$statement) {
                throw new RuntimeException(
                    'Contact statement preparation failed.'
                );
            }

            mysqli_stmt_bind_param(
                $statement,
                'ssss',
                $fields['full_name'],
                $fields['email'],
                $fields['subject'],
                $fields['message']
            );

            if (!mysqli_stmt_execute($statement)) {
                throw new RuntimeException(
                    'Contact message insertion failed.'
                );
            }

            $saved = true;
        } catch (Throwable $exception) {
            // Do not log the visitor's message or contact details.
            error_log('DiSSENT: unable to save contact message.');

            $message = 'Your message could not be saved. Please try again later.';
        } finally {
            if ($statement instanceof mysqli_stmt) {
                mysqli_stmt_close($statement);
            }
        }

        if ($saved) {
            $_SESSION['contact_success'] = true;

            // Redirect after a successful POST so refreshing
            // does not submit the message again.
            header('Location: contact_us.php', true, 303);
            exit();
        }
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

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="contact-page">

    <section class="page-hero" aria-labelledby="contactHeading">
        <div>
            <h1 id="contactHeading">Contact DiSSENT</h1>

            <p>
                Contact our team with questions about the platform,
                technical support or collaboration opportunities.
            </p>
        </div>
    </section>

    <section
        class="contact-section"
        aria-labelledby="contactFormHeading"
    >
        <?php if ($message !== ''): ?>
            <div
                class="notice notice--<?php
                    echo $contact_escape($message_type);
                ?>"
                role="<?php
                    echo $message_type === 'error' ? 'alert' : 'status';
                ?>"
            >
                <p><?php echo $contact_escape($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="contact-container">

            <div class="contact-info">
                <h2>Get in Touch</h2>

                <p>
                    DiSSENT brings together journalists, media organisations
                    and cross-border partners, including reporters working
                    away from their home countries.
                </p>

                <p>
                    Use this form to ask a question, report a technical
                    issue or discuss working with us.
                </p>

                <div class="contact-details">
                    <p>
                        <strong>Location:</strong>
                        Limerick, Ireland
                    </p>

                    <p>
                        <strong>Contact:</strong>
                        Please use the message form.
                    </p>
                </div>

                <p class="contact-help">
                    For technical issues, include the page name and a short
                    description of what happened. Do not include passwords,
                    private keys or confidential source information.
                </p>
            </div>

            <div class="contact-form-container">
                <h2 id="contactFormHeading">Send a Message</h2>

                <p id="contactFormHelp" class="contact-help">
                    Name, email and message are required.
                </p>

                <form
                    method="POST"
                    action="contact_us.php"
                    class="contact-form"
                    aria-describedby="contactFormHelp"
                >
                    <?php echo csrf_field(); ?>

                    <label for="contactFullName">
                        Full name
                    </label>

                    <input
                        type="text"
                        id="contactFullName"
                        name="full_name"
                        autocomplete="name"
                        maxlength="100"
                        placeholder="Your full name"
                        value="<?php
                            echo $contact_escape($fields['full_name']);
                        ?>"
                        required
                    >

                    <label for="contactEmail">
                        Email address
                    </label>

                    <input
                        type="email"
                        id="contactEmail"
                        name="email"
                        autocomplete="email"
                        maxlength="254"
                        placeholder="you@example.com"
                        value="<?php
                            echo $contact_escape($fields['email']);
                        ?>"
                        required
                    >

                    <label for="contactSubject">
                        Subject (optional)
                    </label>

                    <input
                        type="text"
                        id="contactSubject"
                        name="subject"
                        maxlength="150"
                        placeholder="What is your message about?"
                        value="<?php
                            echo $contact_escape($fields['subject']);
                        ?>"
                    >

                    <label for="contactMessage">
                        Message
                    </label>

                    <textarea
                        id="contactMessage"
                        name="message"
                        rows="7"
                        maxlength="5000"
                        placeholder="Write your message here..."
                        aria-describedby="contactMessageHelp"
                        required
                    ><?php
                        echo $contact_escape($fields['message']);
                    ?></textarea>

                    <p id="contactMessageHelp" class="contact-help">
                        Maximum 5,000 characters.
                    </p>

                    <button type="submit" class="contact-btn">
                        Send Message
                    </button>
                </form>
            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>