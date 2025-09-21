<?php
include '../functions.php';
$pdo = dbConnect();

$course_name = $_GET['course'] ?? '';

header('Content-Type: application/json');

$result = null;
if($course_name){
    $stmt = $pdo->prepare("SELECT * FROM course WHERE c_name = :c_name LIMIT 1");
    $stmt->execute([':c_name'=>$course_name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
}

echo json_encode($result);
