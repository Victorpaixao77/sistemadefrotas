<?php
/**
 * Card de suporte no rodapé do menu lateral (WhatsApp).
 */
declare(strict_types=1);

if (!function_exists('sf_support_whatsapp_url')) {
    require_once __DIR__ . '/sf_paths.php';
}

$sf_support_wa_url = sf_support_whatsapp_url();
?>
<div class="sidebar-support">
    <a href="<?php echo htmlspecialchars($sf_support_wa_url, ENT_QUOTES, 'UTF-8'); ?>"
       class="sidebar-support__mini"
       target="_blank"
       rel="noopener noreferrer"
       title="Suporte WhatsApp"
       aria-label="Abrir suporte no WhatsApp">
        <i class="fab fa-whatsapp" aria-hidden="true"></i>
    </a>
    <div class="sidebar-support__card">
        <p class="sidebar-support__title">Suporte 24/7</p>
        <p class="sidebar-support__text">Precisa de ajuda?</p>
        <a href="<?php echo htmlspecialchars($sf_support_wa_url, ENT_QUOTES, 'UTF-8'); ?>"
           class="sidebar-support__btn"
           target="_blank"
           rel="noopener noreferrer">
            Abrir chamado
        </a>
    </div>
</div>
