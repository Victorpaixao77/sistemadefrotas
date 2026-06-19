document.addEventListener('DOMContentLoaded', function() {
  const fab = document.getElementById('iaFabBtn');
  const sf = window.sfNotif;
  if (!fab || !sf) return;

  const badge = document.getElementById('iaNotificationBadge');

  const panel = document.createElement('div');
  panel.className = 'ia-notification-panel';
  panel.innerHTML = '<div class="ia-notification-header"><h3 style="margin:0;font-size:1.1rem;font-weight:600;display:inline-block;">Notificações IA</h3>' +
    '<button type="button" class="notification-clear-btn" style="float:right;margin:0;">Limpar</button></div>' +
    '<div class="ia-notification-list"></div>';
  document.body.appendChild(panel);

  const listEl = panel.querySelector('.ia-notification-list');
  sf.bindItemClicks(listEl);
  listEl.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-notif-acao]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    sf.runAction(btn.getAttribute('data-notif-acao'), btn.getAttribute('data-notif-ref'), btn.getAttribute('data-notif-id'))
      .then(function(data) { if (data && data.success) carregarIANotificacoes(false); });
  });

  function positionPanel() {
    if (fab && fab.id === 'iaFabBtn') {
      const rect = fab.getBoundingClientRect();
      panel.style.position = 'absolute';
      panel.style.top = (window.scrollY + rect.bottom + 8) + 'px';
      panel.style.left = (window.scrollX + rect.left) + 'px';
      panel.style.right = 'auto';
      panel.style.bottom = 'auto';
      panel.style.zIndex = '10001';
      panel.style.width = '350px';
      panel.style.maxHeight = 'min(75vh, 640px)';
    } else {
      panel.style.position = 'fixed';
      panel.style.bottom = '110px';
      panel.style.right = '32px';
      panel.style.left = 'auto';
      panel.style.top = 'auto';
      panel.style.zIndex = '10000';
      panel.style.width = '350px';
      panel.style.maxHeight = 'min(75vh, 640px)';
    }
  }

  function carregarIANotificacoes(verTodas) {
    const url = sf.buildListUrl('ia', !!verTodas);
    return fetch(url, { credentials: 'same-origin' })
      .then(function(res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function(data) {
        listEl.innerHTML = '';
        if (data.success && data.notificacoes && data.notificacoes.length) {
          listEl.innerHTML = data.notificacoes.map(function(n) {
            return sf.renderItem(n, {
              showIaTip: true,
              itemClass: 'ia-notification-item',
              iconWrapClass: 'ia-notification-icon',
              contentClass: 'ia-notification-content',
              titleClass: 'ia-notification-title',
              textClass: 'ia-notification-msg',
              timeClass: 'ia-notification-time'
            });
          }).join('');
        } else if (data.sistema_ativo === false) {
          listEl.innerHTML = '<div style="color:#b8c2d0;text-align:center;padding:20px;">Notificações desativadas nas configurações.</div>';
        } else {
          listEl.innerHTML = '<div style="color:#b8c2d0;text-align:center;padding:20px;">Nenhuma notificação IA encontrada.</div>';
        }
        sf.updateBadge(badge, data.total_nao_lidas ?? data.total_real_nao_lidas ?? 0);
        return data;
      })
      .catch(function(error) {
        console.error('Erro ao carregar notificações IA:', error);
        listEl.innerHTML = '<div style="color:#ef4444;text-align:center;padding:20px;">Erro ao carregar notificações</div>';
        if (badge) {
          badge.textContent = '!';
          badge.style.display = 'flex';
          badge.style.backgroundColor = '#ef4444';
        }
      });
  }

  window.carregarIANotificacoes = carregarIANotificacoes;

  fab.addEventListener('click', function() {
    if (panel.classList.contains('active')) {
      panel.classList.remove('active');
    } else {
      panel.classList.add('active');
      positionPanel();
      carregarIANotificacoes(false);
    }
  });

  window.addEventListener('resize', function() {
    if (panel.classList.contains('active')) positionPanel();
  });
  window.addEventListener('scroll', function() {
    if (panel.classList.contains('active')) positionPanel();
  });

  document.addEventListener('click', function(e) {
    if (panel.classList.contains('active') && !panel.contains(e.target) && e.target !== fab) {
      panel.classList.remove('active');
    }
  });

  const notificationBtn = document.getElementById('notificationBtn');
  if (notificationBtn) {
    notificationBtn.addEventListener('click', function() {
      if (panel.classList.contains('active')) panel.classList.remove('active');
    });
  }

  const clearBtn = panel.querySelector('.notification-clear-btn');
  clearBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    fetch(sfAppUrl('notificacoes/limpar_notificacoes_ia.php'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: sf.mutationHeaders()
    })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.success) {
          listEl.innerHTML = '<div style="color:#b8c2d0">Nenhuma notificação IA encontrada.</div>';
          sf.updateBadge(badge, data.ia ?? 0);
          sf.updateBadge(document.getElementById('notificationBadge'), data.operacional ?? 0);
        }
      });
  });

  const footerDiv = document.createElement('div');
  footerDiv.className = 'ia-notification-footer';
  footerDiv.style.cssText = 'padding:10px 0;text-align:center;border-top:1px solid #243041;background:#1a2332;';
  footerDiv.innerHTML = '<a href="' + sfAppUrl('pages/notificacoes.php') + '" class="view-all-link">Ver todas</a>';
  panel.appendChild(footerDiv);

  footerDiv.querySelector('.view-all-link').addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    carregarIANotificacoes(true);
    listEl.scrollTop = 0;
  });
});
