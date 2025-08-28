<?php
session_start();
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
  if ($_SESSION['role'] === 'admin')  { header('Location: admin/painel_admin.php'); exit; }
  if ($_SESSION['role'] === 'basic')  { header('Location: vote/painel_basic.php'); exit; }
  if ($_SESSION['role'] === 'master') { header('Location: master/painel_master.php'); exit; }
}
header('Location: auth/login.php');
exit;
