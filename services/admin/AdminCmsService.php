<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Database;
use App\Services\CmsHtmlSanitizer;
use PDO;
use RuntimeException;
use Throwable;

final class AdminCmsService
{
    private function db(): PDO { return Database::connection(); }

    public function listing(string $kind, string $status = ''): array
    {
        $pdo=$this->db();
        if ($kind==='articles') {
            $sql='SELECT a.*,c.name category_name FROM articles a JOIN article_categories c ON c.id=a.category_id';
            if (in_array($status,['draft','published','archived'],true)) {$sql.=' WHERE a.status=?';$q=$pdo->prepare($sql.' ORDER BY a.updated_at DESC,a.id DESC LIMIT 300');$q->execute([$status]);}
            else {$q=$pdo->query($sql.' ORDER BY a.updated_at DESC,a.id DESC LIMIT 300');}
            return ['rows'=>$q->fetchAll(),'categories'=>$this->categories()];
        }
        if($kind==='faqs'){$sql='SELECT * FROM faqs';if(in_array($status,['draft','published','archived'],true)){$q=$pdo->prepare($sql.' WHERE status=? ORDER BY category,display_order,id LIMIT 500');$q->execute([$status]);}else{$q=$pdo->query($sql.' ORDER BY category,display_order,id LIMIT 500');}return ['rows'=>$q->fetchAll()];}
        if($kind==='pages'){$sql='SELECT * FROM pages';if(in_array($status,['draft','published','archived'],true)){$q=$pdo->prepare($sql.' WHERE status=? ORDER BY title,id LIMIT 300');$q->execute([$status]);}else{$q=$pdo->query($sql.' ORDER BY title,id LIMIT 300');}return ['rows'=>$q->fetchAll()];}
        throw new RuntimeException('Content type not found.',404);
    }

    public function categories(): array { return $this->db()->query('SELECT c.*,COUNT(a.id) article_count FROM article_categories c LEFT JOIN articles a ON a.category_id=c.id GROUP BY c.id ORDER BY c.display_order,c.name')->fetchAll(); }

    public function item(string $kind,int $id): ?array
    {
        $table=match($kind){'article'=>'articles','faq'=>'faqs','page'=>'pages',default=>throw new RuntimeException('Content type not found.',404)};
        $q=$this->db()->prepare("SELECT * FROM {$table} WHERE id=?");$q->execute([$id]);$row=$q->fetch();return is_array($row)?$row:null;
    }

    public function save(string $kind,int $adminId,array $in,?int $id=null): int
    {
        $pdo=$this->db();if($adminId<1)throw new RuntimeException('A valid administrator is required.',403);$old=$id?$this->item($kind,$id):null;if($id&&$old===null)throw new RuntimeException('Content not found.',404);
        $version=$id?(int)($in['version']??0):0;if($id&&$version!==(int)$old['version'])throw new RuntimeException('This item changed after you opened it. Reload and review the latest version.',409);
        $title=trim((string)($in['title']??''));$slug=self::slug((string)($in['slug']??$title));$status=$this->status($in['status']??'draft');$content=(string)($in['content']??'');$seo=trim((string)($in['seo_title']??''));$description=trim((string)($in['meta_description']??''));$now=$this->publishAt($status,(string)($in['published_at']??''));if(strlen($content)>100000)throw new RuntimeException('Content must be 100,000 characters or fewer.');
        if($kind==='article'){
            if(strlen($title)<2||strlen($title)>220||strlen($seo)>220||strlen($description)>320||strlen($slug)>240||$slug==='')throw new RuntimeException('Enter valid article content and metadata within the supported lengths.');
            $cat=(int)($in['category_id']??0);$catCheck=$pdo->prepare('SELECT id FROM article_categories WHERE id=?');$catCheck->execute([$cat]);if(!$catCheck->fetchColumn())throw new RuntimeException('Choose a valid category.');
            $excerpt=trim((string)($in['excerpt']??''));$alt=trim((string)($in['image_alt']??''));$image=trim((string)($in['featured_image']??''));if(strlen($excerpt)>2000||strlen($alt)>255)throw new RuntimeException('Excerpt or alt text is too long.');
            if($image!==''&&!preg_match('~^uploads/cms/[a-f0-9]{32}\.(?:jpg|png|webp)$~',$image))throw new RuntimeException('Choose an uploaded CMS image.');if($image!==''&&!is_file(APP_ROOT.'/'.str_replace(['..','\\'],['','/'],$image)))throw new RuntimeException('The selected CMS image is not available.');
            $values=[$cat,$title,$slug,$excerpt,CmsHtmlSanitizer::clean($content),$image?:null,$alt?:null,$adminId,$status,$now,$seo?:null,$description?:null];
            $this->uniqueSlug('articles',$slug,$id);
            return $this->persist($pdo,$adminId,'article',$id,$old,$version,'articles','category_id=?,title=?,slug=?,excerpt=?,content=?,featured_image=?,image_alt=?,author_admin_id=?,status=?,published_at=?,seo_title=?,meta_description=?',$values,['category_id'=>$cat,'title'=>$title,'slug'=>$slug,'excerpt'=>$excerpt,'status'=>$status,'published_at'=>$now,'seo_title'=>$seo,'meta_description'=>$description]);
        }
        if($kind==='page'){
            if(in_array($slug,['admin','client','api','login','register','markets','contact','faq','sitemap-xml','robots-txt','education','articles','market-news','about','security','terms','privacy','risk-disclosure','platform','accounts','company','tools','support','design-system'],true))throw new RuntimeException('That URL is reserved for a system route.');
            if(strlen($title)<2||strlen($title)>200||strlen($content)>100000||strlen($seo)>220||strlen($description)>320||$slug==='')throw new RuntimeException('Enter valid page content and metadata within the supported lengths.');$this->uniqueSlug('pages',$slug,$id);
            return $this->persist($pdo,$adminId,'page',$id,$old,$version,'pages','title=?,slug=?,content=?,status=?,published_at=?,seo_title=?,meta_description=?',[$title,$slug,CmsHtmlSanitizer::clean($content),$status,$now,$seo?:null,$description?:null],['title'=>$title,'slug'=>$slug,'status'=>$status,'published_at'=>$now,'seo_title'=>$seo,'meta_description'=>$description]);
        }
        if($kind==='faq'){
            $category=trim((string)($in['category']??''));$question=trim((string)($in['question']??''));$answer=trim((string)($in['answer']??''));$order=filter_var($in['display_order']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>65535]]);
            if($category===''||strlen($category)>100||strlen($question)<5||strlen($question)>500||strlen($answer)<2||strlen($answer)>5000||$order===false)throw new RuntimeException('Enter a valid FAQ category, question, answer and order.');
            return $this->persist($pdo,$adminId,'faq',$id,$old,$version,'faqs','category=?,question=?,answer=?,status=?,display_order=?',[$category,$question,$answer,$status,$order],['category'=>$category,'question'=>$question,'status'=>$status,'display_order'=>$order]);
        }
        throw new RuntimeException('Content type not found.',404);
    }

    public function saveCategory(int $adminId,array $in,?int $id=null):int
    {
        $pdo=$this->db();if($adminId<1)throw new RuntimeException('A valid administrator is required.',403);$name=trim((string)($in['name']??''));$slug=self::slug((string)($in['slug']??$name));$description=trim((string)($in['description']??''));$order=filter_var($in['display_order']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>65535]]);if(strlen($name)<2||strlen($name)>100||$slug===''||strlen($description)>255||$order===false)throw new RuntimeException('Enter a valid category name, slug, description and order.');$check=$pdo->prepare('SELECT id FROM article_categories WHERE slug=? AND (? IS NULL OR id<>?) LIMIT 1');$check->execute([$slug,$id,$id]);if($check->fetchColumn())throw new RuntimeException('That category slug is already in use.');
        $pdo->beginTransaction();try{if($id){$q=$pdo->prepare('SELECT id,name,slug,description,display_order FROM article_categories WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new RuntimeException('Category not found.',404);if($old['name']===$name&&$old['slug']===$slug&&($old['description']??'')===$description&&(int)$old['display_order']===$order)throw new RuntimeException('No category changes to save.',409);$q=$pdo->prepare('UPDATE article_categories SET name=?,slug=?,description=?,display_order=?,updated_at=NOW() WHERE id=?');$q->execute([$name,$slug,$description?:null,$order,$id]);$action='cms_category_updated';}else{$q=$pdo->prepare('INSERT INTO article_categories(name,slug,description,display_order) VALUES(?,?,?,?)');$q->execute([$name,$slug,$description?:null,$order]);$id=(int)$pdo->lastInsertId();$action='cms_category_created';}AdminActivityLogger::logOrFail($adminId,$action,'article_category',$id,json_encode(['name'=>$name,'slug'=>$slug,'display_order'=>$order],JSON_THROW_ON_ERROR));$pdo->commit();return $id;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private function persist(PDO $pdo,int $adminId,string $type,?int $id,?array $old,int $version,string $table,string $set,array $values,array $audit):int
    {
        $pdo->beginTransaction();try{
            if($id){$st=$pdo->prepare("UPDATE {$table} SET {$set},version=version+1,updated_at=NOW() WHERE id=? AND version=?");$st->execute([...$values,$id,$version]);if($st->rowCount()!==1)throw new RuntimeException('This item changed after you opened it. Reload and review the latest version.',409);}
            else{$insertColumns=$this->columns($table,$set).',version';$insertValues=implode(',',array_fill(0,count($values),'?')).',1';$st=$pdo->prepare("INSERT INTO {$table} ({$insertColumns}) VALUES ({$insertValues})");$st->execute($values);$id=(int)$pdo->lastInsertId();}
            if($type!=='faq'&&$old&&$old['slug']!==$audit['slug']){$red=$pdo->prepare('INSERT INTO cms_slug_redirects(entity_type,old_slug,target_slug) VALUES(?,?,?) ON DUPLICATE KEY UPDATE target_slug=VALUES(target_slug),created_at=NOW()');$red->execute([$type,$old['slug'],$audit['slug']]);$pdo->prepare('DELETE FROM cms_slug_redirects WHERE entity_type=? AND old_slug=?')->execute([$type,$audit['slug']]);}
            $safeAudit=['old_slug'=>$old['slug']??null];foreach($audit as $key=>$value)$safeAudit[$key]=$key==='content'||$key==='answer'||$key==='excerpt'?['length'=>strlen((string)$value)]:$value;AdminActivityLogger::logOrFail($adminId,$old?'cms_'. $type.'_updated':'cms_'.$type.'_created',$type,$id,json_encode($safeAudit,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$pdo->commit();return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private function columns(string $table,string $set):string{$cols=preg_replace('/\?/', '', $set);$parts=explode(',',$cols);$names=[];foreach($parts as $part){$names[]=trim(explode('=',$part)[0]);}return implode(',', $names);}
    private function placeholders(array $values):string{return implode(',',array_fill(0,count($values),'?'));}
    private function uniqueSlug(string $table,string $slug,?int $id):void{$q=$this->db()->prepare("SELECT id FROM {$table} WHERE slug=? AND (? IS NULL OR id<>?) LIMIT 1");$q->execute([$slug,$id,$id]);if($q->fetchColumn())throw new RuntimeException('That slug is already in use.');if($table==='pages'){$reserved=['admin','client','api','login','register','markets','contact','faq','sitemap-xml','robots-txt','education','articles','market-news','about','security','terms','privacy','risk-disclosure','platform','accounts','company','tools','support','design-system'];if(in_array($slug,$reserved,true))throw new RuntimeException('That URL is reserved for a system route.');}}
    private function status(mixed $status):string{$status=is_string($status)?$status:'';if(!in_array($status,['draft','published','archived'],true))throw new RuntimeException('Choose a valid publishing status.');return $status;}
    private function publishAt(string $status,string $value):?string{if($status!=='published')return null;if($value==='')return date('Y-m-d H:i:s');$dt=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new \DateTimeZone((string)\config('TIMEZONE','UTC')));$errors=\DateTimeImmutable::getLastErrors();if(!$dt||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0)))throw new RuntimeException('Enter a valid publication date.');return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
    private static function slug(string $slug):string{$slug=strtolower(trim($slug));$slug=preg_replace('/[^a-z0-9]+/','-',$slug)??'';return trim($slug,'-');}
}
