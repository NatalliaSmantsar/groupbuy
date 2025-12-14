<?php
function get_potential_participants($current_order_id, $limit = 10) {
    global $conn;

    $order_stmt = $conn->prepare("
        SELECT g.*, p.category_id, p.name as product_name, p.price as product_price,
               u.name as organizer_name
        FROM group_orders g 
        JOIN products p ON g.product_id = p.id 
        JOIN users u ON g.organizer_id = u.id
        WHERE g.id = ?
    ");
    $order_stmt->bind_param("i", $current_order_id);
    $order_stmt->execute();
    $order_info = $order_stmt->get_result()->fetch_assoc();
    
    if (!$order_info) return [];
    
    $target_product_id = $order_info['product_id'];
    $target_category_id = $order_info['category_id'];
    
    $users_stmt = $conn->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            COUNT(oi.id) as total_participations,
            AVG(oi.quantity) as avg_quantity,
            SUM(CASE WHEN go.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_participations,
            COUNT(DISTINCT p.category_id) as diverse_categories,
            SUM(CASE WHEN p.category_id = ? THEN 1 ELSE 0 END) as same_category_participations,
            SUM(CASE WHEN oi.product_id = ? THEN 1 ELSE 0 END) as same_product_participations,
            MAX(go.created_at) as last_participation_date
        FROM users u
        LEFT JOIN order_items oi ON u.id = oi.participant_id
        LEFT JOIN group_orders go ON oi.group_order_id = go.id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE (u.role = 'user' OR u.role = 'organizer')
        GROUP BY u.id, u.name, u.email
        HAVING total_participations > 0
    ");
    $users_stmt->bind_param("ii", $target_category_id, $target_product_id);
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    
    $users_with_scores = [];
    
    while ($user = $users_result->fetch_assoc()) {
        $score = 0;
        
        $activity_score = min(100, ($user['total_participations'] / 10) * 100) * 0.3;
        $score += $activity_score;
        
        $recent_score = min(100, ($user['recent_participations'] / 5) * 100) * 0.25;
        $score += $recent_score;
        
        $quantity_score = min(100, ($user['avg_quantity'] / 5) * 100) * 0.2;
        $score += $quantity_score;
        
        $category_interest = 0;
        if ($user['same_category_participations'] > 0) {
            $category_interest = min(100, ($user['same_category_participations'] / $user['total_participations']) * 200) * 0.15;
        }
        $score += $category_interest;
        
        $product_interest = 0;
        if ($user['same_product_participations'] > 0) {
            $product_interest = min(100, ($user['same_product_participations'] * 50)) * 0.1;
        }
        $score += $product_interest;
        
        $users_with_scores[] = [
            'user' => $user,
            'score' => round($score, 2),
            'breakdown' => [
                'activity' => round($activity_score, 2),
                'recent' => round($recent_score, 2),
                'quantity' => round($quantity_score, 2),
                'category' => round($category_interest, 2),
                'product' => round($product_interest, 2)
            ]
        ];
    }
    
    usort($users_with_scores, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    return [
        'order_info' => $order_info,
        'participants' => array_slice($users_with_scores, 0, $limit)
    ];
}

function get_optimal_creation_time($organizer_id = null) {
    global $conn;
    
    $query = "
        SELECT 
            HOUR(created_at) as creation_hour,
            DAYOFWEEK(created_at) as creation_day,
            COUNT(*) as success_count,
            AVG(TIMESTAMPDIFF(HOUR, created_at, 
                (SELECT MIN(created_at) 
                 FROM order_items oi 
                 JOIN group_orders go2 ON oi.group_order_id = go2.id 
                 WHERE oi.group_order_id = go.id AND go2.created_at > go.created_at
                 LIMIT 1)
            )) as avg_time_to_first_participant
        FROM group_orders go
        WHERE status = 'completed'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    ";
    
    if ($organizer_id) {
        $query .= " AND organizer_id = " . intval($organizer_id);
    }
    
    $query .= " GROUP BY HOUR(created_at), DAYOFWEEK(created_at) ORDER BY success_count DESC";
    
    $result = $conn->query($query);
    
    $time_analysis = [];
    $total_successful = 0;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $hour = $row['creation_hour'];
            $day = $row['creation_day'];
            $time_analysis[$day][$hour] = $row;
            $total_successful += $row['success_count'];
        }
    }
    
    if ($total_successful === 0) {
        return [
            'top_recommendations' => [],
            'full_analysis' => [],
            'total_analyzed' => 0
        ];
    }
    
    $optimal_times = [];
    $days_of_week = ['', 'Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];
    
    foreach ($time_analysis as $day => $hours) {
        foreach ($hours as $hour => $data) {
            $success_rate = ($data['success_count'] / max(1, $total_successful)) * 100;
            $speed_score = max(0, 100 - (($data['avg_time_to_first_participant'] ?? 24) * 2));
            $efficiency_score = ($success_rate * 0.6) + ($speed_score * 0.4);
            
            $optimal_times[] = [
                'day' => $day,
                'day_name' => $days_of_week[$day],
                'hour' => $hour,
                'hour_display' => sprintf("%02d:00", $hour),
                'success_count' => $data['success_count'],
                'success_rate' => round($success_rate, 2),
                'avg_time_to_first' => round($data['avg_time_to_first_participant'] ?? 24, 1),
                'efficiency_score' => round($efficiency_score, 2)
            ];
        }
    }
    
    usort($optimal_times, function($a, $b) {
        return $b['efficiency_score'] <=> $a['efficiency_score'];
    });
    
    return [
        'top_recommendations' => array_slice($optimal_times, 0, 5),
        'full_analysis' => $optimal_times,
        'total_analyzed' => $total_successful
    ];
}

function send_participation_invitation($to_email, $to_name, $order_info, $inviter_name) {
    global $conn;
    
    $user_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $user_stmt->bind_param("s", $to_email);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    if (!$user) {
        error_log("User not found: $to_email");
        return false;
    }
    
    $user_id = $user['id'];
    
    $subject = "Приглашение к участию в закупке: " . $order_info['title'];
    $message = "Приглашение к участию в закупке";
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO user_notifications 
            (user_id, order_id, organizer_id, subject, message) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiiss", $user_id, $order_info['id'], $_SESSION['user_id'], $subject, $message);
        
        if ($stmt->execute()) {
            error_log("Notification created for user ID: $user_id");
            return true;
        } else {
            error_log("Failed to create notification");
            return false;
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

function has_user_participated($user_id, $order_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id FROM order_items WHERE participant_id = ? AND group_order_id = ?");
    $stmt->bind_param("ii", $user_id, $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}
?>