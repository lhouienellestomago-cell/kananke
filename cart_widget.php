<?php
require_once __DIR__ . '/cart_helper.php';
$count = cart_item_count();
?>

<style>
#kananke-floating-cart {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 9999;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5b400;
    color: white;
    text-decoration: none;
    box-shadow: 0 7px 22px rgba(0,0,0,.22);
    font-size: 27px;
    transition: .2s;
}

#kananke-floating-cart:hover {
    background: #e5a300;
    transform: translateY(-3px);
}

#kananke-floating-cart .cart-count {
    position: absolute;
    top: -3px;
    right: -3px;
    min-width: 23px;
    height: 23px;
    padding: 0 5px;
    border: 2px solid white;
    border-radius: 20px;
    background: #e53935;
    color: white;
    font: bold 11px Arial;
    display: flex;
    align-items: center;
    justify-content: center;
}

@media(max-width:600px) {
    #kananke-floating-cart {
        right: 15px;
        bottom: 16px;
        width: 55px;
        height: 55px;
        font-size: 25px;
    }
}
</style>

<a id="kananke-floating-cart"
   href="cart.php"
   aria-label="Open shopping cart"
   title="My Cart">
    🛒

    <?php if ($count > 0): ?>
        <span class="cart-count">
            <?= $count > 99 ? '99+' : $count ?>
        </span>
    <?php endif; ?>
</a>
