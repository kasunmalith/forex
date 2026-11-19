<?php
$statusLabel = \App\Services\Support\SupportTicketService::STATUSES[$ticket['status']] ?? 'Open';
$categories = \App\Services\Support\SupportTicketService::CATEGORIES;
?>
<section class="client-page-intro">
    <div>
        <span class="client-eyebrow">SUPPORT TICKET · <?= e($ticket['ticket_number']) ?></span>
        <h1><?= e($ticket['subject']) ?></h1>
        <p><?= e($categories[$ticket['category']] ?? $ticket['category']) ?> · <?= e(ucfirst($ticket['priority'])) ?> priority · Created <?= e(\App\Services\DashboardService::formatDate($ticket['created_at'])) ?></p>
    </div>
    <a class="client-button client-button-secondary client-page-action" href="<?= e(\client_url('support')) ?>">All tickets</a>
</section>
<section class="client-panel comm-thread">
    <header class="ticket-thread-head">
        <div><span class="ticket-status is-<?= e($ticket['status']) ?>"><?= e($statusLabel) ?></span><span class="ticket-reference"><?= e($ticket['ticket_number']) ?></span></div>
        <?php if ($ticket['status'] === 'resolved'): ?>
            <form action="<?= e(\site_url('api/support/tickets/' . $ticket['ticket_number'] . '/reopen')) ?>" method="post" data-support-action><?= \csrf_field() ?><button class="client-button client-button-secondary" type="submit">Reopen ticket</button></form>
        <?php elseif (!in_array($ticket['status'], ['closed', 'resolved'], true)): ?>
            <form action="<?= e(\site_url('api/support/tickets/' . $ticket['ticket_number'] . '/close')) ?>" method="post" data-support-action data-confirm="Close this support ticket? You will not be able to send further replies."><?= \csrf_field() ?><button class="client-button client-button-quiet" type="submit">Close ticket</button></form>
        <?php endif; ?>
    </header>
    <ol class="ticket-messages" data-ticket-messages>
        <?php foreach ($ticket['messages'] as $message): ?>
            <li class="ticket-message is-<?= e($message['sender_type'] === 'admin' ? 'support' : 'client') ?>">
                <header><strong><?= e($message['sender_label']) ?></strong><time datetime="<?= e(date(DATE_ATOM, strtotime($message['created_at']))) ?>"><?= e(\App\Services\DashboardService::formatDate($message['created_at'])) ?></time></header>
                <div class="ticket-message-body"><?= $message['message_html'] ?></div>
                <?php if (!empty($message['attachment'])): ?>
                    <a class="ticket-attachment" href="<?= e(\client_url('support/attachment/' . $message['attachment']['attachment_reference'])) ?>" aria-label="Download attachment <?= e($message['attachment']['original_filename']) ?>">Attachment · <?= e($message['attachment']['original_filename']) ?> · <?= e(number_format((int) $message['attachment']['file_size'] / 1024, 0)) ?> KB</a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php if ($ticket['status'] !== 'closed'): ?>
        <form class="ticket-reply-form" action="<?= e(\site_url('api/support/tickets/' . $ticket['ticket_number'] . '/reply')) ?>" method="post" enctype="multipart/form-data" data-support-form data-reply-form>
            <?= \csrf_field() ?><input type="hidden" name="request_token" value="<?= e($requestToken) ?>">
            <label for="ticket-reply">Your reply</label>
            <textarea id="ticket-reply" name="message" rows="5" maxlength="10000" required placeholder="Write a reply…"></textarea>
            <label class="comm-file-label">Optional attachment
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.txt,application/pdf,image/jpeg,image/png,text/plain">
                <small data-file-name>No file selected · max 5 MB</small>
                <button type="button" class="client-button client-button-quiet" data-file-clear hidden>Remove attachment</button>
            </label>
            <div class="comm-form-error" data-form-error role="alert" hidden></div>
            <div class="comm-form-footer"><button class="client-button" type="submit">Send reply</button><span data-form-status role="status" aria-live="polite"></span></div>
            <p class="comm-offline" data-offline hidden>You appear to be offline. Your message is still in this form.</p>
        </form>
    <?php else: ?>
        <div class="ticket-closed-note" role="status">This ticket is closed. Create a new ticket if you need further help.</div>
        <a class="client-button client-button-secondary" href="<?= e(\client_url('support/new')) ?>">Create new ticket</a>
    <?php endif; ?>
</section>
