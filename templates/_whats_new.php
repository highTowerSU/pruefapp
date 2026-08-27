<?php
/** @var array<int,array<string,mixed>> $whatsNewEntries */
echo \Ceneos\PhpBase\View\WhatsNewChecklist::render(
    $whatsNewEntries ?? [],
    $whatsNewChecked ?? [],
    url_for('downloads/was-ist-neu'),
    url_for('downloads/was-ist-neu/alle/erledigt'),
    ['id' => 'whats-new', 'nav_attributes' => 'data-action-nav="Was ist neu?" data-action-icon="fa-sparkles"']
);
