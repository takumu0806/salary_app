<?php
// dashboard.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$current_year = (int)date('Y');

// --- サマリーカード用のデータ取得 ---
$stmt_year = $pdo->prepare("SELECT SUM(total_amount) FROM monthly_salaries WHERE user_id = ? AND target_year = ?");
$stmt_year->execute([$user_id, $current_year]);
$year_total = $stmt_year->fetchColumn() ?: 0;

$stmt_avg = $pdo->prepare("SELECT AVG(total_amount) FROM monthly_salaries WHERE user_id = ? AND total_amount > 0");
$stmt_avg->execute([$user_id]);
$all_time_avg = $stmt_avg->fetchColumn() ?: 0;

// --- グラフ用のデータ取得 ---
$stmt = $pdo->prepare("SELECT target_year, target_month, total_amount FROM monthly_salaries WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$salaries = $stmt->fetchAll();

$chart_data_map = [];
$max_y = (int)date('Y');
$max_m = (int)date('n');

foreach ($salaries as $salary) {
    $y = (int)$salary['target_year'];
    $m = (int)$salary['target_month'];
    $chart_data_map["{$y}-{$m}"] = (int)$salary['total_amount'];
    
    if ($y > $max_y || ($y == $max_y && $m > $max_m)) {
        $max_y = $y;
        $max_m = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード - 給与計算アプリ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .summary-card { transition: transform 0.2s ease-in-out; }
        .summary-card:hover { transform: translateY(-3px); }
        .icon-circle { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        
        .chart-container { position: relative; height: 350px; width: 100%; }
        .btn-slide { transition: all 0.2s; width: 46px; }
        .btn-slide:disabled { opacity: 0.4; cursor: not-allowed; background-color: #f8f9fa; }
        
        .value-text { transition: opacity 0.2s ease; }

        /* --- 【修正】グラフぼかし（すりガラス）用のCSS --- */
        .chart-blur-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(248, 249, 250, 0.4); /* うすい白ベース */
            backdrop-filter: blur(8px); /* ★ これで「ぼかし」を入れる */
            -webkit-backdrop-filter: blur(8px); /* Safari対応 */
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: rgba(108, 117, 125, 0.8);
            z-index: 10;
            transition: opacity 0.4s ease; /* フワッと消える */
            pointer-events: auto; /* 非表示時は下のグラフを触らせない */
        }
        
        /* 表示モードの時はぼかしを消す */
        .chart-blur-overlay.is-visible {
            opacity: 0;
            pointer-events: none; /* 下のグラフを触れるようにする */
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php"><i class="bi bi-wallet2 me-2"></i>給与アプリ</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-house-door me-1"></i> ホーム</a></li>
                    <li class="nav-item"><a class="nav-link" href="add_salary.php"><i class="bi bi-pencil-square me-1"></i> 給与登録</a></li>
                    <li class="nav-item"><a class="nav-link" href="history.php"><i class="bi bi-clock-history me-1"></i> 履歴・詳細</a></li>
                    <li class="nav-item"><a class="nav-link" href="items.php"><i class="bi bi-gear me-1"></i> 項目管理</a></li>
                </ul>
                <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>ログアウト</a>
            </div>
        </div>
    </nav>

    <div class="container" style="max-width: 900px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0 fw-bold text-secondary">ダッシュボード</h2>
            <a href="add_salary.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-1"></i>新規登録</a>
        </div>

        <div class="d-flex gap-2 mb-4 flex-wrap">
            <button id="btn-toggle-summary" class="btn btn-outline-primary fw-bold shadow-sm" onclick="toggleSummary()">
                <i class="bi bi-eye me-1"></i>金額を表示
            </button>
            <button id="btn-toggle-chart" class="btn btn-outline-primary fw-bold shadow-sm" onclick="toggleChart()">
                <i class="bi bi-eye me-1"></i>グラフを表示
            </button>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm border-0 summary-card bg-primary text-white">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-circle bg-white text-primary me-3 shadow-sm">
                            <i class="bi bi-piggy-bank-fill"></i>
                        </div>
                        <div>
                            <h6 class="card-title mb-1 opacity-75"><?= $current_year ?>年の累計支給額</h6>
                            <h3 class="mb-0 fw-bold"><span id="val-year-total" class="value-text">------</span> <span class="fs-6 fw-normal">円</span></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm border-0 summary-card bg-success text-white">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon-circle bg-white text-success me-3 shadow-sm">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div>
                            <h6 class="card-title mb-1 opacity-75">平均月給 (全期間)</h6>
                            <h3 class="mb-0 fw-bold"><span id="val-all-avg" class="value-text">------</span> <span class="fs-6 fw-normal">円</span></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-5">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="fw-bold text-secondary mb-0"><i class="bi bi-bar-chart-line me-2"></i>給与推移</h5>
                <div class="d-flex align-items-center gap-2">
                    <button id="btn-latest" class="btn btn-sm btn-outline-secondary shadow-sm fw-bold px-3" onclick="goToLatest()" style="display: none;">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>最新月
                    </button>
                    <div class="btn-group shadow-sm" role="group">
                        <button id="btn-prev" class="btn btn-outline-primary btn-slide" onclick="slideChart('prev')" title="過去へ">
                            <i class="bi bi-chevron-left fs-5"></i>
                        </button>
                        <button id="btn-next" class="btn btn-outline-primary btn-slide" onclick="slideChart('next')" title="未来へ">
                            <i class="bi bi-chevron-right fs-5"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card-body px-2 px-sm-4 pb-4 pt-0" style="position: relative;">
                <div class="chart-container">
                    <canvas id="salaryChart"></canvas>
                </div>
                <div id="chart-overlay" class="chart-blur-overlay">
                    <i class="bi bi-lock-fill display-4 mb-2"></i>
                    <span class="fs-6 fw-bold">非表示モード</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const actualYearTotal = "<?= number_format($year_total) ?>";
        const actualAvgTotal = "<?= number_format((int)$all_time_avg) ?>";
        const placeholderText = "------";

        let isSummaryVisible = false;
        let isChartVisible = false;

        function toggleSummary() {
            isSummaryVisible = !isSummaryVisible;
            const btn = document.getElementById('btn-toggle-summary');
            const elYear = document.getElementById('val-year-total');
            const elAvg = document.getElementById('val-all-avg');

            elYear.style.opacity = 0; elAvg.style.opacity = 0;
            
            setTimeout(() => {
                if (isSummaryVisible) {
                    elYear.innerText = actualYearTotal;
                    elAvg.innerText = actualAvgTotal;
                    btn.innerHTML = '<i class="bi bi-eye-slash me-1"></i>金額を隠す';
                    btn.classList.replace('btn-outline-primary', 'btn-secondary');
                } else {
                    elYear.innerText = placeholderText;
                    elAvg.innerText = placeholderText;
                    btn.innerHTML = '<i class="bi bi-eye me-1"></i>金額を表示';
                    btn.classList.replace('btn-secondary', 'btn-outline-primary');
                }
                elYear.style.opacity = 1; elAvg.style.opacity = 1;
            }, 150);
        }

        // グラフの表示切り替え（CSSのクラスを操作）
        function toggleChart() {
            isChartVisible = !isChartVisible;
            const btn = document.getElementById('btn-toggle-chart');
            const overlay = document.getElementById('chart-overlay');

            if (isChartVisible) {
                btn.innerHTML = '<i class="bi bi-eye-slash me-1"></i>グラフを隠す';
                btn.classList.replace('btn-outline-primary', 'btn-secondary');
                overlay.classList.add('is-visible'); // ぼかしオーバーレイを消すクラスを出す
            } else {
                btn.innerHTML = '<i class="bi bi-eye me-1"></i>グラフを表示';
                btn.classList.replace('btn-secondary', 'btn-outline-primary');
                overlay.classList.remove('is-visible'); // ぼかしオーバーレイを消すクラスを引っ込める
            }
            
            // 色や数値を切り替えるために再描画（JS側のアプローチはそのまま維持）
            renderChart();
        }


        // --- 以下、グラフ描画用のプログラム ---
        const chartData = <?= json_encode($chart_data_map) ?>;
        const maxYear = <?= $max_y ?>;
        const maxMonth = <?= $max_m ?>;
        
        let currentEndYear = maxYear;
        let currentEndMonth = maxMonth;
        let salaryChart = null;

        function renderChart() {
            let labels = [];
            let data = [];
            
            for (let i = 5; i >= 0; i--) {
                let d = new Date(currentEndYear, currentEndMonth - 1 - i, 1);
                let y = d.getFullYear();
                let m = d.getMonth() + 1;
                
                labels.push(`${y}年${m}月`);
                let key = `${y}-${m}`;
                data.push(chartData[key] || 0);
            }
            
            const ctx = document.getElementById('salaryChart').getContext('2d');
            
            let gradientBlue = ctx.createLinearGradient(0, 0, 0, 400);
            gradientBlue.addColorStop(0, 'rgba(13, 110, 253, 0.8)');
            gradientBlue.addColorStop(1, 'rgba(13, 110, 253, 0.2)');
            
            let gradientGray = ctx.createLinearGradient(0, 0, 0, 400);
            gradientGray.addColorStop(0, 'rgba(206, 212, 218, 0.8)');
            gradientGray.addColorStop(1, 'rgba(233, 236, 239, 0.3)');

            // 状態に応じて色を決定（JS側はそのまま維持）
            const bgColor = isChartVisible ? gradientBlue : gradientGray;
            const borderColor = isChartVisible ? 'rgba(13, 110, 253, 1)' : 'rgba(173, 181, 189, 1)';
            const hoverColor = isChartVisible ? 'rgba(13, 110, 253, 1)' : 'rgba(206, 212, 218, 1)';

            if (salaryChart) {
                salaryChart.data.labels = labels;
                salaryChart.data.datasets[0].data = data;
                
                salaryChart.data.datasets[0].backgroundColor = bgColor;
                salaryChart.data.datasets[0].borderColor = borderColor;
                salaryChart.data.datasets[0].hoverBackgroundColor = hoverColor;
                
                salaryChart.options.scales.y.ticks.callback = function(value) {
                    return isChartVisible ? value.toLocaleString() + '円' : '----';
                };
                
                salaryChart.options.plugins.tooltip.enabled = isChartVisible;
                
                salaryChart.update();
            } else {
                salaryChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '最終支給額',
                            data: data,
                            backgroundColor: bgColor,
                            borderColor: borderColor,
                            borderWidth: 1,
                            borderRadius: 6,
                            hoverBackgroundColor: hoverColor
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: isChartVisible,
                                callbacks: {
                                    label: function(context) { return context.parsed.y.toLocaleString() + ' 円'; }
                                }
                            }
                        },
                        scales: {
                            y: { 
                                beginAtZero: true,
                                grid: { borderDash: [4, 4] },
                                ticks: { 
                                    callback: function(value) { 
                                        return isChartVisible ? value.toLocaleString() + '円' : '----'; 
                                    } 
                                }
                            },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
            
            const isAtMax = (currentEndYear === maxYear && currentEndMonth === maxMonth);
            document.getElementById('btn-next').disabled = isAtMax;
            document.getElementById('btn-latest').style.display = isAtMax ? 'none' : 'inline-block';
        }

        function slideChart(direction) {
            if (direction === 'prev') {
                currentEndMonth--;
                if(currentEndMonth < 1) { 
                    currentEndMonth = 12; 
                    currentEndYear--; 
                }
            } else {
                if (!(currentEndYear === maxYear && currentEndMonth === maxMonth)) {
                    currentEndMonth++;
                    if(currentEndMonth > 12) { 
                        currentEndMonth = 1; 
                        currentEndYear++; 
                    }
                }
            }
            renderChart();
        }
        
        function goToLatest() {
            currentEndYear = maxYear;
            currentEndMonth = maxMonth;
            renderChart();
        }

        window.onload = renderChart;
    </script>
</body>
</html>