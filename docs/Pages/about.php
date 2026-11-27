<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

$content = platform_content();

$features = isset($content['features']) &&
    is_array($content['features'])
    ? $content['features']
    : array();

$benefits = array(
    array(
        'title' => 'Cross-Border Collaboration',
        'text' => 'Bring journalists and media organisations together to share ideas and collaborate across countries.'
    ),
    array(
        'title' => 'Support for Displaced Reporters',
        'text' => 'Create opportunities for refugee and displaced journalists to stay connected with media communities while working away from home.'
    ),
    array(
        'title' => 'Clear Review Process',
        'text' => 'Submit reports for administrator review and follow their progress through pending, approved or rejected decisions.'
    ),
    array(
        'title' => 'Information Integrity',
        'text' => 'Use recorded verification information to support checks for changes to report content. These checks complement editorial review.'
    )
);

$is_logged_in = isset($_SESSION['user_id']);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Learn about DiSSENT, the platform for cross-border and exiled journalists: report review, secure messaging and blockchain verification."
    >

    <title>About DiSSENT</title>

    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/about.css?v=3">
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="about-page" id="main-content" tabindex="-1">

    <section class="page-hero" aria-labelledby="aboutHeading">
        <div>
            <h1 id="aboutHeading">About DiSSENT</h1>

            <p>
                Connecting journalists, media organisations and
                cross-border communities through collaboration,
                report sharing and information integrity tools.
            </p>
        </div>
    </section>

    <section class="about-section" aria-labelledby="missionHeading">
        <h2 id="missionHeading">Our Mission</h2>

        <p>
            DiSSENT aims to help journalists and media organisations
            collaborate across borders. This includes refugee and
            displaced reporters who want to maintain professional
            connections and contribute to journalism while living
            away from their home countries.
        </p>

        <p>
            The platform brings together messaging, report submission,
            editorial review and a media centre for approved publications.
        </p>
    </section>

    <section
        class="technology-section"
        aria-labelledby="featuresHeading"
    >
        <h2 id="featuresHeading">What You Can Do</h2>

        <div class="technology-grid">
            <?php foreach ($features as $feature): ?>
                <?php
                if (
                    !is_array($feature) ||
                    !isset(
                        $feature['link'],
                        $feature['title'],
                        $feature['text']
                    ) ||
                    !is_string($feature['link']) ||
                    !is_string($feature['title']) ||
                    !is_string($feature['text'])
                ) {
                    continue;
                }

                // Feature links point to local platform pages.
                $allowed_feature_pages = array(
                    'messaging.php',
                    'reports.php',
                    'verification.php',
                    'media_center.php',
                    'dashboard.php',
                    'contact_us.php'
                );

                if (
                    !in_array(
                        $feature['link'],
                        $allowed_feature_pages,
                        true
                    )
                ) {
                    continue;
                }
                ?>

                <a
                    href="<?php echo e($feature['link']); ?>"
                    class="tech-card"
                >
                    <h3><?php echo e($feature['title']); ?></h3>
                    <p><?php echo e($feature['text']); ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section
        class="benefits-section"
        aria-labelledby="benefitsHeading"
    >
        <h2 id="benefitsHeading">Who DiSSENT Is For</h2>

        <div class="benefits-grid">
            <?php foreach ($benefits as $benefit): ?>
                <div class="benefit-card">
                    <h3><?php echo e($benefit['title']); ?></h3>
                    <p><?php echo e($benefit['text']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="about-section about-diagrams" aria-labelledby="diagramsHeading">
        <h2 id="diagramsHeading">How DiSSENT Works</h2>
        <p>Three pictures of what happens behind the pages. Each one is also written out as a list.</p>

        <figure class="about-diagram">
            <div class="about-diagram__picture">
                <svg class="about-diagram__svg" viewBox="0 0 320 494" width="320" height="494" role="img" aria-labelledby="flowDiagram-title flowDiagram-desc" font-family="Arial, Helvetica, sans-serif">
                    <title id="flowDiagram-title">How a message travels through DiSSENT</title>
                    <desc id="flowDiagram-desc">A message goes from the website to securecomm, which encrypts and signs it, then through Kafka to securecomm2, which checks the signature with the public key on Hyperledger Fabric and decrypts it.</desc>
                    <defs><marker id="flowDiagram-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#526c96"/></marker></defs>
                    <rect x="24" y="10" width="272" height="54" rx="10" fill="#10244d" stroke="#10244d" stroke-width="2"/>
                    <text x="160" y="34" text-anchor="middle" fill="#ffffff" font-size="13" font-weight="700">You send a message</text>
                    <text x="160" y="51" text-anchor="middle" fill="#ffffff" font-size="13">on the DiSSENT website</text>
                    <line x1="160" y1="64" x2="160" y2="92" stroke="#526c96" stroke-width="2" marker-end="url(#flowDiagram-arrow)"/>
                    <rect x="24" y="94" width="272" height="54" rx="10" fill="#ffffff" stroke="#10244d" stroke-width="2"/>
                    <text x="160" y="118" text-anchor="middle" fill="#10244d" font-size="13" font-weight="700">The website checks it</text>
                    <text x="160" y="135" text-anchor="middle" fill="#10244d" font-size="13">and saves it (MySQL)</text>
                    <line x1="160" y1="148" x2="160" y2="176" stroke="#526c96" stroke-width="2" marker-end="url(#flowDiagram-arrow)"/>
                    <rect x="24" y="178" width="272" height="54" rx="10" fill="#ffffff" stroke="#10244d" stroke-width="2"/>
                    <text x="160" y="202" text-anchor="middle" fill="#10244d" font-size="13" font-weight="700">securecomm encrypts (AES)</text>
                    <text x="160" y="219" text-anchor="middle" fill="#10244d" font-size="13">and signs it (ECDSA)</text>
                    <line x1="160" y1="232" x2="160" y2="260" stroke="#526c96" stroke-width="2" marker-end="url(#flowDiagram-arrow)"/>
                    <rect x="24" y="262" width="272" height="54" rx="10" fill="#ffffff" stroke="#10244d" stroke-width="2"/>
                    <text x="160" y="286" text-anchor="middle" fill="#10244d" font-size="13" font-weight="700">Kafka carries the</text>
                    <text x="160" y="303" text-anchor="middle" fill="#10244d" font-size="13">encrypted copy</text>
                    <line x1="160" y1="316" x2="160" y2="344" stroke="#526c96" stroke-width="2" marker-end="url(#flowDiagram-arrow)"/>
                    <rect x="24" y="346" width="272" height="54" rx="10" fill="#ffffff" stroke="#10244d" stroke-width="2"/>
                    <text x="160" y="370" text-anchor="middle" fill="#10244d" font-size="13" font-weight="700">securecomm2 checks the signature</text>
                    <text x="160" y="387" text-anchor="middle" fill="#10244d" font-size="13">with the key on Hyperledger Fabric</text>
                    <line x1="160" y1="400" x2="160" y2="428" stroke="#526c96" stroke-width="2" marker-end="url(#flowDiagram-arrow)"/>
                    <rect x="24" y="430" width="272" height="54" rx="10" fill="#edf8f1" stroke="#2f8f5b" stroke-width="2"/>
                    <text x="160" y="454" text-anchor="middle" fill="#1d3b2b" font-size="13" font-weight="700">&quot;Secure transfer: verified ✓&quot;</text>
                    <text x="160" y="471" text-anchor="middle" fill="#1d3b2b" font-size="13">is shown next to the message</text>
                    </svg>
            </div>
            <figcaption>
                <h3>1. How a message travels</h3>
                <ol>
                    <li>You send a message on the DiSSENT website.</li>
                    <li>The website checks it and saves it in its database (MySQL).</li>
                    <li>securecomm encrypts a copy (AES) and signs it (ECDSA).</li>
                    <li>Kafka carries the encrypted copy to securecomm2.</li>
                    <li>securecomm2 checks the signature with the sender&rsquo;s public key on Hyperledger Fabric, then decrypts it.</li>
                    <li>The website shows &ldquo;Secure transfer: verified ✓&rdquo; next to the message.</li>
                </ol>
            </figcaption>
        </figure>

        <figure class="about-diagram">
            <div class="about-diagram__picture">
                <svg class="about-diagram__svg" viewBox="0 0 320 332" width="320" height="332" role="img" aria-labelledby="reviewDiagram-title reviewDiagram-desc" font-family="Arial, Helvetica, sans-serif">
                    <title id="reviewDiagram-title">How a report is reviewed</title>
                    <desc id="reviewDiagram-desc">A member submits a report, which is pending. An administrator reads it and approves or rejects it. Approved reports are published and their fingerprint is recorded on Hyperledger Fabric. Rejected reports are not published and the reason is shown to the member.</desc>
                    <defs><marker id="reviewDiagram-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#526c96"/></marker></defs>
                    <rect x="24" y="10" width="272" height="54" rx="10" fill="#10244d" stroke="#10244d" stroke-width="2"/>
                    <text x="160" y="34" text-anchor="middle" fill="#ffffff" font-size="13" font-weight="700">A member submits a report</text>
                    <text x="160" y="51" text-anchor="middle" fill="#ffffff" font-size="13">Status: Pending</text>
                    <line x1="160" y1="64" x2="160" y2="92" stroke="#526c96" stroke-width="2" marker-end="url(#reviewDiagram-arrow)"/>
                    <rect x="24" y="94" width="272" height="54" rx="10" fill="#fff8e6" stroke="#b7791f" stroke-width="2"/>
                    <text x="160" y="118" text-anchor="middle" fill="#4a3500" font-size="13" font-weight="700">An administrator reads it</text>
                    <text x="160" y="135" text-anchor="middle" fill="#4a3500" font-size="13">on the Admin page</text>
                    <line x1="160" y1="148" x2="89" y2="232" stroke="#526c96" stroke-width="2" marker-end="url(#reviewDiagram-arrow)"/>
                    <rect x="28" y="178" width="72" height="24" rx="12" fill="#1f6f43"/><text x="64" y="194" text-anchor="middle" fill="#ffffff" font-size="13" font-weight="700">Approve</text>
                    <line x1="160" y1="148" x2="231" y2="232" stroke="#526c96" stroke-width="2" marker-end="url(#reviewDiagram-arrow)"/>
                    <rect x="220" y="178" width="72" height="24" rx="12" fill="#a12622"/><text x="256" y="194" text-anchor="middle" fill="#ffffff" font-size="13" font-weight="700">Reject</text>
                    <rect x="24" y="234" width="130" height="88" rx="10" fill="#edf8f1" stroke="#2f8f5b" stroke-width="2"/>
                    <text x="89" y="258" text-anchor="middle" fill="#1d3b2b" font-size="13" font-weight="700">Published in the</text>
                    <text x="89" y="275" text-anchor="middle" fill="#1d3b2b" font-size="13">Media Centre;</text>
                    <text x="89" y="292" text-anchor="middle" fill="#1d3b2b" font-size="13">fingerprint on</text>
                    <text x="89" y="309" text-anchor="middle" fill="#1d3b2b" font-size="13">the blockchain</text>
                    <rect x="166" y="234" width="130" height="71" rx="10" fill="#fff3f3" stroke="#a12622" stroke-width="2"/>
                    <text x="231" y="258" text-anchor="middle" fill="#5c1a1f" font-size="13" font-weight="700">Not published;</text>
                    <text x="231" y="275" text-anchor="middle" fill="#5c1a1f" font-size="13">the member sees</text>
                    <text x="231" y="292" text-anchor="middle" fill="#5c1a1f" font-size="13">the reason</text>
                    </svg>
            </div>
            <figcaption>
                <h3>2. How a report is reviewed</h3>
                <ol>
                    <li>A member submits a report. Its status is &ldquo;Pending&rdquo;.</li>
                    <li>An administrator reads it on the Admin page.</li>
                    <li>Approve: the report is published in the Media Centre and its fingerprint (hash) is recorded on the blockchain.</li>
                    <li>Reject: the report is not published, and the member sees the reason.</li>
                </ol>
                <p>Every decision is saved in an activity log. Approval is an editorial decision; the fingerprint only shows the text has not changed since.</p>
                <div class="review-criteria">
                    <h4>What administrators check</h4>
                    <ul>
                        <li><strong>Accurate and sourced:</strong> the claims are supported by evidence or a named, checkable source.</li>
                        <li><strong>No danger to sources:</strong> nothing identifies a person who could be harmed, such as an exact address or a name.</li>
                        <li><strong>No personal data:</strong> no private contact details, ID numbers or other personal information about anyone.</li>
                        <li><strong>Relevant:</strong> it fits DiSSENT&rsquo;s purpose of cross-border and exile journalism.</li>
                        <li><strong>Not defamatory:</strong> it does not make unsupported claims that could damage someone&rsquo;s reputation.</li>
                    </ul>
                </div>
            </figcaption>
        </figure>

        <figure class="about-diagram">
            <div class="about-diagram__picture">
                <svg class="about-diagram__svg" viewBox="0 0 320 298" width="320" height="298" role="img" aria-labelledby="messageDiagram-title messageDiagram-desc" font-family="Arial, Helvetica, sans-serif">
                    <title id="messageDiagram-title">How a message is checked before delivery</title>
                    <desc id="messageDiagram-desc">Every message is checked automatically. A message with a file, phone number, email address, ID number or bank account number waits for an administrator who is not in the conversation. Other messages are delivered at once. Approved messages are delivered; rejected messages are never shown to the receiver.</desc>
                    <defs><marker id="messageDiagram-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#526c96"/></marker></defs>
                    <rect x="24" y="10" width="272" height="37" rx="10" fill="#10244d" stroke="#10244d" stroke-width="2"/>
                    <text x="160" y="34" text-anchor="middle" fill="#ffffff" font-size="13" font-weight="700">A member sends a message</text>
                    <line x1="160" y1="47" x2="160" y2="75" stroke="#526c96" stroke-width="2" marker-end="url(#messageDiagram-arrow)"/>
                    <rect x="24" y="77" width="272" height="54" rx="10" fill="#fff8e6" stroke="#b7791f" stroke-width="2"/>
                    <text x="160" y="101" text-anchor="middle" fill="#4a3500" font-size="13" font-weight="700">Automatic check: a file, phone,</text>
                    <text x="160" y="118" text-anchor="middle" fill="#4a3500" font-size="13">email, ID number or IBAN?</text>
                    <line x1="160" y1="131" x2="89" y2="215" stroke="#526c96" stroke-width="2" marker-end="url(#messageDiagram-arrow)"/>
                    <rect x="52" y="161" width="48" height="24" rx="12" fill="#10244d"/><text x="76" y="177" text-anchor="middle" fill="#ffffff" font-size="13" font-weight="700">No</text>
                    <line x1="160" y1="131" x2="231" y2="215" stroke="#526c96" stroke-width="2" marker-end="url(#messageDiagram-arrow)"/>
                    <rect x="220" y="161" width="48" height="24" rx="12" fill="#10244d"/><text x="244" y="177" text-anchor="middle" fill="#ffffff" font-size="13" font-weight="700">Yes</text>
                    <rect x="24" y="217" width="130" height="54" rx="10" fill="#edf8f1" stroke="#2f8f5b" stroke-width="2"/>
                    <text x="89" y="241" text-anchor="middle" fill="#1d3b2b" font-size="13" font-weight="700">Delivered</text>
                    <text x="89" y="258" text-anchor="middle" fill="#1d3b2b" font-size="13">at once</text>
                    <rect x="166" y="217" width="130" height="71" rx="10" fill="#ffffff" stroke="#10244d" stroke-width="2"/>
                    <text x="231" y="241" text-anchor="middle" fill="#10244d" font-size="13" font-weight="700">Waits for an</text>
                    <text x="231" y="258" text-anchor="middle" fill="#10244d" font-size="13">administrator:</text>
                    <text x="231" y="275" text-anchor="middle" fill="#10244d" font-size="13">approve or reject</text>
                    </svg>
            </div>
            <figcaption>
                <h3>3. How a message is checked</h3>
                <ol>
                    <li>A member sends a message.</li>
                    <li>DiSSENT checks it automatically for a file, a phone number, an email address, an ID number or a bank account number (IBAN).</li>
                    <li>If none is found, the message is delivered at once.</li>
                    <li>If one is found, the message waits for an administrator who is not part of the conversation.</li>
                    <li>Approved messages are delivered. Rejected messages are never shown to the receiver.</li>
                </ol>
            </figcaption>
        </figure>
    </section>

    <section
        class="about-section"
        aria-labelledby="reviewHeading"
    >
        <h2 id="reviewHeading">Why blockchain?</h2>

        <p>
            When a report is approved, DiSSENT records its fingerprint (a hash)
            on a Hyperledger Fabric blockchain. A blockchain is a shared record
            that several computers keep together, so one person cannot quietly
            change or delete an entry. If anyone later edits the report, its
            fingerprint no longer matches, and the Verification page shows it.
        </p>

        <p>
            This proves the text has not changed since it was recorded. It does
            not prove that the report is true: that is what editorial review is
            for. Blockchain records cannot be deleted, so only a fingerprint is
            stored there, never the report itself or personal details.
        </p>

        <p>
            DiSSENT uses Hyperledger Fabric, Kafka and backend
            services to support parts of this workflow. Its messaging
            pipeline includes encryption and signature verification,
            but it is not an end-to-end encrypted messaging service.
        </p>
    </section>

    <!-- Media organisations are listed on the Media Centre page only. -->

    <section
        class="about-section about-cta"
        aria-labelledby="joinHeading"
    >
        <h2 id="joinHeading">Get Involved</h2>

        <p>
            Explore the media centre, join the platform or contact us
            about collaboration.
        </p>

        <div class="about-actions">
            <?php if ($is_logged_in): ?>
                <a href="dashboard.php" class="btn-primary">
                    Go to Dashboard
                </a>
            <?php else: ?>
                <a href="register.php" class="btn-primary">
                    Create an account
                </a>
            <?php endif; ?>

            <a href="contact_us.php" class="btn-secondary">
                Contact Us
            </a>
        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>