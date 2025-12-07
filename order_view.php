<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';
include 'includes/header.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo "<div class='container'><div class='alert alert-error'>Неверный идентификатор закупки</div></div>";
    include 'includes/footer.php';
    exit;
}

$stmt = $conn->prepare("
    SELECT g.*, p.name AS product_name, p.price AS product_price, p.id AS product_id,
           u.name AS organizer_name, u.id AS organizer_id
    FROM group_orders g
    LEFT JOIN products p ON g.product_id = p.id
    LEFT JOIN users u ON g.organizer_id = u.id
    WHERE g.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo "<div class='container'><div class='alert alert-error'>Закупка не найдена</div></div>";
    include 'includes/footer.php';
    exit;
}

// Обработка отмены заказа через GET параметр
if (isset($_GET['cancel_order']) && $_GET['cancel_order'] == '1' && ($_SESSION['user_id'] == $order['organizer_id'] || $_SESSION['role'] == 'admin')) {
    if (confirmCancellation()) {
        $cancel_stmt = $conn->prepare("UPDATE group_orders SET status = 'closed' WHERE id = ?");
        $cancel_stmt->bind_param("i", $id);
        
        if ($cancel_stmt->execute()) {
            echo "<div class='container'><div class='alert alert-success'>Закупка отменена</div></div>";
            $order['status'] = 'closed';
        } else {
            echo "<div class='container'><div class='alert alert-error'>Ошибка при отмене закупки</div></div>";
        }
    }
}

// Функция подтверждения отмены
function confirmCancellation() {
    if (!isset($_GET['confirm'])) {
        echo "
        <div class='container'>
            <div class='alert alert-warning'>
                <h3>Подтверждение отмены закупки</h3>
                <p>Вы уверены, что хотите отменить эту закупку? Это действие нельзя отменить.</p>
                <div style='margin-top: 15px;'>
                    <a href='?id={$_GET['id']}&tab=info&cancel_order=1&confirm=1' class='btn btn-danger'>Да, отменить закупку</a>
                    <a href='?id={$_GET['id']}&tab=info' class='btn'>Нет, вернуться назад</a>
                </div>
            </div>
        </div>";
        include 'includes/footer.php';
        exit;
    }
    return $_GET['confirm'] == '1';
}

if ($order['status'] === 'open' && $order['current_amount'] >= $order['min_amount']) {
    $conn->query("UPDATE group_orders SET status='completed' WHERE id=$id");
    $order['status'] = 'completed';
}

$current_quantity = $order['current_amount'] / $order['product_price'];
$remaining_quantity = $order['quantity'] - $current_quantity;
$progress_percent = min(100, ($order['current_amount'] / $order['min_amount']) * 100);
$status_label = $order['status'] === 'open' ? 'Открыта' : 
               ($order['status'] === 'completed' ? 'Завершена' : 'Закрыта');

$active_tab = $_GET['tab'] ?? 'info';

// Проверка прав для отмены заказа
$can_cancel = isset($_SESSION['user_id']) && 
             ($_SESSION['user_id'] == $order['organizer_id'] || $_SESSION['role'] == 'admin') && 
             $order['status'] === 'open';
?>

<link rel="stylesheet" href="assets/css/tabs.css">
<link rel="stylesheet" href="assets/css/progress.css">
<link rel="stylesheet" href="assets/css/messages.css">
<link rel="stylesheet" href="assets/css/participants.css">
<link rel="stylesheet" href="assets/css/recommendations.css">

<div class="container">
    <!-- Шапка закупки -->
    <div class="order-header">
        <h1 class="order-title"><?= htmlspecialchars($order['title']) ?></h1>
        
        <!-- Прогресс бар -->
        <div class="progress-section">
            <div class="progress-header">
                <div class="progress-stats">
                    <span>Собрано: <strong><?= number_format($order['current_amount'], 2, ',', ' ') ?> BYN</strong></span>
                    <span>Цель: <strong><?= number_format($order['min_amount'], 2, ',', ' ') ?> BYN</strong></span>
                </div>
                <span><strong><?= round($progress_percent) ?>%</strong></span>
            </div>
            
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?= $progress_percent ?>%;">
                </div>
            </div>
        </div>
    </div>

    <!-- Навигация по вкладкам -->
    <div class="tabs-navigation">
        <a href="?tab=info&id=<?= $id ?>" class="tab-link <?= $active_tab === 'info' ? 'active' : '' ?>">
            Основная информация
        </a>
        <a href="?tab=participants&id=<?= $id ?>" class="tab-link <?= $active_tab === 'participants' ? 'active' : '' ?>">
            Участники
        </a>
        <?php if (isset($_SESSION['user_id']) && $order['status'] === 'open' && $remaining_quantity > 0): ?>
            <a href="?tab=join&id=<?= $id ?>" class="tab-link <?= $active_tab === 'join' ? 'active' : '' ?>">
                Присоединиться
            </a>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $order['organizer_id'] && $order['status'] === 'open'): ?>
            <a href="?tab=recommendations&id=<?= $id ?>" class="tab-link <?= $active_tab === 'recommendations' ? 'active' : '' ?>">
                Рекомендации
            </a>
        <?php endif; ?>
        <a href="?tab=discussion&id=<?= $id ?>" class="tab-link <?= $active_tab === 'discussion' ? 'active' : '' ?>">
            Обсуждение
        </a>
    </div>

    <div class="tab-content">
        <!-- Вкладка основной информации -->
        <?php if ($active_tab === 'info'): ?>
            <div class="card">
                <div class="order-meta">
                    <div class="meta-item">
                        <span class="meta-label">Статус</span>
                        <span class="meta-value status status-<?= $order['status'] ?>"><?= $status_label ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Организатор</span>
                        <span class="meta-value"><?= htmlspecialchars($order['organizer_name']) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Товар</span>
                        <span class="meta-value">
                            <?php if($order['product_name']): ?>
                                <a href="product_view.php?id=<?= $order['product_id'] ?>"><?= htmlspecialchars($order['product_name']) ?></a>
                            <?php else: ?>
                                Не указан
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Собрано товара</span>
                        <span class="meta-value"><?= number_format($current_quantity, 1, ',', ' ') ?> / <?= $order['quantity'] ?> шт.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Описание закупки</label>
                    <div class="order-description">
                        <?= nl2br(htmlspecialchars($order['description'])) ?>
                    </div>
                </div>

                <!-- Кнопка отмены без формы -->
                <?php if ($can_cancel): ?>
                    <div style="margin-top: 10px;">
                        <a href="?id=<?= $id ?>&tab=info&cancel_order=1" class="btn btn-danger" 
                           onclick="return confirm('Вы уверены, что хотите отменить эту закупку? Это действие нельзя отменить.')">
                            Отменить закупку
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <!-- Остальное содержимое без изменений -->
        <!-- Вкладка участников -->
        <?php if ($active_tab === 'participants'): ?>
            <div class="card">
                <h3 class="section-title">Участники закупки</h3>
                
                <?php
                $participants_stmt = $conn->prepare("
                    SELECT oi.*, u.name AS participant_name
                    FROM order_items oi
                    LEFT JOIN users u ON oi.participant_id = u.id
                    WHERE oi.group_order_id = ?
                    ORDER BY oi.total_price DESC
                ");
                $participants_stmt->bind_param("i", $id);
                $participants_stmt->execute();
                $participants = $participants_stmt->get_result();
                
                if ($participants->num_rows > 0): ?>
                    <div class="participants-list">
                        <?php while ($participant = $participants->fetch_assoc()): ?>
                            <div class="participant-item">
                                <div class="participant-info">
                                    <span class="participant-name"><?= htmlspecialchars($participant['participant_name']) ?></span>
                                    <span class="participant-total"><?= number_format($participant['total_price'], 2, ',', ' ') ?> BYN</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Пока нет участников.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Вкладка присоединения -->
        <?php if ($active_tab === 'join' && isset($_SESSION['user_id']) && $order['status'] === 'open' && $remaining_quantity > 0): ?>
            <?php $max_quantity = min(floor($remaining_quantity), 100); ?>
            <div class="card">
                <h3 class="section-title">Присоединиться к закупке</h3>
                <form id="join-order-form">
                    <input type="hidden" name="order_id" value="<?= $id ?>">
                    <div class="form-group">
                        <label class="form-label">Количество товара (до <?= $max_quantity ?> шт.)</label>
                        <input type="number" class="form-control" name="quantity" min="1" max="<?= $max_quantity ?>" value="1" required 
                               oninput="calculatePrice(this.value)">
                        <div style="margin-top: 8px;">
                            <small>Стоимость: <span id="calculated-price"><?= number_format($order['product_price'], 2, ',', ' ') ?></span> BYN</small>
                        </div>
                    </div>
                    <button type="submit" class="btn">Присоединиться к закупке</button>
                </form>
                <div id="join-message" style="margin-top: 15px;"></div>
            </div>
        <?php endif; ?>

        <!-- Вкладка рекомендаций -->
        <?php if ($active_tab === 'recommendations' && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $order['organizer_id'] && $order['status'] === 'open'): ?>
            <?php include 'includes/smart_algorithms.php'; ?>
            
            <?php $potential_data = get_potential_participants($id, 6); ?>
            <?php if (!empty($potential_data['participants'])): ?>
                <div class="card">
                    <h3 class="section-title">Рекомендуемые участники</h3>
                    <p>Система рекомендует этих пользователей для приглашения в закупку:</p>
                    
                    <div class="recommendations-list">
                        <?php foreach ($potential_data['participants'] as $participant): ?>
                            <?php 
                            $has_participated = has_user_participated($participant['user']['id'], $id);
                            $status_class = $has_participated ? 'participated' : '';
                            $status_text = $has_participated ? 'Уже участвует' : 'Можно пригласить';
                            ?>
                            <div class="recommended-user-item <?= $status_class ?>">
                                <div class="user-main-info">
                                    <strong><?= htmlspecialchars($participant['user']['name']) ?></strong>
                                    <span class="user-score"><?= $participant['score'] ?>/100</span>
                                </div>
                                
                                <div class="user-details">
                                    <div class="user-stats">
                                        <span>Участий: <?= $participant['user']['total_participations'] ?></span>
                                        <span>В среднем: <?= round($participant['user']['avg_quantity'], 1) ?> шт.</span>
                                        <span>Недавно: <?= $participant['user']['recent_participations'] ?> раз</span>
                                    </div>
                                    
                                    <div class="user-actions">
                                        <span class="status-badge"><?= $status_text ?></span>
                                        <?php if (!$has_participated): ?>
                                            <button class="btn-invite" onclick="sendInvitation(<?= $participant['user']['id'] ?>, '<?= htmlspecialchars($participant['user']['name']) ?>', '<?= htmlspecialchars($participant['user']['email']) ?>')">
                                                Пригласить
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>Нет рекомендаций для этой закупки.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Вкладка обсуждения -->
        <?php if ($active_tab === 'discussion'): ?>
            <div class="card">
                <h3 class="section-title">Обсуждение закупки</h3>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <form id="message-form" style="margin-bottom: 30px;">
                        <input type="hidden" name="group_order_id" value="<?= $id ?>">
                        <div class="form-group">
                            <label class="form-label">Ваше сообщение</label>
                            <textarea class="form-control" name="text" required placeholder="Введите сообщение для участников закупки" maxlength="500"></textarea>
                        </div>
                        <button type="submit" class="btn">Отправить сообщение</button>
                    </form>
                <?php endif; ?>
                
                <?php
                $stmt = $conn->prepare("
                    SELECT m.*, u.name AS username 
                    FROM messages m
                    JOIN users u ON m.sender_id = u.id
                    WHERE m.group_order_id = ?
                    ORDER BY m.created_at ASC
                ");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $messages = $stmt->get_result();
                
                if ($messages->num_rows > 0): ?>
                    <div class="messages-list">
                        <?php while ($msg = $messages->fetch_assoc()): 
                            $initial = mb_substr($msg['username'], 0, 1, 'UTF-8');
                        ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <div class="message-author">
                                        <div class="message-avatar"><?= strtoupper($initial) ?></div>
                                        <?= htmlspecialchars($msg['username']) ?>
                                    </div>
                                    <span class="message-time"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></span>
                                </div>
                                <p class="message-text"><?= nl2br(htmlspecialchars($msg['text'])) ?></p>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Пока нет сообщений. Будьте первым, кто оставит комментарий!</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function calculatePrice(quantity) {
    let qty = parseInt(quantity) || 0;
    let price = <?= $order['product_price'] ?? 0 ?>;
    let total = qty * price;
    document.getElementById('calculated-price').textContent = total.toFixed(2).replace('.', ',');
}

// AJAX отправка сообщения
document.getElementById('message-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const text = this.text.value.trim();
    const groupOrderId = this.group_order_id.value;
    
    if (!text) {
        alert('Введите текст сообщения');
        return false;
    }
    if (text.length > 500) {
        alert('Сообщение не должно превышать 500 символов');
        return false;
    }

    const submitButton = this.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.innerHTML = 'Отправка...';
    submitButton.disabled = true;

    // Отправляем сообщение через AJAX
    fetch('send_message_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `group_order_id=${groupOrderId}&text=${encodeURIComponent(text)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Очищаем поле ввода
            this.text.value = '';
            
            // Добавляем новое сообщение в список
            addMessageToChat(data.message);
            
            // Показываем уведомление
            showNotification('Сообщение отправлено!', 'success');
        } else {
            showNotification('Ошибка: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Ошибка сети', 'error');
    })
    .finally(() => {
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
});

// Функция для добавления сообщения в чат
function addMessageToChat(messageData) {
    const messagesList = document.querySelector('.messages-list');
    const emptyState = document.querySelector('.empty-state');
    
    // Убираем пустое состояние если оно есть
    if (emptyState) {
        emptyState.remove();
    }
    
    // Создаем HTML для нового сообщения
    const initial = messageData.username.charAt(0).toUpperCase();
    const messageHTML = `
        <div class="message-item">
            <div class="message-header">
                <div class="message-author">
                    <div class="message-avatar">${initial}</div>
                    ${messageData.username}
                </div>
                <span class="message-time">${messageData.time}</span>
            </div>
            <p class="message-text">${messageData.text.replace(/\n/g, '<br>')}</p>
        </div>
    `;
    
    // Добавляем сообщение в конец списка
    if (messagesList) {
        messagesList.insertAdjacentHTML('beforeend', messageHTML);
        
        // Прокручиваем к новому сообщению
        const newMessage = messagesList.lastElementChild;
        newMessage.scrollIntoView({ behavior: 'smooth' });
    } else {
        // Если списка нет, создаем его
        const card = document.querySelector('.card');
        const form = document.getElementById('message-form');
        const messagesHTML = `
            <div class="messages-list">
                ${messageHTML}
            </div>
        `;
        form.insertAdjacentHTML('afterend', messagesHTML);
    }
}

document.getElementById('join-order-form')?.addEventListener('submit', function(e){
    e.preventDefault();
    let quantity = parseInt(this.quantity.value);
    let orderId = parseInt(this.order_id.value);
    let maxQuantity = parseInt(this.quantity.max);
    
    if(quantity <= 0 || quantity > maxQuantity) {
        document.getElementById('join-message').innerText = 'Введите корректное количество (1-' + maxQuantity + ' шт.)';
        return;
    }

    let xhr = new XMLHttpRequest();
    xhr.open("POST", "join_order_ajax.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function(){
        if(xhr.status === 200){
            let res = JSON.parse(xhr.responseText);
            document.getElementById('join-message').innerText = res.message;
            if(res.success){
                setTimeout(() => location.reload(), res.completed ? 1500 : 1000);
            }
        } else {
            document.getElementById('join-message').innerText = 'Ошибка сети, попробуйте ещё раз.';
        }
    }
    xhr.send("order_id=" + orderId + "&quantity=" + quantity);
});

function sendInvitation(userId, userName, userEmail) {
    if (!confirm(`Отправить приглашение пользователю ${userName}?`)) {
        return;
    }
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = 'Отправка...';
    button.disabled = true;
    
    fetch('send_invitation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `order_id=<?= $id ?>&user_id=${userId}&user_email=${encodeURIComponent(userEmail)}&user_name=${encodeURIComponent(userName)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.innerHTML = 'Приглашение отправлено';
            button.style.background = '#28a745';
            showNotification('Приглашение успешно отправлено!', 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            button.innerHTML = originalText;
            button.disabled = false;
            showNotification('Ошибка: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.innerHTML = originalText;
        button.disabled = false;
        showNotification('Ошибка сети', 'error');
    });
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        max-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    if (type === 'success') {
        notification.style.background = '#28a745';
    } else if (type === 'error') {
        notification.style.background = '#dc3545';
    } else {
        notification.style.background = '#17a2b8';
    }
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 4000);
}

calculatePrice(1);
</script>

<?php include 'includes/footer.php'; ?>