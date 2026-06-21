<?php
session_start();
include('db.php');
include('db_helpers.php');
include('achievement_helpers.php');

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.html");
    exit();
}

$userId = (int)$_SESSION['UserID'];
$achievementItems = getAchievementsForUser($pdo, $userId);

$activeOrder = db_fetch_one($pdo, 'SELECT "OrderID", "Status" FROM orders WHERE "UserID" = ? AND "Status" NOT IN (\'Finished\', \'Completed\', \'Cancelled\') ORDER BY "OrderID" DESC LIMIT 1', [$userId]);

$noticeMsg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
$noticeType = isset($_GET['type']) ? $_GET['type'] : 'success';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievements - CraveFood</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css?v=20260621-7">
    <style>
        /* ── Reset & base ── */
        *, body { font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: #ffffff; margin: 0; padding: 0; color: #1e1e1e; }

        .achievements-wrap {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px 100px; /* bottom pad for pending pill */
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 40px;
        }
        .page-title {
            color: #ff2a44;
            font-size: 2.2rem;
            font-weight: 800;
            margin: 0 0 8px;
            letter-spacing: -0.5px;
        }
        .page-subtitle {
            color: #666;
            font-size: 1rem;
            margin: 0;
            max-width: 600px;
            margin: 0 auto;
        }

        /* ── Grid Layout ── */
        .achievement-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        @media (min-width: 768px) {
            .achievement-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .achievement-grid { grid-template-columns: repeat(3, 1fr); }
        }

        /* ── Card Styles ── */
        .achievement-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        }
        .achievement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(193,18,31,0.08);
            border-color: #e0e0e0;
        }

        /* Card Header */
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .achievement-icon {
            font-size: 2rem;
            line-height: 1;
        }
        .achievement-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #2d2d2d;
            margin: 0;
        }

        /* Description */
        .achievement-desc {
            color: #777;
            font-size: 0.9rem;
            line-height: 1.4;
            margin: 0 0 16px;
            flex-grow: 1;
        }

        /* Reward Box */
        .achievement-reward {
            background: #f9fafb;
            border: 1px dashed #e0e0e0;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.9rem;
            color: #ff2a44;
            font-weight: 700;
            text-align: center;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        
        .achievement-card.is-used .achievement-reward {
            background: #f8f9fa;
            border-color: #dee2e6;
            color: #adb5bd;
        }

        /* Progress */
        .progress-container {
            margin-bottom: 20px;
        }
        .progress-wrap {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 6px;
        }
        .progress-bar {
            height: 100%;
            background: #ff2a44;
            border-radius: 999px;
            transition: width 0.5s ease;
        }
        .achievement-card.is-used .progress-bar {
            background: #ced4da;
        }
        .progress-text {
            font-size: 0.8rem;
            font-weight: 600;
            color: #888;
            text-align: right;
            display: block;
        }

        /* Footer (Status & Interaction) */
        .card-footer {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-top: 1px solid #e0e0e0;
            padding-top: 16px;
        }
        
        /* Pills */
        .status-pill {
            align-self: flex-start;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 10px;
            border-radius: 6px;
        }
        .status-pill.in-progress { background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
        .status-pill.ready { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-pill.claimed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-pill.used { background: #e2e3e5; color: #6c757d; border: 1px solid #d6d8db; }

        /* Actions/Code */
        .footer-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .btn-claim {
            width: 100%;
            background: #ff2a44;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-claim:hover { background: #a00f1a; }

        .code-display {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f9fafb;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .code-text {
            font-family: Consolas, monospace;
            font-size: 1.05rem;
            letter-spacing: 1px;
            color: #ff2a44;
            font-weight: 700;
        }
        .btn-copy {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ff2a44;
            transition: background 0.2s;
            position: relative;
        }
        .btn-copy:hover { background: #e0e0e0; }
        .btn-copy svg { width: 18px; height: 18px; fill: currentColor; }

        /* Used info */
        .used-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .used-code-text {
            font-family: Consolas, monospace;
            font-size: 0.9rem;
            color: #adb5bd;
            text-decoration: line-through;
        }
        .used-date {
            font-size: 0.8rem;
            color: #888;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e0e0e0;
        }

        /* Tooltip */
        .copy-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(-4px);
            background: #2d2d2d;
            color: #fff;
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 4px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s, transform 0.2s;
            white-space: nowrap;
        }
        .copy-tooltip.show {
            opacity: 1;
            transform: translateX(-50%) translateY(-8px);
        }

        /* ════════════════════════════════
           PENDING ORDER PILL
        ════════════════════════════════ */
        .pending-pill {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            background: #ffffff;
            border: 1.5px solid #e63946;
            border-radius: 999px;
            padding: 11px 20px 11px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 32px rgba(193,18,31,0.2), 0 2px 8px rgba(0,0,0,0.08);
            text-decoration: none;
            white-space: nowrap;
            transition: transform 0.2s, box-shadow 0.2s;
            animation: pill-float 3s ease-in-out infinite;
        }
        .pending-pill:hover {
            transform: translateX(-50%) translateY(-3px);
            box-shadow: 0 12px 40px rgba(193,18,31,0.28), 0 4px 12px rgba(0,0,0,0.1);
        }
        .pending-pill-icon  { color: #ff2a44; display: flex; align-items: center; }
        .pending-pill-text  { font-size: 0.9rem; font-weight: 600; color: #1e1e1e; }
        .pending-pill-chevron { color: #ff2a44; display: flex; align-items: center; }
        @keyframes pill-float {
            0%,100% { box-shadow: 0 8px 32px rgba(193,18,31,0.2), 0 2px 8px rgba(0,0,0,0.08); }
            50%      { box-shadow: 0 12px 40px rgba(193,18,31,0.26), 0 4px 14px rgba(0,0,0,0.1); }
        }

        .notice { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .notice-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .notice-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <?php include('header.php'); ?>

    <div class="achievements-wrap">
        
        <?php if ($noticeMsg !== ''): ?>
            <div class="notice <?php echo $noticeType === 'success' ? 'notice-success' : 'notice-error'; ?>">
                <?php echo htmlspecialchars($noticeMsg); ?>
            </div>
        <?php endif; ?>

        <div class="header-section">
            <h1 class="page-title">Achievements</h1>
            <p class="page-subtitle">Complete tasks from your order history and claim discount rewards to use at checkout.</p>
        </div>

        <?php if (count($achievementItems) === 0): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="#f0cfd3" style="margin-bottom:12px;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/>
                </svg>
                <p>No achievement tasks available yet. Check back later!</p>
            </div>
        <?php else: ?>
            <div class="achievement-grid">
                <?php foreach ($achievementItems as $item):
                    $achievement = $item['achievement'];
                    $progress = $item['progress'];
                    $claim = $item['claim'];
                    $status = $item['status'];
                    $criteriaType = $achievement['CriteriaType'];

                    // Visually fill the bar if claimed or used
                    $visualPercent = round($progress['percent']);
                    if ($status === 'claimed' || $status === 'used') {
                        $visualPercent = 100;
                        $progress['current'] = $progress['target'];
                    }
                    $isUsed = ($status === 'used');
                ?>
                    <div class="achievement-card <?php echo $isUsed ? 'is-used' : ''; ?>" id="achievement-<?php echo (int)$achievement['AchievementID']; ?>">
                        
                        <div class="card-header">
                            <span class="achievement-icon"><?php echo htmlspecialchars($achievement['Icon']); ?></span>
                            <h3 class="achievement-title"><?php echo htmlspecialchars($achievement['Title']); ?></h3>
                        </div>
                        
                        <p class="achievement-desc"><?php echo htmlspecialchars($achievement['Description']); ?></p>
                        
                        <div class="achievement-reward">
                            Reward: <?php echo htmlspecialchars(getRewardTypeLabel($achievement['RewardType'], $achievement['RewardValue'])); ?>
                        </div>

                        <div class="progress-container">
                            <div class="progress-wrap">
                                <div class="progress-bar" style="width: <?php echo $visualPercent; ?>%;"></div>
                            </div>
                            <span class="progress-text">
                                <?php echo formatProgressCurrent($criteriaType, $progress['current']); ?> / <?php echo formatProgressTarget($criteriaType, $progress['target']); ?> 
                                (<?php echo htmlspecialchars(getCriteriaTypeLabel($criteriaType)); ?>)
                            </span>
                        </div>

                        <div class="card-footer">
                            <?php if ($status === 'in_progress'): ?>
                                <span class="status-pill in-progress">In Progress</span>
                            <?php elseif ($status === 'ready'): ?>
                                <span class="status-pill ready">Ready to Claim</span>
                                <div class="footer-action">
                                    <button type="button" class="btn-claim" onclick="claimReward(<?php echo (int)$achievement['AchievementID']; ?>)">
                                        Claim Reward
                                    </button>
                                </div>
                            <?php elseif ($status === 'claimed'): ?>
                                <span class="status-pill claimed">Claimed</span>
                                <div class="code-display">
                                    <span class="code-text" id="code-<?php echo (int)$achievement['AchievementID']; ?>"><?php echo htmlspecialchars($claim['DiscountCode']); ?></span>
                                    <button type="button" class="btn-copy" onclick="copyCode('code-<?php echo (int)$achievement['AchievementID']; ?>', this)" aria-label="Copy Code">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                            <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                                        </svg>
                                        <span class="copy-tooltip">Copied!</span>
                                    </button>
                                </div>
                            <?php else: ?>
                                <span class="status-pill used">Used</span>
                                <div class="used-info">
                                    <span class="used-code-text"><?php echo htmlspecialchars($claim['DiscountCode']); ?></span>
                                    <?php if (!empty($claim['UsedAt'])): ?>
                                        <span class="used-date">Used on <?php echo date('d M Y, h:i A', strtotime($claim['UsedAt'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ══════ PENDING ORDER PILL ══════ -->
    <?php if ($activeOrder): ?>
    <a href="OrderStatus.php?order_id=<?php echo (int)$activeOrder['OrderID']; ?>" class="pending-pill" aria-label="View pending order">
        <span class="pending-pill-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#ff2a44">
                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
            </svg>
        </span>
        <span class="pending-pill-text">Order #<?php echo (int)$activeOrder['OrderID']; ?> is <?php echo htmlspecialchars($activeOrder['Status']); ?></span>
        <span class="pending-pill-chevron">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#ff2a44">
                <path d="M9.29 6.71a.996.996 0 0 0 0 1.41L13.17 12l-3.88 3.88a.996.996 0 1 0 1.41 1.41l4.59-4.59a.996.996 0 0 0 0-1.41L10.7 6.7c-.38-.38-1.02-.38-1.41.01z"/>
            </svg>
        </span>
    </a>
    <?php endif; ?>

<script>
    function claimReward(achievementId) {
        var btn = event.target;
        btn.disabled = true;
        btn.innerText = 'Claiming...';

        var fd = new FormData();
        fd.append('action', 'claim');
        fd.append('achievement_id', achievementId);

        fetch('AchievementActions.php', {
            method: 'POST',
            body: fd
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh the page to show the new claimed state
                window.location.reload();
            } else {
                alert(data.message || 'Error claiming reward.');
                btn.disabled = false;
                btn.innerText = 'Claim Reward';
            }
        })
        .catch(err => {
            console.error(err);
            alert('A network error occurred.');
            btn.disabled = false;
            btn.innerText = 'Claim Reward';
        });
    }

    function copyCode(elementId, btn) {
        var code = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(code).then(function() {
            var tooltip = btn.querySelector('.copy-tooltip');
            tooltip.classList.add('show');
            setTimeout(function() {
                tooltip.classList.remove('show');
            }, 2000);
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
        });
    }

document.addEventListener('DOMContentLoaded', function() {
    var page = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
    if (page === '' || page === 'index.php') page = 'homepage.php';
    var links = document.querySelectorAll('.nav-links a');
    links.forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === page || (page.startsWith('advancesearch') && href === 'homepage.php')) {
            link.classList.add('active');
        }
    });
});
</script>
</body>
</html>




