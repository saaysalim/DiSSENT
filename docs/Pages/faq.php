<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/content.php';

// Public page: no login needed. Questions come from faq_items() in backend/content.php.
$faq_sections = faq_items();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - DiSSENT</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/faq.css?v=1">
    <script src="../assets/JavaScript/faq.js?v=1" defer></script>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<section class="faq-hero">
    <h1>Frequently Asked Questions</h1>
    <p>Short answers about accounts, reports, verification, secure messaging and your privacy on DiSSENT.</p>
</section>

<main class="faq-page">

    <div class="faq-search" role="search">
        <label for="faqSearch">Search the questions</label>
        <input
            type="search"
            id="faqSearch"
            placeholder="For example: password, upload, rejected"
            autocomplete="off"
            aria-describedby="faqSearchStatus"
            hidden
        >
        <p id="faqSearchStatus" class="faq-search__status" aria-live="polite"></p>
    </div>

    <nav class="faq-topics" aria-label="FAQ topics">
        <ul>
            <?php foreach ($faq_sections as $faq_section): ?>
                <li>
                    <a href="#<?php echo e($faq_section['id']); ?>">
                        <?php echo e($faq_section['title']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <?php foreach ($faq_sections as $faq_section): ?>
        <section
            class="faq-section"
            id="<?php echo e($faq_section['id']); ?>"
            aria-labelledby="<?php echo e($faq_section['id']); ?>-title"
        >
            <h2 id="<?php echo e($faq_section['id']); ?>-title">
                <?php echo e($faq_section['title']); ?>
            </h2>

            <?php foreach ($faq_section['items'] as $faq_item): ?>
                <details class="faq-item">
                    <summary><?php echo e($faq_item['q']); ?></summary>

                    <div class="faq-answer">
                        <p><?php echo e($faq_item['a']); ?></p>

                        <?php if (!empty($faq_item['steps'])): ?>
                            <ol>
                                <?php foreach ($faq_item['steps'] as $faq_step): ?>
                                    <li><?php echo e($faq_step); ?></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>

                        <?php if (!empty($faq_item['links'])): ?>
                            <p class="faq-links">
                                <?php foreach ($faq_item['links'] as $faq_href => $faq_label): ?>
                                    <a href="<?php echo e($faq_href); ?>"><?php echo e($faq_label); ?></a>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>

    <p id="faqNoResults" class="faq-empty" hidden>
        No questions match your search. Try another word, or
        <a href="contact_us.php">contact the team</a>.
    </p>

    <aside class="faq-contact">
        <h2>Still need help?</h2>
        <p>If your question is not answered here, send us a message.</p>
        <a href="contact_us.php" class="btn-primary">Contact us</a>
    </aside>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
