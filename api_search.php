<?php
// API for search autocomplete and smart search
session_start();
header('Content-Type: application/json');

include 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$query = $_GET['q'] ?? '';

// Function to calculate Levenshtein distance (for typo tolerance)
function levenshteinDistance($s1, $s2)
{
    $len1 = strlen($s1);
    $len2 = strlen($s2);

    if ($len1 == 0) return $len2;
    if ($len2 == 0) return $len1;

    $d = array();

    for ($i = 0; $i <= $len1; $i++) {
        $d[$i][0] = $i;
    }

    for ($j = 0; $j <= $len2; $j++) {
        $d[0][$j] = $j;
    }

    for ($i = 1; $i <= $len1; $i++) {
        for ($j = 1; $j <= $len2; $j++) {
            $cost = ($s1[$i - 1] == $s2[$j - 1]) ? 0 : 1;
            $d[$i][$j] = min(
                $d[$i - 1][$j] + 1,      // deletion
                $d[$i][$j - 1] + 1,      // insertion
                $d[$i - 1][$j - 1] + $cost // substitution
            );
        }
    }

    return $d[$len1][$len2];
}

if ($action === 'autocomplete') {
    if (strlen($query) < 2) {
        echo json_encode(['suggestions' => []]);
        exit;
    }

    $query_escaped = $conn->real_escape_string($query);
    $suggestions = array();

    // Get product names
    $result = $conn->query("SELECT DISTINCT product_name FROM posts 
                          WHERE is_approved = 1 AND status = 'active' 
                          AND (product_name LIKE '%$query_escaped%' OR category LIKE '%$query_escaped%')
                          LIMIT 10");

    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['product_name'];
    }

    echo json_encode(['suggestions' => array_values(array_unique($suggestions))]);
} elseif ($action === 'smart_search') {
    // Smart search with typo tolerance
    if (strlen($query) < 2) {
        echo json_encode(['results' => []]);
        exit;
    }

    $search_terms = array_filter(array_map('trim', explode(' ', $query)));
    $results = array();

    // Get all products
    $all_result = $conn->query("SELECT id, product_name, category FROM posts 
                              WHERE is_approved = 1 AND status = 'active' 
                              LIMIT 100");

    $threshold = 2; // Maximum distance for typo tolerance

    while ($product = $all_result->fetch_assoc()) {
        $match_score = 0;
        $matched_terms = 0;

        foreach ($search_terms as $term) {
            $product_str = strtolower($product['product_name'] . ' ' . $product['category']);

            // Exact word match
            if (stripos($product_str, $term) !== false) {
                $match_score += 100;
                $matched_terms++;
            } else {
                // Check for typo with Levenshtein distance
                $words = explode(' ', $product_str);
                foreach ($words as $word) {
                    $distance = levenshteinDistance(strtolower($term), $word);
                    if ($distance <= $threshold && strlen($term) >= 3) {
                        $match_score += (10 - $distance);
                        $matched_terms++;
                    }
                }
            }
        }

        // Only include if all search terms matched
        if ($matched_terms > 0) {
            $results[] = array(
                'id' => $product['id'],
                'name' => $product['product_name'],
                'category' => $product['category'],
                'score' => $match_score
            );
        }
    }

    // Sort by score
    usort($results, function ($a, $b) {
        return $b['score'] - $a['score'];
    });

    // Return top 10
    echo json_encode(['results' => array_slice($results, 0, 10)]);
} else {
    echo json_encode(['error' => 'Invalid action']);
}
