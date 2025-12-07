<?php

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password($password) {
    return strlen($password) >= 6;
}

function validate_name($name) {
    return preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{2,50}$/u', $name);
}

function validate_price($price) {
    return is_numeric($price) && $price > 0;
}

function validate_quantity($quantity) {
    return is_numeric($quantity) && $quantity > 0 && $quantity <= 1000;
}

function validate_text($text, $max_length = 1000) {
    return strlen(trim($text)) <= $max_length;
}

function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validate_order_data($title, $description, $min_quantity) {
    $errors = [];
    
    if (empty($title) || !validate_text($title, 200)) {
        $errors[] = "Название закупки должно быть от 1 до 200 символов";
    }
    
    if (!validate_text($description, 1000)) {
        $errors[] = "Описание не должно превышать 1000 символов";
    }
    
    if (!validate_quantity($min_quantity)) {
        $errors[] = "Количество должно быть положительным числом (максимум 1000)";
    }
    
    return $errors;
}
?>