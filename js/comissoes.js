/**
 * Comissões — tabela, pagamentos, gráficos e exportação.
 */
(function (g) {
    'use strict';

    var paymentModal = null;
    var paymentDateInput = null;
    var pendingAction = null;

    function csrfFormData() {
        var fd = new FormData();
        if (typeof g.__SF_CSRF__ === 'string' && g.__SF_CSRF__) {
            fd.append('csrf_token', g.__SF_CSRF__);
        }
        return fd;
    }

    function mutationHeaders() {
        return typeof g.sfMutationHeaders === 'function' ? g.sfMutationHeaders() : {};
    }

    function showPaymentModal(action, rotaIds) {
        if (!paymentModal || !paymentDateInput) return;
        pendingAction = { action: action, rotaIds: rotaIds };
        if (!paymentDateInput.value) {
            paymentDateInput.value = new Date().toISOString().slice(0, 10);
        }
        paymentModal.style.display = 'block';
    }

    function hidePaymentModal() {
        if (paymentModal) paymentModal.style.display = 'none';
        pendingAction = null;
    }

    function confirmPayment() {
        if (!pendingAction) return;
        var dataPag = paymentDateInput ? paymentDateInput.value : '';
        var fd = csrfFormData();
        fd.append('action', pendingAction.action);
        fd.append('data_pagamento', dataPag);

        if (pendingAction.action === 'mark_paid_bulk') {
            fd.append('rota_ids', JSON.stringify(pendingAction.rotaIds));
        } else {
            fd.append('rota_id', String(pendingAction.rotaIds[0]));
            fd.append('action', 'mark_paid');
        }

        fetch(g.sfApiUrl('commissions_actions.php'), {
            method: 'POST',
            body: fd,
            credentials: 'include',
            headers: mutationHeaders()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    g.location.reload();
                } else {
                    alert(data.error || 'Não foi possível atualizar o status da comissão.');
                }
            })
            .catch(function () {
                alert('Erro ao atualizar comissão.');
            });
    }

    function handleCommissionAction(rotaId, action) {
        if (action === 'mark_paid') {
            showPaymentModal('mark_paid', [parseInt(rotaId, 10)]);
            return;
        }

        if (action === 'mark_pending') {
            if (!confirm('Marcar esta comissão como pendente novamente?')) return;
        }

        var fd = csrfFormData();
        fd.append('action', action);
        fd.append('rota_id', String(rotaId));

        fetch(g.sfApiUrl('commissions_actions.php'), {
            method: 'POST',
            body: fd,
            credentials: 'include',
            headers: mutationHeaders()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    g.location.reload();
                } else {
                    alert(data.error || 'Não foi possível atualizar o status da comissão.');
                }
            })
            .catch(function () {
                alert('Erro ao atualizar comissão.');
            });
    }

    function getSelectedRotaIds() {
        return Array.prototype.slice.call(document.querySelectorAll('.com-row-check:checked'))
            .map(function (el) { return parseInt(el.getAttribute('data-rota-id'), 10); })
            .filter(function (id) { return id > 0; });
    }

    function updateBulkBar() {
        var bar = document.getElementById('comBulkBar');
        var countEl = document.getElementById('comBulkCount');
        if (!bar || !countEl) return;
        var n = getSelectedRotaIds().length;
        bar.style.display = n > 0 ? '' : 'none';
        countEl.textContent = n + ' selecionada(s)';
    }

    function wireBulkSelection() {
        var selectAll = document.getElementById('comSelectAll');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.com-row-check').forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
                updateBulkBar();
            });
        }

        document.querySelectorAll('.com-row-check').forEach(function (cb) {
            cb.addEventListener('change', updateBulkBar);
        });

        var bulkBtn = document.getElementById('comBulkPaidBtn');
        if (bulkBtn) {
            bulkBtn.addEventListener('click', function () {
                var ids = getSelectedRotaIds();
                if (!ids.length) {
                    alert('Selecione ao menos uma comissão pendente.');
                    return;
                }
                showPaymentModal('mark_paid_bulk', ids);
            });
        }
    }

    function wirePaymentModal() {
        paymentModal = document.getElementById('comPaymentModal');
        paymentDateInput = document.getElementById('comPaymentDate');
        var confirmBtn = document.getElementById('comPaymentConfirm');
        var cancelBtn = document.getElementById('comPaymentCancel');
        var closeBtn = document.getElementById('comPaymentClose');

        if (confirmBtn) confirmBtn.addEventListener('click', confirmPayment);
        if (cancelBtn) cancelBtn.addEventListener('click', hidePaymentModal);
        if (closeBtn) closeBtn.addEventListener('click', hidePaymentModal);
        if (paymentModal) {
            paymentModal.addEventListener('click', function (e) {
                if (e.target === paymentModal) hidePaymentModal();
            });
        }
    }

    function wireActionButtons() {
        document.querySelectorAll('.mark-paid-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                handleCommissionAction(this.getAttribute('data-rota-id'), 'mark_paid');
            });
        });
        document.querySelectorAll('.mark-pending-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                handleCommissionAction(this.getAttribute('data-rota-id'), 'mark_pending');
            });
        });
    }

    function wireHelpAndExport() {
        var exportBtn = document.getElementById('exportCsvBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                var params = new URLSearchParams(g.location.search);
                g.location.href = 'comissoes_export.php?' + params.toString();
            });
        }

        var filterBtn = document.getElementById('filterBtn');
        var filterSection = document.querySelector('.filter-section') || document.querySelector('.comissoes-fornc-filter');
        if (filterBtn && filterSection) {
            filterBtn.addEventListener('click', function () {
                filterSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                filterSection.classList.add('highlight-filter');
                setTimeout(function () { filterSection.classList.remove('highlight-filter'); }, 1000);
            });
        }

    }

    function wirePagination() {
        var prevBtn = document.getElementById('prevPageBtn');
        var nextBtn = document.getElementById('nextPageBtn');
        if (prevBtn && prevBtn.classList.contains('disabled')) {
            prevBtn.addEventListener('click', function (e) { e.preventDefault(); });
        }
        if (nextBtn && nextBtn.classList.contains('disabled')) {
            nextBtn.addEventListener('click', function (e) { e.preventDefault(); });
        }
    }

    var chartInstances = { status: null, monthly: null, motorista: null };

    function initCharts() {
        var charts = g.__COMISSOES_CHARTS__ || {};
        var statusData = charts.status || { labels: [], values: [] };
        var monthlyData = charts.monthly || { labels: [], total: [], pagas: [], pendentes: [] };
        var motoristaData = charts.motoristas || { labels: [], totais: [], pagas: [], pendentes: [] };

        var statusCanvas = document.getElementById('statusChart');
        if (statusCanvas && statusData.values && statusData.values.some(function (v) { return v > 0; })) {
            if (chartInstances.status) chartInstances.status.destroy();
            chartInstances.status = new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: statusData.labels,
                    datasets: [{ data: statusData.values, backgroundColor: ['#2ecc71', '#f39c12'], borderWidth: 0 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var value = ctx.raw || 0;
                                    return ctx.label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                                }
                            }
                        }
                    }
                }
            });
        }

        var monthlyCanvas = document.getElementById('monthlyChart');
        if (monthlyCanvas && monthlyData.labels && monthlyData.labels.length) {
            if (chartInstances.monthly) chartInstances.monthly.destroy();
            chartInstances.monthly = new Chart(monthlyCanvas, {
                type: 'line',
                data: {
                    labels: monthlyData.labels,
                    datasets: [
                        { label: 'Total', data: monthlyData.total, borderColor: '#3498db', backgroundColor: 'rgba(52,152,219,0.1)', fill: false, tension: 0.3 },
                        { label: 'Pagas', data: monthlyData.pagas, borderColor: '#2ecc71', backgroundColor: 'rgba(46,204,113,0.1)', fill: false, tension: 0.3 },
                        { label: 'Pendentes', data: monthlyData.pendentes, borderColor: '#e67e22', backgroundColor: 'rgba(230,126,34,0.1)', fill: false, tension: 0.3 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var value = ctx.raw || 0;
                                    return ctx.dataset.label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                                }
                            }
                        },
                        legend: { position: 'top' }
                    }
                }
            });
        }

        var motoristaCanvas = document.getElementById('motoristaChart');
        if (motoristaCanvas && motoristaData.labels && motoristaData.labels.length) {
            if (chartInstances.motorista) chartInstances.motorista.destroy();
            chartInstances.motorista = new Chart(motoristaCanvas, {
                type: 'bar',
                data: {
                    labels: motoristaData.labels,
                    datasets: [
                        { label: 'Total', data: motoristaData.totais, backgroundColor: 'rgba(52,152,219,0.7)', borderColor: '#3498db', borderWidth: 1 },
                        { label: 'Pagas', data: motoristaData.pagas, backgroundColor: 'rgba(46,204,113,0.7)', borderColor: '#2ecc71', borderWidth: 1 },
                        { label: 'Pendentes', data: motoristaData.pendentes, backgroundColor: 'rgba(230,126,34,0.7)', borderColor: '#e67e22', borderWidth: 1 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var value = ctx.raw || 0;
                                    return ctx.dataset.label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        wirePagination();
        wireHelpAndExport();
        wireActionButtons();
        wireBulkSelection();
        wirePaymentModal();
        setTimeout(initCharts, 0);
    });
})(typeof window !== 'undefined' ? window : this);
