<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';
include('achievement_helpers.php');

if (!isset($_SESSION['AdminID'])) {
    header("Location: Login.html");
    exit();
}

ensureAchievementTables($pdo);

$adminName = htmlspecialchars($_SESSION['AdminUsername'] ?? 'Admin');
$noticeMsg = '';
$noticeType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $achievementId = (int)($_POST['achievement_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'ðŸ†');
        $criteriaType = trim($_POST['criteria_type'] ?? '');
        $criteriaValue = (float)($_POST['criteria_value'] ?? 0);
        $rewardType = trim($_POST['reward_type'] ?? '');
        $rewardValue = (float)($_POST['reward_value'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $dietaryTagsArray = $_POST['dietary_tags'] ?? [];
        $dietaryTags = null;
        if ($criteriaType === 'order_specific_food' && is_array($dietaryTagsArray) && count($dietaryTagsArray) > 0) {
            $dietaryTags = implode(',', array_map('trim', $dietaryTagsArray));
        }

        $allowedCriteria = ['order_count', 'total_spent', 'unique_vendors', 'unique_categories', 'order_specific_food'];
        $allowedRewards = ['percent', 'fixed'];

        if ($title === '') {
            $noticeMsg = 'Title is required.';
            $noticeType = 'error';
        } elseif (!in_array($criteriaType, $allowedCriteria, true)) {
            $noticeMsg = 'Invalid criteria type.';
            $noticeType = 'error';
        } elseif ($criteriaType === 'order_specific_food' && empty($dietaryTags)) {
            $noticeMsg = 'Please select at least one dietary tag for this task type.';
            $noticeType = 'error';
        } elseif ($criteriaValue <= 0) {
            $noticeMsg = 'Criteria value must be greater than 0.';
            $noticeType = 'error';
        } elseif (!in_array($rewardType, $allowedRewards, true)) {
            $noticeMsg = 'Invalid reward type.';
            $noticeType = 'error';
        } elseif ($rewardValue <= 0) {
            $noticeMsg = 'Reward value must be greater than 0.';
            $noticeType = 'error';
        } elseif ($rewardType === 'percent' && $rewardValue > 100) {
            $noticeMsg = 'Percentage reward cannot exceed 100%.';
            $noticeType = 'error';
        } else {
            if ($action === 'create') {
                $sql = 'INSERT INTO achievements
                        ("Title", "Description", "Icon", "CriteriaType", "CriteriaValue", "RewardType", "RewardValue", "IsActive", "DietaryTags")
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
                try {
                    db_execute($pdo, $sql, [
                        $title, $description, $icon, $criteriaType, $criteriaValue,
                        $rewardType, $rewardValue, $isActive, $dietaryTags
                    ]);
                    $noticeMsg = 'Achievement task created successfully.';
                } catch (Exception $e) {
                    $noticeMsg = 'Failed to create achievement.';
                    $noticeType = 'error';
                }
            } else {
                $sql = 'UPDATE achievements
                        SET "Title" = ?, "Description" = ?, "Icon" = ?, "CriteriaType" = ?, "CriteriaValue" = ?,
                            "RewardType" = ?, "RewardValue" = ?, "IsActive" = ?, "DietaryTags" = ?
                        WHERE "AchievementID" = ?';
                try {
                    db_execute($pdo, $sql, [
                        $title, $description, $icon, $criteriaType, $criteriaValue,
                        $rewardType, $rewardValue, $isActive, $dietaryTags, $achievementId
                    ]);
                    $noticeMsg = 'Achievement updated successfully.';
                } catch (Exception $e) {
                    $noticeMsg = 'Failed to update achievement.';
                    $noticeType = 'error';
                }
            }
        }
    } elseif ($action === 'delete') {
        $achievementId = (int)($_POST['achievement_id'] ?? 0);
        if ($achievementId > 0) {
            try {
                db_execute($pdo, 'DELETE FROM user_achievement_claims WHERE "AchievementID" = ?', [$achievementId]);
                db_execute($pdo, 'DELETE FROM achievements WHERE "AchievementID" = ?', [$achievementId]);
                $noticeMsg = 'Achievement deleted.';
            } catch (Exception $e) {
                $noticeMsg = 'Failed to delete achievement.';
                $noticeType = 'error';
            }
        }
    } elseif ($action === 'toggle') {
        $achievementId = (int)($_POST['achievement_id'] ?? 0);
        $newStatus = (int)($_POST['new_status'] ?? 0);
        try {
            db_execute($pdo, 'UPDATE achievements SET "IsActive" = ? WHERE "AchievementID" = ?', [$newStatus, $achievementId]);
            $noticeMsg = $newStatus ? 'Achievement activated.' : 'Achievement deactivated.';
        } catch (Exception $e) {
            $noticeMsg = 'Failed to update status.';
            $noticeType = 'error';
        }
    }
}

$achievements = getAllAchievements($pdo);
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editAchievement = null;
if ($editId > 0) {
    foreach ($achievements as $achievement) {
        if ((int)$achievement['AchievementID'] === $editId) {
            $editAchievement = $achievement;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Achievements - CraveFood Admin</title>
    <link rel="stylesheet" href="../style.css?v=20260621-4">
    <style>
        .admin-wrap { max-width: 1080px; margin: 0 auto; padding: 30px 20px; }
        .admin-title { color: #c1121f; font-size: 1.8rem; margin-bottom: 6px; }
        .admin-subtitle { color: #888; margin-bottom: 24px; }
        .panel {
            background: #fff;
            border: 1px solid #ffe2e6;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(193,18,31,0.04);
        }
        .panel h2 { color: #c1121f; font-size: 1.3rem; margin: 0 0 20px; font-weight: 700; }
        
        /* Form Improvements */
        .form-section {
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fafafa;
        }
        .form-section legend {
            background: #c1121f;
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }
        .form-grid label { display: flex; align-items: center; font-weight: 600; margin-bottom: 6px; color: #444; }
        .form-grid input[type="text"], .form-grid input[type="number"], .form-grid select, .form-grid textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ffd8de;
            border-radius: 8px;
            box-sizing: border-box;
            background: #fff;
            transition: border-color 0.2s;
        }
        .form-grid input:focus, .form-grid select:focus, .form-grid textarea:focus {
            outline: none;
            border-color: #c1121f;
        }
        .form-grid textarea { min-height: 80px; resize: vertical; }
        .full-width { grid-column: 1 / -1; }
        
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            background: #fff;
            padding: 12px;
            border: 1px solid #ffd8de;
            border-radius: 8px;
        }
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        
        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .checkbox-row { display: flex; align-items: center; gap: 8px; }
        .checkbox-row label { font-weight: 600; color: #444; margin: 0; cursor: pointer; }
        .form-actions { display: flex; gap: 10px; }

        /* Tooltips */
        .tooltip-icon {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 16px;
            height: 16px;
            background: #e0e0e0;
            color: #666;
            border-radius: 50%;
            font-size: 11px;
            font-weight: bold;
            margin-left: 6px;
            cursor: help;
        }

        /* Table Improvements */
        .achievement-table { width: 100%; border-collapse: collapse; }
        .achievement-table th, .achievement-table td {
            padding: 16px 14px;
            border-bottom: 1px solid #f0f0f0;
            text-align: left;
            vertical-align: middle;
        }
        .achievement-table th {
            background: #fff;
            color: #888;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f0f0f0;
        }
        .achievement-table tbody tr:hover { background: #fafafa; }
        
        .task-title { font-weight: 700; font-size: 1.05rem; color: #333; margin-bottom: 4px; display: block; }
        .task-desc { color: #666; font-size: 0.85rem; line-height: 1.4; }
        
        .tag-pill {
            display: inline-block;
            background: #f0f0f0;
            color: #555;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin: 2px;
        }

        .badge-active, .badge-inactive {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-active { background: #e6f4ea; color: #1e8e3e; }
        .badge-inactive { background: #fce8e6; color: #d93025; }
        
        .action-group { display: flex; gap: 8px; align-items: center; }
        .btn-icon {
            background: transparent;
            border: 1px solid #eee;
            border-radius: 8px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: #666;
            text-decoration: none;
        }
        .btn-icon:hover { background: #f5f5f5; border-color: #ddd; color: #333; }
        .btn-edit:hover { color: #1a73e8; border-color: #d2e3fc; background: #e8f0fe; }
        .btn-toggle:hover { color: #e37400; border-color: #fce8b2; background: #fef7e0; }
        .btn-delete:hover { color: #d93025; border-color: #fad2cf; background: #fce8e6; }
        .btn-icon svg { width: 16px; height: 16px; fill: currentColor; }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo"><h2>CraveFood</h2></div>
        <div class="nav-links">
            <a href="AdminDashboard.php">Dashboard</a>
            <a href="AdminAchievements.php">Achievements</a>
            <a href="AdminLogout.php">Logout</a>
        </div>
    </div>

    <div class="admin-wrap">
        <h1 class="admin-title">Achievement Tasks & Rewards</h1>
        <p class="admin-subtitle">Welcome, <?php echo $adminName; ?>. Create tasks based on order history and set discount rewards for users to claim.</p>

        <?php if ($noticeMsg !== ''): ?>
            <div class="notice show <?php echo $noticeType === 'success' ? 'notice-success' : 'notice-error'; ?>">
                <?php echo htmlspecialchars($noticeMsg); ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h2><?php echo $editAchievement ? 'Edit Task' : 'Create New Task'; ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $editAchievement ? 'update' : 'create'; ?>">
                <?php if ($editAchievement): ?>
                    <input type="hidden" name="achievement_id" value="<?php echo (int)$editAchievement['AchievementID']; ?>">
                <?php endif; ?>
                <fieldset class="form-section">
                    <legend>Task Details</legend>
                    <div class="form-grid">
                        <div>
                            <label for="title">Task Title</label>
                            <input type="text" id="title" name="title" maxlength="100" required
                                   value="<?php echo htmlspecialchars($editAchievement['Title'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="icon">Icon</label>
                            <input type="text" id="icon" name="icon" maxlength="20"
                                   value="<?php echo htmlspecialchars($editAchievement['Icon'] ?? 'ðŸ†'); ?>">
                        </div>
                        <div class="full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($editAchievement['Description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Conditions</legend>
                    <div class="form-grid">
                        <div>
                            <label for="criteria_type">Task Type</label>
                            <select id="criteria_type" name="criteria_type" required>
                                <?php
                                $criteriaOptions = [
                                    'order_count' => 'Completed orders',
                                    'total_spent' => 'Total spent (RM)',
                                    'unique_vendors' => 'Unique restaurants',
                                    'unique_categories' => 'Unique food categories',
                                    'order_specific_food' => 'Order Specific Food Type'
                                ];
                                $selectedCriteria = $editAchievement['CriteriaType'] ?? 'order_count';
                                foreach ($criteriaOptions as $value => $label):
                                ?>
                                    <option value="<?php echo $value; ?>" <?php if ($selectedCriteria === $value) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="dietary_tags_container" style="<?php echo $selectedCriteria === 'order_specific_food' ? '' : 'display:none;'; ?>" class="full-width">
                            <label>Dietary Tags <span class="tooltip-icon" title="Select which dietary tags the ordered food must have to count towards this task.">?</span></label>
                            <div class="checkbox-group">
                                <?php
                                $availableTags = ['Halal', 'Vegetarian', 'Vegan', 'High Protein', 'Low Lactose', 'Keto', 'Fiber'];
                                $savedTags = [];
                                if (!empty($editAchievement['DietaryTags'])) {
                                    $savedTags = array_map('trim', explode(',', $editAchievement['DietaryTags']));
                                }
                                foreach ($availableTags as $tag):
                                    $isChecked = in_array($tag, $savedTags) ? 'checked' : '';
                                ?>
                                    <label><input type="checkbox" name="dietary_tags[]" value="<?php echo htmlspecialchars($tag); ?>" <?php echo $isChecked; ?>> <?php echo htmlspecialchars($tag); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <label for="criteria_value">
                                Target Value 
                                <span class="tooltip-icon" title="The number of orders, RM spent, or categories needed to complete this task.">?</span>
                            </label>
                            <input type="number" id="criteria_value" name="criteria_value" min="1" step="0.01" required
                                   value="<?php echo htmlspecialchars((string)($editAchievement['CriteriaValue'] ?? '1')); ?>">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Rewards</legend>
                    <div class="form-grid">
                        <div>
                            <label for="reward_type">Reward Type</label>
                            <select id="reward_type" name="reward_type" required>
                                <?php
                                $rewardOptions = ['percent' => 'Percentage off (%)', 'fixed' => 'Fixed amount off (RM)'];
                                $selectedReward = $editAchievement['RewardType'] ?? 'percent';
                                foreach ($rewardOptions as $value => $label):
                                ?>
                                    <option value="<?php echo $value; ?>" <?php if ($selectedReward === $value) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="reward_value">
                                Reward Value
                                <span class="tooltip-icon" title="The discount amount. e.g., '10' means 10% off or RM10 off depending on the type.">?</span>
                            </label>
                            <input type="number" id="reward_value" name="reward_value" min="0.01" step="0.01" required
                                   value="<?php echo htmlspecialchars((string)($editAchievement['RewardValue'] ?? '10')); ?>">
                        </div>
                    </div>
                </fieldset>

                <div class="form-footer">
                    <div class="checkbox-row">
                        <input type="checkbox" id="is_active" name="is_active" <?php echo !isset($editAchievement['IsActive']) || (int)$editAchievement['IsActive'] === 1 ? 'checked' : ''; ?>>
                        <label for="is_active">Active (visible to users)</label>
                    </div>
                    <div class="form-actions">
                        <?php if ($editAchievement): ?>
                            <a href="AdminAchievements.php" class="btn-secondary" style="display:inline-block;padding:10px 16px;text-decoration:none;">Cancel Edit</a>
                        <?php endif; ?>
                        <button type="submit" class="btn-primary"><?php echo $editAchievement ? 'Save Changes' : 'Create Task'; ?></button>
                    </div>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2>Existing Tasks</h2>
            <?php if (count($achievements) === 0): ?>
                <p style="color:#888;">No achievement tasks yet. Create one above.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="achievement-table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Requirement</th>
                                <th>Tags/Conditions</th>
                                <th>Reward</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($achievements as $achievement): ?>
                                <tr>
                                    <td>
                                        <span class="task-title"><?php echo htmlspecialchars($achievement['Icon'] . ' ' . $achievement['Title']); ?></span>
                                        <div class="task-desc"><?php echo htmlspecialchars($achievement['Description']); ?></div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(getCriteriaTypeLabel($achievement['CriteriaType'])); ?><br>
                                        <strong><?php echo formatProgressTarget($achievement['CriteriaType'], $achievement['CriteriaValue']); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($achievement['CriteriaType'] === 'order_specific_food' && !empty($achievement['DietaryTags'])) {
                                            $tags = explode(',', $achievement['DietaryTags']);
                                            foreach ($tags as $tag) {
                                                echo '<span class="tag-pill">' . htmlspecialchars(trim($tag)) . '</span>';
                                            }
                                        } else {
                                            echo '<span style="color:#aaa;">&mdash;</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(getRewardTypeLabel($achievement['RewardType'], $achievement['RewardValue'])); ?></td>
                                    <td>
                                        <?php if ((int)$achievement['IsActive'] === 1): ?>
                                            <span class="badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-group" style="justify-content: flex-end;">
                                            <a class="btn-icon btn-edit" title="Edit" href="AdminAchievements.php?edit=<?php echo (int)$achievement['AchievementID']; ?>">
                                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a.9959.9959 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                            </a>
                                            <form method="POST" style="display:inline;" title="<?php echo (int)$achievement['IsActive'] === 1 ? 'Deactivate' : 'Activate'; ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="achievement_id" value="<?php echo (int)$achievement['AchievementID']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo (int)$achievement['IsActive'] === 1 ? 0 : 1; ?>">
                                                <button type="submit" class="btn-icon btn-toggle">
                                                    <?php if ((int)$achievement['IsActive'] === 1): ?>
                                                        <svg viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                                    <?php else: ?>
                                                        <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;" title="Delete" onsubmit="return confirm('Delete this task and all related claims?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="achievement_id" value="<?php echo (int)$achievement['AchievementID']; ?>">
                                                <button type="submit" class="btn-icon btn-delete">
                                                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    /* Navbar Active Link */
    var page = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
    if (page === '' || page === 'index.php') page = 'homepage.php';
    var links = document.querySelectorAll('.nav-links a');
    links.forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === page || (page.startsWith('advancesearch') && href === 'homepage.php')) {
            link.classList.add('active');
        }
    });

    /* Dietary Tags Toggle */
    var criteriaSelect = document.getElementById('criteria_type');
    var dietaryTagsContainer = document.getElementById('dietary_tags_container');
    
    if (criteriaSelect && dietaryTagsContainer) {
        criteriaSelect.addEventListener('change', function() {
            if (this.value === 'order_specific_food') {
                dietaryTagsContainer.style.display = 'block';
            } else {
                dietaryTagsContainer.style.display = 'none';
            }
        });
    }
});
</script>
</body>
</html>




