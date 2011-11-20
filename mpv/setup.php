<?php


function api_mpv_autoload($class_name)
{
    if (file_exists(API_ROOT . "/projects/mpv/classes/$class_name.php"))
		require_once API_ROOT . "/projects/mpv/classes/$class_name.php";
}
spl_autoload_register('api_mpv_autoload');

?>
