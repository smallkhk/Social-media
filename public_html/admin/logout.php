<?php
require __DIR__ . '/../app/bootstrap.php';

unset($_SESSION['admin_id']);
session_regenerate_id(true);
redirect('admin/login.php');
