<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['SCRIPT_NAME'] ?? '');
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = false;

// This controls the navigation link only.
// The admin page must still enforce require_admin().
if ($is_logged_in && function_exists('content_user_is_admin')) {
    try {
        $is_admin = content_user_is_admin();
    } catch (Throwable $exception) {
        error_log('DiSSENT: header admin permission check unavailable.');
    }
}

$nav_links = array(
    'home.php' => 'Home',
    'about.php' => 'About',
    'media_center.php' => 'Media Centre',
    'messaging.php' => 'Messaging',
    'reports.php' => 'Reports',
    'verification.php' => 'Verification',
    'dashboard.php' => 'Dashboard',
    'contact_us.php' => 'Contact'
);

if ($is_admin) {
    $nav_links['admin.php'] = 'Admin';
}

$search_input = $_GET['q'] ?? '';
$header_search = is_string($search_input) ? $search_input : '';

$fullname = $_SESSION['fullname'] ?? 'My profile';

if (!is_string($fullname) || trim($fullname) === '') {
    $fullname = 'My profile';
}

$header_escape = static function ($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};
?>

<header id="siteHeader">

    <div class="logo">
        <a href="home.php" aria-label="DiSSENT home">
            DiSSENT
        </a>
    </div>

    <button
        type="button"
        class="nav-toggle"
        aria-label="Open navigation menu"
        aria-expanded="false"
        aria-controls="primaryNavigation headerTools"
    >
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
    </button>

    <nav id="primaryNavigation" aria-label="Main navigation">
        <ul>
            <?php foreach ($nav_links as $link => $label): ?>
                <li>
                    <a
                        href="<?php echo $header_escape($link); ?>"
                        <?php if ($current_page === $link): ?>
                            class="active"
                            aria-current="page"
                        <?php endif; ?>
                    >
                        <?php echo $header_escape($label); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="header-right" id="headerTools">

        <form
            action="media_center.php"
            method="GET"
            class="header-search"
            role="search"
            aria-label="Search publications"
        >
            <input
                type="search"
                name="q"
                aria-label="Search publications"
                placeholder="Search publications..."
                maxlength="255"
                required
                pattern=".*\S.*"
                title="Enter a title, journalist, organisation or category."
                value="<?php echo $header_escape($header_search); ?>"
            >

            <button type="submit" aria-label="Search publications">
                <svg
                    viewBox="0 0 24 24"
                    width="20"
                    height="20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                    focusable="false"
                >
                    <circle cx="10.5" cy="10.5" r="6.5"></circle>
                    <path d="m16 16 4.5 4.5"></path>
                </svg>
            </button>
        </form>

        <details class="header-language-menu">
            <summary
                aria-label="Website languages"
                aria-controls="headerLanguageDropdown"
            >
                <svg
                    viewBox="0 0 24 24"
                    width="23"
                    height="23"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.6"
                    aria-hidden="true"
                    focusable="false"
                >
                    <circle cx="12" cy="12" r="9"></circle>
                    <ellipse cx="12" cy="12" rx="4" ry="9"></ellipse>
                    <path d="M3 12h18M5 6.5h14M5 17.5h14"></path>
                </svg>
            </summary>

            <div
                class="header-language-dropdown"
                id="headerLanguageDropdown"
            >

                <ul>
                    <li><span lang="en">English</span></li>
                    <li><span lang="fa" dir="rtl"> Farsi</span></li>
                    <li><span lang="fr"> French</span></li>
                    <li><span lang="es"> Spanish</span></li>
                    <li><span lang="ar" dir="rtl"> Arabic</span></li>
                    <li><span lang="de"> German</span></li>
                    <li><span lang="nl">Dutch</span></li>
                </ul>
            </div>
        </details>

        <?php if ($is_logged_in): ?>
            <a href="profile.php" class="login-btn header-account">
                <?php echo $header_escape($fullname); ?>
            </a>

            <a href="logout.php" class="login-btn header-logout">
                Logout
            </a>
        <?php else: ?>
            <a href="login.php" class="login-btn header-account">
                Login
            </a>

            <a href="register.php" class="login-btn">
                Get Started
            </a>
        <?php endif; ?>

    </div>
</header>

<script>
(function () {
    const header = document.getElementById('siteHeader');

    if (!header || header.dataset.navigationReady === 'true') {
        return;
    }

    const toggle = header.querySelector('.nav-toggle');
    const navigation = header.querySelector('#primaryNavigation');
    const tools = header.querySelector('#headerTools');
    const languages = header.querySelector('.header-language-menu');
    const languageToggle = languages
        ? languages.querySelector('summary')
        : null;
    const searchForm = header.querySelector('.header-search');
    const searchInput = searchForm
        ? searchForm.querySelector('input[name="q"]')
        : null;

    if (!toggle || !navigation || !tools) {
        return;
    }

    header.dataset.navigationReady = 'true';

    // Keep this breakpoint identical to the mobile rules in base.css.
    const mobile = window.matchMedia('(max-width: 900px)');

    function focusIsInsideMenu() {
        return navigation.contains(document.activeElement) ||
            tools.contains(document.activeElement);
    }

    function closeLanguages(restoreFocus) {
        if (!languages) {
            return;
        }

        languages.open = false;

        if (restoreFocus && languageToggle) {
            languageToggle.focus();
        }
    }

    function setMenu(open, restoreFocus) {
        header.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute(
            'aria-label',
            open ? 'Close navigation menu' : 'Open navigation menu'
        );

        if (!open) {
            closeLanguages(false);
        }

        if (restoreFocus) {
            toggle.focus();
        }
    }

    toggle.addEventListener('click', function () {
        if (!mobile.matches) {
            return;
        }

        const open = toggle.getAttribute('aria-expanded') === 'true';
        setMenu(!open, false);
    });

    header.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (languages && languages.open) {
            closeLanguages(true);
            event.preventDefault();
            return;
        }

        if (mobile.matches && header.classList.contains('nav-open')) {
            setMenu(false, true);
            event.preventDefault();
        }
    });

    document.addEventListener('click', function (event) {
        if (
            languages &&
            languages.open &&
            !languages.contains(event.target)
        ) {
            closeLanguages(languages.contains(document.activeElement));
        }

        if (
            mobile.matches &&
            header.classList.contains('nav-open') &&
            !header.contains(event.target)
        ) {
            setMenu(false, focusIsInsideMenu());
        }
    });

    function handleScreenChange() {
        const focusInside = focusIsInsideMenu();
        const toggleHadFocus = document.activeElement === toggle;

        setMenu(false, mobile.matches && focusInside);

        if (!mobile.matches && toggleHadFocus) {
            const homeLink = header.querySelector('.logo a');

            if (homeLink) {
                homeLink.focus();
            }
        }
    }

    if (typeof mobile.addEventListener === 'function') {
        mobile.addEventListener('change', handleScreenChange);
    } else {
        mobile.addListener(handleScreenChange);
    }

    if (searchForm && searchInput) {
        searchInput.addEventListener('input', function () {
            searchInput.setCustomValidity('');
        });

        searchForm.addEventListener('submit', function (event) {
            searchInput.value = searchInput.value.trim();

            if (searchInput.value === '') {
                event.preventDefault();
                searchInput.setCustomValidity('Enter something to search for.');
                searchInput.reportValidity();
            }
        });
    }

    setMenu(false, false);
    header.classList.add('menu-ready');
})();
</script>