<?php

//Ginagamit nito ang session_destroy() function para burahin ang lahat ng session variables
session_start();
session_destroy();
header("Location: index.php");
exit();
