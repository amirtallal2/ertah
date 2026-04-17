<?php
/**
 * Legacy Offers page
 * Redirected to unified promo-codes management page.
 */

require_once '../init.php';
requireLogin();

redirect('promo-codes.php');
exit;
