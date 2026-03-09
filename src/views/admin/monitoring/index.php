<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoramento em Tempo Real - <?= Config::SITE_NAME ?></title>
    <link href="<?= Config::SITE_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include SRC_PATH . '/views/admin/components/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Monitoramento em Tempo Real</h1>
                <p>Acompanhe as métricas e alertas das rifas ativas</p>
                <div class="header-actions">
                    <button class="btn btn-outline" onclick="refreshMetrics()">
                        🔄 Atualizar
                    </button>
                    <button class="btn btn-primary" onclick="toggleAutoRefresh()">
                        ⏱️ Auto-refresh: <span id="auto-refresh-status">ON</span>
                    </button>
                </div>
            </header>
            
            <div class="content-section">
                <!-- Rifas Ativas -->
                <section class="active-raffles-section">
                    <h2>Rifas Ativas</h2>
                    <div id="active-raffles" class="raffles-grid">
                        <!-- Carregado via AJAX -->
                    </div>
                </section>
                
                <!-- Métricas Gerais -->
                <section class="metrics-section">
                    <h2>Métricas Gerais</h2>
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <div class="metric-icon">🎯</div>
                            <div class="metric-content">
                                <h3>Total de Rifas Ativas</h3>
                                <div class="metric-value" id="total-active-raffles">0</div>
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-icon">💰</div>
                            <div class="metric-content">
                                <h3>Faturamento do Dia</h3>
                                <div class="metric-value" id="daily-revenue">R$ 0,00</div>
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-icon">👥</div>
                            <div class="metric-content">
                                <h3>Participantes Únicos</h3>
                                <div class="metric-value" id="unique-participants">0</div>
                            </div>
                        </div>
                        
                        <div class="metric-card">
                            <div class="metric-icon">⏰</div>
                            <div class="metric-content">
                                <h3>Reservas Pendentes</h3>
                                <div class="metric-value" id="pending-reservations">0</div>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Alertas Críticos -->
                <section class="alerts-section">
                    <h2>Alertas Críticos</h2>
                    <div id="critical-alerts" class="alerts-container">
                        <!-- Carregado via AJAX -->
                    </div>
                </section>
                
                <!-- Gráfico de Vendas -->
                <section class="chart-section">
                    <h2>Evolução de Vendas (Últimas 24h)</h2>
                    <div class="chart-container">
                        <canvas id="sales-chart"></canvas>
                    </div>
                </section>
                
                <!-- Log de Eventos em Tempo Real -->
                <section class="events-section">
                    <h2>Eventos em Tempo Real</h2>
                    <div class="events-container">
                        <div class="events-header">
                            <div class="filter-tabs">
                                <button class="tab-btn active" onclick="filterEvents('all')">Todos</button>
                                <button class="tab-btn" onclick="filterEvents('payment')">Pagamentos</button>
                                <button class="tab-btn" onclick="filterEvents('reservation')">Reservas</button>
                                <button class="tab-btn" onclick="filterEvents('system')">Sistema</button>
                            </div>
                            <button class="btn btn-sm btn-outline" onclick="clearEvents()">Limpar</button>
                        </div>
                        <div id="events-log" class="events-log">
                            <!-- Eventos em tempo real -->
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
    
    <script>
        let autoRefresh = true;
        let refreshInterval;
        let currentFilter = 'all';
        let salesChart;
        
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            initializeMonitoring();
            startAutoRefresh();
        });
        
        // Inicializar monitoramento
        function initializeMonitoring() {
            loadActiveRaffles();
            loadMetrics();
            loadCriticalAlerts();
            loadSalesChart();
            loadRecentEvents();
        }
        
        // Carregar rifas ativas
        function loadActiveRaffles() {
            fetch('/admin/api/raffles/active')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('active-raffles');
                    container.innerHTML = '';
                    
                    if (data.raffles && data.raffles.length > 0) {
                        data.raffles.forEach(raffle => {
                            const card = createRaffleCard(raffle);
                            container.appendChild(card);
                        });
                    } else {
                        container.innerHTML = '<div class="empty-state">Nenhuma rifa ativa no momento</div>';
                    }
                    
                    // Atualizar métrica
                    document.getElementById('total-active-raffles').textContent = data.raffles?.length || 0;
                })
                .catch(error => {
                    console.error('Error loading active raffles:', error);
                    document.getElementById('active-raffles').innerHTML = 
                        '<div class="error-state">Erro ao carregar rifas ativas</div>';
                });
        }
        
        // Criar card de rifa
        function createRaffleCard(raffle) {
            const card = document.createElement('div');
            card.className = 'raffle-card';
            
            const salesPercentage = raffle.total_count > 0 
                ? Math.round((raffle.paid_count / raffle.total_count) * 100) 
                : 0;
            
            const timeRemaining = getTimeRemaining(raffle.end_sales_datetime);
            const statusColor = getStatusColor(salesPercentage);
            
            card.innerHTML = `
                <div class="raffle-header">
                    <h3>${htmlspecialchars(raffle.title)}</h3>
                    <span class="status-badge" style="background: ${statusColor}">
                        ${salesPercentage}% vendido
                    </span>
                </div>
                
                <div class="raffle-metrics">
                    <div class="metric-row">
                        <span class="metric-label">Vendidos:</span>
                        <span class="metric-value">${raffle.paid_count} / ${raffle.total_count}</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Faturamento:</span>
                        <span class="metric-value">${formatMoney(raffle.revenue || 0)}</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Tempo restante:</span>
                        <span class="metric-value">${timeRemaining}</span>
                    </div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${salesPercentage}%; background: ${statusColor}"></div>
                </div>
                
                <div class="raffle-actions">
                    <button class="btn btn-sm btn-outline" onclick="viewRaffleDetails(${raffle.id})">
                        Ver Detalhes
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="manageRaffle(${raffle.id})">
                        Gerenciar
                    </button>
                </div>
            `;
            
            return card;
        }
        
        // Carregar métricas gerais
        function loadMetrics() {
            fetch('/admin/api/metrics/general')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('daily-revenue').textContent = formatMoney(data.daily_revenue || 0);
                    document.getElementById('unique-participants').textContent = data.unique_participants || 0;
                    document.getElementById('pending-reservations').textContent = data.pending_reservations || 0;
                })
                .catch(error => console.error('Error loading metrics:', error));
        }
        
        // Carregar alertas críticos
        function loadCriticalAlerts() {
            fetch('/admin/api/alerts/critical')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('critical-alerts');
                    container.innerHTML = '';
                    
                    if (data.alerts && data.alerts.length > 0) {
                        data.alerts.forEach(alert => {
                            const alertElement = createAlertElement(alert);
                            container.appendChild(alertElement);
                        });
                    } else {
                        container.innerHTML = '<div class="empty-state">Nenhum alerta crítico</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading alerts:', error);
                    document.getElementById('critical-alerts').innerHTML = 
                        '<div class="error-state">Erro ao carregar alertas</div>';
                });
        }
        
        // Criar elemento de alerta
        function createAlertElement(alert) {
            const element = document.createElement('div');
            element.className = `alert-item alert-${alert.severity}`;
            
            const severityIcon = {
                'critical': '🚨',
                'high': '⚠️',
                'medium': '🟡',
                'low': '🟢'
            }[alert.severity] || '📢';
            
            element.innerHTML = `
                <div class="alert-header">
                    <span class="alert-icon">${severityIcon}</span>
                    <span class="alert-title">${alert.title}</span>
                    <span class="alert-time">${formatTime(alert.created_at)}</span>
                </div>
                <div class="alert-message">${alert.message}</div>
                <div class="alert-actions">
                    <button class="btn btn-sm btn-outline" onclick="dismissAlert(${alert.id})">
                        Dispensar
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="viewAlert(${alert.id})">
                        Ver Detalhes
                    </button>
                </div>
            `;
            
            return element;
        }
        
        // Carregar gráfico de vendas
        function loadSalesChart() {
            fetch('/admin/api/metrics/sales-chart')
                .then(response => response.json())
                .then(data => {
                    const ctx = document.getElementById('sales-chart').getContext('2d');
                    
                    // Implementar gráfico (usando Chart.js ou similar)
                    // Por enquanto, mostrar placeholder
                    ctx.font = '16px Arial';
                    ctx.fillStyle = '#666';
                    ctx.textAlign = 'center';
                    ctx.fillText('Gráfico de vendas (em desenvolvimento)', ctx.canvas.width / 2, ctx.canvas.height / 2);
                })
                .catch(error => console.error('Error loading sales chart:', error));
        }
        
        // Carregar eventos recentes
        function loadRecentEvents() {
            fetch('/admin/api/events/recent')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('events-log');
                    
                    if (data.events && data.events.length > 0) {
                        data.events.forEach(event => {
                            addEventToLog(event);
                        });
                    }
                })
                .catch(error => console.error('Error loading events:', error));
        }
        
        // Adicionar evento ao log
        function addEventToLog(event) {
            const container = document.getElementById('events-log');
            const eventElement = document.createElement('div');
            eventElement.className = `event-item event-${event.type}`;
            
            const typeIcon = {
                'payment': '💰',
                'reservation': '🎯',
                'system': '⚙️',
                'alert': '🚨'
            }[event.type] || '📢';
            
            eventElement.innerHTML = `
                <div class="event-time">${formatTime(event.created_at)}</div>
                <div class="event-content">
                    <span class="event-icon">${typeIcon}</span>
                    <span class="event-message">${event.message}</span>
                </div>
            `;
            
            // Adicionar no topo
            container.insertBefore(eventElement, container.firstChild);
            
            // Limitar quantidade de eventos
            while (container.children.length > 50) {
                container.removeChild(container.lastChild);
            }
        }
        
        // Atualizar métricas
        function refreshMetrics() {
            loadActiveRaffles();
            loadMetrics();
            loadCriticalAlerts();
            loadSalesChart();
        }
        
        // Toggle auto-refresh
        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;
            document.getElementById('auto-refresh-status').textContent = autoRefresh ? 'ON' : 'OFF';
            
            if (autoRefresh) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        }
        
        // Iniciar auto-refresh
        function startAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
            
            refreshInterval = setInterval(() => {
                if (autoRefresh) {
                    refreshMetrics();
                }
            }, 30000); // 30 segundos
        }
        
        // Parar auto-refresh
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }
        
        // Filtrar eventos
        function filterEvents(type) {
            currentFilter = type;
            
            // Atualizar tabs
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filtrar eventos
            const events = document.querySelectorAll('.event-item');
            events.forEach(event => {
                if (type === 'all' || event.classList.contains(`event-${type}`)) {
                    event.style.display = 'block';
                } else {
                    event.style.display = 'none';
                }
            });
        }
        
        // Limpar eventos
        function clearEvents() {
            document.getElementById('events-log').innerHTML = '';
        }
        
        // Ações de rifa
        function viewRaffleDetails(raffleId) {
            window.location.href = `/admin/raffles/${raffleId}`;
        }
        
        function manageRaffle(raffleId) {
            window.location.href = `/admin/raffles/${raffleId}/edit`;
        }
        
        // Ações de alerta
        function dismissAlert(alertId) {
            fetch(`/admin/api/alerts/${alertId}/dismiss`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadCriticalAlerts();
                }
            })
            .catch(error => console.error('Error dismissing alert:', error));
        }
        
        function viewAlert(alertId) {
            window.location.href = `/admin/alerts/${alertId}`;
        }
        
        // Utilitários
        function getTimeRemaining(endDate) {
            const now = new Date();
            const end = new Date(endDate);
            const diff = end - now;
            
            if (diff <= 0) {
                return 'Encerrada';
            }
            
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            if (days > 0) {
                return `${days}d ${hours}h`;
            } else if (hours > 0) {
                return `${hours}h ${minutes}m`;
            } else {
                return `${minutes}m`;
            }
        }
        
        function getStatusColor(percentage) {
            if (percentage >= 95) return '#e74c3c';
            if (percentage >= 85) return '#f39c12';
            if (percentage >= 70) return '#3498db';
            return '#27ae60';
        }
        
        function formatMoney(value) {
            return 'R$ ' + parseFloat(value).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        function formatTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleTimeString('pt-BR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // WebSocket para eventos em tempo real (opcional)
        function connectWebSocket() {
            // Implementar WebSocket se disponível
            // Por enquanto, usar polling
        }
        
        // Limpar ao sair
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });
    </script>
    
    <style>
    .header-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .raffles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .raffle-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #3498db;
    }
    
    .raffle-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .raffle-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.1rem;
    }
    
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        color: white;
    }
    
    .raffle-metrics {
        margin-bottom: 1rem;
    }
    
    .metric-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .metric-label {
        color: #666;
    }
    
    .metric-value {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .progress-bar {
        height: 8px;
        background: #e1e5e9;
        border-radius: 4px;
        margin-bottom: 1rem;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        transition: width 0.3s ease;
    }
    
    .raffle-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .metric-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    
    .metric-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    
    .metric-content h3 {
        margin: 0 0 0.5rem 0;
        font-size: 0.875rem;
        color: #666;
    }
    
    .metric-content .metric-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
    }
    
    .alerts-container {
        margin-bottom: 2rem;
    }
    
    .alert-item {
        background: white;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #e74c3c;
    }
    
    .alert-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }
    
    .alert-icon {
        font-size: 1.2rem;
    }
    
    .alert-title {
        font-weight: 600;
        color: #2c3e50;
        flex: 1;
    }
    
    .alert-time {
        font-size: 0.75rem;
        color: #666;
    }
    
    .alert-message {
        color: #666;
        margin-bottom: 1rem;
    }
    
    .alert-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .chart-container {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
        height: 300px;
    }
    
    .events-container {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .events-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .filter-tabs {
        display: flex;
        gap: 0.5rem;
    }
    
    .tab-btn {
        padding: 0.5rem 1rem;
        border: 1px solid #e1e5e9;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .tab-btn:hover {
        background: #f8f9fa;
    }
    
    .tab-btn.active {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }
    
    .events-log {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 1rem;
    }
    
    .event-item {
        display: flex;
        gap: 1rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f8f9fa;
    }
    
    .event-item:last-child {
        border-bottom: none;
    }
    
    .event-time {
        font-size: 0.75rem;
        color: #666;
        min-width: 60px;
    }
    
    .event-content {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 1;
    }
    
    .event-icon {
        font-size: 1rem;
    }
    
    .event-message {
        font-size: 0.875rem;
        color: #2c3e50;
    }
    
    .empty-state, .error-state {
        text-align: center;
        padding: 2rem;
        color: #666;
        font-style: italic;
    }
    
    .error-state {
        color: #e74c3c;
    }
    
    @media (max-width: 768px) {
        .header-actions {
            flex-direction: column;
            align-items: stretch;
        }
        
        .raffles-grid {
            grid-template-columns: 1fr;
        }
        
        .metrics-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .events-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .filter-tabs {
            justify-content: center;
        }
    }
    </style>
</body>
</html>
