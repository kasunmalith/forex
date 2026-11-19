<?php require APP_ROOT . '/views/components/header.php'; ?>
<div class="container-wide design-page">
    <?php render('components/breadcrumbs', ['items' => [
        ['label' => 'Home', 'url' => site_url()],
        ['label' => 'Design System'],
    ]]); ?>
    <div class="design-page-header">
        <div><span class="eyebrow">Development preview · Not linked in public navigation</span><h1>Design system</h1><p class="body-large">A reusable visual foundation for <?= e($companyName ?? 'MultiForex') ?> public pages.</p></div>
        <?php render('components/badge', ['variant' => 'demo', 'text' => 'DEMO UI']); ?>
    </div>
    <div class="design-section"><?php render('components/section-heading', ['eyebrow'=>'Reusable component','heading'=>'Section heading','description'=>'A shared heading pattern with an optional link, consistent spacing and a centered mode.','actionLabel'=>'Explore markets','actionUrl'=>site_url('markets')]); ?></div>

    <section class="design-section" aria-labelledby="tokens-title">
        <h2 class="design-section-title" id="tokens-title">Colour tokens</h2>
        <div class="swatch-grid">
            <?php foreach ([['Background','--color-bg'],['Alt background','--color-bg-alt'],['Surface','--color-surface'],['Elevated','--color-surface-elevated'],['Text','--color-text'],['Muted','--color-text-muted'],['Primary','--color-primary'],['Accent','--color-accent'],['Success','--color-success'],['Warning','--color-warning'],['Danger','--color-danger'],['Info','--color-info']] as [$label, $token]): ?>
                <div class="swatch"><div class="swatch-color" style="background:var(<?= e($token) ?>)"></div><div class="swatch-label"><?= e($label) ?> · <?= e($token) ?></div></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="design-section" aria-labelledby="type-title">
        <h2 class="design-section-title" id="type-title">Typography</h2>
        <p class="eyebrow">Display heading · H1</p><h2 class="display-heading">Markets move.<br>Practice with clarity.</h2>
        <h2>H2 · Platform foundations</h2><h3>H3 · Clear information hierarchy</h3><h4>H4 · Supporting section title</h4>
        <p class="body-large">Body large · Readable supporting copy with a calm, measured pace for public financial information.</p>
        <p>Body normal · Instrument names, helpful content and platform descriptions have a consistent baseline.</p>
        <p class="body-small">Body small · Secondary descriptions stay comfortable to read on smaller screens.</p>
        <p class="caption">Caption · Simulated values for interface preview only.</p>
        <p class="label">Label · <span class="market-symbol">EURUSD</span> · <span class="financial-number">1.08420</span> · <span class="text-positive">▲ +0.24%</span> · <span class="text-negative">▼ −0.83%</span></p>
    </section>

    <section class="design-section" aria-labelledby="buttons-title">
        <h2 class="design-section-title" id="buttons-title">Buttons and links</h2>
        <div class="cluster">
            <?php foreach ([['primary','Primary'],['secondary','Secondary'],['outline','Outline'],['ghost','Ghost'],['danger','Danger'],['success','Success']] as [$variant,$label]): ?>
                <?php render('components/button', ['label' => $label, 'variant' => $variant]); ?>
            <?php endforeach; ?>
        </div>
        <div class="cluster preview-space-top">
            <?php render('components/button', ['label' => 'Small', 'variant' => 'secondary', 'size' => 'sm']); ?>
            <?php render('components/button', ['label' => 'Default', 'variant' => 'primary']); ?>
            <?php render('components/button', ['label' => 'Large CTA', 'variant' => 'primary', 'size' => 'lg', 'icon' => 'arrow-up-right']); ?>
            <?php render('components/button', ['label' => 'Disabled', 'variant' => 'secondary', 'disabled' => true]); ?>
            <?php render('components/button', ['label' => 'Loading', 'variant' => 'primary', 'loading' => true]); ?>
            <a class="text-link" href="<?= e(site_url('markets')) ?>">Text link <?= ui_icon('arrow-right') ?></a>
        </div>
    </section>

    <section class="design-section" aria-labelledby="forms-title">
        <h2 class="design-section-title" id="forms-title">Form fields</h2>
        <form class="demo-form form-grid" data-demo-form>
            <?php render('components/form-field', ['name'=>'name','label'=>'Full name','placeholder'=>'Your name','required'=>true,'help'=>'A visible label and helper text.']); ?>
            <?php render('components/form-field', ['name'=>'email','label'=>'Email address','type'=>'email','placeholder'=>'name@example.com','value'=>'hello@example.com','required'=>true]); ?>
            <?php render('components/form-field', ['name'=>'password','label'=>'Password','type'=>'password','placeholder'=>'Enter a password']); ?>
            <?php render('components/form-field', ['name'=>'volume','label'=>'Demo volume','type'=>'number','value'=>'0.10','help'=>'Numeric field with decimal support.']); ?>
            <?php render('components/form-field', ['name'=>'phone','label'=>'Phone','type'=>'tel','placeholder'=>'+94']); ?>
            <?php render('components/form-field', ['name'=>'account_type','label'=>'Account type','type'=>'select','placeholder'=>'Choose an account','options'=>['standard'=>'Standard','pro'=>'Pro','ecn_demo'=>'ECN Demo']]); ?>
            <?php render('components/form-field', ['name'=>'message','label'=>'Message','type'=>'textarea','placeholder'=>'Write a short note','fullWidth'=>true]); ?>
            <?php render('components/form-field', ['name'=>'country','label'=>'Country','type'=>'select','value'=>'LK','options'=>['LK'=>'Sri Lanka','GB'=>'United Kingdom','AU'=>'Australia']]); ?>
            <?php render('components/form-field', ['name'=>'birth_date','label'=>'Date','type'=>'date']); ?>
            <?php render('components/form-field', ['name'=>'attachment','label'=>'File upload','type'=>'file','help'=>'Private documents belong in protected storage.']); ?>
            <?php render('components/form-field', ['name'=>'updates','label'=>'Receive platform updates','type'=>'checkbox','checked'=>true]); ?>
            <?php render('components/form-field', ['name'=>'terms_demo','label'=>'Demo consent toggle','type'=>'toggle']); ?>
            <div class="cluster form-field is-full"><button class="btn btn-primary" type="submit">Preview form state</button><span class="caption">This sample form does not submit data.</span></div>
        </form>
        <div class="form-state-samples"><div class="form-field"><label for="sample-error">Error state</label><input class="field-control is-invalid" id="sample-error" value="Invalid value" aria-invalid="true" aria-describedby="sample-error-message"><p class="field-error" id="sample-error-message">Check this field and try again.</p></div><div class="form-field"><label for="sample-disabled">Disabled state</label><input class="field-control" id="sample-disabled" value="Unavailable" disabled></div></div>
    </section>

    <section class="design-section" aria-labelledby="cards-title">
        <h2 class="design-section-title" id="cards-title">Cards and market data</h2>
        <div class="grid grid-3">
            <?php render('components/card', ['title'=>'Standard content card','description'=>'A restrained surface for grouped information.','body'=>'<p class="card-description">Use a card where content benefits from a clear boundary.</p>']); ?>
            <article class="card card-stat"><span class="stat-label">Demo account balance</span><p class="stat-value financial-number">$100,000.00</p><span class="badge badge-demo">VIRTUAL FUNDS</span></article>
            <article class="card card-article"><div class="article-image-placeholder" role="img" aria-label="Article image placeholder"><?= ui_icon('chart') ?></div><div class="article-card-body"><span class="eyebrow">Education · Draft preview</span><h3>Understanding a demo spread</h3><p class="body-small">Article card with a fixed ratio image area to help prevent layout shift.</p><a class="text-link" href="<?= e(site_url('education')) ?>">Read more <?= ui_icon('arrow-right') ?></a></div></article>
            <?php render('components/market-card', ['symbol'=>'EURUSD','name'=>'Euro / US Dollar · Demo','price'=>'1.08420','change'=>'+0.24%','direction'=>'positive','bid'=>'1.08408','ask'=>'1.08432']); ?>
            <?php render('components/market-card', ['symbol'=>'XAUUSD','name'=>'Gold / US Dollar · Demo','price'=>'2,325.50','change'=>'−0.18%','direction'=>'negative','bid'=>'2,325.25','ask'=>'2,325.75']); ?>
            <article class="card card-stat"><span class="stat-label">Illustrative equity</span><p class="stat-value financial-number">$100,248.60</p><span class="caption">Static design preview only</span></article>
        </div>
        <p class="preview-note preview-space-top">All prices and balances above are static visual examples. No feed or trading logic is active.</p>
    </section>

    <section class="design-section" aria-labelledby="badges-title">
        <h2 class="design-section-title" id="badges-title">Badges and statuses</h2>
        <div class="cluster">
            <?php foreach ([['success','Active'],['warning','Pending'],['success','Verified'],['danger','Rejected'],['danger','Suspended'],['demo','Demo'],['info','Open'],['neutral','Closed']] as [$variant,$text]): ?><?php render('components/badge', ['variant'=>$variant,'text'=>$text]); ?><?php endforeach; ?>
        </div>
    </section>

    <section class="design-section" aria-labelledby="tables-title">
        <h2 class="design-section-title" id="tables-title">Responsive data table</h2>
        <div class="table-wrap" role="region" aria-label="Example market instruments table" tabindex="0">
            <table class="data-table"><thead><tr><th>Instrument</th><th>Name</th><th class="numeric">Demo price</th><th class="numeric">Change</th><th>Status</th><th>Action</th></tr></thead><tbody>
                <tr><td class="symbol-cell"><span class="market-symbol">EURUSD</span></td><td>Euro / US Dollar</td><td class="numeric">1.08420</td><td class="numeric text-positive">▲ +0.24%</td><td><span class="badge badge-demo">Simulated</span></td><td><a class="row-action" href="<?= e(site_url('markets')) ?>" aria-label="View EURUSD">↗</a></td></tr>
                <tr><td class="symbol-cell"><span class="market-symbol">XAUUSD</span></td><td>Gold / US Dollar</td><td class="numeric">2,325.50</td><td class="numeric text-negative">▼ −0.18%</td><td><span class="badge badge-demo">Simulated</span></td><td><a class="row-action" href="<?= e(site_url('markets')) ?>" aria-label="View XAUUSD">↗</a></td></tr>
                <tr><td class="symbol-cell"><span class="market-symbol">BTCUSD</span></td><td>Bitcoin / US Dollar</td><td class="numeric">65,000.00</td><td class="numeric text-neutral">• 0.00%</td><td><span class="badge badge-demo">Simulated</span></td><td><a class="row-action" href="<?= e(site_url('markets')) ?>" aria-label="View BTCUSD">↗</a></td></tr>
            </tbody></table>
        </div>
        <div class="table-wrap preview-space-top"><table class="data-table"><tbody><tr><td class="table-empty">No open positions</td></tr></tbody></table></div>
    </section>

    <section class="design-section" aria-labelledby="messages-title">
        <h2 class="design-section-title" id="messages-title">Alerts and toast messages</h2>
        <div class="stack">
            <?php render('components/alert', ['type'=>'success','title'=>'Saved','message'=>'Your demo preferences have been saved.','dismissible'=>true]); ?>
            <?php render('components/alert', ['type'=>'error','title'=>'Request failed','message'=>'We could not load this information. Please try again.','dismissible'=>true]); ?>
            <?php render('components/alert', ['type'=>'warning','title'=>'Simulated values','message'=>'The values shown in this preview are not live market data.']); ?>
            <?php render('components/alert', ['type'=>'info','title'=>'Demo environment','message'=>'No real funds are processed in this platform.']); ?>
        </div>
        <div class="cluster preview-space-top"><button class="btn btn-secondary" type="button" data-toast="success">Show success toast</button><button class="btn btn-secondary" type="button" data-toast="warning">Show warning toast</button></div>
    </section>

    <section class="design-section" aria-labelledby="modal-title-section">
        <h2 class="design-section-title" id="modal-title-section">Modal and dialog</h2>
        <button class="btn btn-outline" type="button" data-modal-open="#sample-modal">Open information modal</button>
    </section>
    <?php render('components/modal', ['id'=>'sample-modal','eyebrow'=>'Demo information','title'=>'Preview dialog','message'=>'This accessible dialog can be dismissed with Escape, the close control, or the close button.']); ?>

    <section class="design-section" aria-labelledby="loading-title">
        <h2 class="design-section-title" id="loading-title">Loading and skeleton states</h2>
        <div class="grid grid-3"><div class="card"><h3 class="card-title">Inline loading</h3><?php render('components/loading', ['message'=>'Loading demo information…']); ?></div>
            <div class="skeleton-card"><div class="skeleton skeleton-line is-short"></div><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line is-mid"></div><div class="skeleton skeleton-line"></div></div>
            <div class="skeleton-card"><div class="skeleton skeleton-line is-mid"></div><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line is-short"></div></div>
        </div>
    </section>

    <section class="design-section" aria-labelledby="empty-title-section">
        <h2 class="design-section-title" id="empty-title-section">Empty and error states</h2>
        <div class="grid grid-2"><?php render('components/empty-state', ['heading'=>'No open positions','message'=>'Open demo positions will appear here when that future feature is available.']); ?><?php render('components/error-state', ['heading'=>'Unable to load market data','message'=>'The request did not complete. Try again when the service is available.','retryLabel'=>'Retry']); ?></div>
    </section>

    <section class="design-section" aria-labelledby="hero-foundation-title">
        <h2 class="design-section-title" id="hero-foundation-title">Reusable hero foundation</h2>
        <div class="hero-demo">
            <?php render('components/hero', ['eyebrow'=>'Hero component','heading'=>'A clear starting point','description'=>'Eyebrow, responsive heading, supporting text, actions and a visual slot are assembled from reusable pieces.','actions'=>[['label'=>'Primary action','variant'=>'primary','href'=>site_url('markets')],['label'=>'Secondary action','variant'=>'outline','href'=>site_url('platform')]]]); ?>
        </div>
    </section>

    <section class="design-section" aria-labelledby="ticker-title">
        <h2 class="design-section-title" id="ticker-title">Static market ticker</h2>
        <?php render('components/market-ticker', ['items'=>[
            ['symbol'=>'EURUSD','price'=>'1.08420','change'=>'+0.24%','direction'=>'positive'],
            ['symbol'=>'US30','price'=>'38,850.0','change'=>'−0.12%','direction'=>'negative'],
            ['symbol'=>'BTCUSD','price'=>'65,000.00','change'=>'0.00%','direction'=>'neutral'],
        ]]); ?>
        <p class="caption caption-small-spaced">Static demo examples · No price updates are running.</p>
    </section>

    <section class="design-section" aria-labelledby="light-title">
        <h2 class="design-section-title" id="light-title">Light section support</h2>
        <div class="surface-light demo-light"><span class="eyebrow">Selective light surface</span><h3>Consistent type on a light background</h3><p>Future public pages can alternate dark and light sections while keeping spacing and type tokens consistent.</p><a class="text-link" href="<?= e(site_url('about')) ?>">Light surface link <?= ui_icon('arrow-right') ?></a></div>
    </section>

    <section class="design-section" aria-labelledby="utilities-title">
        <h2 class="design-section-title" id="utilities-title">Navigation utilities and accessible tooltips</h2>
        <div class="stack">
            <?php render('components/breadcrumbs', ['items'=>[['label'=>'Home','url'=>site_url()],['label'=>'Markets','url'=>site_url('markets')],['label'=>'Forex']]]); ?>
            <?php render('components/pagination', ['current'=>2,'total'=>5,'url'=>site_url('articles')]); ?>
            <p>Hover or focus for a short definition: <span tabindex="0" data-tooltip="The funds reserved to maintain an open demo position.">Margin</span>, <span tabindex="0" data-tooltip="The difference between a demo bid and demo ask price.">Spread</span>.</p>
        </div>
    </section>
</div>
<?php require APP_ROOT . '/views/components/footer.php'; ?>
