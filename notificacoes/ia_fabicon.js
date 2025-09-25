document.addEventListener('DOMContentLoaded', function() {
  const fab = document.getElementById('iaFabBtn');
  if (!fab) return; // Não faz nada se não existir

  const panel = document.createElement('div');
  panel.className = 'ia-notification-panel';
  panel.innerHTML = `<div class="ia-notification-header"><h3 style='margin:0;font-size:1.1rem;font-weight:600;display:inline-block;'>Notificações IA</h3>
    <button class='notification-clear-btn' style='float:right;margin:0;'>Limpar</button></div><div class="ia-notification-list"></div>`;
  document.body.appendChild(panel);

  // Referência ao badge de notificações
  const badge = document.getElementById('iaNotificationBadge');

  function positionPanel() {
    // Se for botão IA do header, posiciona abaixo dele
    if (fab && fab.id === 'iaFabBtn') {
      const rect = fab.getBoundingClientRect();
      panel.style.position = 'absolute';
      panel.style.top = (window.scrollY + rect.bottom + 8) + 'px';
      panel.style.left = (window.scrollX + rect.left) + 'px';
      panel.style.right = 'auto';
      panel.style.bottom = 'auto';
      panel.style.zIndex = 10001;
      panel.style.width = '350px';
      panel.style.maxHeight = '60vh';
    } else {
      // fallback: canto inferior direito
      panel.style.position = 'fixed';
      panel.style.bottom = '110px';
      panel.style.right = '32px';
      panel.style.left = 'auto';
      panel.style.top = 'auto';
      panel.style.zIndex = 10000;
      panel.style.width = '350px';
      panel.style.maxHeight = '60vh';
    }
  }

  fab.addEventListener('click', function() {
    if (panel.classList.contains('active')) {
      panel.classList.remove('active');
    } else {
      panel.classList.add('active');
      positionPanel();
      carregarIANotificacoes();
    }
  });

  window.addEventListener('resize', function() {
    if (panel.classList.contains('active')) positionPanel();
  });
  window.addEventListener('scroll', function() {
    if (panel.classList.contains('active')) positionPanel();
  });

  function carregarIANotificacoes(verTodas = false) {
    const url = verTodas ? '/sistema-frotas/notificacoes/notificacoes.php?todas=1' : '/sistema-frotas/notificacoes/notificacoes.php';
    fetch(url)
      .then(res => res.json())
      .then(data => {
        const list = panel.querySelector('.ia-notification-list');
        list.innerHTML = '';
        let unreadCount = 0;
        
        if (data.success && data.notificacoes.length) {
          data.notificacoes.forEach(n => {
            const item = document.createElement('div');
            item.className = 'ia-notification-item';
            if (n.lida == 0) {
              item.classList.add('unread');
              unreadCount++;
            } else {
              item.classList.add('lida');
            }
            
            // Determina o ícone baseado no tipo
            let iconClass = 'info';
            let icon = 'fa-bell';
            if (n.tipo === 'manutencao') {
              iconClass = 'warning';
              icon = 'fa-tools';
            } else if (n.tipo === 'alerta') {
              iconClass = 'warning';
              icon = 'fa-exclamation-triangle';
            } else if (n.tipo === 'pneu') {
              iconClass = 'success';
              icon = 'fa-tire';
            } else if (n.tipo === 'documento') {
              iconClass = 'info';
              icon = 'fa-file-alt';
            } else if (n.tipo === 'financeiro') {
              iconClass = 'danger';
              icon = 'fa-dollar-sign';
            } else if (n.tipo === 'rota') {
              iconClass = 'info';
              icon = 'fa-route';
            } else if (n.tipo === 'abastecimento') {
              iconClass = 'warning';
              icon = 'fa-gas-pump';
            }
            
            // Formatar data de forma mais amigável
            const dataNotif = new Date(n.data_criacao);
            const agora = new Date();
            const diffDias = Math.floor((agora - dataNotif) / (1000 * 60 * 60 * 24));
            
            let tempoAtraso = '';
            if (diffDias === 0) {
              tempoAtraso = 'Hoje';
            } else if (diffDias === 1) {
              tempoAtraso = 'Ontem';
            } else if (diffDias < 7) {
              tempoAtraso = `${diffDias} dias atrás`;
            } else {
              tempoAtraso = dataNotif.toLocaleDateString('pt-BR');
            }
            
            item.innerHTML = `
              <div class='ia-notification-icon ${iconClass}'>
                <i class='fas ${icon}'></i>
              </div>
              <div class='ia-notification-content'>
                <div class='ia-notification-title'>${n.titulo}</div>
                <div class='ia-notification-msg'>${n.mensagem}</div>
                ${n.ia_mensagem ? `<div class='ia-dica'><i class='fas fa-lightbulb'></i> <b>Dica IA:</b> ${n.ia_mensagem}</div>` : ''}
                <div class='ia-notification-time'>${tempoAtraso} às ${dataNotif.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}</div>
              </div>
            `;
            list.appendChild(item);
          });
        } else {
          list.innerHTML = '<div style="color:#b8c2d0; text-align: center; padding: 20px;">Nenhuma notificação IA encontrada.</div>';
        }
        
        badge.textContent = unreadCount;
        badge.style.display = unreadCount > 0 ? 'flex' : 'none';
      })
      .catch(error => {
        console.error('Erro ao carregar notificações IA:', error);
        const list = panel.querySelector('.ia-notification-list');
        list.innerHTML = '<div style="color:#ef4444; text-align: center; padding: 20px;">Erro ao carregar notificações</div>';
      });
  }

  // Fecha o painel IA ao clicar fora
  document.addEventListener('click', function(e) {
    if (panel.classList.contains('active') && !panel.contains(e.target) && e.target !== fab) {
      panel.classList.remove('active');
    }
  });

  // Fecha o painel IA ao clicar no botão de notificações regular
  const notificationBtn = document.getElementById('notificationBtn');
  if (notificationBtn) {
    notificationBtn.addEventListener('click', function() {
      if (panel.classList.contains('active')) {
        panel.classList.remove('active');
      }
    });
  }

  // Limpar notificações IA (marca como lidas)
  const clearBtn = panel.querySelector('.notification-clear-btn');
  clearBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    fetch('/sistema-frotas/notificacoes/limpar_notificacoes_ia.php', { method: 'POST' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const list = panel.querySelector('.ia-notification-list');
          list.innerHTML = '<div style="color:#b8c2d0">Nenhuma notificação IA encontrada.</div>';
          // Atualiza o badge
          badge.textContent = '0';
          badge.style.display = 'none';
          
          // Atualiza também o badge das notificações regulares se existir
          const regularBadge = document.getElementById('notificationBadge');
          if (regularBadge && data.total_restantes !== undefined) {
            regularBadge.textContent = data.total_restantes;
            regularBadge.style.display = data.total_restantes > 0 ? 'flex' : 'none';
          }
        }
      });
  });

  // Adiciona footer com 'Ver todas'
  const footerDiv = document.createElement('div');
  footerDiv.className = 'ia-notification-footer';
  footerDiv.style = 'padding: 10px 0; text-align: center; border-top: 1px solid #243041; background: #1a2332;';
  footerDiv.innerHTML = `<a href='#' class='view-all-link'>Ver todas</a>`;
  panel.appendChild(footerDiv);
  const viewAllBtn = footerDiv.querySelector('.view-all-link');

  // 'Ver todas' mostra todas as notificações IA
  viewAllBtn.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    carregarIANotificacoes(true);
  });

  // Carrega as notificações inicialmente
  carregarIANotificacoes();
  
  // Torna a função global para ser chamada de outros scripts
  window.carregarIANotificacoes = carregarIANotificacoes;
}); 