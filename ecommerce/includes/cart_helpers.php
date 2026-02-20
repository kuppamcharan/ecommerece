<?php
// includes/cart_helpers.php

/**
 * Get or create the guest cart token cookie and return the token string.
 * Cookie name: cart_token. Lifetime: 30 days.
 */
function get_cart_token() {
    $name = 'cart_token';
    if (!empty($_COOKIE[$name])) {
        return $_COOKIE[$name];
    }
    // generate a secure random token
    $token = bin2hex(random_bytes(20));
    setcookie($name, $token, [
        'expires' => time() + 60*60*24*30, // 30 days
        'path' => '/ecommerce/',           // set to your app root
        'secure' => false,                 // set true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // also set in $_COOKIE for immediate availability
    $_COOKIE[$name] = $token;
    return $token;
}

/**
 * Clear the cart_token cookie (if you ever need to).
 */
function clear_cart_token() {
    setcookie('cart_token', '', time()-3600, '/ecommerce/');
    unset($_COOKIE['cart_token']);
}
