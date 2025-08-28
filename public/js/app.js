// Modal de confirmação genérica (funciona com <form> e <a>)
document.addEventListener('DOMContentLoaded', () => {
  // 1) Bootstrap carregado?
  if (!window.bootstrap || !bootstrap.Modal) {
    console.error('[confirm] Bootstrap JS não carregado antes do app.js');
    return;
  }

  // 2) Garante a existência do modal; cria se não houver
  let modalEl = document.getElementById('confirmModal');
  if (!modalEl) {
    modalEl = document.createElement('div');
    modalEl.id = 'confirmModal';
    modalEl.className = 'modal fade';
    modalEl.tabIndex = -1;
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-light">
            <h5 class="modal-title">Confirmação</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <p class="mb-0" data-confirm-body>Tem certeza?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-danger" data-confirm-yes>Sim</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(modalEl);
  }

  const modal   = new bootstrap.Modal(modalEl);
  const bodyEl  = modalEl.querySelector('[data-confirm-body]');
  const btnYes  = modalEl.querySelector('[data-confirm-yes]');

  let pendingAction = null;

  // 3) Captura qualquer clique em elemento com [data-confirm]
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;

    e.preventDefault();

    const msg = btn.getAttribute('data-confirm') || 'Tem certeza?';
    bodyEl.textContent = msg;

    // Se estiver dentro de um <form>, vamos submeter o form
    const form = btn.closest('form');
    if (form) {
      pendingAction = () => form.submit();
      modal.show();
      return;
    }

    // Se for um link <a>, redireciona para o href
    const a = btn.matches('a, a *') ? btn.closest('a') : null;
    const href = a?.getAttribute('href');
    if (href) {
      pendingAction = () => { window.location.href = href; };
      modal.show();
      return;
    }

    // Fallback: dispara o clique real no elemento
    pendingAction = () => btn.click();
    modal.show();
  });

  btnYes?.addEventListener('click', () => {
    if (pendingAction) {
      const fn = pendingAction;
      pendingAction = null;
      modal.hide();
      fn();
    }
  });
});
