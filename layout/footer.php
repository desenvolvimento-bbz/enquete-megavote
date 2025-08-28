  </div><!-- /.container -->
</main>

<!-- Bootstrap Bundle + App JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $prefix ?>public/js/app.js"></script>

<!-- Modal genérica de confirmação -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar ação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body" id="confirmModalBody">
        Tem certeza?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmModalYes">Confirmar</button>
      </div>
    </div>
  </div>
</div>

</body>

<!-- Footer simples -->
<footer class="border-top bg-white">
  <div class="container py-3 text-center">
    <small>Powered by <strong>MegaVote</strong></small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
