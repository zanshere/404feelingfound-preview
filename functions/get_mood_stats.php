<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Ambil data statistik mood
$moodStats = $pdo->query("
    SELECT 
        mood_value,
        COUNT(*) as count,
        (COUNT(*) * 100 / (SELECT COUNT(*) FROM mood_entries WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))) as percentage
    FROM mood_entries
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY mood_value
    ORDER BY mood_value
")->fetchAll(PDO::FETCH_ASSOC);

// Format data untuk response
$response = [];
$total = 0;

foreach ($moodStats as $stat) {
    $response[$stat['mood_value']] = [
        'count' => $stat['count'],
        'percentage' => round($stat['percentage'], 1)
    ];
    $total += $stat['count'];
}

// Pastikan semua mood value ada
for ($i = 1; $i <= 5; $i++) {
    if (!isset($response[$i])) {
        $response[$i] = ['count' => 0, 'percentage' => 0];
    }
}

$response['total'] = $total;

echo json_encode($response);
?>