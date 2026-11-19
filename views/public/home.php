<?php require APP_ROOT . '/views/components/header.php'; ?>
<?php render('components/home/hero', ['companyName' => $companyName, 'companyTagline' => $companyTagline, 'heroInstrument' => $heroInstrument, 'goldInstrument' => $goldInstrument, 'tickerInstruments' => $tickerInstruments]); ?>
<?php render('components/home/ticker', ['tickerInstruments' => $tickerInstruments]); ?>
<?php render('components/home/markets', ['categories' => $categories]); ?>
<?php render('components/home/popular', ['instruments' => $instruments, 'instrumentCount' => $instrumentCount]); ?>
<?php render('components/home/platform', ['companyName' => $companyName, 'heroInstrument' => $heroInstrument, 'tickerInstruments' => $tickerInstruments]); ?>
<?php render('components/home/benefits'); ?>
<?php render('components/home/accounts'); ?>
<?php render('components/home/technology', ['categoryCount' => $categoryCount, 'instrumentCount' => $instrumentCount]); ?>
<?php render('components/home/demo-process'); ?>
<?php render('components/home/tools'); ?>
<?php render('components/home/security'); ?>
<?php render('components/home/insights', ['educationArticles' => $educationArticles, 'insightArticles' => $insightArticles, 'educationFromContent' => $educationFromContent, 'insightsFromContent' => $insightsFromContent]); ?>
<?php render('components/home/final-cta', ['supportEmail' => $supportEmail]); ?>
<?php require APP_ROOT . '/views/components/footer.php'; ?>
