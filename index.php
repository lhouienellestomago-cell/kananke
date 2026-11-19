<?php
session_start();

if (($_SESSION["loggedIn"] ?? false) !== true) {
    header("Location: login_page.php");
    exit;
}

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/cart_helper.php";

$user_id = (int)($_SESSION["user_id"] ?? 0);
if ($user_id <= 0) {
    die("User session not found. Please log in again.");
}

// Keep the existing unread admin-reply notification feature.
$unreadCount = 0;
try {
    $unreadStmt = $conn->prepare(
        "SELECT COUNT(*) AS unread_count
         FROM support_replies r
         INNER JOIN support_messages s ON s.id = r.support_id
         WHERE s.user_id = ?
           AND r.sender_role = 'admin'
           AND r.read_at IS NULL"
    );
    $unreadStmt->bind_param("i", $user_id);
    $unreadStmt->execute();
    $unreadCount = (int)($unreadStmt->get_result()->fetch_assoc()["unread_count"] ?? 0);
    $unreadStmt->close();
} catch (Throwable $e) {
    error_log("Dashboard unread count error: " . $e->getMessage());
}

$isAdmin = (($_SESSION["role"] ?? "user") === "admin");
$userEmail = (string)($_SESSION["user_email"] ?? "Customer");
$displayName = trim((string)($_SESSION["name"] ?? $_SESSION["full_name"] ?? ""));
if ($displayName === "") {
    $displayName = $userEmail !== "Customer" ? ucfirst(strstr($userEmail, "@", true) ?: $userEmail) : "Customer";
}
$cartCount = cart_item_count();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#f5ae00">
<title>Dashboard | Kananke Food Hauz</title>
<style>
:root{--gold:#f5b400;--gold-dark:#e5a300;--orange:#f28b28;--ink:#292929;--muted:#92929d;--page:#f5f5f6;--line:#eeeeef;--white:#fff;--shadow:0 8px 26px rgba(31,31,31,.045)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:#fffaf1;color:var(--ink);font-family:Arial,Helvetica,sans-serif}.app{width:min(1500px,100%);min-height:100vh;margin:auto;background:#fff;display:grid;grid-template-columns:220px minmax(0,1fr) 270px;box-shadow:0 0 50px rgba(0,0,0,.035)}
a{color:inherit;text-decoration:none}button{font:inherit}.sidebar{padding:30px 18px 24px;background:#fff;border-right:1px solid #f3f3f3;display:flex;flex-direction:column;min-height:100vh}.brand{font-size:23px;font-weight:900;letter-spacing:-.8px;margin:8px 8px 42px;white-space:nowrap}.brand span{color:var(--gold)}.nav-label{font-size:10px;text-transform:uppercase;letter-spacing:1.4px;color:#b3b3bb;font-weight:700;margin:0 12px 12px}.nav{display:grid;gap:8px}.nav a{min-height:43px;display:flex;align-items:center;gap:12px;padding:0 13px;border-radius:11px;color:#92929d;font-size:13px;font-weight:600;transition:.2s}.nav a .ico{width:20px;text-align:center;font-size:17px}.nav a:hover,.nav a.active{background:var(--gold);color:#fff;box-shadow:0 5px 13px rgba(245,180,0,.2)}.nav a .count{margin-left:auto;background:#e94f4f;color:#fff;border-radius:20px;font-size:10px;min-width:18px;text-align:center;padding:3px 5px}.side-spacer{flex:1;min-height:30px}.promo{background:linear-gradient(140deg,#f4b400,#ffd75c);border-radius:14px;padding:17px 15px;color:#fff;position:relative;overflow:hidden}.promo:after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;background:rgba(255,255,255,.2);right:-38px;bottom:-55px}.promo strong{display:block;font-size:13px;line-height:1.45;max-width:145px}.promo small{display:block;font-size:10px;line-height:1.5;margin:7px 0 12px;color:#fff9}.promo a{display:inline-block;background:#fff;color:#444;padding:8px 13px;border-radius:7px;font-size:11px;font-weight:700}.logout{margin-top:16px;color:#92929d;font-size:12px;padding:12px 13px}.main{background:var(--page);padding:30px 24px 42px;min-width:0}.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:23px}.welcome h1{font-size:23px;margin:0 0 6px;font-weight:800;letter-spacing:-.5px}.welcome p{margin:0;color:var(--muted);font-size:12px}.search{height:42px;width:min(320px,48%);background:#fff;border:1px solid #f0f0f0;border-radius:12px;display:flex;align-items:center;gap:9px;padding:0 13px;color:var(--gold)}.search input{border:0;outline:0;background:transparent;width:100%;font-size:12px;color:#555}.banner{height:150px;border-radius:15px;background:linear-gradient(105deg,#f5b400,#ffc928);overflow:hidden;position:relative;padding:28px 30px;color:#fff;display:flex;align-items:center;margin-bottom:26px}.banner:before,.banner:after{content:"";position:absolute;border:20px solid rgba(255,255,255,.17);border-radius:50%;width:160px;height:160px;right:22%;top:-115px}.banner:after{width:220px;height:220px;right:-60px;top:65px;border-width:24px}.banner-copy{position:relative;z-index:1;max-width:62%}.banner h2{font-size:21px;line-height:1.25;margin:0 0 8px}.banner p{font-size:11px;line-height:1.6;margin:0;color:#fff9;max-width:390px}.banner-tag{display:inline-block;margin-top:12px;background:#fff;color:#d99b00;padding:7px 12px;border-radius:7px;font-size:10px;font-weight:800}.banner-art{position:absolute;right:28px;bottom:-8px;font-size:100px;filter:drop-shadow(0 8px 8px #9a680033)}.section-head{display:flex;align-items:center;justify-content:space-between;margin:0 0 13px}.section-head h2{font-size:15px;margin:0}.section-head a{font-size:11px;color:#dca400;font-weight:700}.categories{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px;margin-bottom:26px}.category{background:#fff;border-radius:12px;min-height:94px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;box-shadow:var(--shadow);transition:.2s}.category:hover{transform:translateY(-3px);box-shadow:0 9px 20px #0000000c}.category .emoji{font-size:27px}.category span:last-child{font-size:11px;color:#8e8e98;font-weight:600}.products{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:26px}.product{background:#fff;border-radius:13px;padding:12px;min-width:0;box-shadow:var(--shadow);position:relative}.product-visual{height:105px;border-radius:10px;background:#fff9e8;display:flex;align-items:center;justify-content:center;font-size:55px;margin-bottom:11px}.product h3{font-size:12px;margin:0 0 5px}.product p{font-size:10px;color:var(--muted);margin:0 0 10px;line-height:1.4}.product-bottom{display:flex;align-items:center;justify-content:space-between;gap:5px}.price{font-size:13px;font-weight:800}.price small{font-size:9px;color:var(--muted);font-weight:400}.add{width:29px;height:29px;border-radius:8px;background:var(--gold);color:#fff;display:grid;place-items:center;font-size:19px;font-weight:700}.recent{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.recent-card{background:#fff;border-radius:12px;padding:14px;display:flex;align-items:center;gap:10px;box-shadow:var(--shadow)}.recent-icon{width:40px;height:40px;border-radius:10px;background:#fff5d8;display:grid;place-items:center;font-size:21px;flex-shrink:0}.recent-card strong{font-size:11px;display:block}.recent-card small{font-size:10px;color:var(--muted)}.rightbar{padding:30px 18px 25px;background:#fff;border-left:1px solid #f3f3f3;min-width:0}.right-top{display:flex;align-items:center;justify-content:flex-end;gap:16px;margin-bottom:30px}.icon-btn{position:relative;border:0;background:none;font-size:18px;color:#444;padding:3px}.dot{position:absolute;width:6px;height:6px;background:var(--gold);border-radius:50%;top:0;right:0}.avatar{width:37px;height:37px;border-radius:12px;background:#f5e9d0;color:#a77a00;display:grid;place-items:center;font-weight:800;font-size:14px;margin-left:8px}.right-title{font-size:15px;margin:0 0 13px}.customer-card{border-radius:13px;background:linear-gradient(120deg,#f18a2b,#f6b400);padding:16px;color:#fff;margin-bottom:24px;position:relative;overflow:hidden}.customer-card small{display:block;color:#fffC;font-size:10px;margin-bottom:7px}.customer-card strong{font-size:14px;display:block;overflow-wrap:anywhere}.customer-card p{font-size:10px;color:#fffD;margin:9px 0 0;line-height:1.5}.right-section{margin-bottom:23px}.right-section h3{font-size:14px;margin:0 0 12px}.quick-link{display:flex;align-items:center;gap:10px;padding:11px 0;border-bottom:1px solid var(--line);font-size:11px;color:#555}.quick-link:last-child{border-bottom:0}.quick-link .qicon{width:30px;height:30px;background:#fff5d8;border-radius:9px;display:grid;place-items:center;font-size:15px}.quick-link small{display:block;color:#aaa;font-size:9px;margin-top:3px}.support-box{background:#fffaf0;border:1px solid #f8e8bd;border-radius:12px;padding:13px;margin-top:23px}.support-box strong{font-size:11px}.support-box p{font-size:10px;color:#888;line-height:1.5;margin:6px 0 10px}.support-box a{display:block;text-align:center;background:var(--gold);color:#fff;padding:9px;border-radius:8px;font-size:10px;font-weight:700}.mobile-menu{display:none}

/* FLOATING CART BUTTON */
.floating-cart{
    position:fixed;
    right:24px;
    bottom:24px;
    z-index:999;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    min-height:54px;
    padding:0 21px;
    border-radius:999px;
    background:linear-gradient(135deg,#f5b400,#f28b28);
    color:#493500;
    font-size:14px;
    font-weight:800;
    box-shadow:0 7px 22px rgba(160,105,0,.30);
    transition:transform .2s ease,box-shadow .2s ease;
}
.floating-cart:hover{transform:translateY(-3px);box-shadow:0 10px 26px rgba(160,105,0,.38)}
.floating-cart .cart-icon{font-size:21px;line-height:1}
.floating-cart .cart-count{
    display:inline-grid;
    place-items:center;
    min-width:22px;
    height:22px;
    padding:0 6px;
    border-radius:50px;
    background:#fff;
    color:#b87900;
    font-size:11px;
}
@media(max-width:1100px){.app{grid-template-columns:190px minmax(0,1fr) 235px}.sidebar{padding-inline:14px}.main{padding-inline:18px}.rightbar{padding-inline:14px}.products,.recent{gap:9px}.product{padding:9px}.product-visual{height:90px}}
@media(max-width:850px){.app{grid-template-columns:175px minmax(0,1fr)}.rightbar{grid-column:2;display:grid;grid-template-columns:1fr 1fr;gap:12px;border-left:0;border-top:1px solid #eee;padding:20px}.right-top{grid-column:1/-1;margin:0}.customer-card{margin:0}.support-box{margin:0}.sidebar{grid-row:1/3}.main{padding:24px 18px}.banner{height:140px}}
@media(max-width:600px){.app{display:flex;flex-direction:column}.sidebar{min-height:auto;padding:14px 15px;border-right:0;border-bottom:1px solid #eee}.brand{margin:0 0 12px;font-size:20px}.nav-label,.promo,.side-spacer,.logout{display:none}.nav{display:flex;overflow-x:auto;gap:7px;padding-bottom:2px}.nav a{white-space:nowrap;min-height:38px;padding:0 11px;font-size:11px}.nav a .ico{font-size:14px}.main{padding:20px 14px 25px}.topbar{align-items:flex-start;flex-direction:column;margin-bottom:17px}.welcome h1{font-size:21px}.search{width:100%}.banner{height:130px;padding:20px}.banner h2{font-size:17px}.banner p{font-size:10px;max-width:250px}.banner-art{font-size:68px;right:8px}.categories{gap:8px}.category{min-height:78px}.category .emoji{font-size:23px}.products{grid-template-columns:repeat(2,minmax(0,1fr))}.product-visual{height:90px}.recent{grid-template-columns:1fr}.rightbar{display:grid;grid-template-columns:1fr;padding:17px 14px;gap:14px}.right-top{grid-column:auto;justify-content:flex-end;margin:0}.customer-card{margin:0}.right-section{margin:0}.support-box{margin:0}}
</style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="index.php">KANANKE<span>.</span></a>
        <div class="nav-label">Menu</div>
        <nav class="nav" aria-label="Main navigation">
            <a class="active" href="index.php"><span class="ico">⌂</span>Dashboard</a>
            <a href="tray.php"><span class="ico">🍽</span>Food Order</a>
            <a href="bundle.php"><span class="ico">🍱</span>Food Bundles</a>
            <a href="additionals.php"><span class="ico">🥤</span>Additionals</a>
            <a href="order_history.php"><span class="ico">◷</span>Order History</a>
            <a href="contact_support.php"><span class="ico">▣</span>Messages<?php if($unreadCount>0): ?><span class="count"><?= $unreadCount>99?'99+':$unreadCount ?></span><?php endif; ?></a>
            <?php if($isAdmin): ?><a href="admin_dashboard.php"><span class="ico">⚙</span>Admin Panel</a><?php endif; ?>
        </nav>
        <div class="side-spacer"></div>
        <div class="promo"><strong>Hungry? Find your next favorite meal!</strong><small>Explore our food trays, bundles, and add-ons.</small><a href="tray.php">Browse Menu</a></div>
        <a class="logout" href="logout.php">↪ &nbsp; Log Out</a>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="welcome"><h1>Hello, <?= h($displayName) ?>!</h1><p>What would you like to order today?</p></div>
            <label class="search"><span>⌕</span><input id="quickSearch" type="search" placeholder="Search food and categories..." aria-label="Search food and categories"></label>
        </div>

        <section class="banner">
            <div class="banner-copy"><h2>Good Food, Great Moments!</h2><p>Make every gathering special with delicious food from Kananke Food Hauz. Browse our menu and find something to enjoy.</p><a class="banner-tag" href="tray.php">EXPLORE OUR MENU&nbsp; →</a></div>
            <div class="banner-art" aria-hidden="true">🍗</div>
        </section>

        <section>
            <div class="section-head"><h2>Food Categories</h2><a href="tray.php">View menu&nbsp; ›</a></div>
            <div class="categories">
                <a class="category searchable" href="tray.php"><span class="emoji">🍗</span><span>Food by Tray</span></a>
                <a class="category searchable" href="bundle.php"><span class="emoji">🍱</span><span>Food Bundles</span></a>
                <a class="category searchable" href="additionals.php"><span class="emoji">🥤</span><span>Additionals</span></a>
            </div>
        </section>

        <section>
            <div class="section-head"><h2>Order Favorites</h2><a href="tray.php">View all&nbsp; ›</a></div>
            <div class="products" id="favoriteProducts">
                <article class="product searchable"><div class="product-visual">🍗</div><h3>Food by Tray</h3><p>Perfect for sharing with family and friends.</p><div class="product-bottom"><span class="price">Explore menu</span><a class="add" href="tray.php" aria-label="Explore food by tray">+</a></div></article>
                <article class="product searchable"><div class="product-visual">🍱</div><h3>Food Bundles</h3><p>Delicious combinations for every occasion.</p><div class="product-bottom"><span class="price">Explore menu</span><a class="add" href="bundle.php" aria-label="Explore food bundles">+</a></div></article>
                <article class="product searchable"><div class="product-visual">🥤</div><h3>Additionals</h3><p>Complete your order with extra favorites.</p><div class="product-bottom"><span class="price">Explore menu</span><a class="add" href="additionals.php" aria-label="Explore additionals">+</a></div></article>
            </div>
        </section>

        <section>
            <div class="section-head"><h2>Quick Access</h2><a href="order_history.php">My orders&nbsp; ›</a></div>
            <div class="recent">
                <a class="recent-card" href="order_history.php"><span class="recent-icon">🧾</span><span><strong>Order History</strong><small>Review your previous orders</small></span></a>
                <a class="recent-card" href="contact_support.php"><span class="recent-icon">💬</span><span><strong>Contact Support</strong><small>We're here to help</small></span></a>
                <a class="recent-card" href="<?= $isAdmin ? 'admin_dashboard.php' : 'order.php' ?>"><span class="recent-icon">⭐</span><span><strong><?= $isAdmin ? 'Admin Dashboard' : 'Start an Order' ?></strong><small><?= $isAdmin ? 'Manage your store' : 'Choose something delicious' ?></small></span></a>
            </div>
        </section>
    </main>

    <aside class="rightbar">
        <div class="right-top"><a class="icon-btn" href="contact_support.php" title="Messages">▣<?php if($unreadCount>0): ?><i class="dot"></i><?php endif; ?></a><a class="icon-btn" href="order_history.php" title="Order history">◷</a><a class="icon-btn" href="<?= $isAdmin?'admin_dashboard.php':'logout.php' ?>" title="<?= $isAdmin?'Admin panel':'Log out' ?>">⚙</a><div class="avatar"><?= h(strtoupper(substr($displayName,0,1))) ?></div></div>
        <section class="right-section"><h2 class="right-title">Your Account</h2><div class="customer-card"><small>LOGGED IN AS</small><strong><?= h($displayName) ?></strong><p><?= h($userEmail) ?></p></div></section>
        <section class="right-section"><h3>Order Menu</h3>
            <a class="quick-link" href="tray.php"><span class="qicon">🍗</span><span>Food by Tray<small>Browse available food trays</small></span></a>
            <a class="quick-link" href="bundle.php"><span class="qicon">🍱</span><span>Food Bundles<small>Choose a meal bundle</small></span></a>
            <a class="quick-link" href="additionals.php"><span class="qicon">🥤</span><span>Additionals<small>Browse extra items</small></span></a>
        </section>
        <section class="right-section"><h3>Your Orders</h3><a class="quick-link" href="order_history.php"><span class="qicon">📋</span><span>View Order History<small>Check your order status</small></span></a></section>
        <div class="support-box"><strong>Need assistance?</strong><p>Have a question about your order? Send a message to our support team.</p><a href="contact_support.php">Contact Support<?= $unreadCount>0?' · '.$unreadCount.' unread':'' ?></a></div>
    </aside>
</div>

<a class="floating-cart" href="cart.php" aria-label="Open shopping cart, <?= $cartCount ?> items">
    <span class="cart-icon" aria-hidden="true">🛒</span>
    <span>My Cart</span>
    <span class="cart-count"><?= (int)$cartCount ?></span>
</a>

<script>
const quickSearch=document.getElementById('quickSearch');
quickSearch.addEventListener('input',function(){
 const q=this.value.trim().toLowerCase();
 document.querySelectorAll('.searchable').forEach(item=>{item.style.display=item.textContent.toLowerCase().includes(q)?'':'none';});
});
</script>
</body>
</html>
<?php $conn->close(); ?>
