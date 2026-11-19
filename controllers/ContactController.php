<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PublicContentModel;
use Throwable;

final class ContactController
{
    public function index(): void
    {
        $brand=\site_setting('company_name','MultiForex');
        \render('public/contact',[
            'title'=>'Contact Support · '.$brand,'metaDescription'=>'Contact the MultiForex demo platform team through the general enquiry form.',
            'canonicalUrl'=>\site_url('contact'),'openGraphTitle'=>'Contact · '.$brand,
            'openGraphDescription'=>'Send an enquiry about this demo platform. No physical office or reply time is implied.',
            'companyName'=>$brand,'supportEmail'=>\site_setting('support_email',''),
            'publicPageAssets'=>true,'contactAssets'=>true,
        ]);
    }

    public function submit(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST'){ $this->respond(405,false,'Submit the enquiry using the contact form.'); return; }
        $token=$_POST['_csrf']??null;
        if(!\verify_csrf_token(is_string($token)?$token:null)){ $this->respond(403,false,'Your session has expired. Refresh the page and try again.'); return; }
        $honeypot=$_POST['website']??'';
        if(is_string($honeypot)&&trim($honeypot)!==''){ $this->respond(200,true,'Thank you. Your message has been received.'); return; }
        $attempts=array_values(array_filter($_SESSION['contact_attempts']??[],static fn($time):bool=>is_int($time)&&$time>time()-600));
        if(count($attempts)>=5){ $_SESSION['contact_attempts']=$attempts; $this->respond(429,false,'Please wait a little before sending another message.'); return; }
        $name=$this->input('name',160);$email=$this->input('email',254);$phone=$this->input('phone',40);$subject=$this->input('subject',200);$message=$this->input('message',5000);
        $errors=[];
        if(strlen($name)<2||preg_match('/[<>]/',$name)){ $errors['name']='Enter your name using 2–160 characters.'; }
        if(filter_var($email,FILTER_VALIDATE_EMAIL)===false){ $errors['email']='Enter a valid email address.'; }
        if($phone!==''&&preg_match('/^[0-9+().\s-]{5,40}$/',$phone)!==1){ $errors['phone']='Enter a valid phone number or leave this field blank.'; }
        if(strlen($subject)<3){ $errors['subject']='Choose a subject with at least 3 characters.'; }
        if(strlen($message)<10){ $errors['message']='Write a message with at least 10 characters.'; }
        if($errors!==[]){ $this->respond(422,false,'Please review the highlighted fields.',['errors'=>$errors]); return; }
        $_SESSION['contact_attempts']=$attempts;
        $_SESSION['contact_attempts'][]=time();
        try{
            PublicContentModel::saveContact(compact('name','email','phone','subject','message'));
            $this->respond(200,true,'Thank you. Your message has been received.');
        }catch(Throwable $exception){
            error_log('Contact message save failed: '.$exception->getMessage());
            $this->respond(503,false,'We could not save your message right now. Please try again later.');
        }
    }

    private function input(string $key,int $max):string
    {
        $value=$_POST[$key]??'';
        return is_string($value)?substr(trim($value),0,$max):'';
    }
    private function respond(int $status,bool $success,string $message,array $extra=[]):void
    {
        http_response_code($status);
        echo json_encode(['success'=>$success,'message'=>$message]+$extra,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES);
    }
}
