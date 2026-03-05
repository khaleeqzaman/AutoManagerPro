<?php
session_start();
session_unset();
session_destroy();

// Redirect to login
header('Location: /car-showroom/index.php');
exit;