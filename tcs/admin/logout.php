<?php
session_start();
session_destroy();
header('Location: /tcs/admin/admin-login.php');
exit;
