<?php
/**
 * Workflow Management Handler
 */

if (isset($_POST['update_content_workflow'])) {
    $workflow = trim((string)($_POST['content_workflow'] ?? 'rss'));
    if (!in_array($workflow, ['rss', 'web'], true)) {
        $workflow = 'rss';
    }

    setSetting('content_workflow', $workflow);
    $_SESSION['flash_message'] = 'Content workflow updated.';
    $_SESSION['flash_type'] = 'success';
    header('Location: admin.php');
    exit;
}

if (isset($_POST['run_content_workflow'])) {
    $result = runSelectedContentWorkflow();
    $workflowName = ($result['workflow'] ?? 'rss') === 'web' ? 'Normal Sites' : 'RSS';
    $published = (int)($result['published'] ?? 0);
    $sourcesCount = (int)($result['sources_count'] ?? 0);

    $_SESSION['flash_message'] = "Workflow {$workflowName}: published {$published} new article(s) from {$sourcesCount} source(s).";
    $_SESSION['flash_type'] = 'info';
    header('Location: admin.php');
    exit;
}
