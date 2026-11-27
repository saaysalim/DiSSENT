<?php
session_start();

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

$is_logged_in = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="DiSSENT is a cross-border collaboration platform for exiled and displaced journalists: submit reports for editorial review, message media teams and check report integrity."
    >

    <title>DiSSENT - Cross-border Exile Journalist Collaboration Platform</title>

    <link rel="stylesheet" href="../assets/css/base.css?v=5">
    <link rel="stylesheet" href="../assets/css/home.css?v=2">
</head>

<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main id="main-content" tabindex="-1">

    <!-- Hero -->
    <section class="page-hero page-hero--alt page-hero--tall" aria-labelledby="homeHeading">
        <div>
            <h1 id="homeHeading">
                Cross-border Exile Journalist Collaboration Platform
            </h1>

            <p>
                A shared workspace for journalists, including refugee
                and displaced reporters, to submit stories, communicate
                with media teams and follow editorial review.
            </p>

            <div class="page-hero__actions">
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php" class="btn-primary">
                        Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="register.php" class="btn-primary">
                        Create an account
                    </a>
                <?php endif; ?>

                <a href="about.php" class="btn-secondary">
                    Learn More
                </a>
            </div>
        </div>
    </section>

    <!-- What DiSSENT is (the features list lives on About; organisations on Media Centre) -->
    <section class="home-intro" aria-labelledby="aboutHeading">
        <div class="home-intro__text">
            <h2 id="aboutHeading">What DiSSENT is</h2>
            <p>
                DiSSENT is a shared workspace for journalists who work across borders,
                including reporters living in exile. You can submit reports for editorial
                review, message other members, and check that a published report has not
                been changed.
            </p>
            <p><a href="about.php">How DiSSENT works</a></p>
        </div>
        <div class="home-intro__picture">
            <svg class="home-illustration" viewBox="0 0 320 200" width="320" height="200" role="img" aria-labelledby="illBorderTitle">
                    <title id="illBorderTitle">Two journalists on different sides of a border, sending messages to each other</title>
                    <rect x="0" y="0" width="320" height="200" rx="18" fill="#eef3fb"/>
                    <line x1="160" y1="18" x2="160" y2="182" stroke="#8a9ab5" stroke-width="3" stroke-dasharray="8 8"/>
                    <circle cx="78" cy="78" r="20" fill="#10244d"/>
                    <path d="M44 150 q34 -52 68 0 z" fill="#10244d"/>
                    <circle cx="242" cy="78" r="20" fill="#285bb5"/>
                    <path d="M208 150 q34 -52 68 0 z" fill="#285bb5"/>
                    <path d="M104 62 q56 -46 112 0" fill="none" stroke="#2f8f5b" stroke-width="3" stroke-dasharray="2 7" stroke-linecap="round"/>
                    <rect x="112" y="96" width="44" height="28" rx="8" fill="#ffffff" stroke="#10244d" stroke-width="2"/>
                    <path d="M120 124 l-4 10 l12 -10" fill="#ffffff" stroke="#10244d" stroke-width="2" stroke-linejoin="round"/>
                    <line x1="120" y1="106" x2="148" y2="106" stroke="#10244d" stroke-width="2"/>
                    <line x1="120" y1="114" x2="140" y2="114" stroke="#10244d" stroke-width="2"/>
                    <rect x="166" y="120" width="44" height="28" rx="8" fill="#ffffff" stroke="#285bb5" stroke-width="2"/>
                    <path d="M202 148 l4 10 l-12 -10" fill="#ffffff" stroke="#285bb5" stroke-width="2" stroke-linejoin="round"/>
                    <line x1="174" y1="130" x2="202" y2="130" stroke="#285bb5" stroke-width="2"/>
                    <line x1="174" y1="138" x2="194" y2="138" stroke="#285bb5" stroke-width="2"/>
                </svg>
        </div>
    </section>

    <!-- Who it is for -->
    <section class="home-audience" aria-labelledby="audienceHeading">
        <h2 id="audienceHeading">Who it is for</h2>
        <ul class="home-audience__list">
            <li><strong>Exiled and displaced journalists</strong> who want to keep reporting and stay in touch with newsrooms.</li>
            <li><strong>Media organisations</strong> looking for trusted reports and contributors from other countries.</li>
            <li><strong>Fact checkers, researchers and NGOs</strong> who need to know a report has not changed since it was published.</li>
        </ul>
    </section>

    <!-- Get started in 3 steps -->
    <section class="workflow-section" aria-labelledby="workflowHeading">
        <h2 id="workflowHeading">Get started in 3 steps</h2>

        <div class="home-steps-picture">
            <svg class="home-illustration" viewBox="0 0 320 200" width="320" height="200" role="img" aria-labelledby="illReviewTitle">
                    <title id="illReviewTitle">A report goes to a reviewer, gets a green tick, and is published</title>
                    <rect x="0" y="0" width="320" height="200" rx="18" fill="#eef3fb"/>
                    <rect x="22" y="46" width="70" height="92" rx="6" fill="#ffffff" stroke="#10244d" stroke-width="2.5"/>
                    <line x1="34" y1="66" x2="80" y2="66" stroke="#10244d" stroke-width="2.5"/>
                    <line x1="34" y1="80" x2="80" y2="80" stroke="#8a9ab5" stroke-width="2"/>
                    <line x1="34" y1="92" x2="74" y2="92" stroke="#8a9ab5" stroke-width="2"/>
                    <line x1="34" y1="104" x2="78" y2="104" stroke="#8a9ab5" stroke-width="2"/>
                    <line x1="34" y1="116" x2="66" y2="116" stroke="#8a9ab5" stroke-width="2"/>
                    <path d="M100 92 h22" stroke="#526c96" stroke-width="3" stroke-linecap="round"/>
                    <path d="M116 84 l9 8 l-9 8" fill="none" stroke="#526c96" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="160" cy="92" r="30" fill="#ffffff" stroke="#10244d" stroke-width="2.5"/>
                    <circle cx="160" cy="84" r="9" fill="#10244d"/>
                    <path d="M144 110 q16 -20 32 0" fill="#10244d"/>
                    <circle cx="182" cy="116" r="12" fill="#2f8f5b"/>
                    <path d="M176 116 l4 4 l8 -8" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M198 92 h22" stroke="#526c96" stroke-width="3" stroke-linecap="round"/>
                    <path d="M214 84 l9 8 l-9 8" fill="none" stroke="#526c96" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <rect x="232" y="52" width="70" height="82" rx="8" fill="#10244d"/>
                    <rect x="242" y="64" width="50" height="22" rx="3" fill="#90c7ff"/>
                    <line x1="242" y1="98" x2="292" y2="98" stroke="#ffffff" stroke-width="2"/>
                    <line x1="242" y1="110" x2="282" y2="110" stroke="#ffffff" stroke-width="2"/>
                    <line x1="242" y1="122" x2="288" y2="122" stroke="#ffffff" stroke-width="2"/>
                    <text x="57" y="164" text-anchor="middle" font-size="13" fill="#10244d" font-family="Arial, Helvetica, sans-serif">Report</text>
                    <text x="160" y="164" text-anchor="middle" font-size="13" fill="#10244d" font-family="Arial, Helvetica, sans-serif">Review</text>
                    <text x="267" y="164" text-anchor="middle" font-size="13" fill="#10244d" font-family="Arial, Helvetica, sans-serif">Published</text>
                </svg>
        </div>

        <div class="workflow-container">
            <a href="register.php" class="workflow-card">
                <h3>1. Create an account</h3>
                <p>It takes about a minute and your account works at once.</p>
                <span class="card-arrow" aria-hidden="true">Open &rarr;</span>
            </a>

            <div class="workflow-arrow" aria-hidden="true">→</div>

            <a href="reports.php" class="workflow-card">
                <h3>2. Submit a report</h3>
                <p>Add a title, a category and your text, and optionally one PDF.</p>
                <span class="card-arrow" aria-hidden="true">Open &rarr;</span>
            </a>

            <div class="workflow-arrow" aria-hidden="true">→</div>

            <a href="media_center.php" class="workflow-card">
                <h3>3. Follow the review</h3>
                <p>An administrator reviews it. Approved reports appear in the Media Centre.</p>
                <span class="card-arrow" aria-hidden="true">Open &rarr;</span>
            </a>
        </div>

        <div class="home-cta">
            <?php if ($is_logged_in): ?>
                <a href="dashboard.php" class="btn-primary">Go to Dashboard</a>
            <?php else: ?>
                <a href="register.php" class="btn-primary">Create an account</a>
            <?php endif; ?>
        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>