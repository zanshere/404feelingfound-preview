
<?php
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';
require_once __DIR__ . '/../includes/helpers.php'; 


// Only admin and guru can access this report
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'guru') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

// Mood emoji mapping
$mood_emojis = [
    1 => '😭', // Very sad
    2 => '😞', // Sad
    3 => '😐', // Neutral
    4 => '😊', // Happy
    5 => '😁'  // Very happy
];

// Mood color mapping
$mood_colors = [
    1 => '#e74c3c', // Red
    2 => '#e67e22', // Orange
    3 => '#f1c40f', // Yellow
    4 => '#2ecc71', // Green
    5 => '#3498db'  // Blue
];

// Function to adjust color brightness

// Get mood data for the last 7 days
$stmt = $pdo->prepare("
    SELECT 
        mood_value,
        COUNT(*) as count,
        DATE(date) as day
    FROM mood_entries
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY mood_value, DATE(date)
    ORDER BY DATE(date), mood_value
");
$stmt->execute();
$rawMoodData = $stmt->fetchAll();

// Process mood data for chart
$moodData = [];
$totalMoods = 0;
$dailyTotals = [];

// Initialize daily totals
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dailyTotals[$date] = 0;
}

// Process raw data
foreach ($rawMoodData as $row) {
    $dailyTotals[$row['day']] += $row['count'];
    $totalMoods += $row['count'];
}

// Prepare chart data
foreach ($mood_emojis as $value => $emoji) {
    $moodData[$value] = [
        'emoji' => $emoji,
        'color' => $mood_colors[$value],
        'label' => '',
        'count' => 0,
        'percentage' => 0
    ];
    
    // Calculate total count for this mood value
    foreach ($rawMoodData as $row) {
        if ($row['mood_value'] == $value) {
            $moodData[$value]['count'] += $row['count'];
        }
    }
    
    // Calculate percentage
    if ($totalMoods > 0) {
        $moodData[$value]['percentage'] = round(($moodData[$value]['count'] / $totalMoods) * 100);
    }
    
    // Set labels
    switch($value) {
        case 1: $moodData[$value]['label'] = 'Sangat Buruk'; break;
        case 2: $moodData[$value]['label'] = 'Buruk'; break;
        case 3: $moodData[$value]['label'] = 'Biasa'; break;
        case 4: $moodData[$value]['label'] = 'Baik'; break;
        case 5: $moodData[$value]['label'] = 'Sangat Baik'; break;
    }
}

// Get mood data by class
$stmt = $pdo->prepare("
    SELECT 
        u.kelas,
        m.mood_value,
        COUNT(*) as count
    FROM mood_entries m
    JOIN users u ON m.user_id = u.id
    WHERE m.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY u.kelas, m.mood_value
    ORDER BY u.kelas, m.mood_value
");
$stmt->execute();
$classMoodData = $stmt->fetchAll();

// Organize by class
$moodByClass = [];
foreach ($classMoodData as $row) {
    if (!isset($moodByClass[$row['kelas']])) {
        $moodByClass[$row['kelas']] = [
            'total' => 0,
            'moods' => array_fill_keys(array_keys($mood_emojis), 0)
        ];
    }
    
    $moodByClass[$row['kelas']]['moods'][$row['mood_value']] = $row['count'];
    $moodByClass[$row['kelas']]['total'] += $row['count'];
}

// Calculate percentages for each class
foreach ($moodByClass as $kelas => &$data) {
    foreach ($data['moods'] as $value => $count) {
        $data['percentages'][$value] = $data['total'] > 0 ? round(($count / $data['total']) * 100) : 0;
    }
}
unset($data); // Break reference
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Mood Siswa</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <style>
        :root {
            --primary: #4361ee;
            --dark: #212529;
            --gray: #e9ecef;
            --light: #f8f9fa;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .section-title h2 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .section-title p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        /* Mood Chart Styles */
        .mood-chart-container {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            height: 350px;
            gap: 40px;
            margin: 40px 0;
        }
        
        .mood-bar {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100px;
        }
        
        .mood-bar-emoji {
            font-size: 3rem;
            margin-bottom: 20px;
            transition: var(--transition);
        }
        
        .mood-bar-emoji:hover {
            transform: scale(1.2) rotate(10deg);
        }
        
        .mood-bar-fill {
            width: 60px;
            background: linear-gradient(to top, var(--bar-color), var(--bar-color-light));
            border-radius: 8px 8px 0 0;
            position: relative;
            box-shadow: inset 0 -10px 20px rgba(0, 0, 0, 0.05);
            transition: height 1s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .mood-bar-fill::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100%;
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.3), transparent);
            border-radius: 8px 8px 0 0;
        }
        
        .mood-bar-value {
            position: absolute;
            top: -40px;
            left: 0;
            right: 0;
            text-align: center;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            font-size: 1.1rem;
        }
        
        .mood-bar-label {
            margin-top: 20px;
            font-size: 1rem;
            text-align: center;
            color: var(--dark);
            font-weight: 600;
        }
        
        .mood-bar-count {
            margin-top: 5px;
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .mood-total {
            text-align: center;
            margin-top: 40px;
            font-size: 1rem;
            color: var(--dark);
            padding: 15px;
            background: white;
            border-radius: 8px;
            display: inline-block;
            box-shadow: var(--card-shadow);
        }
        
        .mood-total strong {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* Class Mood Cards */
        .class-mood-section {
            margin-top: 60px;
        }
        
        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .class-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }
        
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.15);
        }
        
        .class-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray);
        }
        
        .class-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .class-total {
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .class-mood-bars {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .class-mood-item {
            text-align: center;
            flex: 1;
        }
        
        .class-mood-emoji {
            font-size: 1.8rem;
            margin-bottom: 8px;
        }
        
        .class-mood-percentage {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .class-mood-label {
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                color: black;
                font-size: 12pt;
            }
            
            .container {
                padding: 0;
                max-width: 100%;
            }
            
            .section-title h2 {
                font-size: 18pt;
                color: black;
            }
            
            .mood-chart-container {
                height: 250px;
                gap: 30px;
            }
            
            .class-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .class-card {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="section-title">
            <h2>Laporan Mood Siswa</h2>
            <p>Statistik mood siswa dalam 7 hari terakhir</p>
        </div>
        
        <!-- Mood Stats Section -->
        <section class="mood-stats">
            <div class="mood-chart-container">
                <?php foreach ($moodData as $value => $mood): ?>
                <div class="mood-bar fade-in">
                    <div class="mood-bar-emoji"><?= $mood['emoji'] ?></div>
                    <div class="mood-bar-fill" 
                         style="--bar-color: <?= $mood['color'] ?>; --bar-color-light: <?= adjustBrightness($mood['color'], 30) ?>; height: <?= $mood['percentage'] * 3 ?>px;">
                        <div class="mood-bar-value"><?= $mood['percentage'] ?>%</div>
                    </div>
                    <div class="mood-bar-label"><?= $mood['label'] ?></div>
                    <div class="mood-bar-count"><?= $mood['count'] ?> siswa</div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mood-total fade-in">
                Total <strong><?= $totalMoods ?></strong> mood check tercatat dalam seminggu terakhir
            </div>
        </section>
        
        <!-- Class Mood Section -->
        <section class="class-mood-section">
            <div class="section-title">
                <h2>Statistik Mood per Kelas</h2>
                <p>Distribusi mood siswa berdasarkan kelas</p>
            </div>
            
            <div class="class-grid">
                <?php foreach ($moodByClass as $kelas => $data): ?>
                <div class="class-card">
                    <div class="class-card-header">
                        <div class="class-name">Kelas <?= $kelas ?></div>
                        <div class="class-total"><?= $data['total'] ?> mood check</div>
                    </div>
                    
                    <div class="class-mood-bars">
                        <?php foreach ($mood_emojis as $value => $emoji): ?>
                        <div class="class-mood-item">
                            <div class="class-mood-emoji"><?= $emoji ?></div>
                            <div class="class-mood-percentage"><?= $data['percentages'][$value] ?>%</div>
                            <div class="class-mood-label"><?= $moodData[$value]['label'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <div style="margin-top: 40px; text-align: center;">
            <button onclick="window.print()" style="padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">Cetak Laporan</button>
        </div>
    </div>
    
    <script>
        // Simple animation for elements
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = 1;
                }, index * 200);
            });
        });
    </script>
</body>
</html>