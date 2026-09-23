<?php
require __DIR__ . '/app/bootstrap.php';

unset($_SESSION['user_id']);
session_regenerate_id(true);
redirect('login.php');
