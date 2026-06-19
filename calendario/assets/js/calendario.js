/**
 * Calendar Management System
 * Integrates with fleet management system to show automatic events
 */

function calEscapeHtml(value) {
    if (value == null) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function calNormalizeCategory(cat) {
    const map = {
        cnh: 'CNH', CNH: 'CNH',
        multas: 'Multas', Multas: 'Multas',
        contas: 'Contas', Contas: 'Contas',
        financiamento: 'Financiamento', Financiamento: 'Financiamento',
        manutencao: 'Manutenção', 'Manutenção': 'Manutenção',
        personalizado: 'Personalizado', Personalizado: 'Personalizado'
    };
    return map[cat] || cat || 'Personalizado';
}

function calAppUrl(rel) {
    if (typeof sfAppUrl === 'function') {
        return sfAppUrl(rel);
    }
    const base = (typeof window.__SF_APP_BASE__ === 'string' && window.__SF_APP_BASE__)
        ? window.__SF_APP_BASE__.replace(/\/+$/, '') : '';
    rel = String(rel || '').replace(/^\/+/, '');
    return base ? base + '/' + rel : '/' + rel;
}

class CalendarManager {
    constructor() {
        this.calendar = null;
        this.events = [];
        this.currentEvent = null;
        this.filters = {
            'CNH': true,
            'Multas': true,
            'Contas': true,
            'Financiamento': true,
            'Manutenção': true,
            'Personalizado': true
        };
        
        this.init();
    }
    
    init() {
        try {
            console.log('=== INICIANDO CALENDAR MANAGER ===');
            this.setupEventListeners();
            this.initializeCalendar();
            this.setupFilters();
            
            // Configurar sincronização automática
            this.setupAutoSync();
            
            // Carregar eventos após o calendário estar inicializado
            setTimeout(() => {
                console.log('Timeout executado, carregando eventos...');
                this.loadAutomaticEvents();
            }, 500);
            
            console.log('=== CALENDAR MANAGER INICIADO ===');
        } catch (error) {
            console.error('Erro na inicialização do CalendarManager:', error);
        }
    }
    
    setupEventListeners() {
        try {
            console.log('Configurando event listeners...');
            
            // Add event button
            const addEventBtn = document.getElementById('addEventBtn');
            if (addEventBtn) {
                addEventBtn.addEventListener('click', () => {
                    this.openEventModal();
                });
                console.log('Botão adicionar evento configurado');
            }
            
            // Refresh events button
            const refreshEventsBtn = document.getElementById('refreshEventsBtn');
            if (refreshEventsBtn) {
                refreshEventsBtn.addEventListener('click', () => {
                    this.loadAutomaticEvents();
                });
                console.log('Botão atualizar eventos configurado');
            }
            
            // Sync status button
            const syncStatusBtn = document.getElementById('syncStatusBtn');
            if (syncStatusBtn) {
                syncStatusBtn.addEventListener('click', () => {
                    this.checkSyncStatus();
                });
                console.log('Botão status sincronização configurado');
            }
            
            // Force sync button
            const forceSyncBtn = document.getElementById('forceSyncBtn');
            if (forceSyncBtn) {
                forceSyncBtn.addEventListener('click', () => {
                    this.forceSync();
                });
                console.log('Botão forçar sincronização configurado');
            }
            
            // Modal close button
            const closeBtn = document.querySelector('#eventModal .close-modal');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.closeEventModal();
                });
                console.log('Botão fechar modal configurado');
            }
            
            // Cancel button
            const cancelBtn = document.getElementById('cancelEventBtn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    this.closeEventModal();
                });
                console.log('Botão cancelar configurado');
            }
            
            // Delete button
            const deleteBtn = document.getElementById('deleteEventBtn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', () => {
                    this.deleteEvent();
                });
                console.log('Botão excluir configurado');
            }
            
            const openSourceBtn = document.getElementById('openSourceLinkBtn');
            if (openSourceBtn) {
                openSourceBtn.addEventListener('click', () => {
                    const url = openSourceBtn.dataset.url;
                    if (url) window.location.href = url;
                });
            }

            // Form submission
            const eventForm = document.getElementById('eventForm');
            if (eventForm) {
                eventForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.saveEvent();
                });
                console.log('Formulário configurado');
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', (e) => {
                const modal = document.getElementById('eventModal');
                if (modal && e.target === modal) {
                    this.closeEventModal();
                }
            });
            
            console.log('Event listeners configurados com sucesso');
        } catch (error) {
            console.error('Erro ao configurar event listeners:', error);
        }
    }
    
    initializeCalendar() {
        try {
            console.log('Inicializando calendário...');
            
            // Verificar se FullCalendar está disponível
            if (typeof FullCalendar === 'undefined') {
                console.error('FullCalendar não está carregado');
                return;
            }
            
            const calendarEl = document.getElementById('calendar');
            
            if (!calendarEl) {
                console.error('Elemento do calendário não encontrado');
                return;
            }
            
            console.log('Elemento do calendário encontrado:', calendarEl);
            
            this.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'pt-br',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                week: 'Semana',
                day: 'Dia',
                list: 'Lista'
            },
            height: '100%',
            editable: true,
            selectable: true,
            selectMirror: true,
            dayMaxEvents: true,
            weekends: true,
            events: [],
            eventClick: (info) => {
                this.openEventModal(info.event);
            },
            eventDrop: (info) => {
                if (this.isAutomaticEvent(info.event)) {
                    info.revert();
                    this.showError('Eventos automáticos não podem ser movidos.');
                    return;
                }
                this.updateEventDate(info.event);
            },
            eventResize: (info) => {
                if (this.isAutomaticEvent(info.event)) {
                    info.revert();
                    return;
                }
                this.updateEventDate(info.event);
            },
            select: (info) => {
                this.openEventModal(null, info.start, info.end);
            },
            eventDidMount: (info) => {
                this.setupEventTooltip(info);
            }
        });
        
        this.calendar.render();
        console.log('Calendário inicializado com sucesso');
        console.log('Calendário objeto:', this.calendar);
        
        } catch (error) {
            console.error('Erro ao inicializar calendário:', error);
        }
    }
    
    setupEventTooltip(info) {
        const event = info.event;
        const props = event.extendedProps || {};
        const tooltip = document.createElement('div');
        tooltip.className = 'fc-event-tooltip';
        const titleEl = document.createElement('div');
        titleEl.className = 'tooltip-title';
        titleEl.textContent = event.title;
        const catEl = document.createElement('div');
        catEl.className = 'tooltip-category';
        catEl.textContent = this.getCategoryName(props.category);
        const descEl = document.createElement('div');
        descEl.className = 'tooltip-description';
        descEl.textContent = props.description || '';
        tooltip.appendChild(titleEl);
        tooltip.appendChild(catEl);
        tooltip.appendChild(descEl);

        info.el.addEventListener('mouseenter', () => {
            document.body.appendChild(tooltip);
            this.positionTooltip(tooltip, info.el);
        });
        info.el.addEventListener('mouseleave', () => {
            if (tooltip.parentNode) tooltip.parentNode.removeChild(tooltip);
        });
    }
    
    positionTooltip(tooltip, element) {
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        let top = rect.top - tooltipRect.height - 10;
        
        if (left < 0) left = 10;
        if (left + tooltipRect.width > window.innerWidth) {
            left = window.innerWidth - tooltipRect.width - 10;
        }
        if (top < 0) {
            top = rect.bottom + 10;
        }
        
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
    }
    
    getCategoryName(category) {
        return calNormalizeCategory(category);
    }
    
    setupFilters() {
        try {
            console.log('Configurando filtros...');
            const filterCheckboxes = document.querySelectorAll('.filter-option input[type="checkbox"]');
            
            if (filterCheckboxes.length === 0) {
                console.warn('Nenhum checkbox de filtro encontrado');
                return;
            }
            
            filterCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    this.filters[checkbox.value] = checkbox.checked;
                    this.applyFilters();
                });
            });
            
            console.log('Filtros configurados:', filterCheckboxes.length);
        } catch (error) {
            console.error('Erro ao configurar filtros:', error);
        }
    }
    
    isAutomaticEvent(event) {
        if (!event) return false;
        const props = event.extendedProps || {};
        if (props.source === 'automatic' || props.source === 'plano') return true;
        const id = String(event.id || '');
        return !/^\d+$/.test(id);
    }

    getEventSourceUrl(props) {
        if (!props || props.source === 'manual') return null;
        const cat = calNormalizeCategory(props.category);
        if (props.multa_id) return calAppUrl('pages/multas.php');
        if (props.motorista_id && cat === 'CNH') return calAppUrl('pages/motorists.php');
        if (props.conta_id) return calAppUrl('pages/contas_pagar.php');
        if (props.parcela_id || props.financiamento_id) return calAppUrl('pages/financiamento.php');
        if (props.manutencao_id || cat === 'Manutenção') return calAppUrl('pages/manutencoes.php');
        return null;
    }

    setModalReadonlyMode(readonly, sourceUrl) {
        const fields = ['eventTitle', 'eventCategory', 'eventStart', 'eventEnd', 'eventDescription', 'eventColor'];
        fields.forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.disabled = readonly;
        });
        const notice = document.getElementById('eventAutoNotice');
        if (notice) notice.style.display = readonly ? 'block' : 'none';
        const saveBtn = document.getElementById('saveEventBtn');
        const deleteBtn = document.getElementById('deleteEventBtn');
        const linkBtn = document.getElementById('openSourceLinkBtn');
        if (saveBtn) saveBtn.style.display = readonly ? 'none' : 'flex';
        if (deleteBtn) deleteBtn.style.display = readonly ? 'none' : 'flex';
        if (linkBtn) {
            if (readonly && sourceUrl) {
                linkBtn.style.display = 'flex';
                linkBtn.dataset.url = sourceUrl;
            } else {
                linkBtn.style.display = 'none';
                delete linkBtn.dataset.url;
            }
        }
    }

    applyFilters() {
        if (!this.calendar) return;

        const filteredEvents = this.events.filter((event) => {
            const category = calNormalizeCategory(event.extendedProps?.category);
            return this.filters[category] !== false;
        });

        this.calendar.removeAllEvents();
        this.calendar.addEventSource(filteredEvents);
    }

    async fetchEventApi(url, className) {
        try {
            const response = await fetch(url);
            if (!response.ok) return [];
            const data = await response.json();
            if (!Array.isArray(data)) return [];
            return data.map((event) => ({
                ...event,
                className: className,
                editable: event.editable === true,
                extendedProps: {
                    ...(event.extendedProps || {}),
                    category: calNormalizeCategory(event.extendedProps?.category)
                }
            }));
        } catch (e) {
            return [];
        }
    }
    
    async loadAutomaticEvents() {
        try {
            console.log('=== INICIANDO CARREGAMENTO DE EVENTOS ===');
            
            // Verificar se o calendário está inicializado
            if (!this.calendar) {
                console.error('Calendário não está inicializado, abortando carregamento');
                return;
            }
            
            // Show loading state
            this.showLoading();
            
            // Load events from different sources
            console.log('Carregando eventos de CNH...');
            const cnhEvents = await this.loadCNHEvents();
            console.log('Eventos de CNH carregados:', cnhEvents);
            
            console.log('Carregando eventos de multas...');
            const multasEvents = await this.loadMultasEvents();
            console.log('Eventos de multas carregados:', multasEvents);
            
            console.log('Carregando eventos de contas...');
            const contasEvents = await this.loadContasEvents();
            console.log('Eventos de contas carregados:', contasEvents);
            
            console.log('Carregando eventos de financiamento...');
            const financiamentoEvents = await this.loadFinanciamentoEvents();
            console.log('Eventos de financiamento carregados:', financiamentoEvents);
            
            console.log('Carregando eventos de manutenção...');
            const manutencaoEvents = await this.loadManutencaoEvents();
            console.log('Eventos de manutenção carregados:', manutencaoEvents);
            
            console.log('Carregando eventos personalizados...');
            const personalEvents = await this.loadPersonalEvents();
            console.log('Eventos personalizados carregados:', personalEvents);
            
            // Combine all events
            this.events = [
                ...cnhEvents,
                ...multasEvents,
                ...contasEvents,
                ...financiamentoEvents,
                ...manutencaoEvents,
                ...personalEvents
            ];
            
            console.log('Total de eventos carregados:', this.events.length);
            
            // Apply filters and display
            this.applyFilters();
            
            // Hide loading
            this.hideLoading();
            
            // Check for upcoming events and show notifications
            this.checkUpcomingEvents();
            
            console.log('=== CARREGAMENTO DE EVENTOS CONCLUÍDO ===');
            
        } catch (error) {
            console.error('Erro ao carregar eventos:', error);
            this.hideLoading();
            this.showError('Não foi possível carregar os eventos. Tente atualizar a página.');
        }
    }

    async loadCNHEvents() {
        return this.fetchEventApi('api/calendario_cnh.php', 'event-cnh');
    }

    async loadMultasEvents() {
        return this.fetchEventApi('api/calendario_multas.php', 'event-multas');
    }

    async loadContasEvents() {
        return this.fetchEventApi('api/calendario_contas.php', 'event-contas');
    }

    async loadFinanciamentoEvents() {
        return this.fetchEventApi('api/calendario_financiamento.php', 'event-financiamento');
    }

    async loadManutencaoEvents() {
        return this.fetchEventApi('api/calendario_manutencao.php', 'event-manutencao');
    }

    async loadPersonalEvents() {
        return this.fetchEventApi('api/calendario_personal.php', 'event-personalizado');
    }
    
    openEventModal(event = null, start = null, end = null) {
        const modal = document.getElementById('eventModal');
        const modalTitle = document.getElementById('modalTitle');
        const deleteBtn = document.getElementById('deleteEventBtn');

        if (event) {
            this.currentEvent = event;
            const auto = this.isAutomaticEvent(event);
            modalTitle.textContent = auto ? 'Detalhes do evento' : 'Editar evento';
            const props = event.extendedProps || {};
            const sourceUrl = this.getEventSourceUrl(props);

            document.getElementById('eventId').value = event.id;
            document.getElementById('eventTitle').value = event.title;
            document.getElementById('eventCategory').value = calNormalizeCategory(props.category);
            document.getElementById('eventStart').value = this.formatDateTimeLocal(event.start);
            document.getElementById('eventEnd').value = event.end ? this.formatDateTimeLocal(event.end) : '';
            document.getElementById('eventDescription').value = props.description || event.description || '';
            document.getElementById('eventColor').value = event.backgroundColor || '#3788d8';
            document.getElementById('eventSource').value = props.source || 'manual';

            this.setModalReadonlyMode(auto, sourceUrl);
            if (deleteBtn) deleteBtn.style.display = auto ? 'none' : 'flex';
        } else {
            this.currentEvent = null;
            modalTitle.textContent = 'Novo evento';
            if (deleteBtn) deleteBtn.style.display = 'none';
            this.setModalReadonlyMode(false, null);
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';
            document.getElementById('eventSource').value = 'manual';
            if (start) document.getElementById('eventStart').value = this.formatDateTimeLocal(start);
            if (end) document.getElementById('eventEnd').value = this.formatDateTimeLocal(end);
        }

        modal.classList.add('active');
    }

    closeEventModal() {
        const modal = document.getElementById('eventModal');
        if (modal) modal.classList.remove('active');
        this.currentEvent = null;
        this.setModalReadonlyMode(false, null);
    }
    
    async saveEvent() {
        try {
            // Validar campos obrigatórios
            const title = document.getElementById('eventTitle').value.trim();
            const category = document.getElementById('eventCategory').value;
            const start = document.getElementById('eventStart').value;
            
            if (!title || !category || !start) {
                this.showError('Preencha todos os campos obrigatórios');
                return;
            }
            
            const formData = {
                id: document.getElementById('eventId').value,
                title: title,
                category: category,
                start: start,
                end: document.getElementById('eventEnd').value || null,
                description: document.getElementById('eventDescription').value.trim() || null,
                color: document.getElementById('eventColor').value,
                source: document.getElementById('eventSource').value
            };
            
            console.log('Dados do formulário:', formData);
            
            if (this.currentEvent) {
                // Update existing event
                await this.updateEvent(formData);
            } else {
                // Create new event
                await this.createEvent(formData);
            }
            
            this.closeEventModal();
            this.loadAutomaticEvents();
            
        } catch (error) {
            console.error('Erro ao salvar evento:', error);
            this.showError('Erro ao salvar evento');
        }
    }
    
    async createEvent(formData) {
        // Converter para FormData para compatibilidade com $_POST
        const postData = new FormData();
        for (const [key, value] of Object.entries(formData)) {
            postData.append(key, value);
        }
        
        const response = await fetch('api/calendario_create_simples.php', {
            method: 'POST',
            body: postData
        });
        
        if (!response.ok) {
            throw new Error('Erro ao criar evento');
        }
        
        this.showSuccess('Evento criado com sucesso!');
    }
    
    async updateEvent(formData) {
                    const response = await fetch('api/calendario_update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao atualizar evento');
        }
        
        this.showSuccess('Evento atualizado com sucesso!');
    }
    
    async deleteEvent() {
        if (!this.currentEvent) return;
        
        try {
            const confirmed = await Swal.fire({
                title: 'Confirmar exclusão',
                text: 'Tem certeza que deseja excluir este evento?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            });
            
            if (confirmed.isConfirmed) {
                const response = await fetch('api/calendario_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: this.currentEvent.id })
                });
                
                if (response.ok) {
                    this.showSuccess('Evento excluído com sucesso!');
                    this.closeEventModal();
                    this.loadAutomaticEvents();
                } else {
                    throw new Error('Erro ao excluir evento');
                }
            }
        } catch (error) {
            console.error('Erro ao excluir evento:', error);
            this.showError('Erro ao excluir evento');
        }
    }
    
    async updateEventDate(event) {
        if (this.isAutomaticEvent(event)) return;
        try {
            const response = await fetch('api/calendario_update_date.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: event.id,
                    start: this.formatDateTimeLocal(event.start),
                    end: event.end ? this.formatDateTimeLocal(event.end) : null
                })
            });
            if (!response.ok) {
                this.showError('Não foi possível mover o evento.');
                this.loadAutomaticEvents();
            }
        } catch (error) {
            this.showError('Erro ao atualizar data do evento.');
            this.loadAutomaticEvents();
        }
    }
    
    formatDateTimeLocal(date) {
        if (!date) return '';
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }
    
    checkUpcomingEvents() {
        const key = 'calendario_critico_' + new Date().toISOString().slice(0, 10);
        if (sessionStorage.getItem(key)) return;

        const today = new Date();
        const todayOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const criticalToday = this.removeDuplicateEvents(this.events.filter((event) => {
            const d = new Date(event.start);
            const dOnly = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            if (dOnly.getTime() !== todayOnly.getTime()) return false;
            const p = event.extendedProps || {};
            return p.priority === 'critical' || p.dias_para_vencimento === 0;
        }));

        if (criticalToday.length > 0) {
            sessionStorage.setItem(key, '1');
            this.showTodayEventsNotification(criticalToday);
        }
    }
    
    /**
     * Remove eventos duplicados baseado no título e data
     */
    removeDuplicateEvents(events) {
        const seen = new Map();
        const uniqueEvents = [];
        
        events.forEach(event => {
            const eventDate = new Date(event.start);
            const eventDateOnly = new Date(eventDate.getFullYear(), eventDate.getMonth(), eventDate.getDate());
            const key = `${event.title}_${eventDateOnly.getTime()}`;
            
            if (!seen.has(key)) {
                seen.set(key, true);
                uniqueEvents.push(event);
            }
        });
        
        return uniqueEvents;
    }
    
    showUpcomingEventsNotification(events) {
        // Agrupar eventos por data para melhor visualização
        const eventsByDate = this.groupEventsByDate(events);
        
        let eventList = '';
        Object.keys(eventsByDate).sort().forEach(date => {
            const dayEvents = eventsByDate[date];
            eventList += `\n📅 ${date}:\n`;
            dayEvents.forEach(event => {
                eventList += `  • ${event.title}\n`;
            });
        });
        
        // Obter nome do mês atual
        const monthNames = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];
        const currentMonth = monthNames[new Date().getMonth()];
        
        Swal.fire({
            title: 'Eventos do Mês Atual',
            html: `<div style="text-align: left; max-height: 300px; overflow-y: auto;">
                <p>Você tem <strong>${events.length} evento(s)</strong> em <strong>${currentMonth}</strong> que ainda estão por vir:</p>
                <pre style="font-family: inherit; white-space: pre-wrap; font-size: 12px;">${eventList}</pre>
            </div>`,
            icon: 'info',
            confirmButtonText: 'OK',
            width: '600px'
        });
    }
    
    /**
     * Agrupa eventos por data para melhor visualização
     */
    groupEventsByDate(events) {
        const grouped = {};
        
        events.forEach(event => {
            const eventDate = new Date(event.start);
            const dateKey = eventDate.toLocaleDateString('pt-BR');
            
            if (!grouped[dateKey]) {
                grouped[dateKey] = [];
            }
            grouped[dateKey].push(event);
        });
        
        return grouped;
    }

    showTodayEventsNotification(events) {
        // Agrupar eventos por data para melhor visualização
        const eventsByDate = this.groupEventsByDate(events);
        
        let eventList = '';
        Object.keys(eventsByDate).sort().forEach(date => {
            const dayEvents = eventsByDate[date];
            eventList += `\n📅 ${date}:\n`;
            dayEvents.forEach(event => {
                eventList += `  • ${event.title}\n`;
            });
        });

        Swal.fire({
            title: 'Vencimentos hoje',
            html: '<div style="text-align:left;max-height:300px;overflow-y:auto"><p><strong>' +
                calEscapeHtml(String(events.length)) +
                '</strong> item(ns) crítico(s) hoje:</p><pre style="font-family:inherit;white-space:pre-wrap;font-size:12px">' +
                calEscapeHtml(eventList) + '</pre></div>',
            icon: 'warning',
            confirmButtonText: 'OK',
            width: '600px'
        });
    }

    showNoUpcomingEventsMessage() {
        /* desativado — não interromper o usuário */
    }

    showLoading() {
        try {
            console.log('Mostrando loading...');
            const container = document.querySelector('.calendar-container');
            if (container) {
                // Adicionar loading sem destruir o calendário
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'calendar-loading';
                loadingDiv.innerHTML = '<i class="fas fa-spinner"></i> Carregando eventos...';
                loadingDiv.style.position = 'absolute';
                loadingDiv.style.top = '50%';
                loadingDiv.style.left = '50%';
                loadingDiv.style.transform = 'translate(-50%, -50%)';
                loadingDiv.style.zIndex = '1000';
                loadingDiv.style.background = 'rgba(255, 255, 255, 0.9)';
                loadingDiv.style.padding = '20px';
                loadingDiv.style.borderRadius = '8px';
                loadingDiv.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                
                container.style.position = 'relative';
                container.appendChild(loadingDiv);
                console.log('Loading exibido');
            } else {
                console.error('Container do calendário não encontrado');
            }
        } catch (error) {
            console.error('Erro ao mostrar loading:', error);
        }
    }
    
    hideLoading() {
        try {
            console.log('Ocultando loading...');
            const container = document.querySelector('.calendar-container');
            if (container) {
                // Remover apenas o loading
                const loadingDiv = container.querySelector('.calendar-loading');
                if (loadingDiv) {
                    loadingDiv.remove();
                    console.log('Loading removido');
                }
            } else {
                console.error('Container não encontrado');
            }
        } catch (error) {
            console.error('Erro ao remover loading:', error);
        }
    }
    
    showSuccess(message) {
        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: message,
            timer: 3000,
            showConfirmButton: false
        });
    }
    
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: message
        });
    }
    
    // =====================================================
    // FUNÇÕES DE SINCRONIZAÇÃO AUTOMÁTICA
    // =====================================================
    
    /**
     * Sincronizar calendário automaticamente
     */
    async syncCalendar() {
        try {
            console.log('Iniciando sincronização automática...');
            this.showLoading();
            
            const response = await fetch('api/calendario_sync.php?action=sync_all');
            const result = await response.json();
            
            if (result.success) {
                console.log('Sincronização concluída:', result);
                
                // Verificar se há dados válidos
                if (result.data && result.data.multas && result.data.cnh) {
                    this.showSuccess(`Sincronização concluída! ${result.data.multas.eventos_criados || 0} eventos de multas e ${result.data.cnh.eventos_criados || 0} eventos de CNH criados.`);
                } else if (result.data && result.data.error) {
                    this.showError('Erro na sincronização: ' + result.data.error);
                    return;
                } else {
                    this.showSuccess('Sincronização concluída com sucesso!');
                }
                
                // Não recarregar eventos automaticamente para evitar duplicação
                // Os eventos já foram carregados na inicialização
                // await this.loadAutomaticEvents();
                
                // Mostrar notificação de eventos próximos
                this.checkUpcomingEvents();
            } else {
                console.error('Erro na sincronização:', result);
                this.showError('Erro na sincronização: ' + (result.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro na sincronização:', error);
            this.showError('Erro na sincronização: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }
    
    /**
     * Verificar status da sincronização
     */
    async checkSyncStatus() {
        try {
            console.log('Verificando status da sincronização...');
            
            const response = await fetch('api/calendario_sync.php?action=status');
            const result = await response.json();
            
            if (result.success) {
                console.log('Status da sincronização:', result);
                this.showSyncStatusModal(result.data);
            } else {
                console.error('Erro ao verificar status:', result);
                this.showError('Erro ao verificar status: ' + (result.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro ao verificar status:', error);
            this.showError('Erro ao verificar status: ' + error.message);
        }
    }
    
    /**
     * Verificar se os triggers estão funcionando
     */
    async checkTriggers() {
        try {
            console.log('Verificando status dos triggers...');
            
            const response = await fetch('api/calendario_sync.php?action=check_triggers');
            const result = await response.json();
            
            if (result.success) {
                console.log('Status dos triggers:', result);
                this.showTriggersStatusModal(result.data);
            } else {
                console.error('Erro ao verificar triggers:', result);
                this.showError('Erro ao verificar triggers: ' + (result.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro ao verificar triggers:', error);
            this.showError('Erro ao verificar triggers: ' + error.message);
        }
    }
    
    /**
     * Forçar sincronização completa
     */
    async forceSync() {
        try {
            console.log('Iniciando sincronização forçada...');
            
            const result = await Swal.fire({
                title: 'Confirmar Sincronização Forçada',
                text: 'Esta ação irá remover todos os eventos automáticos existentes e recriar baseado nos dados atuais. Deseja continuar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, Forçar Sincronização',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444'
            });
            
            if (result.isConfirmed) {
                this.showLoading();
                
                const response = await fetch('api/calendario_sync.php?action=sync_all');
                const result = await response.json();
                
                if (result.success) {
                    console.log('Sincronização forçada concluída:', result);
                    
                    // Verificar se há dados válidos
                    if (result.data && result.data.multas && result.data.cnh) {
                        this.showSuccess(`Sincronização forçada concluída! ${result.data.multas.eventos_criados || 0} eventos de multas e ${result.data.cnh.eventos_criados || 0} eventos de CNH criados.`);
                    } else if (result.data && result.data.error) {
                        this.showError('Erro na sincronização forçada: ' + result.data.error);
                        return;
                    } else {
                        this.showSuccess('Sincronização forçada concluída com sucesso!');
                    }
                    
                    // Recarregar eventos do calendário (apenas na sincronização forçada)
                    await this.loadAutomaticEvents();
                    
                    // Mostrar notificação de eventos próximos
                    this.checkUpcomingEvents();
                } else {
                    console.error('Erro na sincronização forçada:', result);
                    this.showError('Erro na sincronização forçada: ' + (result.message || 'Erro desconhecido'));
                }
            }
        } catch (error) {
            console.error('Erro na sincronização forçada:', error);
            this.showError('Erro na sincronização forçada: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }
    
    /**
     * Mostrar modal com status da sincronização
     */
    showSyncStatusModal(data) {
        Swal.fire({
            title: 'Status da Sincronização',
            html: `
                <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                    <h4>Resumo da Sincronização</h4>
                    <div class="sync-status-grid">
                        <div class="sync-status-item">
                            <strong>Eventos de Multas:</strong> ${data.eventos_multas}
                        </div>
                        <div class="sync-status-item">
                            <strong>Eventos de CNH:</strong> ${data.eventos_cnh}
                        </div>
                        <div class="sync-status-item">
                            <strong>Multas Pendentes:</strong> ${data.multas_pendentes}
                        </div>
                        <div class="sync-status-item">
                            <strong>CNHs Válidas:</strong> ${data.cnh_validas}
                        </div>
                    </div>
                    
                    <h4>Status da Sincronização</h4>
                    <div class="sync-status-grid">
                        <div class="sync-status-item ${data.multas_sincronizadas ? 'sync-ok' : 'sync-error'}">
                            <strong>Multas:</strong> ${data.multas_sincronizadas ? '✅ Sincronizadas' : '❌ Desincronizadas'}
                        </div>
                        <div class="sync-status-item ${data.cnh_sincronizadas ? 'sync-ok' : 'sync-error'}">
                            <strong>CNH:</strong> ${data.cnh_sincronizadas ? '✅ Sincronizadas' : '❌ Desincronizadas'}
                        </div>
                    </div>
                    
                    <p class="sync-note">
                        <small>💡 Os eventos são criados automaticamente quando há novas multas ou alterações na CNH dos motoristas.</small>
                    </p>
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'OK',
            width: '600px'
        });
    }
    
    /**
     * Mostrar modal com status dos triggers
     */
    showTriggersStatusModal(data) {
        const triggersHtml = data.triggers.map(trigger => 
            `<tr>
                <td><strong>${trigger.Trigger}</strong></td>
                <td>${trigger.Table}</td>
                <td>${trigger.Timing}</td>
                <td>${trigger.Event}</td>
            </tr>`
        ).join('');
        
        const proceduresHtml = data.procedures.map(proc => 
            `<tr>
                <td><strong>${proc.Name}</strong></td>
                <td>${proc.Type}</td>
                <td>${proc.Definer}</td>
            </tr>`
        ).join('');
        
        Swal.fire({
            title: 'Status dos Triggers e Procedimentos',
            html: `
                <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                    <h4>Triggers Encontrados: ${data.triggers_encontrados}</h4>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Trigger</th>
                                <th>Tabela</th>
                                <th>Timing</th>
                                <th>Evento</th>
                            </tr>
                        </thead>
                        <tbody>${triggersHtml}</tbody>
                    </table>
                    
                    <h4>Procedimentos Encontrados: ${data.procedures_encontrados}</h4>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Definidor</th>
                            </tr>
                        </thead>
                        <tbody>${proceduresHtml}</tbody>
                    </table>
                    
                    <p class="sync-note">
                        <small>💡 Os triggers são responsáveis pela sincronização automática do calendário.</small>
                    </p>
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'OK',
            width: '700px'
        });
    }
    
    /**
     * Configurar sincronização automática
     */
    setupAutoSync() {
        // Sync automático desativado — eventos operacionais vêm das APIs ao vivo.
        // Use "Sync BD" apenas para recriar cópias em calendario_eventos (legado/triggers).
    }
}

// Initialize calendar when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DOM CARREGADO ===');
    console.log('Verificando FullCalendar:', typeof FullCalendar);
    console.log('Verificando Swal:', typeof Swal);
    
    try {
        new CalendarManager();
        console.log('CalendarManager inicializado com sucesso');
    } catch (error) {
        console.error('Erro ao inicializar CalendarManager:', error);
    }
});
