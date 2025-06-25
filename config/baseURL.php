<?php 

if(!defined('BASE_URL')) {
    define('BASE_URL', 'https://localhost/git-project/404feelingfound-preview/');
}

function base_url($path = '') {
    return BASE_URL . ltrim($path, '/');
}


?>