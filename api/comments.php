<?php
session_start();
require_once '../config/config.php';
header('Content-Type: application/json');
if(!isLoggedIn()){ echo json_encode(['success'=>false,'error'=>'Login required']); exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){ echo json_encode(['success'=>false,'error'=>'Invalid request']); exit;}

$input=json_decode(file_get_contents('php://input'),true);
$poem_id=intval($input['poem_id']??0);
$content=trim($input['content']??'');

if(!$poem_id || !$content){ echo json_encode(['success'=>false,'error'=>'Missing data']); exit;}

$db=(new Database())->getConnection();
$stmt=$db->prepare("INSERT INTO comments (user_id,poem_id,content) VALUES (:u,:p,:c)");
$stmt->bindParam(':u',$_SESSION['user_id']);
$stmt->bindParam(':p',$poem_id);
$stmt->bindParam(':c',$content);

if($stmt->execute()){
    echo json_encode(['success'=>true,'comment'=>['username'=>$_SESSION['username'],'content'=>$content,'created_at'=>date('Y-m-d H:i:s')]]);
}else echo json_encode(['success'=>false,'error'=>'Failed to post comment']);
