<?php
// includes/profile_check.php
$stmt = $pdo->prepare("SELECT name, student_id, contact, course FROM users WHERE id=?");
$stmt->execute([$user->id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

$required = ['name','student_id','contact','course'];
foreach ($required as $field) {
    if (empty($profile[$field])) {
        redirect('account.php?incomplete=1');
    }
}
?>