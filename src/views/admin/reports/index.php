<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - <?= Config::SITE_NAME ?></title>
    <link href="<?= Config::SITE_URL ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include SRC_PATH . '/views/admin/components/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1>Relatórios</h1>
                <p>Gerencie e visualize relatórios do sistema</p>
            </header>
            
            <div class="content-section">
                <!-- Relatórios de Rifa -->
                <section class="report-section">
                    <h2>Relatórios de Rifa</h2>
                    <div class="report-grid">
                        <div class="report-card">
                            <div class="report-icon">📊</div>
                            <h3>Relatório Completo</h3>
                            <p>Relatório detalhado com todos os dados da rifa</p>
                            <div class="report-actions">
                                <select class="form-control" id="raffle-select">
                                    <option value="">Selecione uma rifa...</option>
                                    <?php foreach ($raffles as $raffle): ?>
                                        <option value="<?= $raffle['id'] ?>"><?= htmlspecialchars($raffle['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary" onclick="generateRaffleReport('pdf')">PDF</button>
                                <button class="btn btn-outline" onclick="generateRaffleReport('excel')">Excel</button>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <div class="report-icon">👥</div>
                            <h3>Lista de Participantes</h3>
                            <p>Relatório com todos os participantes e números</p>
                            <div class="report-actions">
                                <select class="form-control" id="participants-raffle-select">
                                    <option value="">Selecione uma rifa...</option>
                                    <?php foreach ($raffles as $raffle): ?>
                                        <option value="<?= $raffle['id'] ?>"><?= htmlspecialchars($raffle['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary" onclick="exportParticipants()">Excel</button>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Relatórios Financeiros -->
                <section class="report-section">
                    <h2>Relatórios Financeiros</h2>
                    <div class="report-card">
                        <div class="report-icon">💰</div>
                        <h3>Relatório Financeiro</h3>
                        <p>Conciliação financeira e fluxo de caixa</p>
                        <div class="report-filters">
                            <div class="filter-row">
                                <input type="date" id="start-date" class="form-control" value="<?= date('Y-m-01') ?>">
                                <span>a</span>
                                <input type="date" id="end-date" class="form-control" value="<?= date('Y-m-t') ?>">
                            </div>
                            <div class="report-actions">
                                <button class="btn btn-primary" onclick="generateFinancialReport('pdf')">PDF</button>
                                <button class="btn btn-outline" onclick="generateFinancialReport('excel')">Excel</button>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Relatórios de Auditoria -->
                <section class="report-section">
                    <h2>Relatórios de Auditoria</h2>
                    <div class="report-card">
                        <div class="report-icon">🔍</div>
                        <h3>Log de Auditoria</h3>
                        <p>Histórico completo de todas as ações no sistema</p>
                        <div class="report-filters">
                            <div class="filter-row">
                                <input type="date" id="audit-start-date" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                                <span>a</span>
                                <input type="date" id="audit-end-date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="report-actions">
                                <button class="btn btn-primary" onclick="generateAuditReport('pdf')">PDF</button>
                                <button class="btn btn-outline" onclick="generateAuditReport('excel')">Excel</button>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Relatórios Gerados -->
                <section class="report-section">
                    <h2>Relatórios Gerados</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Data</th>
                                    <th>Tamanho</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="generated-reports">
                                <!-- Carregado via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
    
    <script>
        function generateRaffleReport(format) {
            const raffleId = document.getElementById('raffle-select').value;
            if (!raffleId) {
                showNotification('Selecione uma rifa', 'error');
                return;
            }
            
            window.open(`/admin/reports/raffle/${raffleId}/export/${format}`, '_blank');
        }
        
        function exportParticipants() {
            const raffleId = document.getElementById('participants-raffle-select').value;
            if (!raffleId) {
                showNotification('Selecione uma rifa', 'error');
                return;
            }
            
            window.open(`/admin/reports/participants/${raffleId}/excel`, '_blank');
        }
        
        function generateFinancialReport(format) {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            window.open(`/admin/reports/financial/export/${format}?start_date=${startDate}&end_date=${endDate}`, '_blank');
        }
        
        function generateAuditReport(format) {
            const startDate = document.getElementById('audit-start-date').value;
            const endDate = document.getElementById('audit-end-date').value;
            
            window.open(`/admin/reports/audit/export/${format}?start_date=${startDate}&end_date=${endDate}`, '_blank');
        }
        
        function loadGeneratedReports() {
            fetch('/admin/api/reports/generated')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('generated-reports');
                    tbody.innerHTML = '';
                    
                    if (data.reports && data.reports.length > 0) {
                        data.reports.forEach(report => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${report.title}</td>
                                <td><span class="badge badge-${report.report_type}">${report.report_type}</span></td>
                                <td>${formatDate(report.generated_at)}</td>
                                <td>${formatFileSize(report.file_size)}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="downloadReport('${report.file_path}')">
                                        Download
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum relatório gerado</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error loading reports:', error);
                });
        }
        
        function downloadReport(filePath) {
            window.open(`${Config.SITE_URL}/uploads/reports/${filePath}`, '_blank');
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function showNotification(message, type = 'info') {
            // Implementar notificação
            console.log(`${type}: ${message}`);
        }
        
        // Carregar relatórios ao abrir página
        document.addEventListener('DOMContentLoaded', loadGeneratedReports);
    </script>
</body>
</html>
