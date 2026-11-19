<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;
use App\Services\DemoTradingSettings;
use App\Services\DecimalMath;
use App\Services\CTraderConfiguration;
use PDO;
use RuntimeException;
use Throwable;

final class AdminTradingSettingsService
{
    public function getSettings():array
    {
        $pdo=Database::connection();$settings=DemoTradingSettings::all($pdo);
        $summary=$pdo->query("SELECT COUNT(*) total,COALESCE(SUM(status='active'),0) enabled,COALESCE(SUM(status='active' AND trading_enabled=0),0) trading_disabled,COALESCE(SUM(status='active' AND market_data_enabled=0),0) market_data_disabled FROM instruments")->fetch(PDO::FETCH_ASSOC)?:[];
        $open=(int)$pdo->query("SELECT COUNT(*) FROM positions WHERE status='open'")->fetchColumn();
        $recent=$pdo->query("SELECT l.action,l.entity_type,l.entity_id,l.description,l.created_at,a.name AS admin_name FROM admin_activity_logs l LEFT JOIN admins a ON a.id=l.admin_id WHERE l.entity_type IN ('instrument','instrument_category','trading_setting') ORDER BY l.created_at DESC,l.id DESC LIMIT 16")->fetchAll(PDO::FETCH_ASSOC);
        $trading=(string)\config('TRADING_PROVIDER','demo');$market=(string)\config('MARKET_DATA_PROVIDER','demo');
        $mapping=$pdo->query("SELECT COUNT(*) total,COALESCE(SUM(is_active=1),0) active FROM instrument_provider_mappings WHERE provider_code='ctrader'")->fetch(PDO::FETCH_ASSOC)?:['total'=>0,'active'=>0];
        $ctrader=CTraderConfiguration::safeStatus();$ctrader['mapping_status']=(int)$mapping['active'].' active mapping(s)';
        $providerStatus=static function(string $provider)use($ctrader):array{
            if($provider==='demo')return ['provider'=>'demo','configured'=>true,'connected'=>true,'environment'=>'demo','status'=>'active','mapping_status'=>'not_required'];
            if($provider==='ctrader')return $ctrader;
            return ['provider'=>$provider,'configured'=>false,'connected'=>false,'environment'=>'unknown','status'=>'invalid_provider','mapping_status'=>'not_checked'];
        };
        return ['settings'=>$settings,'counts'=>['total_instruments'=>(int)$summary['total'],'enabled_instruments'=>(int)$summary['enabled'],'trading_enabled'=>(int)$summary['enabled']-(int)$summary['trading_disabled'],'trading_disabled'=>(int)$summary['trading_disabled'],'market_data_enabled'=>(int)$summary['enabled']-(int)$summary['market_data_disabled'],'market_data_disabled'=>(int)$summary['market_data_disabled'],'open_positions'=>$open],'providers'=>['trading'=>$trading,'market_data'=>$market,'environment'=>$market==='ctrader'||$trading==='ctrader'?CTraderConfiguration::environment():'demo','configured'=>$ctrader['configured'],'connected'=>false,'real_trading_enabled'=>false,'mapping_status'=>$ctrader['mapping_status'],'market_status'=>$providerStatus($market),'trading_status'=>$providerStatus($trading)],'recent_audit'=>$recent];
    }

    public function updateSettings(int $adminId,array $input):array
    {
        $boolKeys=['demo_trading_enabled','demo_market_data_enabled','market_orders_enabled'];$values=[];
        foreach($boolKeys as $key){$value=$input[$key]??null;if(!in_array((string)$value,['0','1'],true))throw new RuntimeException('Choose a valid state for each Demo control.');$values[$key]=(string)$value;}
        $open=filter_var($input['max_open_positions_per_account']??null,FILTER_VALIDATE_INT);if($open===false||$open<1||$open>1000)throw new RuntimeException('Maximum open positions must be between 1 and 1,000.');
        $max=trim((string)($input['max_order_volume']??''));if(preg_match('/^\d{1,6}(?:\.\d{1,8})?$/D',$max)!==1||DecimalMath::compare($max,'0')<=0||DecimalMath::compare($max,'10000')>0)throw new RuntimeException('Maximum order volume must be a positive value no greater than 10,000 lots.');
        $values['max_open_positions_per_account']=(string)$open;$values['max_order_volume']=$max;
        if(($input['confirm_global_change']??'')!=='1')throw new RuntimeException('Review and confirm the global Demo control changes.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$old=DemoTradingSettings::all($pdo,false,true);$changes=[];foreach($values as $key=>$value)if((string)$old[$key] !== $value)$changes[$key]=['old'=>(string)$old[$key],'new'=>$value];if($changes===[])throw new RuntimeException('No Demo trading settings changed.',409);
            if(($values['demo_market_data_enabled']??'1')==='0'&&$old['demo_market_data_enabled']){$positions=(int)$pdo->query("SELECT COUNT(*) FROM positions WHERE status='open'")->fetchColumn();if($positions>0)throw new RuntimeException('Global Demo Market Data cannot be paused while open positions require valuation.',409);}
            $upsert=$pdo->prepare('INSERT INTO site_settings(setting_key,setting_value,setting_type,is_public) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),setting_type=VALUES(setting_type),is_public=0,updated_at=NOW()');
            foreach($values as $key=>$value){$type=in_array($key,$boolKeys,true)?'boolean':($key==='max_open_positions_per_account'?'integer':'decimal');$upsert->execute([$key,$value,$type]);}
            foreach($changes as $key=>$pair){$action=match($key){'demo_trading_enabled'=>'demo_trading_control_changed','demo_market_data_enabled'=>'demo_market_data_control_changed','market_orders_enabled'=>'demo_market_orders_control_changed',default=>'demo_risk_limit_changed'};AdminActivityLogger::logOrFail($adminId,$action,'trading_setting',null,json_encode(['setting'=>$key,'old'=>$pair['old'],'new'=>$pair['new']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
            $pdo->commit();return ['changed'=>$changes];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
