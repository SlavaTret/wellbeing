<?php
// Standalone script — uses PDO directly, no Yii app needed
$dsn = 'pgsql:host=aws-1-us-east-2.pooler.supabase.com;port=6543;dbname=postgres';
$user = 'postgres.qtcazurbyzuqczljyppp';
$pass = 'qR&mf%&6_Vx&h$J';

$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$password = 'Test1234!';
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]);
$authKey = bin2hex(random_bytes(16));
$now = time();

$stmt = $pdo->prepare("INSERT INTO \"user\"
  (username, email, password_hash, auth_key, status, first_name, last_name, company, phone, accepted_terms, created_at, updated_at)
  VALUES (?, ?, ?, ?, 10, ?, ?, ?, ?, true, ?, ?)");

$stmt->execute([
    'test',
    'test@wellbeing.com',
    $passwordHash,
    $authKey,
    'Тестовий',
    'Користувач',
    'Wellbeing Corp',
    '+38 050 000 00 00',
    $now,
    $now,
]);

echo "Done!\nEmail: test@wellbeing.com\nPassword: $password\n";
