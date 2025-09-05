<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Footer do sistema
 */
?>

<footer class="megavote-footer" style="margin-top: auto; background: var(--megavote-gray-800); color: white; padding: 2rem 0;">
    <div class="megavote-container">
        <div class="megavote-grid megavote-grid-3">
            <!-- Informações da empresa -->
            <div>
                <h5 style="color: white; margin-bottom: 1rem;">
                    <span class="megavote-logo" style="margin-right: 0.5rem;">MV</span>
                    MegaVote
                </h5>
                <p class="megavote-text-small" style="color: var(--megavote-gray-400); line-height: 1.6;">
                    Sistema eletrônico transparente para sorteio de vagas de garagem. 
                    Desenvolvido com tecnologia moderna e segura.
                </p>
            </div>

            <!-- Links úteis -->
            <div>
                <h6 style="color: white; margin-bottom: 1rem;">Links Úteis</h6>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="margin-bottom: 0.5rem;">
                        <a href="https://megavote.com.br" target="_blank" 
                           style="color: var(--megavote-gray-400); text-decoration: none; font-size: 0.875rem;">
                            🌐 Site Oficial
                        </a>
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <a href="modelo.xlsx" 
                           style="color: var(--megavote-gray-400); text-decoration: none; font-size: 0.875rem;">
                            📥 Modelo de Planilha
                        </a>
                    </li>
                    <li style="margin-bottom: 0.5rem;">
                        <a href="mailto:contato@megavote.com.br" 
                           style="color: var(--megavote-gray-400); text-decoration: none; font-size: 0.875rem;">
                            📧 Suporte Técnico
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Informações do sistema -->
            <div>
                <h6 style="color: white; margin-bottom: 1rem;">Sistema</h6>
                <div class="megavote-text-small" style="color: var(--megavote-gray-400);">
                    <p style="margin-bottom: 0.5rem;">
                        <strong>Versão:</strong> <?= APP_VERSION ?>
                    </p>
                    <p style="margin-bottom: 0.5rem;">
                        <strong>Última atualização:</strong> <?= date('d/m/Y') ?>
                    </p>
                    <?php if (isset($_SESSION['logado']) && $_SESSION['logado']): ?>
                    <p style="margin-bottom: 0.5rem;">
                        <strong>Usuário:</strong> <?= htmlspecialchars($_SESSION['usuario']) ?>
                    </p>
                    <p style="margin-bottom: 0.5rem;">
                        <strong>Login:</strong> <?= date('d/m/Y H:i', $_SESSION['login_time'] ?? time()) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <hr style="border: none; border-top: 1px solid var(--megavote-gray-600); margin: 2rem 0 1rem 0;">

        <div class="megavote-flex megavote-flex-between" style="align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div class="megavote-text-small" style="color: var(--megavote-gray-400);">
                © <?= date('Y') ?> MegaVote. Todos os direitos reservados.
            </div>
            
            <div class="megavote-flex" style="gap: 1.5rem;">
                <a href="https://megavote.com.br" target="_blank" 
                   style="color: var(--megavote-gray-400); text-decoration: none; font-size: 0.875rem;"
                   data-tooltip="Visite nosso site oficial">
                    🌐 megavote.com.br
                </a>
                <a href="mailto:contato@megavote.com.br" 
                   style="color: var(--megavote-gray-400); text-decoration: none; font-size: 0.875rem;"
                   data-tooltip="Entre em contato conosco">
                    📧 Contato
                </a>
            </div>
        </div>
    </div>
</footer>

<style>
/* Estilos específicos do footer */
.megavote-footer a:hover {
    color: var(--megavote-primary-light) !important;
    transition: var(--transition);
}

@media (max-width: 768px) {
    .megavote-footer .megavote-grid-3 {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .megavote-footer .megavote-flex-between {
        flex-direction: column;
        text-align: center;
    }
}
</style>

