<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Calculators\CalculatorService;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class ToolsController
{
    private const CALCULATORS = [
        'forex-calculator' => ['Forex Calculator', 'Estimate forex pip value and trade value from configured demo contract sizes.'],
        'margin-calculator' => ['Margin Calculator', 'Estimate required margin using each instrument’s configured demo margin rate.'],
        'profit-loss-calculator' => ['Profit / Loss Calculator', 'Explore how price movement changes an illustrative position result.'],
        'currency-converter' => ['Currency Converter', 'Convert currencies using static seeded demo forex rates.'],
    ];

    public function index(): void
    {
        $this->render('tools/index', 'Trading Tools · ' . $this->brand(), 'Explore educational calculators using configured demo instrument data.');
    }

    public function calculator(string $slug): void
    {
        if (!isset(self::CALCULATORS[$slug])) { http_response_code(404); \render('public/not-found', ['title' => 'Tool not found']); return; }
        try {
            $instruments = CalculatorService::instruments($slug === 'forex-calculator' ? 'forex' : null);
            $currencies = CalculatorService::currencies();
            $available = $slug === 'currency-converter' ? count($currencies) >= 2 : count($instruments) > 0;
        } catch (Throwable $exception) {
            error_log('Calculator catalogue unavailable: ' . $exception->getMessage());
            $instruments = [];
            $currencies = [];
            $available = false;
        }
        [$name, $description] = self::CALCULATORS[$slug];
        $this->render('tools/calculator', $name . ' · ' . $this->brand(), $description, [
            'calculatorSlug' => $slug, 'calculatorName' => $name, 'calculatorDescription' => $description,
            'instruments' => $instruments, 'currencies' => $currencies, 'catalogueAvailable' => $available,
        ]);
    }

    public function calculateForex(): void { $this->calculate(static fn(array $v): array => CalculatorService::forex(self::text($v,'symbol'), self::text($v,'account_currency'), self::number($v,'lots'), self::optionalNumber($v,'price'))); }
    public function calculateMargin(): void { $this->calculate(static fn(array $v): array => CalculatorService::margin(self::text($v,'symbol'), self::number($v,'lots'), self::optionalNumber($v,'price'))); }
    public function calculateProfitLoss(): void { $this->calculate(static fn(array $v): array => CalculatorService::profitLoss(self::text($v,'symbol'), self::text($v,'direction'), self::number($v,'lots'), self::number($v,'open_price'), self::number($v,'close_price'))); }
    public function calculateCurrency(): void { $this->calculate(static fn(array $v): array => CalculatorService::convert(self::number($v,'amount', true), self::text($v,'from_currency'), self::text($v,'to_currency'))); }

    private function calculate(callable $operation): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Use the calculator form to submit values.']); return; }
        if (!\verify_csrf_token(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Your session has expired. Refresh the page and try again.']); return; }
        try {
            $values = $operation($_POST);
            echo json_encode(['success'=>true,'data'=>$values], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
        } catch (InvalidArgumentException|DomainException $exception) {
            http_response_code(422); echo json_encode(['success'=>false,'message'=>$exception->getMessage()], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT);
        } catch (Throwable $exception) {
            error_log('Calculator request failed: ' . $exception->getMessage());
            http_response_code(503); echo json_encode(['success'=>false,'message'=>'Calculator data is temporarily unavailable. Please try again.']);
        }
    }

    private function render(string $view, string $title, string $description, array $data=[]): void
    {
        $brand=$this->brand();
        \render('public/'.$view, array_merge($data,[
            'title'=>$title,'metaDescription'=>$description,'canonicalUrl'=>\site_url(isset($data['calculatorSlug']) ? 'tools/' . $data['calculatorSlug'] : 'tools'),
            'openGraphTitle'=>$title,'openGraphDescription'=>$description,'companyName'=>$brand,
            'publicPageAssets'=>true,'toolsAssets'=>true,'supportEmail'=>\site_setting('support_email',''),
        ]));
    }
    private function brand(): string { return \site_setting('company_name','MultiForex'); }
    private static function text(array $values,string $key): string { $value=$values[$key]??''; return is_string($value)?substr(trim($value),0,80):''; }
    private static function number(array $values,string $key,bool $allowZero=false): float
    {
        $value=$values[$key]??null;
        if (!is_string($value) && !is_int($value) && !is_float($value)) { throw new InvalidArgumentException('Enter valid numeric values.'); }
        $string=trim((string)$value);
        if ($string==='' || !is_numeric($string)) { throw new InvalidArgumentException('Enter valid numeric values.'); }
        $number=(float)$string;
        if (!is_finite($number) || (!$allowZero && $number<=0) || ($allowZero && $number<0)) { throw new InvalidArgumentException($allowZero?'Amount cannot be negative.':'Values must be greater than zero.'); }
        return $number;
    }
    private static function optionalNumber(array $values,string $key): ?float
    {
        if (!isset($values[$key]) || $values[$key]==='') { return null; }
        return self::number($values,$key);
    }
}
