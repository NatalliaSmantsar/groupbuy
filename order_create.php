<?php
session_start();
require 'includes/db.php';
require 'includes/validation.php';
require 'includes/smart_algorithms.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    echo "<div class='container'><div class='alert alert-error'>Требуются права организатора для создания закупок.</div></div>";
    include 'includes/footer.php';
    exit;
}

$preselected_product = intval($_GET['product_id'] ?? 0);
$error = '';
$success = '';

// Получаем рекомендации по оптимальному времени
$optimal_time_data = get_optimal_creation_time($_SESSION['user_id']);
$has_recommendations = !empty($optimal_time_data['top_recommendations']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $min_quantity = intval($_POST['min_quantity']);
    $product_id = intval($_POST['product_id']);

    $validation_errors = validate_order_data($title, $description, $min_quantity);
    
    if ($product_id <= 0) {
        $validation_errors[] = "Выберите товар";
    }

    if (empty($validation_errors)) {
        $product_stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
        $product_stmt->bind_param("i", $product_id);
        $product_stmt->execute();
        $product_result = $product_stmt->get_result();
        
        if ($product_result->num_rows > 0) {
            $product = $product_result->fetch_assoc();
            $product_price = $product['price'];
            $min_amount = $product_price * $min_quantity;

            $stmt = $conn->prepare("INSERT INTO group_orders (title, description, min_amount, status, current_amount, product_id, quantity, organizer_id, created_at) VALUES (?, ?, ?, 'open', 0, ?, ?, ?, NOW())");
            $stmt->bind_param("ssdiii", $title, $description, $min_amount, $product_id, $min_quantity, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success = "Закупка успешно создана! Необходимо собрать $min_quantity шт. для запуска.";
                $_POST = [];
            } else {
                $error = "Ошибка при создании закупки: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Товар не найден";
        }
        $product_stmt->close();
    } else {
        $error = implode("<br>", $validation_errors);
    }
}

$active_tab = $_GET['tab'] ?? 'create';
?>

<link rel="stylesheet" href="assets/css/tabs.css">
<link rel="stylesheet" href="assets/css/order_create.css">

<div class="container">
    <h1 class="page-title">Создать новую закупку</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="tabs-navigation create-order-tabs">
        <a href="?tab=create" class="tab-link <?= $active_tab === 'create' ? 'active' : '' ?>">
            Создание закупки
        </a>
        <a href="?tab=recommendations" class="tab-link <?= $active_tab === 'recommendations' ? 'active' : '' ?>">
            Рекомендации по времени
        </a>
    </div>

    <div class="tab-content">
        <?php if ($active_tab === 'create'): ?>
            <!-- Оригинальная форма создания закупки -->
            <form method="POST" class="form" id="create-order-form">
                <div class="form-group">
                    <label class="form-label" for="title">Название закупки</label>
                    <input type="text" class="form-control" id="title" name="title" placeholder="Введите название закупки" 
                           value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" 
                           maxlength="200" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Описание закупки</label>
                    <textarea class="form-control" id="description" name="description" placeholder="Опишите детали закупки, условия участия, сроки..." 
                              maxlength="1000" required rows="5"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="min_quantity">Минимальное количество для запуска (шт.)</label>
                    <input type="number" class="form-control" id="min_quantity" name="min_quantity" placeholder="10" 
                           min="1" max="1000" value="<?= htmlspecialchars($_POST['min_quantity'] ?? '10') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="product_id">Выберите товар</label>
                    <select class="form-control" id="product_id" name="product_id" required>
                        <option value="">-- Выберите товар из каталога --</option>
                        <?php
                        $res = $conn->query("SELECT id, name, price FROM products ORDER BY name ASC");
                        while($p = $res->fetch_assoc()) {
                            $selected = ($p['id'] == $preselected_product || $p['id'] == ($_POST['product_id'] ?? 0)) ? 'selected' : '';
                            echo "<option value='{$p['id']}' $selected data-price='{$p['price']}'>" . 
                                 htmlspecialchars($p['name']) . " (" . number_format($p['price'], 2, ',', ' ') . " BYN)" . 
                                 "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Создать закупку</button>
                </div>
            </form>

        <?php elseif ($active_tab === 'recommendations'): ?>
            <!-- Вкладка с рекомендациями -->
            <div class="card">
                <div class="card-content">
                    
                    <?php if ($has_recommendations): ?>
                        <div class="recommendation-card">
                            <div class="card-content">
                                <div class="recommendation-grid">
                                    <?php foreach ($optimal_time_data['top_recommendations'] as $index => $recommendation): ?>
                                    <div class="recommendation-item <?= $index === 0 ? 'recommendation-best' : '' ?>">
                                        <div class="recommendation-rank">#<?= $index + 1 ?></div>
                                        <div class="recommendation-time">
                                            <span class="recommendation-day"><?= $recommendation['day_name'] ?></span>
                                            <span class="recommendation-hour"><?= $recommendation['hour_display'] ?></span>
                                        </div>
                                        <div class="recommendation-stats">
                                            <div class="stat-item">
                                                <span class="stat-label">Эффективность:</span>
                                                <span class="stat-value"><?= $recommendation['efficiency_score'] ?>%</span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-label">Успешных:</span>
                                                <span class="stat-value"><?= $recommendation['success_count'] ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-label">Первый участник:</span>
                                                <span class="stat-value">~<?= $recommendation['avg_time_to_first'] ?>ч</span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <div class="text-muted">
                            <p>Рекомендации появятся после анализа ваших первых успешных закупок</p>
                            <div style="text-align: center; margin-top: 20px;">
                                <a href="?tab=create" class="btn btn-primary">Создать первую закупку</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('product_id').addEventListener('change', updateCalculation);
document.getElementById('min_quantity').addEventListener('input', updateCalculation);

function updateCalculation() {
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('min_quantity');
    const calculationInfo = document.getElementById('calculation-info');
    const calculationDetails = document.getElementById('calculation-details');
    
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    const price = parseFloat(selectedOption.getAttribute('data-price'));
    const quantity = parseInt(quantityInput.value) || 0;
    
    if (price && quantity > 0) {
        const totalAmount = price * quantity;
        calculationInfo.textContent = `Общая стоимость: ${totalAmount.toFixed(2)} BYN`;
        calculationDetails.textContent = `${quantity} шт. × ${price.toFixed(2)} BYN = ${totalAmount.toFixed(2)} BYN`;
    } else {
        calculationInfo.textContent = 'Выберите товар и укажите количество';
        calculationDetails.textContent = 'Стоимость будет рассчитана автоматически после выбора товара';
    }
}

document.getElementById('create-order-form').addEventListener('submit', function(e) {
    const quantity = parseInt(document.getElementById('min_quantity').value);
    const productId = document.getElementById('product_id').value;
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    
    if (!title) {
        e.preventDefault();
        alert('Введите название закупки');
        return false;
    }
    
    if (!description) {
        e.preventDefault();
        alert('Введите описание закупки');
        return false;
    }
    
    if (quantity <= 0 || quantity > 1000) {
        e.preventDefault();
        alert('Количество должно быть от 1 до 1000');
        return false;
    }
    
    if (!productId) {
        e.preventDefault();
        alert('Выберите товар');
        return false;
    }
});

// Инициализация расчета при загрузке
updateCalculation();
</script>

<?php include 'includes/footer.php'; ?>