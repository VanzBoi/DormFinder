<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') abort_page(405,'Use the Log out button to end your session.');
if (current_user()) log_activity('auth.logout');
$_SESSION=[];
$cookie=session_get_cookie_params();
setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>$cookie['path'],'secure'=>$cookie['secure'],'httponly'=>true,'samesite'=>'Lax']);
session_destroy(); redirect('auth/login.php');
