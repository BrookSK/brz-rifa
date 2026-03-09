<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Participantes - <?= Config::SITE_NAME ?></title>
    <link href="<?= Config::SITE_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include SRC_PATH . '/views/admin/components/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Gestão de Participantes</h1>
                <p>Monitore e gerencie os participantes das rifas</p>
                <div class="header-actions">
                    <button class="btn btn-outline" onclick="refreshParticipants()">
                        🔄 Atualizar
                    </button>
                    <button class="btn btn-primary" onclick="showSuspicious()">
                        ⚠️ Ver Suspeitos
                    </button>
                </div>
            </header>
            
            <div class="content-section">
                <!-- Estatísticas -->
                <section class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">👥</div>
                            <div class="stat-content">
                                <h3>Total de Participantes</h3>
                                <div class="stat-value" id="total-participants">0</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">✅</div>
                            <div class="stat-content">
                                <h3>Ativos</h3>
                                <div class="stat-value" id="active-participants">0</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">⚠️</div>
                            <div class="stat-content">
                                <h3>Suspeitos</h3>
                                <div class="stat-value" id="suspicious-participants">0</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">🚫</div>
                            <div class="stat-content">
                                <h3>Bloqueados</h3>
                                <div class="stat-value" id="blocked-participants">0</div>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Filtros -->
                <section class="filters-section">
                    <div class="filter-controls">
                        <div class="filter-group">
                            <label for="status-filter">Status:</label>
                            <select id="status-filter" class="form-control" onchange="filterParticipants()">
                                <option value="">Todos</option>
                                <option value="active">Ativos</option>
                                <option value="suspicious">Suspeitos</option>
                                <option value="suspended">Suspensos</option>
                                <option value="blocked">Bloqueados</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="raffle-filter">Rifa:</label>
                            <select id="raffle-filter" class="form-control" onchange="filterParticipants()">
                                <option value="">Todas</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search-filter">Buscar:</label>
                            <input type="text" id="search-filter" class="form-control" 
                                   placeholder="Nome, CPF ou e-mail..." onkeyup="filterParticipants()">
                        </div>
                        
                        <div class="filter-group">
                            <label for="fraud-score-filter">Score de Fraude:</label>
                            <select id="fraud-score-filter" class="form-control" onchange="filterParticipants()">
                                <option value="">Todos</option>
                                <option value="0-20">Baixo (0-20)</option>
                                <option value="21-50">Médio (21-50)</option>
                                <option value="51-70">Alto (51-70)</option>
                                <option value="71-100">Crítico (71-100)</option>
                            </select>
                        </div>
                    </div>
                </section>
                
                <!-- Lista de Participantes -->
                <section class="participants-list-section">
                    <div class="list-header">
                        <h2>Participantes</h2>
                        <div class="list-actions">
                            <button class="btn btn-sm btn-outline" onclick="exportParticipants()">
                                📥 Exportar
                            </button>
                        </div>
                    </div>
                    
                    <div class="participants-table-container">
                        <table class="data-table" id="participants-table">
                            <thead>
                                <tr>
                                    <th>Participante</th>
                                    <th>CPF</th>
                                    <th>E-mail</th>
                                    <th>Telefone</th>
                                    <th>Score Fraude</th>
                                    <th>Status</th>
                                    <th>Rifas</th>
                                    <th>Total Gasto</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="participants-tbody">
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
    <div id="participant-modal" class="modal" style="display: none;">
        <div class="modal-content large">
            <div class="modal-header">
                <h3>Detalhes do Participante</h3>
                <button class="modal-close" onclick="closeParticipantModal()">&times;</button>
            </div>
            <div class="modal-body" id="participant-modal-body">
                <!-- Conteúdo dinâmico -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeParticipantModal()">Fechar</button>
                <button class="btn btn-primary" id="modal-action-btn" onclick="executeModalAction()">
                    Ação
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Histórico -->
    <div id="history-modal" class="modal" style="display: none;">
        <div class="modal-content large">
            <div class="modal-header">
                <h3>Histórico do Participante</h3>
                <button class="modal-close" onclick="closeHistoryModal()">&times;</button>
            </div>
            <div class="modal-body" id="history-modal-body">
                <!-- Conteúdo dinâmico -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeHistoryModal()">Fechar</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let currentFilters = {};
        
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            loadRaffles();
            loadParticipants();
            loadStats();
            setInterval(loadStats, 30000); // Atualizar stats a cada 30 segundos
        });
        
        // Carregar rifas para o select
        function loadRaffles() {
            fetch('/admin/api/raffles/all')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('raffle-filter');
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
        
        // Carregar participantes
        function loadParticipants() {
            const filters = getFilters();
            
            fetch(`/admin/api/participants?page=${currentPage}&${filters}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderParticipants(data.participants);
                        updatePagination(data.pagination);
                    }
                })
                .catch(error => {
                    console.error('Error loading participants:', error);
                    showError('Erro ao carregar participantes');
                });
        }
        
        // Renderizar participantes
        function renderParticipants(participants) {
            const tbody = document.getElementById('participants-tbody');
            tbody.innerHTML = '';
            
            if (participants.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">Nenhum participante encontrado</td></tr>';
                return;
            }
            
            participants.forEach(participant => {
                const row = createParticipantRow(participant);
                tbody.appendChild(row);
            });
        }
        
        // Criar linha de participante
        function createParticipantRow(participant) {
            const row = document.createElement('tr');
            
            const fraudScoreColor = getFraudScoreColor(participant.fraud_score);
            const statusBadge = getStatusBadge(participant.status);
            
            row.innerHTML = `
                <td>
                    <div class="participant-info">
                        <strong>${participant.name}</strong>
                        <div class="participant-meta">
                            Criado em ${formatDate(participant.created_at)}
                        </div>
                    </div>
                </td>
                <td>${formatCPF(participant.cpf)}</td>
                <td>${participant.email}</td>
                <td>${participant.phone || '-'}</td>
                <td>
                    <div class="fraud-score">
                        <div class="score-bar" style="background: ${fraudScoreColor}; width: ${participant.fraud_score}%"></div>
                        <span class="score-value">${participant.fraud_score}</span>
                    </div>
                </td>
                <td>${statusBadge}</td>
                <td>${participant.total_raffles || 0}</td>
                <td>${formatMoney(participant.total_spent || 0)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline" onclick="viewParticipant(${participant.id})" title="Ver detalhes">
                            👁️
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="viewHistory('${participant.cpf}')" title="Ver histórico">
                            📋
                        </button>
                        ${getActionButtons(participant)}
                    </div>
                </td>
            `;
            
            return row;
        }
        
        // Obter botões de ação conforme status
        function getActionButtons(participant) {
            switch (participant.status) {
                case 'active':
                    return `
                        <button class="btn btn-sm btn-outline" onclick="suspendParticipant(${participant.id})" title="Suspender">
                            ⏸️
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="blockParticipant(${participant.id})" title="Bloquear">
                            🚫
                        </button>
                    `;
                case 'suspicious':
                    return `
                        <button class="btn btn-sm btn-outline" onclick="suspendParticipant(${participant.id})" title="Suspender">
                            ⏸️
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="blockParticipant(${participant.id})" title="Bloquear">
                            🚫
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="reactivateParticipant(${participant.id})" title="Reativar">
                            ✅
                        </button>
                    `;
                case 'suspended':
                    return `
                        <button class="btn btn-sm btn-outline" onclick="reactivateParticipant(${participant.id})" title="Reativar">
                            ✅
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="blockParticipant(${participant.id})" title="Bloquear">
                            🚫
                        </button>
                    `;
                case 'blocked':
                    return `
                        <button class="btn btn-sm btn-outline" onclick="reactivateParticipant(${participant.id})" title="Reativar">
                            ✅
                        </button>
                    `;
                default:
                    return '';
            }
        }
        
        // Obter cor do score de fraude
        function getFraudScoreColor(score) {
            if (score <= 20) return '#27ae60';
            if (score <= 50) return '#f39c12';
            if (score <= 70) return '#e67e22';
            return '#e74c3c';
        }
        
        // Obter badge de status
        function getStatusBadge(status) {
            const badges = {
                'active': '<span class="badge badge-active">Ativo</span>',
                'suspicious': '<span class="badge badge-suspicious">Suspeito</span>',
                'suspended': '<span class="badge badge-suspended">Suspenso</span>',
                'blocked': '<span class="badge badge-blocked">Bloqueado</span>'
            };
            return badges[status] || status;
        }
        
        // Carregar estatísticas
        function loadStats() {
            fetch('/admin/api/participants/stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('total-participants').textContent = data.total || 0;
                        document.getElementById('active-participants').textContent = data.active || 0;
                        document.getElementById('suspicious-participants').textContent = data.suspicious || 0;
                        document.getElementById('blocked-participants').textContent = data.blocked || 0;
                    }
                })
                .catch(error => console.error('Error loading stats:', error));
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
            loadParticipants();
        }
        
        // Obter filtros
        function getFilters() {
            const filters = [];
            
            const status = document.getElementById('status-filter').value;
            if (status) filters.push(`status=${status}`);
            
            const raffle = document.getElementById('raffle-filter').value;
            if (raffle) filters.push(`raffle_id=${raffle}`);
            
            const search = document.getElementById('search-filter').value;
            if (search) filters.push(`search=${encodeURIComponent(search)}`);
            
            const fraudScore = document.getElementById('fraud-score-filter').value;
            if (fraudScore) filters.push(`fraud_score=${fraudScore}`);
            
            return filters.join('&');
        }
        
        // Filtrar participantes
        function filterParticipants() {
            currentPage = 1;
            loadParticipants();
        }
        
        // Ações
        function viewParticipant(participantId) {
            fetch(`/admin/api/participants/${participantId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showParticipantModal(data.participant);
                    }
                })
                .catch(error => {
                    console.error('Error loading participant:', error);
                    showError('Erro ao carregar participante');
                });
        }
        
        function viewHistory(cpf) {
            fetch(`/admin/api/participants/history/${cpf}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showHistoryModal(data.history, cpf);
                    }
                })
                .catch(error => {
                    console.error('Error loading history:', error);
                    showError('Erro ao carregar histórico');
                });
        }
        
        function suspendParticipant(participantId) {
            const reason = prompt('Motivo da suspensão:');
            if (!reason) return;
            
            if (!confirm('Deseja suspender este participante?')) return;
            
            fetch(`/admin/api/participants/${participantId}/suspend`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reason })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Participante suspenso com sucesso');
                    loadParticipants();
                    loadStats();
                } else {
                    showError(data.error || 'Erro ao suspender participante');
                }
            })
            .catch(error => {
                console.error('Error suspending participant:', error);
                showError('Erro ao suspender participante');
            });
        }
        
        function blockParticipant(participantId) {
            const reason = prompt('Motivo do bloqueio:');
            if (!reason) return;
            
            if (!confirm('Deseja bloquear este participante? Esta ação cancelará todas as suas compras.')) return;
            
            fetch(`/admin/api/participants/${participantId}/block`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reason })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Participante bloqueado com sucesso');
                    loadParticipants();
                    loadStats();
                } else {
                    showError(data.error || 'Erro ao bloquear participante');
                }
            })
            .catch(error => {
                console.error('Error blocking participant:', error);
                showError('Erro ao bloquear participante');
            });
        }
        
        function reactivateParticipant(participantId) {
            if (!confirm('Deseja reativar este participante?')) return;
            
            fetch(`/admin/api/participants/${participantId}/reactivate`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Participante reativado com sucesso');
                    loadParticipants();
                    loadStats();
                } else {
                    showError(data.error || 'Erro ao reativar participante');
                }
            })
            .catch(error => {
                console.error('Error reactivating participant:', error);
                showError('Erro ao reativar participante');
            });
        }
        
        function showSuspicious() {
            document.getElementById('status-filter').value = 'suspicious';
            filterParticipants();
        }
        
        function exportParticipants() {
            const filters = getFilters();
            window.open(`/admin/api/participants/export?${filters}`, '_blank');
        }
        
        // Modais
        function showParticipantModal(participant) {
            const modal = document.getElementById('participant-modal');
            const body = document.getElementById('participant-modal-body');
            const actionBtn = document.getElementById('modal-action-btn');
            
            body.innerHTML = `
                <div class="participant-details">
                    <div class="detail-section">
                        <h4>Informações Pessoais</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Nome:</label>
                                <span>${participant.name}</span>
                            </div>
                            <div class="detail-item">
                                <label>CPF:</label>
                                <span>${formatCPF(participant.cpf)}</span>
                            </div>
                            <div class="detail-item">
                                <label>E-mail:</label>
                                <span>${participant.email}</span>
                            </div>
                            <div class="detail-item">
                                <label>Telefone:</label>
                                <span>${participant.phone || '-'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Endereço:</label>
                                <span>${participant.address || '-'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Data de Cadastro:</label>
                                <span>${formatDateTime(participant.created_at)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4>Status e Segurança</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Status:</label>
                                <span>${getStatusBadge(participant.status)}</span>
                            </div>
                            <div class="detail-item">
                                <label>Score de Fraude:</label>
                                <span>
                                    <div class="fraud-score">
                                        <div class="score-bar" style="background: ${getFraudScoreColor(participant.fraud_score)}; width: ${participant.fraud_score}%"></div>
                                        <span class="score-value">${participant.fraud_score}</span>
                                    </div>
                                </span>
                            </div>
                            ${participant.suspension_reason ? `
                                <div class="detail-item">
                                    <label>Motivo da Suspensão:</label>
                                    <span>${participant.suspension_reason}</span>
                                </div>
                            ` : ''}
                            ${participant.block_reason ? `
                                <div class="detail-item">
                                    <label>Motivo do Bloqueio:</label>
                                    <span>${participant.block_reason}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h4>Estatísticas</h4>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value">${participant.total_raffles || 0}</div>
                                <div class="stat-label">Rifas Participadas</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${participant.total_numbers || 0}</div>
                                <div class="stat-label">Números Comprados</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${formatMoney(participant.total_spent || 0)}</div>
                                <div class="stat-label">Total Gasto</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${formatMoney(participant.avg_ticket || 0)}</div>
                                <div class="stat-label">Ticket Médio</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Configurar botão de ação
            if (participant.status === 'active') {
                actionBtn.style.display = 'inline-block';
                actionBtn.textContent = 'Suspender';
                actionBtn.onclick = () => {
                    suspendParticipant(participant.id);
                    closeParticipantModal();
                };
            } else if (participant.status === 'suspended' || participant.status === 'suspicious') {
                actionBtn.style.display = 'inline-block';
                actionBtn.textContent = 'Reativar';
                actionBtn.onclick = () => {
                    reactivateParticipant(participant.id);
                    closeParticipantModal();
                };
            } else {
                actionBtn.style.display = 'none';
            }
            
            modal.style.display = 'block';
        }
        
        function closeParticipantModal() {
            document.getElementById('participant-modal').style.display = 'none';
        }
        
        function showHistoryModal(history, cpf) {
            const modal = document.getElementById('history-modal');
            const body = document.getElementById('history-modal-body');
            
            let html = `
                <div class="history-header">
                    <h4>Histórico de ${formatCPF(cpf)}</h4>
                </div>
                <div class="history-list">
            `;
            
            if (history.length === 0) {
                html += '<div class="empty-state">Nenhum histórico encontrado</div>';
            } else {
                history.forEach(item => {
                    html += `
                        <div class="history-item">
                            <div class="history-header-item">
                                <span class="history-raffle">${item.raffle_title}</span>
                                <span class="history-date">${formatDateTime(item.created_at)}</span>
                            </div>
                            <div class="history-details">
                                <div class="history-number">Número: ${item.number}</div>
                                <div class="history-status">${getStatusBadge(item.status)}</div>
                                ${item.payment_amount ? `
                                    <div class="history-amount">${formatMoney(item.payment_amount)}</div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
            }
            
            html += '</div>';
            body.innerHTML = html;
            
            modal.style.display = 'block';
        }
        
        function closeHistoryModal() {
            document.getElementById('history-modal').style.display = 'none';
        }
        
        // Utilitários
        function formatCPF(cpf) {
            if (!cpf) return '-';
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR');
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
        }
        
        function formatMoney(value) {
            return 'R$ ' + parseFloat(value).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        function refreshParticipants() {
            loadParticipants();
            loadStats();
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
    
    .list-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .participants-table-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .participant-info strong {
        display: block;
        color: #2c3e50;
    }
    
    .participant-meta {
        font-size: 0.75rem;
        color: #666;
        margin-top: 0.25rem;
    }
    
    .fraud-score {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .score-bar {
        height: 8px;
        width: 50px;
        border-radius: 4px;
        background: #e1e5e9;
        overflow: hidden;
    }
    
    .score-value {
        font-size: 0.875rem;
        font-weight: 600;
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
    
    .badge-active { background: #27ae60; color: white; }
    .badge-suspicious { background: #f39c12; color: white; }
    .badge-suspended { background: #e67e22; color: white; }
    .badge-blocked { background: #e74c3c; color: white; }
    
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
        max-width: 800px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-content.large {
        max-width: 1000px;
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
    
    .participant-details {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    
    .detail-section h4 {
        margin: 0 0 1rem 0;
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 0.5rem;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .detail-item label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.875rem;
    }
    
    .detail-item span {
        color: #666;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .stat-item {
        text-align: center;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .stat-item .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
        display: block;
    }
    
    .stat-item .stat-label {
        font-size: 0.875rem;
        color: #666;
        margin-top: 0.25rem;
    }
    
    .history-header {
        margin-bottom: 1rem;
    }
    
    .history-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .history-item {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    
    .history-header-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    
    .history-raffle {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .history-date {
        font-size: 0.875rem;
        color: #666;
    }
    
    .history-details {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .history-number {
        font-size: 0.875rem;
        color: #666;
    }
    
    .history-amount {
        font-weight: 600;
        color: #27ae60;
    }
    
    .empty-state {
        text-align: center;
        padding: 2rem;
        color: #666;
        font-style: italic;
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
        
        .detail-grid {
            grid-template-columns: 1fr;
        }
        
        .history-details {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
    }
    </style>
</body>
</html>
