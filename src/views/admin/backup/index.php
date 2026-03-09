<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup e Recuperação - <?= Config::SITE_NAME ?></title>
    <link href="<?= Config::SITE_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include SRC_PATH . '/views/admin/components/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Backup e Recuperação</h1>
                <p>Gerencie backups do sistema e recupere dados</p>
                <div class="header-actions">
                    <button class="btn btn-outline" onclick="refreshBackups()">
                        🔄 Atualizar
                    </button>
                    <button class="btn btn-primary" onclick="showCreateBackup()">
                        💾 Novo Backup
                    </button>
                </div>
            </header>
            
            <div class="content-section">
                <!-- Estatísticas -->
                <section class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">💾</div>
                            <div class="stat-content">
                                <h3>Total de Backups</h3>
                                <div class="stat-value" id="total-backups">0</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📦</div>
                            <div class="stat-content">
                                <h3>Backups Completos</h3>
                                <div class="stat-value" id="full-backups">0</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">⚡</div>
                            <div class="stat-content">
                                <h3>Backups Críticos</h3>
                                <div class="stat-value" id="critical-backups">0</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">💿</div>
                            <div class="stat-content">
                                <h3>Espaço Usado</h3>
                                <div class="stat-value" id="total-size">0 MB</div>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Configuração Automática -->
                <section class="config-section">
                    <h2>Backup Automático</h2>
                    <div class="config-card">
                        <form id="backup-config-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="backup-enabled">Backup Automático:</label>
                                    <select id="backup-enabled" name="backup_enabled" class="form-control">
                                        <option value="true">Ativado</option>
                                        <option value="false">Desativado</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="backup-type">Tipo de Backup:</label>
                                    <select id="backup-type" name="backup_type" class="form-control">
                                        <option value="full">Completo</option>
                                        <option value="critical">Apenas Dados Críticos</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="backup-frequency">Frequência:</label>
                                    <select id="backup-frequency" name="backup_frequency" class="form-control">
                                        <option value="daily">Diário</option>
                                        <option value="weekly">Semanal</option>
                                        <option value="monthly">Mensal</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-outline" onclick="testBackup()">
                                    🧪 Testar Backup
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    💾 Salvar Configuração
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
                
                <!-- Lista de Backups -->
                <section class="backups-list-section">
                    <div class="list-header">
                        <h2>Backups Disponíveis</h2>
                        <div class="list-actions">
                            <button class="btn btn-sm btn-outline" onclick="verifyAllBackups()">
                                🔍 Verificar Todos
                            </button>
                            <button class="btn btn-sm btn-outline" onclick="cleanupOldBackups()">
                                🗑️ Limpar Antigos
                            </button>
                        </div>
                    </div>
                    
                    <div class="backups-table-container">
                        <table class="data-table" id="backups-table">
                            <thead>
                                <tr>
                                    <th>Arquivo</th>
                                    <th>Tipo</th>
                                    <th>Tamanho</th>
                                    <th>Criado em</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="backups-tbody">
                                <!-- Carregado via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
    
    <!-- Modal de Novo Backup -->
    <div id="create-backup-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Criar Novo Backup</h3>
                <button class="modal-close" onclick="closeCreateBackupModal()">&times;</button>
            </div>
            <form id="create-backup-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="backup-type-create">Tipo de Backup:</label>
                        <select id="backup-type-create" name="type" class="form-control" required>
                            <option value="full">Completo (Todas as tabelas)</option>
                            <option value="critical">Crítico (Apenas dados essenciais)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="backup-description">Descrição (opcional):</label>
                        <textarea id="backup-description" name="description" class="form-control" rows="3"
                                  placeholder="Descrição deste backup..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>⚠️ Atenção:</strong> O backup pode levar alguns minutos para ser criado, dependendo do tamanho do banco de dados.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeCreateBackupModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="create-backup-btn">
                        Criar Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de Restauração -->
    <div id="restore-backup-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Restaurar Backup</h3>
                <button class="modal-close" onclick="closeRestoreBackupModal()">&times;</button>
            </div>
            <div class="modal-body" id="restore-modal-body">
                <!-- Conteúdo dinâmico -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeRestoreBackupModal()">Cancelar</button>
                <button class="btn btn-danger" id="restore-backup-btn" onclick="confirmRestore()">
                    Restaurar Backup
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Verificação -->
    <div id="verify-backup-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Verificação de Backup</h3>
                <button class="modal-close" onclick="closeVerifyBackupModal()">&times;</button>
            </div>
            <div class="modal-body" id="verify-modal-body">
                <!-- Conteúdo dinâmico -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="closeVerifyBackupModal()">Fechar</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentBackupFile = null;
        
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            loadBackups();
            loadBackupConfig();
            loadStats();
            setInterval(loadStats, 60000); // Atualizar stats a cada minuto
        });
        
        // Carregar backups
        function loadBackups() {
            fetch('/admin/api/backup/list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderBackups(data.backups);
                    }
                })
                .catch(error => {
                    console.error('Error loading backups:', error);
                    showError('Erro ao carregar backups');
                });
        }
        
        // Renderizar backups
        function renderBackups(backups) {
            const tbody = document.getElementById('backups-tbody');
            tbody.innerHTML = '';
            
            if (backups.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhum backup encontrado</td></tr>';
                return;
            }
            
            backups.forEach(backup => {
                const row = createBackupRow(backup);
                tbody.appendChild(row);
            });
        }
        
        // Criar linha de backup
        function createBackupRow(backup) {
            const row = document.createElement('tr');
            
            const typeBadge = backup.type === 'full' 
                ? '<span class="badge badge-full">Completo</span>'
                : '<span class="badge badge-critical">Crítico</span>';
            
            row.innerHTML = `
                <td>
                    <div class="backup-info">
                        <strong>${backup.file}</strong>
                        <div class="backup-meta">Arquivo de backup</div>
                    </div>
                </td>
                <td>${typeBadge}</td>
                <td>${formatFileSize(backup.size)}</td>
                <td>${formatDateTime(backup.created)}</td>
                <td><span class="badge badge-success">OK</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline" onclick="verifyBackup('${backup.file}')" title="Verificar">
                            🔍
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="downloadBackup('${backup.file}')" title="Download">
                            📥
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="showRestoreBackup('${backup.file}')" title="Restaurar">
                            🔄
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="deleteBackup('${backup.file}')" title="Excluir">
                            🗑️
                        </button>
                    </div>
                </td>
            `;
            
            return row;
        }
        
        // Carregar configuração de backup
        function loadBackupConfig() {
            fetch('/admin/api/backup/config')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.config) {
                        document.getElementById('backup-enabled').value = data.config.backup_enabled || 'false';
                        document.getElementById('backup-type').value = data.config.backup_type || 'full';
                        document.getElementById('backup-frequency').value = data.config.backup_frequency || 'daily';
                    }
                })
                .catch(error => console.error('Error loading backup config:', error));
        }
        
        // Carregar estatísticas
        function loadStats() {
            fetch('/admin/api/backup/stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('total-backups').textContent = data.stats.total_backups || 0;
                        document.getElementById('full-backups').textContent = data.stats.full_backups || 0;
                        document.getElementById('critical-backups').textContent = data.stats.critical_backups || 0;
                        document.getElementById('total-size').textContent = formatFileSize(data.stats.total_size || 0);
                    }
                })
                .catch(error => console.error('Error loading stats:', error));
        }
        
        // Salvar configuração de backup
        document.getElementById('backup-config-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            fetch('/admin/api/backup/config', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Configuração salva com sucesso');
                } else {
                    showError(data.error || 'Erro ao salvar configuração');
                }
            })
            .catch(error => {
                console.error('Error saving config:', error);
                showError('Erro ao salvar configuração');
            });
        });
        
        // Criar backup
        document.getElementById('create-backup-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            const submitBtn = document.getElementById('create-backup-btn');
            const originalText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Criando backup...';
            
            fetch('/admin/api/backup/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(`Backup criado com sucesso: ${data.file}`);
                    closeCreateBackupModal();
                    loadBackups();
                    loadStats();
                } else {
                    showError(data.error || 'Erro ao criar backup');
                }
            })
            .catch(error => {
                console.error('Error creating backup:', error);
                showError('Erro ao criar backup');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
        
        // Testar backup
        function testBackup() {
            if (!confirm('Deseja executar um backup de teste?')) return;
            
            showInfo('Executando backup de teste...');
            
            fetch('/admin/api/backup/test', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Backup de teste executado com sucesso');
                    loadBackups();
                } else {
                    showError(data.error || 'Erro ao executar backup de teste');
                }
            })
            .catch(error => {
                console.error('Error testing backup:', error);
                showError('Erro ao executar backup de teste');
            });
        }
        
        // Verificar backup
        function verifyBackup(backupFile) {
            fetch(`/admin/api/backup/verify/${encodeURIComponent(backupFile)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showVerifyModal(backupFile, data.verification);
                    } else {
                        showError(data.error || 'Erro ao verificar backup');
                    }
                })
                .catch(error => {
                    console.error('Error verifying backup:', error);
                    showError('Erro ao verificar backup');
                });
        }
        
        // Download de backup
        function downloadBackup(backupFile) {
            window.location.href = `/admin/api/backup/download/${encodeURIComponent(backupFile)}`;
        }
        
        // Restaurar backup
        function showRestoreBackup(backupFile) {
            currentBackupFile = backupFile;
            
            const modal = document.getElementById('restore-backup-modal');
            const body = document.getElementById('restore-modal-body');
            
            body.innerHTML = `
                <div class="restore-warning">
                    <div class="alert alert-danger">
                        <strong>⚠️ ATENÇÃO:</strong> A restauração substituirá todos os dados atuais do sistema.
                        Esta ação é irreversível e pode causar perda de dados.
                    </div>
                    
                    <div class="restore-info">
                        <h4>Informações do Backup:</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Arquivo:</label>
                                <span>${backupFile}</span>
                            </div>
                            <div class="info-item">
                                <label>Data de Criação:</label>
                                <span>Carregando...</span>
                            </div>
                            <div class="info-item">
                                <label>Tamanho:</label>
                                <span>Carregando...</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="restore-confirmation">
                        <label>
                            <input type="checkbox" id="restore-confirmation" required>
                            Eu entendo os riscos e deseja continuar com a restauração
                        </label>
                    </div>
                </div>
            `;
            
            // Carregar informações do backup
            fetch(`/admin/api/backup/info/${encodeURIComponent(backupFile)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const infoElements = body.querySelectorAll('.info-item span');
                        infoElements[1].textContent = formatDateTime(data.info.created);
                        infoElements[2].textContent = formatFileSize(data.info.size);
                    }
                })
                .catch(error => console.error('Error loading backup info:', error));
            
            modal.style.display = 'block';
        }
        
        function confirmRestore() {
            const checkbox = document.getElementById('restore-confirmation');
            if (!checkbox.checked) {
                showError('Você deve confirmar que entende os riscos');
                return;
            }
            
            if (!confirm('Tem certeza que deseja restaurar este backup? Esta ação é irreversível!')) {
                return;
            }
            
            const restoreBtn = document.getElementById('restore-backup-btn');
            const originalText = restoreBtn.textContent;
            
            restoreBtn.disabled = true;
            restoreBtn.textContent = 'Restaurando...';
            
            fetch(`/admin/api/backup/restore/${encodeURIComponent(currentBackupFile)}`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(`Backup restaurado com sucesso. ${data.commands} comandos executados.`);
                    closeRestoreBackupModal();
                } else {
                    showError(data.error || 'Erro ao restaurar backup');
                }
            })
            .catch(error => {
                console.error('Error restoring backup:', error);
                showError('Erro ao restaurar backup');
            })
            .finally(() => {
                restoreBtn.disabled = false;
                restoreBtn.textContent = originalText;
            });
        }
        
        // Excluir backup
        function deleteBackup(backupFile) {
            if (!confirm(`Deseja excluir o backup "${backupFile}"?`)) return;
            
            fetch(`/admin/api/backup/delete/${encodeURIComponent(backupFile)}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Backup excluído com sucesso');
                    loadBackups();
                    loadStats();
                } else {
                    showError(data.error || 'Erro ao excluir backup');
                }
            })
            .catch(error => {
                console.error('Error deleting backup:', error);
                showError('Erro ao excluir backup');
            });
        }
        
        // Verificar todos os backups
        function verifyAllBackups() {
            showInfo('Verificando todos os backups...');
            
            fetch('/admin/api/backup/verify-all')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(`Verificação concluída. ${data.verified} backups verificados.`);
                        loadBackups();
                    } else {
                        showError(data.error || 'Erro ao verificar backups');
                    }
                })
                .catch(error => {
                    console.error('Error verifying all backups:', error);
                    showError('Erro ao verificar backups');
                });
        }
        
        // Limpar backups antigos
        function cleanupOldBackups() {
            if (!confirm('Deseja limpar backups antigos? Apenas os backups mais recentes serão mantidos.')) return;
            
            fetch('/admin/api/backup/cleanup', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(`${data.deleted} backups antigos excluídos.`);
                    loadBackups();
                    loadStats();
                } else {
                    showError(data.error || 'Erro ao limpar backups');
                }
            })
            .catch(error => {
                console.error('Error cleaning backups:', error);
                showError('Erro ao limpar backups');
            });
        }
        
        // Modais
        function showCreateBackup() {
            document.getElementById('create-backup-modal').style.display = 'block';
        }
        
        function closeCreateBackupModal() {
            document.getElementById('create-backup-modal').style.display = 'none';
            document.getElementById('create-backup-form').reset();
        }
        
        function closeRestoreBackupModal() {
            document.getElementById('restore-backup-modal').style.display = 'none';
            currentBackupFile = null;
        }
        
        function showVerifyModal(backupFile, verification) {
            const modal = document.getElementById('verify-backup-modal');
            const body = document.getElementById('verify-modal-body');
            
            const statusClass = verification.valid ? 'success' : 'error';
            const statusIcon = verification.valid ? '✅' : '❌';
            const statusText = verification.valid ? 'Válido' : 'Inválido';
            
            let html = `
                <div class="verify-result">
                    <div class="verify-status">
                        <h4>Status: <span class="badge badge-${statusClass}">${statusIcon} ${statusText}</span></h4>
                    </div>
                    
                    <div class="verify-details">
                        <div class="detail-item">
                            <label>Arquivo:</label>
                            <span>${backupFile}</span>
                        </div>
                        <div class="detail-item">
                            <label>Comandos SQL:</label>
                            <span>${verification.commands}</span>
                        </div>
                        <div class="detail-item">
                            <label>Tabelas:</label>
                            <span>${verification.tables ? verification.tables.length : 0}</span>
                        </div>
                    </div>
            `;
            
            if (verification.tables && verification.tables.length > 0) {
                html += `
                    <div class="verify-tables">
                        <h5>Tabelas encontradas:</h5>
                        <div class="tables-list">
                            ${verification.tables.map(table => `<span class="table-tag">${table}</span>`).join('')}
                        </div>
                    </div>
                `;
            }
            
            if (verification.errors && verification.errors.length > 0) {
                html += `
                    <div class="verify-errors">
                        <h5>Erros encontrados:</h5>
                        <ul class="errors-list">
                            ${verification.errors.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
            
            html += '</div>';
            body.innerHTML = html;
            modal.style.display = 'block';
        }
        
        function closeVerifyBackupModal() {
            document.getElementById('verify-backup-modal').style.display = 'none';
        }
        
        // Utilitários
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
        }
        
        function refreshBackups() {
            loadBackups();
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
        
        function showInfo(message) {
            // Implementar notificação de informação
            console.log('Info:', message);
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
    
    .config-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .form-group label {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .form-control {
        padding: 0.75rem;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 16px;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #3498db;
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding-top: 1rem;
        border-top: 1px solid #e1e5e9;
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
    
    .backups-table-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .backup-info strong {
        display: block;
        color: #2c3e50;
    }
    
    .backup-meta {
        font-size: 0.75rem;
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
    
    .badge-full { background: #3498db; color: white; }
    .badge-critical { background: #e67e22; color: white; }
    .badge-success { background: #27ae60; color: white; }
    .badge-error { background: #e74c3c; color: white; }
    
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
    
    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    
    .alert-info {
        background: #d9edf7;
        color: #31708f;
        border: 1px solid #bce8f1;
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .restore-warning .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin: 1rem 0;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .info-item label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.875rem;
    }
    
    .restore-confirmation {
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #e1e5e9;
    }
    
    .restore-confirmation label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: #e74c3c;
    }
    
    .verify-status h4 {
        margin: 0 0 1rem 0;
    }
    
    .verify-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
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
    
    .verify-tables {
        margin: 1rem 0;
    }
    
    .verify-tables h5 {
        margin: 0 0 0.5rem 0;
        color: #2c3e50;
    }
    
    .tables-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .table-tag {
        background: #f8f9fa;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.875rem;
        color: #2c3e50;
    }
    
    .verify-errors {
        margin: 1rem 0;
    }
    
    .verify-errors h5 {
        margin: 0 0 0.5rem 0;
        color: #e74c3c;
    }
    
    .errors-list {
        margin: 0;
        padding-left: 1.5rem;
        color: #e74c3c;
    }
    
    .errors-list li {
        margin-bottom: 0.25rem;
    }
    
    @media (max-width: 768px) {
        .header-actions {
            flex-direction: column;
            align-items: stretch;
        }
        
        .form-row {
            grid-template-columns: 1fr;
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
        
        .restore-warning .info-grid {
            grid-template-columns: 1fr;
        }
        
        .verify-details {
            grid-template-columns: 1fr;
        }
    }
    </style>
</body>
</html>
