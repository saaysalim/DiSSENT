<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

$nav_links = array(
    "home.php" => "Home",
    "about.php" => "About",
    "media_center.php" => "Media Center",
    "messaging.php" => "Messaging",
    "reports.php" => "Reports",
    "verification.php" => "Verification",
    "dashboard.php" => "Dashboard",
    "contact_us.php" => "Contact"
);

if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'){
    $nav_links["admin.php"] = "Admin";
}
?>

<header>

    <div class="logo">
        <a href="home.php">DiSSENT</a>
    </div>

    <button type="button" class="nav-toggle" aria-label="Toggle menu" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <nav>
        <ul>
            <?php foreach($nav_links as $link => $label) { ?>
                <li>
                    <a href="<?php echo $link; ?>" <?php echo $current_page == $link ? 'class="active"' : ''; ?>>
                        <?php echo $label; ?>
                    </a>
                </li>
            <?php } ?>
        </ul>
    </nav>

    <div class="header-right">

        <form action="media_center.php" method="GET" class="header-search">
            <input
                type="text"
                name="q"
                placeholder="Search..."
                value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
        </form>

        <select class="language-selector">
            <option>English</option>
            <option>Arabic</option>
            <option>French</option>
            <option>German</option>
            <option>Spanish</option>
            <option>Farsi</option>
        </select>

        <?php if(isset($_SESSION['fullname'])) { ?>

            <a href="profile.php" class="login-btn">
                <?php echo htmlspecialchars($_SESSION['fullname']); ?>
            </a>

            <a href="logout.php" class="login-btn">
                Logout
            </a>

        <?php } else { ?>

            <a href="login.php" class="login-btn">
                Login
            </a>

        <?php } ?>

    </div>

</header>
