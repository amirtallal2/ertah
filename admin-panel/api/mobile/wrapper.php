<?php
// Mock Environment
$_GET['action'] = 'send-otp';
$_SERVER['REQUEST_METHOD'] = 'POST';
require 'auth.php';
