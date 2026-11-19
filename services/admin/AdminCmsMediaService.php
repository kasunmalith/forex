<?php
declare(strict_types=1);
namespace App\Services\Admin;

use App\Database;
use RuntimeException;

final class AdminCmsMediaService
{
    public function store(int $adminId,array $file):string
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($file['tmp_name']??'')))throw new RuntimeException('Choose a valid image upload.');if($adminId<1)throw new RuntimeException('A valid administrator is required.',403);
        if((int)($file['size']??0)<1||(int)$file['size']>5*1024*1024)throw new RuntimeException('Images must be smaller than 5 MB.');
        $tmp=(string)$file['tmp_name'];$finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($tmp);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowed[$mime]))throw new RuntimeException('Only JPEG, PNG and WebP images are accepted.');
        $image=@getimagesize($tmp);if(!$image||$image[0]<1||$image[1]<1||$image[0]>6000||$image[1]>6000||($image['mime']??'')!==$mime)throw new RuntimeException('The uploaded file is not a valid supported image.');
        $dir=APP_ROOT.'/uploads/cms';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('CMS media storage is unavailable.');$htaccess=$dir.'/.htaccess';if(!is_file($htaccess)&&file_put_contents($htaccess,"Options -Indexes\n<FilesMatch \\\"\\.(?:php[0-9]?|phtml|phar|cgi|pl|py|sh)$\\\">\n    Require all denied\n</FilesMatch>\n",LOCK_EX)===false)throw new RuntimeException('CMS media execution protection could not be configured.');$name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];$target=$dir.'/'.$name;if(!move_uploaded_file($tmp,$target))throw new RuntimeException('The image could not be stored.');
        try{\App\Services\Admin\AdminActivityLogger::logOrFail($adminId,'cms_media_uploaded','cms_media',null,json_encode(['filename'=>$name],JSON_THROW_ON_ERROR));}catch(\Throwable $e){@unlink($target);throw $e;}
        return 'uploads/cms/'.$name;
    }
}
