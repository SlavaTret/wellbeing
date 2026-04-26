<?php
$dsn = 'pgsql:host=aws-1-us-east-2.pooler.supabase.com;port=6543;dbname=postgres';
$user = 'postgres.qtcazurbyzuqczljyppp';
$pass = 'qR&mf%&6_Vx&h$J';

$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$now = time();

$companies = [
    [
        'code' => 'epam',
        'name' => 'EPAM',
        'logo_url' => '/assets/companies/epam.svg',
        'primary_color' => '#FECB00',
        'secondary_color' => '#1A1A1A',
        'accent_color' => '#FFF8DC',
    ],
    [
        'code' => 'pumb',
        'name' => 'ПУМБ',
        'logo_url' => '/assets/companies/pumb.svg',
        'primary_color' => '#E30613',
        'secondary_color' => '#A50410',
        'accent_color' => '#FDECEE',
    ],
    [
        'code' => 'monobank',
        'name' => 'monobank',
        'logo_url' => '/assets/companies/monobank.svg',
        'primary_color' => '#000000',
        'secondary_color' => '#333333',
        'accent_color' => '#F5F5F5',
    ],
];

$stmt = $pdo->prepare("INSERT INTO company
  (code, name, logo_url, primary_color, secondary_color, accent_color, is_active, created_at, updated_at)
  VALUES (?, ?, ?, ?, ?, ?, true, ?, ?)
  ON CONFLICT (code) DO UPDATE SET
    name = EXCLUDED.name,
    logo_url = EXCLUDED.logo_url,
    primary_color = EXCLUDED.primary_color,
    secondary_color = EXCLUDED.secondary_color,
    accent_color = EXCLUDED.accent_color,
    updated_at = EXCLUDED.updated_at");

foreach ($companies as $c) {
    $stmt->execute([
        $c['code'], $c['name'], $c['logo_url'],
        $c['primary_color'], $c['secondary_color'], $c['accent_color'],
        $now, $now,
    ]);
    echo "Seeded: {$c['name']}\n";
}

// Link the existing test user (test@wellbeing.com) to EPAM
$pdo->exec("UPDATE \"user\" SET company_id = (SELECT id FROM company WHERE code = 'epam') WHERE email = 'test@wellbeing.com'");
echo "Linked test user to EPAM\n";
