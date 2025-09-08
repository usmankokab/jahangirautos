<?php
include __DIR__ . '/../config/db.php';

$inst = (int)$_GET['inst_id'];
if($inst){
  $stmt = $conn->prepare("
    UPDATE installments
    SET status='paid', paid_at=NOW()
    WHERE id=?
  ");
  $stmt->bind_param("i",$inst);
  $stmt->execute();
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;