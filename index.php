<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$router = new App\Router();
$pages = new App\Controllers\PageController();
$markets = new App\Controllers\MarketsController();
$publicPages = new App\Controllers\PublicPagesController();
$tools = new App\Controllers\ToolsController();
$contact = new App\Controllers\ContactController();
$faq = new App\Controllers\FaqController();
$auth = new App\Controllers\AuthController();
$client = new App\Controllers\ClientController();
$marketData = new App\Controllers\MarketDataController();
$trading = new App\Controllers\TradingController();
$verification = new App\Controllers\VerificationController();
$communication = new App\Controllers\CommunicationController();
$adminAuth = new App\Controllers\Admin\AdminAuthController();
$adminHome = new App\Controllers\Admin\AdminHomeController();
$adminOperations = new App\Controllers\Admin\AdminOperationsController();
$adminInstruments = new App\Controllers\Admin\AdminInstrumentsController();
$adminTrading = new App\Controllers\Admin\AdminTradingController();
$adminCms = new App\Controllers\Admin\AdminCmsController();
$cmsPublic = new App\Controllers\CmsPublicController();

$router->get('/', [$pages, 'home']);
$router->get('/register', [$auth,'registerForm']);
$router->post('/register', [$auth,'register']);
$router->get('/login', [$auth,'loginForm']);
$router->post('/login', [$auth,'login']);
$router->post('/logout', [$auth,'logout']);
$router->get('/forgot-password', [$auth,'forgotForm']);
$router->post('/forgot-password', [$auth,'forgot']);
$router->get('/reset-password', [$auth,'resetForm']);
$router->post('/reset-password', [$auth,'reset']);
foreach (['platform','accounts','company','about','security','terms','privacy','risk-disclosure'] as $page) {
    $router->get('/'.$page, static function () use ($publicPages,$page): void { $publicPages->show($page); });
}
$router->get('/contact', [$contact,'index']);
$router->get('/support', static function (): void { \redirect('contact'); });
$router->get('/faq', [$faq,'index']);
$router->get('/tools', [$tools,'index']);
foreach (['forex-calculator','margin-calculator','profit-loss-calculator','currency-converter'] as $toolSlug) {
    $router->get('/tools/'.$toolSlug, static function () use ($tools,$toolSlug): void { $tools->calculator($toolSlug); });
}
$router->post('/api/contact', [$contact,'submit']);
$router->get('/api/market/quotes', [$marketData,'quotes']);
$router->get('/api/market/quote', [$marketData,'quote']);
$router->get('/api/market/candles', [$marketData,'candles']);
$router->post('/api/trading/order', [$trading,'placeOrder']);
$router->post('/api/trading/position/close', [$trading,'closePosition']);
$router->post('/api/trading/position/modify', [$trading,'modifyPosition']);
$router->get('/api/trading/positions', [$trading,'positions']);
$router->get('/api/trading/state', [$trading,'state']);
$router->get('/api/trading/orders', [$trading,'orders']);
$router->get('/api/trading/history/detail', [$trading,'historyDetail']);
$router->get('/api/trading/history/export', [$trading,'historyExport']);
$router->get('/api/trading/history', [$trading,'history']);
$router->post('/api/client/watchlist', [$client,'watchlistApi']);
$router->get('/api/client/snapshot', [$trading,'clientSnapshot']);
$router->post('/api/verification/profile', [$verification,'saveProfile']);
$router->post('/api/verification/documents', [$verification,'upload']);
$router->post('/api/verification/documents/remove', [$verification,'removeDocument']);
$router->post('/api/verification/submit', [$verification,'submit']);
$router->get('/client/verification/document', [$verification,'document']);
$router->get('/api/notifications/count', [$communication,'notificationsCount']);
$router->post('/api/notifications/read-all', [$communication,'markAllRead']);
$router->post('/api/notifications/{reference}/read', [$communication,'markRead']);
$router->post('/api/support/tickets', [$communication,'create']);
$router->post('/api/support/tickets/{reference}/reply', [$communication,'reply']);
$router->post('/api/support/tickets/{reference}/close', [$communication,'close']);
$router->post('/api/support/tickets/{reference}/reopen', [$communication,'reopen']);
$router->get('/client/support/attachment/{reference}', [$communication,'download']);
$router->get('/client/support/new', [$communication,'newTicket']);
$router->get('/client/support', [$communication,'support']);
$router->get('/client/support/{reference}', [$communication,'ticket']);
$router->get('/client/notifications', [$communication,'notifications']);
foreach (['forex-calculator'=>'calculateForex','margin-calculator'=>'calculateMargin','profit-loss-calculator'=>'calculateProfitLoss','currency-converter'=>'calculateCurrency'] as $toolSlug=>$method) {
    $router->post('/api/calculate/'.$toolSlug, [$tools,$method]);
}
$router->get('/markets', [$markets, 'index']);
$router->get('/client', [$client,'show']);
$router->get('/client/dashboard', static function () use ($client): void { $client->show('dashboard'); });
foreach (['trade','positions','orders','history','watchlist','profile','funds','verification'] as $page) {
    $router->get('/client/'.$page, static function () use ($client,$page): void { $client->show($page); });
}
$router->post('/client/watchlist', [$client,'updateWatchlist']);
$router->post('/client/funds/request', [$client,'submitFunding']);
$router->post('/client/funds/cancel', [$client,'cancelFundingRequest']);
foreach (['forex', 'metals', 'indices', 'commodities', 'stocks', 'digital-assets'] as $marketSlug) {
    $router->get('/markets/' . $marketSlug, static function () use ($markets, $marketSlug): void { $markets->category($marketSlug); });
}
$router->get('/education', static function () use ($cmsPublic): void { $cmsPublic->listing('education'); });
$router->get('/articles', static function () use ($cmsPublic): void { $cmsPublic->listing('articles'); });
$router->get('/market-news', static function () use ($cmsPublic): void { $cmsPublic->listing('market-news'); });
$router->get('/article/{slug}', [$cmsPublic,'article']);
$router->get('/articles/{slug}', [$cmsPublic,'article']);
$router->get('/pages/{slug}', [$cmsPublic,'page']);
$router->get('/{slug}', [$cmsPublic,'page']);
$router->get('/robots.txt', [$cmsPublic,'robots']);
$router->get('/sitemap.xml', [$cmsPublic,'sitemap']);
$router->get('/design-system', [$pages, 'designSystem']);
$router->get('/admin/login', [$adminAuth, 'loginForm']);
$router->post('/admin/login', [$adminAuth, 'login']);
$router->post('/admin/logout', [$adminAuth, 'logout']);
$router->get('/admin', [$adminHome, 'index']);
$router->get('/admin/dashboard', [$adminHome, 'index']);
$router->get('/admin/users', [$adminOperations, 'users']);
$router->get('/admin/users/{reference}', [$adminOperations, 'userDetail']);
$router->post('/admin/users/{reference}/status', [$adminOperations, 'changeUserStatus']);
$router->get('/admin/accounts', [$adminOperations, 'accounts']);
$router->get('/admin/accounts/{accountNumber}', [$adminOperations, 'accountDetail']);
$router->post('/admin/accounts/{accountNumber}/status', [$adminOperations, 'changeAccountStatus']);
$router->post('/admin/accounts/{accountNumber}/adjust-balance', [$adminOperations, 'adjustBalance']);
$router->get('/admin/instruments', [$adminInstruments, 'index']);
$router->get('/admin/instruments/create', [$adminInstruments, 'createForm']);
$router->post('/admin/instruments', [$adminInstruments, 'create']);
$router->get('/admin/instruments/categories', [$adminInstruments, 'categories']);
$router->post('/admin/instruments/categories/{reference}', [$adminInstruments, 'updateCategory']);
$router->post('/admin/instruments/{reference}', [$adminInstruments, 'update']);
$router->post('/admin/instruments/{reference}/reset-market', [$adminInstruments, 'resetMarketState']);
$router->get('/admin/instruments/{reference}', [$adminInstruments, 'show']);
$router->get('/admin/trading', [$adminTrading, 'index']);
$router->get('/admin/trading/settings', [$adminTrading, 'index']);
$router->post('/admin/trading/settings', [$adminTrading, 'update']);
$router->get('/admin/cms', [$adminCms,'index']);
$router->get('/admin/cms/articles', static function()use($adminCms):void{$adminCms->listing('articles');});
$router->get('/admin/cms/pages', static function()use($adminCms):void{$adminCms->listing('pages');});
$router->get('/admin/cms/faqs', static function()use($adminCms):void{$adminCms->listing('faqs');});
$router->get('/admin/cms/categories', [$adminCms,'categories']);
$router->post('/admin/cms/categories', [$adminCms,'saveCategory']);
$router->post('/admin/cms/categories/{reference}', static function(string $reference)use($adminCms):void{$adminCms->saveCategory($reference);});
$router->get('/admin/cms/contacts', [$adminCms,'contacts']);
$router->post('/admin/cms/contacts/{reference}/status', [$adminCms,'contactStatus']);
$router->get('/admin/cms/media', [$adminCms,'media']);
$router->post('/admin/cms/media', [$adminCms,'upload']);
$router->get('/admin/cms/preview/{nonce}', [$adminCms,'preview']);
foreach(['articles','pages','faqs'] as $cmsKind){$router->get('/admin/cms/'.$cmsKind.'/create',static function()use($adminCms,$cmsKind):void{$adminCms->form($cmsKind);});$router->post('/admin/cms/'.$cmsKind,static function()use($adminCms,$cmsKind):void{$adminCms->save($cmsKind);});$router->get('/admin/cms/'.$cmsKind.'/{reference}/edit',static function(string $reference)use($adminCms,$cmsKind):void{$adminCms->form($cmsKind,$reference);});$router->post('/admin/cms/'.$cmsKind.'/{reference}/edit',static function(string $reference)use($adminCms,$cmsKind):void{$adminCms->save($cmsKind,$reference);});}
$router->get('/admin/settings', [$adminCms,'settings']);
$router->post('/admin/settings', [$adminCms,'saveSettings']);
$adminPlaceholders = [
    'funds' => ['funds.view','Demo Funds'], 'kyc' => ['kyc.view','Verification'],
    'support' => ['support.view','Support'],
    'admins' => ['admins.view','Administrators'], 'roles' => ['roles.view','Roles & Permissions'],
    'audit' => ['audit.view','Audit Logs'],
];
foreach ($adminPlaceholders as $route => [$permission, $module]) {
    $router->get('/admin/'.$route, static function () use ($adminHome,$permission,$module,$route): void { $adminHome->placeholder($permission,$module,$route); });
}
$router->get('/api/status', static function (): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['status' => 'ok', 'application' => 'MultiForex'], JSON_THROW_ON_ERROR);
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
