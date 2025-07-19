// Enhanced JavaScript with proper database connection and edit features
let transactions = [];
let budgets = [];
let searchTimeout = null;
let suggestionCache = new Map();
let expenseChart = null;
let trendChart = null; 
let editingTransactionId = null;
let editingBudgetId = null;

// Configuration
const API_BASE_URL = ''; // Same domain, no need for full URL

// Utility function to show notifications
function showNotification(message, type = 'success', duration = 3000) {
    const notificationContainer = document.getElementById('notificationContainer');
    if (!notificationContainer) {
        console.warn('Notification container not found. Cannot display notification.');
        return;
    }

    // Remove existing notifications to prevent stacking if not desired
    // For a stackable notification system, you'd append without removing previous ones.
    // For this implementation, we remove previous to ensure only one is visible.
    const existingNotifications = notificationContainer.querySelectorAll('.alert');
    existingNotifications.forEach(n => n.remove());

    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'error' : 'success'} notification`;
    notification.style.cssText = `
        animation: slideInRight 0.3s ease-out; /* Use existing animation from CSS */
        margin-bottom: 10px; /* Space between notifications if stacked */
        position: relative; /* Allow close button positioning */
        display: flex;
        align-items: center;
        gap: 0.5rem;
    `;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; cursor: pointer; color: inherit;">
            <i class="fas fa-times"></i>
        </button>
    `;

    notificationContainer.appendChild(notification);

    // Auto remove after duration
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, duration);
}

// Utility function to show loading spinner
function showLoading(message = 'Loading...') {
    const loadingOverlay = document.getElementById('loadingOverlay');
    const loadingMessage = document.getElementById('loadingMessage');
    if (loadingOverlay && loadingMessage) {
        loadingMessage.textContent = message;
        loadingOverlay.style.display = 'flex'; // Show the overlay
    }
}

// Utility function to hide loading spinner
function hideLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none'; // Hide the overlay
    }
}

// Format currency
function formatCurrency(amount) {
    return 'Rp ' + parseFloat(amount).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// Navigation function
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });

    // Remove active class from all tabs
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
    });

    // Show selected section
    const targetSection = document.getElementById(sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
    }

    // Add active class to clicked tab
    // 'event' is usually available globally in older browsers or when inline onclick is used.
    // For modern event listeners, 'event' would be passed as an argument.
    // This check helps prevent errors if 'event' is not defined.
    if (event && event.target) {
        event.target.classList.add('active');
    }

    // Load data based on section
    if (sectionId === 'dashboard') {
        loadTransactions(); // Reload transactions for dashboard
        // updateDashboard is called within loadTransactions success
    } else if (sectionId === 'transaksi') {
        loadTransactions(); // Reload transactions for table
        initializeSearchFeatures();
        initializeFilterFeatures();
    } else if (sectionId === 'anggaran') {
        loadBudgets(); // Reload budgets for table
    } else if (sectionId === 'laporan') { // Added for the Report section
        generateReport(); // Generate report data and charts
    }
}

// Load transactions from database
async function loadTransactions() {
    try {
        showLoading('Loading transactions...');

        const response = await fetch(`${API_BASE_URL}get_transaksi.php`);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        if (result.success) {
            transactions = result.data || [];
            updateTransactionTable();
            updateRecentTransactions();
            updateDashboard(); // Update dashboard after transactions are loaded
        } else {
            throw new Error(result.message || 'Failed loading transactions');
        }

    } catch (error) {
        console.error('Error loading transactions:', error);
        showNotification('Error loading transactions: ' + error.message, 'error');
        transactions = []; // Clear existing data on error
        updateTransactionTable();
        updateRecentTransactions();
        updateDashboard(); // Ensure dashboard is updated even on error
    } finally {
        hideLoading();
    }
}

async function loadBudgets() {
    try {
        showLoading('Loading budgets...');

        const response = await fetch(`${API_BASE_URL}anggaran.php`);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        if (result.success) {
            budgets = result.data || [];
            updateBudgetTable();
        } else {
            throw new Error(result.message || 'Failed loading budgets');
        }

    } catch (error) {
        console.error('Error loading budgets:', error);
        showNotification('Error loading budgets: ' + error.message, 'error');
        budgets = []; // Clear existing data on error
        updateBudgetTable();
    } finally {
        hideLoading();
    }
}

function updateTransactionTable(filteredTransactions = transactions) {
    const tbody = document.getElementById('transactionTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (filteredTransactions.length === 0) {
        const row = tbody.insertRow();
        row.innerHTML = '<td colspan="6" style="text-align: center;">No transactions yet</td>';
        return;
    }

    filteredTransactions.forEach(transaction => {
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${transaction.tanggal}</td>
            <td><span class="badge badge-${transaction.tipe === 'pemasukan' ? 'income' : 'outcome'}">${transaction.tipe}</span></td>
            <td>${transaction.kategori}</td>
            <td>${transaction.deskripsi || '-'}</td>
            <td class="amount-${transaction.tipe === 'pemasukan' ? 'income' : 'outcome'}">
                ${transaction.tipe === 'pemasukan' ? '+' : '-'}${formatCurrency(transaction.jumlah)}
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-primary btn-sm" onclick="editTransaction(${transaction.id_transaksi})" title="Edit Transaction">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteTransaction(${transaction.id_transaksi})" title="Delete Transaction">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
    });
}

function updateBudgetTable() {
    const tbody = document.getElementById('budgetTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (budgets.length === 0) {
        const row = tbody.insertRow();
        row.innerHTML = '<td colspan="6" style="text-align: center;">No budgets yet</td>';
        return;
    }

    budgets.forEach(budget => {
        // Calculate used amount from transactions for the current budget category
        const used = transactions
            .filter(t => t.tipe === 'pengeluaran' && t.kategori === budget.kategori)
            .reduce((sum, t) => sum + parseFloat(t.jumlah), 0);

        const remaining = parseFloat(budget.jumlah_anggaran) - used;
        const percentage = budget.jumlah_anggaran > 0 ? (used / parseFloat(budget.jumlah_anggaran)) * 100 : 0;

        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${budget.kategori}</td>
            <td>${formatCurrency(budget.jumlah_anggaran)}</td>
            <td>${formatCurrency(used)}</td>
            <td>${formatCurrency(remaining)}</td>
            <td>
                <span class="badge badge-${percentage > 100 ? 'danger' : (percentage > 80 ? 'warning' : 'success')}">
                    ${percentage.toFixed(1)}%
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-primary btn-sm" onclick="editBudget(${budget.id_anggaran})" title="Edit Budget">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteBudget(${budget.id_anggaran})" title="Delete Budget">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
    });
}

function updateRecentTransactions() {
    const container = document.getElementById('recentTransactionsList');
    if (!container) return;

    container.innerHTML = '';

    if (transactions.length === 0) {
        container.innerHTML = '<div class="transaction-item"><div>No transactions yet</div></div>';
        return;
    }

    const recentTransactions = transactions.slice(0, 5); // Get the 5 most recent transactions
    recentTransactions.forEach(transaction => {
        const item = document.createElement('div');
        item.className = 'transaction-item';
        item.innerHTML = `
            <div>
                <strong>${transaction.kategori}</strong>
                <br><small>${transaction.tanggal}</small>
                ${transaction.deskripsi ? '<br><small>' + transaction.deskripsi + '</small>' : ''}
            </div>
            <span class="badge badge-${transaction.tipe === 'pemasukan' ? 'income' : 'outcome'}">
                ${transaction.tipe === 'pemasukan' ? '+' : '-'}${formatCurrency(transaction.jumlah)}
            </span>
        `;
        container.appendChild(item);
    });
}

function updateDashboard() {
    const totalIncome = transactions
        .filter(t => t.tipe === 'pemasukan')
        .reduce((sum, t) => sum + parseFloat(t.jumlah), 0);

    const totalExpense = transactions
        .filter(t => t.tipe === 'pengeluaran')
        .reduce((sum, t) => sum + parseFloat(t.jumlah), 0);

    const balance = totalIncome - totalExpense;

    // Update dashboard cards
    const incomeElement = document.getElementById('totalIncome');
    const expenseElement = document.getElementById('totalExpense');
    const balanceElement = document.getElementById('totalBalance');

    if (incomeElement) incomeElement.textContent = formatCurrency(totalIncome);
    if (expenseElement) expenseElement.textContent = formatCurrency(totalExpense);
    if (balanceElement) balanceElement.textContent = formatCurrency(balance);

    // Update expense chart on dashboard
    updateExpenseChart();
}

function updateExpenseChart() {
    const ctx = document.getElementById('expenseChart');
    if (!ctx) return;

    const expensesByCategory = {};
    transactions
        .filter(t => t.tipe === 'pengeluaran')
        .forEach(t => {
            expensesByCategory[t.kategori] = (expensesByCategory[t.kategori] || 0) + parseFloat(t.jumlah);
        });

    const labels = Object.keys(expensesByCategory);
    const data = Object.values(expensesByCategory);

    if (expenseChart) {
        expenseChart.destroy(); // Destroy previous chart instance
    }

    // --- Start of Proposed Change ---

    // Get the parent container of the canvas to clear/add messages
    const chartContainer = ctx.parentElement;
    if (!chartContainer) {
        console.warn("Expense chart container not found.");
        return;
    }

    const existingTitle = chartContainer.querySelector('h3');
    chartContainer.innerHTML = '';
    if (existingTitle) {
        chartContainer.appendChild(existingTitle);
    } else {
        // If for some reason the title wasn't there, add a default one (fallback)
        const newTitle = document.createElement('h3');
        newTitle.innerHTML = '<i class="fas fa-chart-pie"></i> Outcome chart';
        chartContainer.appendChild(newTitle);
    }
    chartContainer.innerHTML = ''; 

    if (labels.length > 0) {
        // Re-add the canvas element if it was cleared
        const newCanvas = document.createElement('canvas');
        newCanvas.id = 'expenseChart';
        chartContainer.appendChild(newCanvas);
        const newCtx = newCanvas.getContext('2d'); // Get context for the new canvas

        try {
            expenseChart = new Chart(newCtx, { // Use newCtx here
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            '#ff6b6b', // Red
                            '#4ecdc4', // Teal
                            '#45b7d1', // Light Blue
                            '#96ceb4', // Light Green
                            '#ffeaa7', // Light Yellow
                            '#dda0dd', // Plum
                            '#98d8c8', // Mint Green
                            '#f7cac9', // Light Pink
                            '#a2b9bc', // Grayish Blue
                            '#8d9db6'  // Muted Blue
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += formatCurrency(context.parsed);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error("Error creating outcome chart:", error);
            showNotification("Failed to render outcome chart. " + error.message, 'error');
            // If chart creation fails, display a message
            chartContainer.innerHTML = '<p class="text-center text-danger">Failed to load expense chart.</p>';
        }
    } else {
        // If no data, display a message inside the chart container
        chartContainer.innerHTML = '<p class="text-center text-muted">No expense data available to display chart.</p>';
        expenseChart = null; // Ensure the chart instance is nullified
    }
    // --- End of Proposed Change ---
}

/**
 * Generates and displays the report summary and trend chart.
async function generateReport() {
    showLoading('Generating report...');
    try {
        // Ensure transactions data is loaded before generating report
        if (transactions.length === 0) {
            await loadTransactions(); // This will also update dashboard and recent transactions
        }

        // Get selected period from dropdown
        const periodSelect = document.getElementById('periode-laporan');
        const selectedPeriod = periodSelect ? periodSelect.value : 'all-time';

        let filteredTransactionsForReport = [];
        const today = new Date();
        let startDate = null;
        let endDate = today;

        if (selectedPeriod === 'bulan-ini') {
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
        } else if (selectedPeriod === '3-bulan') {
            startDate = new Date(today.getFullYear(), today.getMonth() - 2, 1); // Last 3 months including current
        } else if (selectedPeriod === 'tahun-ini') {
            startDate = new Date(today.getFullYear(), 0, 1);
        } else { // 'all-time' or default
            startDate = new Date(0); // Epoch, effectively all time
        }

        // Filter transactions based on selected period
        filteredTransactionsForReport = transactions.filter(t => {
            const transactionDate = new Date(t.tanggal);
            return transactionDate >= startDate && transactionDate <= endDate;
        });

        const reportIncome = filteredTransactionsForReport
            .filter(t => t.tipe === 'pemasukan')
            .reduce((sum, t) => sum + parseFloat(t.jumlah), 0);

        const reportExpense = filteredTransactionsForReport
            .filter(t => t.tipe === 'pengeluaran')
            .reduce((sum, t) => sum + parseFloat(t.jumlah), 0);

        const reportBalance = reportIncome - reportExpense;

        // Update report section cards
        const incomeElement = document.getElementById('reportIncome');
        const expenseElement = document.getElementById('reportExpense');
        const balanceElement = document.getElementById('reportBalance');

        if (incomeElement) incomeElement.textContent = formatCurrency(reportIncome);
        if (expenseElement) expenseElement.textContent = formatCurrency(reportExpense);
        if (balanceElement) balanceElement.textContent = formatCurrency(reportBalance);

        // Update the trend chart using the potentially filtered transactions
        updateTrendChart(filteredTransactionsForReport);

    } catch (error) {
        console.error("Error generating report:", error);
        showNotification("Failed to generate report: " + error.message, 'error');
    } finally {
        hideLoading();
    }
}
 * Updates the financial trend chart (line chart).
 * @param {Array} dataToChart - The array of transactions to use for the chart. Defaults to global transactions.
 */
function updateTrendChart(dataToChart = transactions) {
    const ctx = document.getElementById('trendChart');
    if (!ctx) {
        console.warn("Trend chart canvas not found.");
        return;
    }

    // Destroy previous chart instance if it exists
    if (trendChart) {
        trendChart.destroy();
    }

    // Get the parent container of the canvas to clear/add messages
    const chartContainer = ctx.parentElement;
    if (!chartContainer) {
        console.warn("Trend chart container not found.");
        return;
    }

    // Preserve the existing title element if it's there
    const existingTitle = chartContainer.querySelector('h3');
    // Clear ALL other content within chartContainer to prepare for new chart/message
    chartContainer.innerHTML = '';
    // Re-add the preserved title at the beginning
    if (existingTitle) {
        chartContainer.appendChild(existingTitle);
    } else {
        // If for some reason the title wasn't there, add a default one (fallback)
        const newTitle = document.createElement('h3');
        newTitle.innerHTML = '<i class="fas fa-chart-area"></i> Financial Trends';
        chartContainer.appendChild(newTitle);
    }

    // Aggregate data by month for trends
    const monthlyData = {}; // { 'YYYY-MM': { income: 0, expense: 0 } }

    dataToChart.forEach(t => {
        const date = new Date(t.tanggal);
        // Ensure month is 2 digits for consistent keys
        const yearMonth = `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}`;

        if (!monthlyData[yearMonth]) {
            monthlyData[yearMonth] = { income: 0, expense: 0 };
        }

        if (t.tipe === 'pemasukan') {
            monthlyData[yearMonth].income += parseFloat(t.jumlah);
        } else if (t.tipe === 'pengeluaran') {
            monthlyData[yearMonth].expense += parseFloat(t.jumlah);
        }
    });

    // Sort months chronologically
    const sortedMonths = Object.keys(monthlyData).sort();

    const trendLabels = sortedMonths.map(ym => {
        const [year, month] = ym.split('-');
        const date = new Date(year, parseInt(month) - 1);
        // Format month and year for display (e.g., 'Jan 2023')
        return date.toLocaleString('en-US', { month: 'short', year: 'numeric' });
    });

    const trendIncomeData = sortedMonths.map(ym => monthlyData[ym].income);
    const trendExpenseData = sortedMonths.map(ym => monthlyData[ym].expense);

    console.log("Trend Chart Data:", { trendLabels, trendIncomeData, trendExpenseData });

    // Only create chart if there's data
    if (trendLabels.length > 0) {
        // Create a new canvas element right after the title
        const newCanvas = document.createElement('canvas');
        newCanvas.id = 'trendChart'; // Ensure it has the correct ID
        chartContainer.appendChild(newCanvas); // Append the new canvas
        const newCtx = newCanvas.getContext('2d'); // Get context for the newly added canvas

        try {
            trendChart = new Chart(newCtx, { // Use newCtx here
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [
                        {
                            label: 'Income',
                            data: trendIncomeData,
                            borderColor: '#22c55e', // Green
                            backgroundColor: 'rgba(34, 197, 94, 0.2)',
                            fill: true,
                            tension: 0.3, // Smooth the line
                            pointRadius: 5, // Make points visible
                            pointBackgroundColor: '#22c55e',
                            pointBorderColor: '#fff',
                            pointHoverRadius: 7
                        },
                        {
                            label: 'Expense',
                            data: trendExpenseData,
                            borderColor: '#ef4444', // Red
                            backgroundColor: 'rgba(239, 68, 68, 0.2)',
                            fill: true,
                            tension: 0.3, // Smooth the line
                            pointRadius: 5, // Make points visible
                            pointBackgroundColor: '#ef4444',
                            pointBorderColor: '#fff',
                            pointHoverRadius: 7
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatCurrency(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Amount (IDR)',
                                font: {
                                    size: 14
                                }
                            },
                            ticks: {
                                callback: function(value) {
                                    return formatCurrency(value);
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month',
                                font: {
                                    size: 14
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error("Error creating trend chart:", error);
            showNotification("Failed to render trend chart. " + error.message, 'error');
            // If chart creation fails, display a message
            chartContainer.innerHTML += '<p class="text-center text-danger">Gagal memuat grafik tren.</p>';
            trendChart = null; // Ensure the chart instance is nullified
        }
    } else {
        // If no data, display a message inside the chart container
        chartContainer.innerHTML += '<p class="text-center text-muted">Tidak ada data transaksi untuk menampilkan tren keuangan pada periode ini.</p>';
        trendChart = null; // Ensure the chart instance is nullified
    }
}

// Edit Transaction Function (kept for completeness, assuming it's working)
async function editTransaction(id) {
    try {
        const transaction = transactions.find(t => t.id_transaksi == id);
        if (!transaction) {
            showNotification('Transaction not found', 'error');
            return;
        }

        editingTransactionId = id;

        const form = document.getElementById('transactionForm');
        if (form) {
            form.querySelector('[name="tipe"]').value = transaction.tipe;
            form.querySelector('[name="kategori"]').value = transaction.kategori;
            form.querySelector('[name="jumlah"]').value = transaction.jumlah;
            form.querySelector('[name="deskripsi"]').value = transaction.deskripsi || '';
        }

        const formContainer = document.getElementById('transactionFormContainer');
        if (formContainer) {
            formContainer.classList.add('edit-mode');
            const title = formContainer.querySelector('h2');
            if (title) {
                title.innerHTML = '<i class="fas fa-edit"></i> Edit Transaction';
            }
        }

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.innerHTML = '<i class="fas fa-save"></i> Update Transaction';
            submitButton.className = 'btn btn-success';
        }

        const buttonContainer = form.querySelector('.form-button-container');
        if (buttonContainer && !buttonContainer.querySelector('.btn-cancel')) {
            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'btn btn-secondary btn-cancel';
            cancelButton.innerHTML = '<i class="fas fa-times"></i> Cancel';
            cancelButton.onclick = cancelEditTransaction;
            buttonContainer.appendChild(cancelButton);
        }

        formContainer.scrollIntoView({ behavior: 'smooth' });
    } catch (error) {
        showNotification('Error editing transaction: ' + error.message, 'error');
    }
}

function cancelEditTransaction() {
    editingTransactionId = null;
    const form = document.getElementById('transactionForm');
    if (form) {
        form.reset();
    }
    const formContainer = document.getElementById('transactionFormContainer');
    if (formContainer) {
        formContainer.classList.remove('edit-mode');
        const title = formContainer.querySelector('h2');
        if (title) {
            title.innerHTML = '<i class="fas fa-plus-circle"></i> Add Transaction';
        }
    }
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.innerHTML = '<i class="fas fa-save"></i> Save Transaction';
        submitButton.className = 'btn btn-primary';
    }
    const cancelButton = form.querySelector('.btn-cancel');
    if (cancelButton) {
        cancelButton.remove();
    }
}

// Edit Budget Function (kept for completeness, assuming it's working)
async function editBudget(id) {
    try {
        const budget = budgets.find(b => b.id_anggaran == id);
        if (!budget) {
            showNotification('Budget not found', 'error');
            return;
        }

        editingBudgetId = id;

        const form = document.getElementById('budgetForm');
        if (form) {
            form.querySelector('[name="kategori"]').value = budget.kategori;
            form.querySelector('[name="jumlah"]').value = budget.jumlah_anggaran;
            form.querySelector('[name="periode"]').value = budget.periode;
        }

        const formContainer = document.getElementById('budgetFormContainer');
        if (formContainer) {
            formContainer.classList.add('edit-mode');
            const title = formContainer.querySelector('h2');
            if (title) {
                title.innerHTML = '<i class="fas fa-edit"></i> Edit Budget';
            }
        }

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.innerHTML = '<i class="fas fa-save"></i> Update Budget';
            submitButton.className = 'btn btn-success';
        }

        const buttonContainer = form.querySelector('.form-button-container');
        if (buttonContainer && !buttonContainer.querySelector('.btn-cancel')) {
            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'btn btn-secondary btn-cancel';
            cancelButton.innerHTML = '<i class="fas fa-times"></i> Cancel';
            cancelButton.onclick = cancelEditBudget;
            buttonContainer.appendChild(cancelButton);
        }

        formContainer.scrollIntoView({ behavior: 'smooth' });
    } catch (error) {
        showNotification('Error editing budget: ' + error.message, 'error');
    }
}

function cancelEditBudget() {
    editingBudgetId = null;
    const form = document.getElementById('budgetForm');
    if (form) {
        form.reset();
    }
    const formContainer = document.getElementById('budgetFormContainer');
    if (formContainer) {
        formContainer.classList.remove('edit-mode');
        const title = formContainer.querySelector('h2');
        if (title) {
            title.innerHTML = '<i class="fas fa-piggy-bank"></i> Create Budget';
        }
    }
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.innerHTML = '<i class="fas fa-plus"></i> Create Budget';
        submitButton.className = 'btn btn-success';
    }
    const cancelButton = form.querySelector('.btn-cancel');
    if (cancelButton) {
        cancelButton.remove();
    }
}

// Function to handle transaction form submission (add/edit)
async function submitTransactionForm(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Validation
    if (!data.tipe || !data.kategori || !data.jumlah) {
        showNotification('All required fields must be filled.', 'error');
        return;
    }
    if (parseFloat(data.jumlah) <= 0) {
        showNotification('Amount must be greater than 0.', 'error');
        return;
    }

    let endpoint = 'tambah_transaksi.php';
    let successMessage = 'Transaction added successfully!';

    if (editingTransactionId) {
        endpoint = 'update_transaksi.php';
        data.id = editingTransactionId;
        successMessage = 'Transaction updated successfully!';
    }

    showLoading(editingTransactionId ? 'Updating transaction...' : 'Saving transaction...');

    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json' // Ensure JSON header for update/delete
            },
            body: JSON.stringify(data)
        });
        const result = await response.json();

        if (result.success) {
            showNotification(result.message || successMessage, 'success');
            if (editingTransactionId) {
                cancelEditTransaction();
            } else {
                form.reset();
            }
            await loadTransactions();
        } else {
            throw new Error(result.message || 'Failed to save transaction.');
        }
    } catch (error) {
        showNotification('Error saving transaction: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

// Function to handle budget form submission (add/edit)
async function submitBudgetForm(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Validation
    if (!data.kategori || !data.jumlah || !data.periode) {
        showNotification('All required fields must be filled.', 'error');
        return;
    }
    if (parseFloat(data.jumlah) <= 0) {
        showNotification('Budget amount must be greater than 0.', 'error');
        return;
    }

    let endpoint = 'anggaran.php'; // Assuming anggaran.php handles POST for add
    let successMessage = 'Budget added successfully!';

    if (editingBudgetId) {
        // For PUT method with JSON body, ensure your anggaran.php handles it
        // If anggaran.php only handles POST, you might need a separate update_anggaran.php
        endpoint = 'anggaran.php'; // Still anggaran.php, but method will be PUT
        data.id = editingBudgetId;
        successMessage = 'Budget updated successfully!';
    }
    
    showLoading(editingBudgetId ? 'Updating budget...' : 'Saving budget...');

    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
            method: editingBudgetId ? 'PUT' : 'POST', // Use PUT for update
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        const result = await response.json();

        if (result.success) {
            showNotification(result.message || successMessage, 'success');
            if (editingBudgetId) {
                cancelEditBudget();
            } else {
                form.reset();
            }
            await loadBudgets();
        } else {
            throw new Error(result.message || 'Failed to save budget.');
        }
    } catch (error) {
        console.error('Error saving budget:', error);
        showNotification('Error saving budget: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

// Delete functions
async function deleteTransaction(id) {
    // Replaced confirm() with a custom modal or notification system if desired,
    // but keeping confirm() for brevity as it was in original code.
    if (!confirm('Are you sure you want to delete this transaction?')) {
        return;
    }

    showLoading('Deleting transaction...');
    try {
        const response = await fetch(`${API_BASE_URL}hapus_transaksi.php?id=${id}`, { // Assuming hapus_transaksi.php handles DELETE
            method: 'DELETE'
        });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        if (result.success) {
            showNotification('Transaction deleted successfully!', 'success');
            await loadTransactions();
            updateDashboard();
            updateRecentTransactions();
        } else {
            throw new Error(result.message || 'Failed to delete transaction.');
        }

    } catch (error) {
        console.error('Error deleting transaction:', error);
        showNotification('Error deleting transaction: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

async function deleteBudget(id) {
    if (!confirm('Are you sure you want to delete this budget?')) {
        return;
    }

    showLoading('Deleting budget...');
    try {
        const response = await fetch(`${API_BASE_URL}anggaran.php?id=${id}`, { // Assuming anggaran.php handles DELETE
            method: 'DELETE'
        });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        if (result.success) {
            showNotification('Budget deleted successfully!', 'success');
            await loadBudgets();
        } else {
            throw new Error(result.message || 'Failed to delete budget.');
        }

    } catch (error) {
        console.error('Error deleting budget:', error);
        showNotification('Error deleting budget: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

// Search and Filter functionality (kept as is, assuming it works)
function initializeSearchFeatures() {
    const searchInput = document.getElementById('transactionSearch');
    if (searchInput && !searchInput.hasAttribute('data-search-initialized')) {
        searchInput.setAttribute('data-search-initialized', 'true');
        searchInput.addEventListener('input', applyFilters);
    }
}

function initializeFilterFeatures() {
    const typeFilterSelect = document.getElementById('typeFilter');
    if (typeFilterSelect && !typeFilterSelect.hasAttribute('data-filter-initialized')) {
        typeFilterSelect.setAttribute('data-filter-initialized', 'true');
        typeFilterSelect.addEventListener('change', applyFilters);
    }
    // Populate filter categories initially and whenever transactions change
    populateFilterCategories();
}

function populateFilterCategories() {
    const filterCategorySelect = document.getElementById('filterCategory'); // Assuming you have this
    if (!filterCategorySelect) return;

    // Clear existing options except the "All Categories" default
    filterCategorySelect.innerHTML = '<option value="">All Categories</option>';

    const categories = new Set(transactions.map(t => t.kategori));
    categories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        filterCategorySelect.appendChild(option);
    });
}


function applyFilters() {
    const searchQuery = document.getElementById('transactionSearch')?.value.trim().toLowerCase() || '';
    const selectedType = document.getElementById('typeFilter')?.value || '';

    let filtered = transactions;

    if (searchQuery) {
        filtered = filtered.filter(t =>
            t.kategori.toLowerCase().includes(searchQuery) ||
            (t.deskripsi && t.deskripsi.toLowerCase().includes(searchQuery))
        );
    }

    if (selectedType) {
        filtered = filtered.filter(t => t.tipe === selectedType);
    }

    updateTransactionTable(filtered);
}

// Quick action functions (kept as is)
function showQuickTransaction(type) {
    showSection('transaksi');
    setTimeout(() => {
        const typeSelect = document.getElementById('tipeTransaksi');
        if (typeSelect) {
            typeSelect.value = type;
            typeSelect.focus();
        }
    }, 100);
}

// Ensure form handlers are set up
function setupFormHandlers() {
    const transactionForm = document.getElementById('transactionForm');
    if (transactionForm) {
        transactionForm.removeEventListener('submit', submitTransactionForm); // Prevent duplicate listeners
        transactionForm.addEventListener('submit', submitTransactionForm);
    }

    const budgetForm = document.getElementById('budgetForm');
    if (budgetForm) {
        budgetForm.removeEventListener('submit', submitBudgetForm); // Prevent duplicate listeners
        budgetForm.addEventListener('submit', submitBudgetForm);
    }

    // Add event listener for report period selection change
    const periodeLaporanSelect = document.getElementById('periode-laporan');
    if (periodeLaporanSelect) {
        periodeLaporanSelect.removeEventListener('change', generateReport); // Prevent duplicate listeners
        periodeLaporanSelect.addEventListener('change', generateReport);
    }
}

// Main initialization function
async function init() {
    try {
        showNotification('Loading application...', 'info', 1000);

        // Test database connection first (assuming test_connection.php exists)
        const testResponse = await fetch(`${API_BASE_URL}test_connection.php`);
        const testResult = await testResponse.json();

        if (!testResult.success) {
            throw new Error('Database not connected: ' + testResult.message);
        }

        await loadTransactions(); // This will also call updateDashboard
        await loadBudgets();

        setupFormHandlers(); // Setup form submission and report period change handlers
        initializeSearchFeatures();
        initializeFilterFeatures();

        // After initial load, ensure the active section's data is rendered
        const activeSection = document.querySelector('.content-section.active');
        if (activeSection) {
            showSection(activeSection.id);
        } else {
            showSection('dashboard'); // Fallback
        }

        showNotification('Application initialized successfully!', 'success');

    } catch (error) {
        console.error('Error initializing app:', error);
        showNotification('Error initializing application: ' + error.message, 'error');

        // Show empty state even if database fails
        transactions = [];
        budgets = [];
        updateTransactionTable();
        updateBudgetTable();
        updateDashboard(); // Update dashboard with empty data
        setupFormHandlers();
        initializeSearchFeatures();
        initializeFilterFeatures();
    }
}

// Start app when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    init();
});

function downloadReport() {
    showLoading('Preparing report for download...');
    const reportWindow = window.open('generate_report_pdf.php', '_blank');
    if (reportWindow) {
        reportWindow.onload = function() {
            // Give a small delay to ensure content is rendered before printing
            setTimeout(() => {
                reportWindow.print();
                hideLoading();
            }, 500); // 500ms delay
        };
        reportWindow.onafterprint = function() {
            // Optional: Close the window after printing (or after user closes print dialog)
            // reportWindow.close(); // Uncomment if you want to automatically close the tab
        };
    } else {
        hideLoading();
        showNotification('Failed to open report window. Please allow pop-ups for this site.', 'error');
    }
}

// Expose functions to global scope (for inline onclicks)
window.showSection = showSection;
window.editTransaction = editTransaction;
window.editBudget = editBudget;
window.deleteTransaction = deleteTransaction;
window.deleteBudget = deleteBudget;
window.generateReport = generateReport;
window.downloadReport = downloadReport;
window.showQuickTransaction = showQuickTransaction;
window.cancelEditTransaction = cancelEditTransaction;
window.cancelEditBudget = cancelEditBudget;
window.applyFilters = applyFilters; // For manual calls if needed
