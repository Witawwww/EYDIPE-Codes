<?php
/**
 * VoltHub — logout.php
 */
require 'db.php';
session_start();

session_unset();
session_destroy();

redirect('login_reg.php');
