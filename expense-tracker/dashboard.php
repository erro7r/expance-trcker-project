<?php
require 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$display_name = strtok($username, '@'); // Get name before @

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_expense'])) {
        // Add new expense
        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, amount, description, category, expense_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $_POST['amount'],
            $_POST['description'],
            $_POST['category'],
            $_POST['expense_date']
        ]);
    } elseif (isset($_POST['update_expense'])) {
        // Update existing expense
        $stmt = $pdo->prepare("UPDATE expenses SET amount=?, description=?, category=?, expense_date=? WHERE id=? AND user_id=?");
        $stmt->execute([
            $_POST['amount'],
            $_POST['description'],
            $_POST['category'],
            $_POST['expense_date'],
            $_POST['expense_id'],
            $user_id
        ]);
        header("Location: dashboard.php");
        exit;
    }
}

// Handle expense deletion
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['delete'], $user_id]);
    header("Location: dashboard.php");
    exit;
}

// Get filter values
$category_filter = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search_query = $_GET['search'] ?? ''; // New search parameter

// Build query with filters
$query = "SELECT * FROM expenses WHERE user_id = ?";
$params = [$user_id];

if (!empty($category_filter)) {
    $query .= " AND category = ?";
    $params[] = $category_filter;
}

if (!empty($date_from)) {
    $query .= " AND expense_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND expense_date <= ?";
    $params[] = $date_to;
}

// Add search condition
if (!empty($search_query)) {
    $query .= " AND (description LIKE ? OR category LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY expense_date DESC";

// Fetch expenses
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Calculate total
$total = array_sum(array_column($expenses, 'amount'));

// Get distinct categories
$categories = $pdo->query("SELECT DISTINCT category FROM expenses WHERE user_id = $user_id")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Welcome, <?= htmlspecialchars($display_name) ?></h1>
            <div class="header-actions">
                <p class="total-expenses">Total: $<?= number_format($total, 2) ?></p>
                <a href="logout.php" class="btn">Logout</a>
            </div>
        </header>

        <section class="add-expense">
            <h2>Add New Expense</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Amount ($)</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="Food">Food</option>
                            <option value="Transport">Transport</option>
                            <option value="Entertainment">Entertainment</option>
                            <option value="Utilities">Utilities</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" required>
                </div>
                <div class="form-group">
                    <label for="expense_date">Date</label>
                    <input type="date" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <button type="submit" name="add_expense" class="btn">Add Expense</button>
            </form>
        </section>

        <section class="filters">
            <h2>Filter Expenses</h2>
            <form method="GET" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Search description or category" value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                    <div class="form-group">
                        <label for="category_filter">Category</label>
                        <select id="category_filter" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option <?= $category_filter === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">From</label>
                        <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">To</label>
                        <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Apply Filters</button>
                    <a href="dashboard.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </section>

        <section class="expenses-list">
            <h2>Your Expenses</h2>
            <?php if (empty($expenses)): ?>
                <p class="no-expenses">No expenses found</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($expense['expense_date'])) ?></td>
                                    <td><?= htmlspecialchars($expense['category']) ?></td>
                                    <td><?= htmlspecialchars($expense['description']) ?></td>
                                    <td>$<?= number_format($expense['amount'], 2) ?></td>
                                    <td class="actions">
                                        <button onclick="openEditModal(
                                            <?= $expense['id'] ?>,
                                            <?= $expense['amount'] ?>,
                                            '<?= addslashes($expense['description']) ?>',
                                            '<?= $expense['category'] ?>',
                                            '<?= $expense['expense_date'] ?>'
                                        )" class="btn btn-edit">Edit</button>
                                        <a href="dashboard.php?delete=<?= $expense['id'] ?>" class="btn btn-delete">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Edit Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Edit Expense</h2>
                <form method="POST">
                    <input type="hidden" name="expense_id" id="modalExpenseId">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modalAmount">Amount ($)</label>
                            <input type="number" id="modalAmount" name="amount" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="modalCategory">Category</label>
                            <select id="modalCategory" name="category" required>
                                <option value="Food">Food</option>
                                <option value="Transport">Transport</option>
                                <option value="Entertainment">Entertainment</option>
                                <option value="Utilities">Utilities</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modalDescription">Description</label>
                        <input type="text" id="modalDescription" name="description" required>
                    </div>
                    <div class="form-group">
                        <label for="modalDate">Date</label>
                        <input type="date" id="modalDate" name="expense_date" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_expense" class="btn">Save Changes</button>
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openEditModal(id, amount, description, category, date) {
                document.getElementById('modalExpenseId').value = id;
                document.getElementById('modalAmount').value = amount;
                document.getElementById('modalDescription').value = description;
                document.getElementById('modalCategory').value = category;
                document.getElementById('modalDate').value = date;
                document.getElementById('editModal').style.display = 'block';
            }

            function closeModal() {
                document.getElementById('editModal').style.display = 'none';
            }

            window.onclick = function(event) {
                const modal = document.getElementById('editModal');
                if (event.target === modal) {
                    closeModal();
                }
            }
        </script>
    </div>
</body>
</html>