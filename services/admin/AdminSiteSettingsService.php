<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;
use PDO;
use RuntimeException;

final class AdminSiteSettingsService
{
    private const FIELDS = [
        'company_name'=>['Company name','string',120], 'company_tagline'=>['Company tagline','string',180],
        'support_email'=>['Public support email','email',254], 'support_phone'=>['Public contact phone','phone',40],
        'support_address'=>['Public contact address','text',500], 'social_links'=>['Social links (JSON)','json',1500],
        'default_seo_title'=>['Default SEO title','string',220], 'default_seo_description'=>['Default SEO description','text',320],
        'footer_copyright'=>['Footer text','string',180], 'demo_disclaimer'=>['Demo disclaimer','text',700],
        'risk_warning'=>['Risk warning','text',700], 'site_logo'=>['Site logo','media',255],'site_favicon'=>['Site favicon','media',255],
    ];

    public function all():array
    {
        $keys=array_keys(self::FIELDS);$sql='SELECT setting_key,setting_value FROM site_settings WHERE setting_key IN ('.implode(',',array_fill(0,count($keys),'?')).')';$q=Database::connection()->prepare($sql);$q->execute($keys);$values=[];foreach($q->fetchAll() as $row)$values[$row['setting_key']]=$row['setting_value'];
        $fields=[];foreach(self::FIELDS as $key=>$definition){$fields[$key]=['label'=>is_array($definition)?$definition[0]:$definition,'value'=>$values[$key]??match($key){'company_name'=>'MultiForex','social_links'=>'{}','demo_disclaimer'=>'Demo Trading Environment — no real funds are used.','risk_warning'=>'All displayed prices and balances are simulated.','default_seo_title'=>'MultiForex | Demo Global Trading Platform','default_seo_description'=>'Explore simulated markets and demo trading tools with virtual funds on MultiForex.',default=>''}];}
        return $fields;
    }

    public function save(int $adminId,array $input):void
    {
        if($adminId<1)throw new RuntimeException('A valid administrator is required.',403);$pdo=Database::connection();$pdo->beginTransaction();try{$before=$this->all();$changes=[];$upsert=$pdo->prepare('INSERT INTO site_settings(setting_key,setting_value,setting_type,is_public) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),setting_type=VALUES(setting_type),is_public=1,updated_at=NOW()');
            foreach(self::FIELDS as $key=>$definition){if(!array_key_exists($key,$input))continue;$value=$input[$key];if(!is_string($value))throw new RuntimeException('Enter plain text values.');$type=is_array($definition)?$definition[1]:'media';$max=is_array($definition)?$definition[2]:255;$value=trim($value);if(strlen($value)>$max)throw new RuntimeException((is_array($definition)?$definition[0]:$definition).' is too long.');
                if($type==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid support email.');if($type==='phone'&&$value!==''&&preg_match('/^[0-9+().\s-]{5,40}$/',$value)!==1)throw new RuntimeException('Enter a valid support phone.');if($type==='json'){try{$links=json_decode($value,true,8,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new RuntimeException('Social links must be valid JSON.');}if(!is_array($links)||array_is_list($links))throw new RuntimeException('Social links must be a JSON object with recognized platforms.');$allowed=['facebook','instagram','linkedin','youtube','x'];foreach($links as $platform=>$url)if(!in_array($platform,$allowed,true)||!is_string($url)||($url!==''&&filter_var($url,FILTER_VALIDATE_URL)===false)||($url!==''&&!str_starts_with(strtolower($url),'https://')))throw new RuntimeException('Use HTTPS URLs for Facebook, Instagram, LinkedIn, YouTube or X.');$value=json_encode($links,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
                if($type==='media'&&$value!==''&&(!preg_match('~^uploads/cms/[a-f0-9]{32}\.(?:jpg|png|webp)$~',$value)||!is_file(APP_ROOT.'/'.str_replace(['..','\\'],['','/'],$value))))throw new RuntimeException('Choose an uploaded CMS image that exists.');if(($before[$key]['value']??'')!==$value){$changes[$key]=['old'=>$before[$key]['value']??'','new'=>$value];$settingType=match($type){'email','phone','media'=>'string','text'=>'text',default=>$type};$upsert->execute([$key,$value,$settingType,1]);}}
            if($changes===[])throw new RuntimeException('No site settings changed.',409);$audit=[];foreach($changes as $key=>$item)$audit[$key]=['changed'=>true];AdminActivityLogger::logOrFail($adminId,'site_settings_updated','site_settings',null,json_encode($audit,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
