<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use App\Services\AuthValidationException;
use App\Services\PasswordResetService;
use RuntimeException;
use Throwable;

final class AuthController
{
    public function loginForm(): void
    {
        \guest_only();
        $this->form('login', ['errors'=>[], 'email'=>'', 'notice'=>\flash_get('auth_info'), 'error'=>\flash_get('auth_error')]);
    }

    public function login(): void
    {
        \guest_only();
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        if (!$this->csrfValid()) { http_response_code(403); $this->form('login',['errors'=>['_form'=>'Your request could not be verified. Refresh the page and try again.'],'email'=>$email]); return; }
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        try {
            if (AuthService::attemptLogin($email, $password)) { \redirect('client'); }
            $this->form('login',['errors'=>['_form'=>'Invalid email or password.'],'email'=>$email]);
        } catch (AuthValidationException $exception) {
            if (isset($exception->errors['_form']) && str_starts_with($exception->errors['_form'], 'Too many login attempts.')) { http_response_code(429); }
            $this->form('login',['errors'=>$exception->errors,'email'=>$email]);
        } catch (Throwable $exception) {
            error_log('Login request failed: '.$exception->getMessage());
            $this->form('login',['errors'=>['_form'=>'Sign in is temporarily unavailable. Please try again.'],'email'=>$email]);
        }
    }

    public function registerForm(): void
    {
        \guest_only();
        $this->form('register',['errors'=>[],'old'=>[],'notice'=>\flash_get('auth_info'),'error'=>\flash_get('auth_error')]);
    }

    public function register(): void
    {
        \guest_only();
        $old = $this->oldRegistrationInput();
        if (!$this->csrfValid()) { http_response_code(403); $this->form('register',['errors'=>['_form'=>'Your request could not be verified. Refresh the page and try again.'],'old'=>$old]); return; }
        try {
            $account = AuthService::register($_POST);
            try {
                AuthService::authenticate($account['user_id']);
                \flash_set('auth_success','Your demo account has been created. Virtual balance: '.self::money($account['balance'],$account['currency']).'. No real funds are used.');
                \redirect('client');
            } catch (Throwable $sessionException) {
                error_log('Registration session creation failed after commit: '.$sessionException->getMessage());
                \flash_set('auth_info','Your demo account was created. Please sign in to continue.');
                \redirect('login');
            }
        } catch (AuthValidationException $exception) {
            http_response_code(422);
            $this->form('register',['errors'=>$exception->errors,'old'=>$old]);
        } catch (Throwable $exception) {
            error_log('Registration request failed: '.$exception->getMessage());
            http_response_code(503);
            $this->form('register',['errors'=>['_form'=>'We couldn’t create your demo account. Please try again.'],'old'=>$old]);
        }
    }

    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); return; }
        if (!$this->csrfValid()) { http_response_code(403); $this->form('login',['errors'=>['_form'=>'Your request could not be verified. Please try again.'],'email'=>'']); return; }
        $userId = \auth_user_id();
        if ($userId !== null) { AuthService::logEvent('logout',$userId); }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
        $_SESSION['_flash']['auth_info'] = 'You have been logged out.';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        \redirect('login');
    }

    public function forgotForm(): void
    {
        \guest_only();
        $this->form('forgot-password',['errors'=>[],'email'=>'','notice'=>\flash_get('auth_info'),'error'=>\flash_get('auth_error')]);
    }

    public function forgot(): void
    {
        \guest_only();
        if (!$this->csrfValid()) { http_response_code(403); $this->form('forgot-password',['errors'=>['_form'=>'Your request could not be verified. Refresh the page and try again.'],'email'=>'']); return; }
        $email = is_string($_POST['email'] ?? null) ? trim(substr($_POST['email'],0,254)) : '';
        $started = hrtime(true);
        try { PasswordResetService::request($email); }
        catch (Throwable $exception) { error_log('Password reset request failed: '.$exception->getMessage()); }
        $remainingNs = 100_000_000 - (hrtime(true) - $started);
        if ($remainingNs > 0) { usleep((int) ceil($remainingNs / 1000)); }
        \flash_set('auth_info','If an account exists for that email address, password reset instructions have been generated.');
        \redirect('forgot-password');
    }

    public function resetForm(): void
    {
        \guest_only();
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
        $valid = false;
        try { $valid = PasswordResetService::isValid($token); }
        catch (Throwable $exception) { error_log('Reset token lookup failed: '.$exception->getMessage()); }
        $this->form('reset-password',['errors'=>[],'token'=>$token,'tokenValid'=>$valid]);
    }

    public function reset(): void
    {
        \guest_only();
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        if (!$this->csrfValid()) {
            http_response_code(403);
            $submittedToken=is_string($_POST['token']??null)?$_POST['token']:'';
            $tokenValid=false;
            try { $tokenValid=PasswordResetService::isValid($submittedToken); } catch(Throwable $exception) { error_log('Reset token lookup failed: '.$exception->getMessage()); }
            $this->form('reset-password',['errors'=>['_form'=>'Your request could not be verified. Refresh the page and try again.'],'token'=>$submittedToken,'tokenValid'=>$tokenValid]);
            return;
        }
        $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
        try {
            PasswordResetService::reset($token,$password,$confirmation);
            \flash_set('auth_info','Password changed successfully. Sign in using your new password.');
            \redirect('login');
        } catch (AuthValidationException $exception) {
            $expired = isset($exception->errors['_form']);
            http_response_code(422);
            $this->form('reset-password',['errors'=>$exception->errors,'token'=>$expired?'':$token,'tokenValid'=>!$expired]);
        } catch (Throwable $exception) {
            error_log('Password reset submission failed: '.$exception->getMessage());
            http_response_code(503);
            $this->form('reset-password',['errors'=>['_form'=>'We could not update your password. Please request a new reset link.'],'token'=>$token,'tokenValid'=>PasswordResetService::isValid($token)]);
        }
    }

    public function client(): void
    {
        (new ClientController())->show('dashboard');
    }

    public function clientPlaceholder(string $page): void
    {
        (new ClientController())->show($page);
    }

    private function csrfValid(): bool
    {
        $token=$_POST['_csrf']??null;
        return \verify_csrf_token(is_string($token)?$token:null);
    }

    private function form(string $page,array $data): void
    {
        $brand=\site_setting('company_name','MultiForex');
        $titles=['login'=>'Sign In','register'=>'Create a Demo Account','forgot-password'=>'Reset Your Password','reset-password'=>'Choose a New Password','client-unavailable'=>'Demo Account Unavailable'];
        $descriptions=['login'=>'Sign in to your MultiForex virtual demo account.','register'=>'Create a demo account with virtual funds for the MultiForex platform preview.','forgot-password'=>'Request secure password reset instructions for your demo account.','reset-password'=>'Set a new password for your demo account using your secure reset link.','client-unavailable'=>'Your demo account is temporarily unavailable.'];
        \render('public/auth/'.$page,array_merge($data,[
            'title'=>$titles[$page].' · '.$brand,'metaDescription'=>$descriptions[$page],
            'canonicalUrl'=>\site_url($page==='client-unavailable'?'login':$page),'openGraphTitle'=>$titles[$page].' · '.$brand,
            'openGraphDescription'=>$descriptions[$page],'companyName'=>$brand,'publicPageAssets'=>true,'authAssets'=>true,
        ]));
    }

    private function oldRegistrationInput(): array
    {
        $out=[];
        foreach(['first_name'=>100,'last_name'=>100,'email'=>254,'phone'=>40,'country'=>2] as $key=>$max){$value=$_POST[$key]??'';$out[$key]=is_string($value)?trim(substr($value,0,$max*4)):'';}
        return $out;
    }

    private static function money(string $amount,string $currency): string
    {
        return \format_currency_amount($amount,$currency);
    }
}
