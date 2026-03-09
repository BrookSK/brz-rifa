<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas - <?= Config::SITE_NAME ?></title>
    <link href="<?= Config::SITE_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include SRC_PATH . '/views/admin/components/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Alertas do Sistema</h1>
                <p>Gerencie e monitore os alertas críticos do sistema</p>
                <div class="header-actions">
                    <button class="btn btn-outline" onclick="refreshAlerts()">
                        🔄 Atualizar
                    </button>
                    <button class="btn btn-primary" onclick="createAlert()">
                        ➕ Novo Alerta
                    </button>
                </div>
            </header>
            
            <div class="content-section">
                <!-- Filtros -->
                <section class="filters-section">
                    <div class="filter-controls">
                        <div class="filter-group">
                            <label for="severity-filter">Severidade:</label>
                            <select id="severity-filter" class="form-control" onchange="filterAlerts()">
                                <option value="">Todas</option>
                                <option value="critical">🚨 Crítico</option>
                                <option value="high">⚠️ Alto</option>
                                <option value="medium">🟡 Médio</option>
                                <option value="low">🟢 Baixo</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status-filter">Status:</label>
                            <select id="status-filter" class="form-control" onchange="filterAlerts()">
                                <option value="">Todos</option>
                                <option value="active">Ativos</option>
                                <option value="dismissed">Dispensados</option>
                                <option value="resolved">Resolvidos</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date-filter">Período:</label>
                            <select id="date-filter" class="form-control" onchange="filterAlerts()">
                                <option value="">Todos</option>
                                <option value="1h">Última hora</option>
                                <option value="24h">Últimas 24h</option>
                                <option value="7d">Últimos 7 dias</option>
                                <option value="30d">Últimos 30 dias</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search-filter">Buscar:</label>
                            <input type="text" id="search-filter" class="form-control" 
                                   placeholder="Título ou mensagem..." onkeyup="filterAlerts()">
                        </div>
                    </div>
                </section>
                
                <!-- Estatísticas -->
                <section class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-card critical">
                            <div class="stat-icon">🚨</div>
                            <div class="stat-content">
                                <h3>Críticos</h3>
                                <div class="stat-value" id="critical-count">0</div>
                            </div>
                        </div>
                        
                        <div class="stat-card high">
                            <div class="stat-icon">⚠️</div>
                            <div class="stat-content">
                                <h3>Altos</h3>
                                <div class="stat-value" id="high-count">0</div>
                            </div>
                        </div>
                        
                        <div class="stat-card medium">
                            <div class="stat-icon">🟡</div>
                            <div class="stat-content">
                                <h3>Médios</h3>
                                <div class="stat-value" id="medium-count">0</div>
                            </div>
                        </div>
                        
                        <div class="stat-card low">
                            <div class="stat-icon">🟢</div>
                            <div class="stat-content">
                                <h3>Baixos</h3>
                                <div class="stat-value" id="low-count">0</div>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Lista de Alertas -->
                <section class="alerts-list-section">
                    <div class="list-header">
                        <h2>Alertas</h2>
                        <div class="list-actions">
                            <button class="btn btn-sm btn-outline" onclick="dismissSelected()">
                                Dispensar Selecionados
                            </button>
                            <button class="btn btn-sm btn-outline" onclick="exportAlerts()">
                                📥 Exportar
                            </button>
                        </div>
                    </div>
                    
                    <div class="alerts-table-container">
                        <table class="data-table" id="alerts-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="select-all" onchange="toggleSelectAll()"></th>
                                    <th>Severidade</th>
                                    <th>Título</th>
                                    <th>Rifa</th>
                                    <th>Criado em</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="alerts-tbody">
                                <!-- Carregado via AJAX -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginação -->
                    <div class="pagination" id="pagination">
                        <!-- Carregado via AJAX -->
                    </div>
                </section>
            </div>
        </main>
    </div>
    
    <!-- Modal de Detalhes -->
    <div id="alert-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalhes do Alerta</h3>
                <button class="modal-close" onclick="closeAlertModal()">&times;</button>
            </div>
            <div class="modal-body" id="alert-modal-body">
                <!-- Conteúdo dinâmico -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeAlertModal()">Fechar</button>
                <button class="btn btn-primary" id="modal-action-btn" onclick="executeModalAction()">
                    Ação
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Novo Alerta -->
    <div id="new-alert-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Criar Novo Alerta</h3>
                <button class="modal-close" onclick="closeNewAlertModal()">&times;</button>
            </div>
            <form id="new-alert-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="alert-title">Título:</label>
                        <input type="text" id="alert-title" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="alert-message">Mensagem:</label>
                        <textarea id="alert-message" name="message" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="alert-severity">Severidade:</label>
                        <select id="alert-severity" name="severity" class="form-control" required>
                            <option value="low">🟢 Baixo</option>
                            <option value="medium">🟡 Médio</option>
                            <option value="high">⚠️ Alto</option>
                            <option value="critical">🚨 Crítico</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="alert-raffle">Rifa (opcional):</label>
                        <select id="alert-raffle" name="raffle_id" class="form-control">
                            <option value="">Selecione uma rifa...</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="alert-details">Detalhes (JSON):</label>
                        <textarea id="alert-details" name="details" class="form-control" rows="3" 
                                  placeholder='{"key": "value"}'></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeNewAlertModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Alerta</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let selectedAlerts = new Set();
        
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            loadAlerts();
            loadRaffles();
            setInterval(loadAlerts, 30000); // Atualizar a cada 30 segundos
        });
        
        // Carregar alertas
        function loadAlerts() {
            const filters = getFilters();
            
            fetch(`/admin/api/alerts?page=${currentPage}&${filters}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderAlerts(data.alerts);
                        updateStats(data.stats);
                        updatePagination(data.pagination);
                    }
                })
                .catch(error => {
                    console.error('Error loading alerts:', error);
                    showError('Erro ao carregar alertas');
                });
        }
        
        // Carregar rifas para o select
        function loadRaffles() {
            fetch('/admin/api/raffles/all')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('alert-raffle');
                        data.raffles.forEach(raffle => {
                            const option = document.createElement('option');
                            option.value = raffle.id;
                            option.textContent = raffle.title;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading raffles:', error));
        }
        
        // Renderizar alertas
        function renderAlerts(alerts) {
            const tbody = document.getElementById('alerts-tbody');
            tbody.innerHTML = '';
            
            if (alerts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">Nenhum alerta encontrado</td></tr>';
                return;
            }
            
            alerts.forEach(alert => {
                const row = createAlertRow(alert);
                tbody.appendChild(row);
            });
        }
        
        // Criar linha de alerta
        function createAlertRow(alert) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="checkbox" value="${alert.id}" onchange="toggleAlertSelection(${alert.id})"></td>
                <td>${getSeverityBadge(alert.severity)}</td>
                <td>
                    <div class="alert-title-cell">
                        <strong>${alert.title}</strong>
                        <div class="alert-message-preview">${alert.message}</div>
                    </div>
                </td>
                <td>${alert.raffle_title || '-'}</td>
                <td>${formatDateTime(alert.created_at)}</td>
                <td>${getStatusBadge(alert.status)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline" onclick="viewAlert(${alert.id})" title="Ver detalhes">
                            👁️
                        </button>
                        ${alert.status === 'active' ? `
                            <button class="btn btn-sm btn-outline" onclick="dismissAlert(${alert.id})" title="Dispensar">
                                ✅
                            </button>
                            <button class="btn btn-sm btn-outline" onclick="resolveAlert(${alert.id})" title="Resolver">
                                🔧
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
            return row;
        }
        
        // Obter badge de severidade
        function getSeverityBadge(severity) {
            const badges = {
                'critical': '<span class="badge badge-critical">🚨 Crítico</span>',
                'high': '<span class="badge badge-high">⚠️ Alto</span>',
                'medium': '<span class="badge badge-medium">🟡 Médio</span>',
                'low': '<span class="badge badge-low">🟢 Baixo</span>'
            };
            return badges[severity] || severity;
        }
        
        // Obter badge de status
        function getStatusBadge(status) {
            const badges = {
                'active': '<span class="badge badge-active">Ativo</span>',
                'dismissed': '<span class="badge badge-dismissed">Dispensado</span>',
                'resolved': '<span class="badge badge-resolved">Resolvido</span>'
            };
            return badges[status] || status;
        }
        
        // Atualizar estatísticas
        function updateStats(stats) {
            document.getElementById('critical-count').textContent = stats.critical || 0;
            document.getElementById('high-count').textContent = stats.high || 0;
            document.getElementById('medium-count').textContent = stats.medium || 0;
            document.getElementById('low-count').textContent = stats.low || 0;
        }
        
        // Atualizar paginação
        function updatePagination(pagination) {
            const container = document.getElementById('pagination');
            container.innerHTML = '';
            
            if (pagination.total_pages <= 1) return;
            
            // Botão anterior
            const prevBtn = document.createElement('button');
            prevBtn.className = 'btn btn-outline';
            prevBtn.textContent = '← Anterior';
            prevBtn.disabled = pagination.current_page === 1;
            prevBtn.onclick = () => goToPage(pagination.current_page - 1);
            container.appendChild(prevBtn);
            
            // Páginas
            for (let i = 1; i <= pagination.total_pages; i++) {
                if (i === 1 || i === pagination.total_pages || 
                    (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
                    
                    const pageBtn = document.createElement('button');
                    pageBtn.className = i === pagination.current_page ? 'btn btn-primary' : 'btn btn-outline';
                    pageBtn.textContent = i;
                    pageBtn.onclick = () => goToPage(i);
                    container.appendChild(pageBtn);
                } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
                    const dots = document.createElement('span');
                    dots.textContent = '...';
                    dots.className = 'pagination-dots';
                    container.appendChild(dots);
                }
            }
            
            // Botão próximo
            const nextBtn = document.createElement('button');
            nextBtn.className = 'btn btn-outline';
            nextBtn.textContent = 'Próximo →';
            nextBtn.disabled = pagination.current_page === pagination.total_pages;
            nextBtn.onclick = () => goToPage(pagination.current_page + 1);
            container.appendChild(nextBtn);
        }
        
        // Ir para página
        function goToPage(page) {
            currentPage = page;
            loadAlerts();
        }
        
        // Obter filtros
        function getFilters() {
            const filters = [];
            
            const severity = document.getElementById('severity-filter').value;
            if (severity) filters.push(`severity=${severity}`);
            
            const status = document.getElementById('status-filter').value;
            if (status) filters.push(`status=${status}`);
            
            const date = document.getElementById('date-filter').value;
            if (date) filters.push(`date=${date}`);
            
            const search = document.getElementById('search-filter').value;
            if (search) filters.push(`search=${encodeURIComponent(search)}`);
            
            return filters.join('&');
        }
        
        // Filtrar alertas
        function filterAlerts() {
            currentPage = 1;
            loadAlerts();
        }
        
        // Seleção de alertas
        function toggleAlertSelection(alertId) {
            if (selectedAlerts.has(alertId)) {
                selectedAlerts.delete(alertId);
            } else {
                selectedAlerts.add(alertId);
            }
            updateSelectAllCheckbox();
        }
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('#alerts-tbody input[type="checkbox"]');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
                const alertId = parseInt(checkbox.value);
                if (selectAll.checked) {
                    selectedAlerts.add(alertId);
                } else {
                    selectedAlerts.delete(alertId);
                }
            });
        }
        
        function updateSelectAllCheckbox() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('#alerts-tbody input[type="checkbox"]');
            const checkedCount = document.querySelectorAll('#alerts-tbody input[type="checkbox"]:checked').length;
            
            selectAll.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
        }
        
        // Ações
        function viewAlert(alertId) {
            fetch(`/admin/api/alerts/${alertId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlertModal(data.alert);
                    }
                })
                .catch(error => {
                    console.error('Error loading alert:', error);
                    showError('Erro ao carregar alerta');
                });
        }
        
        function dismissAlert(alertId) {
            if (!confirm('Deseja dispensar este alerta?')) return;
            
            fetch(`/admin/api/alerts/${alertId}/dismiss`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Alerta dispensado com sucesso');
                    loadAlerts();
                } else {
                    showError(data.error || 'Erro ao dispensar alerta');
                }
            })
            .catch(error => {
                console.error('Error dismissing alert:', error);
                showError('Erro ao dispensar alerta');
            });
        }
        
        function resolveAlert(alertId) {
            const resolution = prompt('Descreva como o alerta foi resolvido:');
            if (!resolution) return;
            
            fetch(`/admin/api/alerts/${alertId}/resolve`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ resolution })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Alerta resolvido com sucesso');
                    loadAlerts();
                } else {
                    showError(data.error || 'Erro ao resolver alerta');
                }
            })
            .catch(error => {
                console.error('Error resolving alert:', error);
                showError('Erro ao resolver alerta');
            });
        }
        
        function dismissSelected() {
            if (selectedAlerts.size === 0) {
                showError('Selecione pelo menos um alerta');
                return;
            }
            
            if (!confirm(`Deseja dispensar ${selectedAlerts.size} alerta(s) selecionado(s)?`)) return;
            
            const alertIds = Array.from(selectedAlerts);
            
            fetch('/admin/api/alerts/batch-dismiss', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ alert_ids: alertIds })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(`${data.dismissed_count} alerta(s) dispensado(s)`);
                    selectedAlerts.clear();
                    loadAlerts();
                } else {
                    showError(data.error || 'Erro ao dispensar alertas');
                }
            })
            .catch(error => {
                console.error('Error dismissing alerts:', error);
                showError('Erro ao dispensar alertas');
            });
        }
        
        function exportAlerts() {
            const filters = getFilters();
            window.open(`/admin/api/alerts/export?${filters}`, '_blank');
        }
        
        // Modais
        function showAlertModal(alert) {
            const modal = document.getElementById('alert-modal');
            const body = document.getElementById('alert-modal-body');
            const actionBtn = document.getElementById('modal-action-btn');
            
            body.innerHTML = `
                <div class="alert-details">
                    <div class="detail-row">
                        <label>ID:</label>
                        <span>${alert.id}</span>
                    </div>
                    <div class="detail-row">
                        <label>Severidade:</label>
                        <span>${getSeverityBadge(alert.severity)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Status:</label>
                        <span>${getStatusBadge(alert.status)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Título:</label>
                        <span>${alert.title}</span>
                    </div>
                    <div class="detail-row">
                        <label>Mensagem:</label>
                        <span>${alert.message}</span>
                    </div>
                    <div class="detail-row">
                        <label>Rifa:</label>
                        <span>${alert.raffle_title || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <label>Criado em:</label>
                        <span>${formatDateTime(alert.created_at)}</span>
                    </div>
                    ${alert.details ? `
                        <div class="detail-row">
                            <label>Detalhes:</label>
                            <pre>${JSON.stringify(JSON.parse(alert.details), null, 2)}</pre>
                        </div>
                    ` : ''}
                </div>
            `;
            
            // Configurar botão de ação
            if (alert.status === 'active') {
                actionBtn.style.display = 'inline-block';
                actionBtn.textContent = 'Dispensar';
                actionBtn.onclick = () => {
                    dismissAlert(alert.id);
                    closeAlertModal();
                };
            } else {
                actionBtn.style.display = 'none';
            }
            
            modal.style.display = 'block';
        }
        
        function closeAlertModal() {
            document.getElementById('alert-modal').style.display = 'none';
        }
        
        function createAlert() {
            document.getElementById('new-alert-modal').style.display = 'block';
        }
        
        function closeNewAlertModal() {
            document.getElementById('new-alert-modal').style.display = 'none';
            document.getElementById('new-alert-form').reset();
        }
        
        // Formulário de novo alerta
        document.getElementById('new-alert-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            // Validar JSON dos detalhes
            if (data.details) {
                try {
                    JSON.parse(data.details);
                } catch (e) {
                    showError('JSON inválido no campo detalhes');
                    return;
                }
            }
            
            fetch('/admin/api/alerts', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Alerta criado com sucesso');
                    closeNewAlertModal();
                    loadAlerts();
                } else {
                    showError(data.error || 'Erro ao criar alerta');
                }
            })
            .catch(error => {
                console.error('Error creating alert:', error);
                showError('Erro ao criar alerta');
            });
        });
        
        // Utilitários
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
        }
        
        function refreshAlerts() {
            loadAlerts();
        }
        
        function showSuccess(message) {
            // Implementar notificação de sucesso
            console.log('Success:', message);
        }
        
        function showError(message) {
            // Implementar notificação de erro
            console.error('Error:', message);
        }
        
        // Fechar modais ao clicar fora
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    </script>
    
    <style>
    .header-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .filter-controls {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .filter-group label {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    
    .stat-card.critical { border-left: 4px solid #e74c3c; }
    .stat-card.high { border-left: 4px solid #f39c12; }
    .stat-card.medium { border-left: 4px solid #f1c40f; }
    .stat-card.low { border-left: 4px solid #27ae60; }
    
    .stat-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    
    .stat-content h3 {
        margin: 0 0 0.5rem 0;
        font-size: 0.875rem;
        color: #666;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #2c3e50;
    }
    
    .list-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .list-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .alerts-table-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .alert-title-cell strong {
        display: block;
        color: #2c3e50;
    }
    
    .alert-message-preview {
        font-size: 0.875rem;
        color: #666;
        margin-top: 0.25rem;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.25rem;
    }
    
    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge-critical { background: #e74c3c; color: white; }
    .badge-high { background: #f39c12; color: white; }
    .badge-medium { background: #f1c40f; color: #2c3e50; }
    .badge-low { background: #27ae60; color: white; }
    .badge-active { background: #3498db; color: white; }
    .badge-dismissed { background: #95a5a6; color: white; }
    .badge-resolved { background: #27ae60; color: white; }
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .pagination-dots {
        padding: 0 0.5rem;
        color: #666;
    }
    
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid #e1e5e9;
    }
    
    .modal-header h3 {
        margin: 0;
        color: #2c3e50;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #666;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        padding: 1.5rem;
        border-top: 1px solid #e1e5e9;
    }
    
    .alert-details {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .detail-row {
        display: flex;
        gap: 1rem;
    }
    
    .detail-row label {
        font-weight: 600;
        color: #2c3e50;
        min-width: 100px;
    }
    
    .detail-row pre {
        background: #f8f9fa;
        padding: 0.5rem;
        border-radius: 4px;
        font-size: 0.875rem;
        overflow-x: auto;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 16px;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #3498db;
    }
    
    @media (max-width: 768px) {
        .header-actions {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-controls {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .list-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .modal-content {
            width: 95%;
            margin: 1rem;
        }
    }
    </style>
</body>
</html>
