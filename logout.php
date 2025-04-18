<?php
session_start();
session_regenerate_id(true); // Prevent session fixation
session_destroy();
header("Location: index.php");
exit;
?>