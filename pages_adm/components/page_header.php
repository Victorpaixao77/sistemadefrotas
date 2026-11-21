<?php
/** @var string $cc_title */
/** @var string|null $cc_subtitle */
/** @var string|null $cc_actions HTML */
?>
<header class="adm-cc-page-head">
    <div class="adm-cc-page-head__text">
        <h1 class="adm-cc-page-head__title"><?php echo htmlspecialchars($cc_title, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($cc_subtitle)): ?>
        <p class="adm-cc-page-head__sub"><?php echo htmlspecialchars($cc_subtitle, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($cc_actions)): ?>
    <div class="adm-cc-page-head__actions"><?php echo $cc_actions; ?></div>
    <?php endif; ?>
</header>
