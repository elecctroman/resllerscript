<?php if (!empty($items) && is_array($items)): ?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <?php foreach ($items as $crumb): ?>
            <?php
            $label = isset($crumb['label']) ? (string) $crumb['label'] : '';
            $href = isset($crumb['href']) ? (string) $crumb['href'] : '';
            $isCurrent = !empty($crumb['active']);
            ?>
            <li class="breadcrumb-item<?php echo $isCurrent ? ' active' : ''; ?>"<?php echo $isCurrent ? ' aria-current="page"' : ''; ?>>
                <?php if ($href && !$isCurrent): ?>
                    <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php else: ?>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
