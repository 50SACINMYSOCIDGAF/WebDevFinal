<?php
/**
 * AJAX endpoint for fetching post statistics
 * Returns post data for charts in the admin dashboard
 */

session_start();
require_once '../../config.php';
require_once '../../functions.php';

// Verify admin and CSRF
if (!isLoggedIn() || !isAdmin() || !isValidCSRFToken($_GET['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDbConnection();

// Get range parameter
$range = isset($_GET['range']) ? $_GET['range'] : 'week';

// Generate date ranges based on the period
function getDateRange($period) {
    switch ($period) {
        case 'week':
            return [
                'start' => date('Y-m-d', strtotime('-7 days')),
                'end' => date('Y-m-d'),
                'format' => '%Y-%m-%d',
                'group' => 'day',
                'display' => 'l' // Day of week
            ];
        case 'month':
            return [
                'start' => date('Y-m-d', strtotime('-30 days')),
                'end' => date('Y-m-d'),
                'format' => '%Y-%m-%d',
                'group' => 'day',
                'display' => 'M j' // Jan 1
            ];
        case 'year':
            return [
                'start' => date('Y-m-d', strtotime('-12 months')),
                'end' => date('Y-m-d'),
                'format' => '%Y-%m',
                'group' => 'month',
                'display' => 'M Y' // Jan 2023
            ];
        default:
            return getDateRange('week');
    }
}

$rangeData = getDateRange($range);

// Generate date series for the selected period
$dates = [];
$dateLabels = [];

if ($rangeData['group'] === 'day') {
    $startDate = new DateTime($rangeData['start']);
    $endDate = new DateTime($rangeData['end']);
    $interval = new DateInterval('P1D');
    
    $datePeriod = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
    
    foreach ($datePeriod as $date) {
        $dates[] = $date->format('Y-m-d');
        $dateLabels[] = $date->format($rangeData['display']);
    }
} else {
    $startDate = new DateTime($rangeData['start']);
    $endDate = new DateTime($rangeData['end']);
    $startDate->modify('first day of this month');
    
    while ($startDate <= $endDate) {
        $dates[] = $startDate->format('Y-m');
        $dateLabels[] = $startDate->format($rangeData['display']);
        $startDate->modify('+1 month');
    }
}

// Initialize data array
$postsData = array_fill(0, count($dates), 0);

if ($rangeData['group'] === 'day') {
    // Posts per day
    $postsQuery = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM posts
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
    ");
    $postsQuery->bind_param("ss", $rangeData['start'], $rangeData['end']);
} else {
    // Posts per month
    $postsQuery = $conn->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as date, COUNT(*) as count
        FROM posts
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ");
    $postsQuery->bind_param("ss", $rangeData['start'], $rangeData['end']);
}

// Execute query and populate data array
$postsQuery->execute();
$postsResult = $postsQuery->get_result();
while ($row = $postsResult->fetch_assoc()) {
    $index = array_search($row['date'], $dates);
    if ($index !== false) {
        $postsData[$index] = (int)$row['count'];
    }
}

$conn->close();

// Return the data
echo json_encode([
    'success' => true,
    'labels' => $dateLabels,
    'values' => $postsData,
    'range' => $range
]);
?>