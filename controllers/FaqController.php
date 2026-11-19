<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PublicContentModel;

final class FaqController
{
    public function index(): void
    {
        $result=PublicContentModel::faqs();
        $groups=[];
        foreach($result['rows'] as $faq){$groups[(string)$faq['category']][]=$faq;}
        $brand=\site_setting('company_name','MultiForex');
        $structured=['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>[]];
        foreach($result['rows'] as $faq){$structured['mainEntity'][]=['@type'=>'Question','name'=>(string)$faq['question'],'acceptedAnswer'=>['@type'=>'Answer','text'=>strip_tags((string)$faq['answer'])]];}
        $json=json_encode($structured,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES);
        \render('public/faq',[
            'title'=>'Frequently Asked Questions · '.$brand,'metaDescription'=>'Answers about demo trading, accounts, markets, the platform, security and support.',
            'canonicalUrl'=>\site_url('faq'),'openGraphTitle'=>'Frequently Asked Questions · '.$brand,
            'openGraphDescription'=>'Straightforward information about the MultiForex demo environment.',
            'companyName'=>$brand,'publicPageAssets'=>true,'faqAssets'=>true,'faqGroups'=>$groups,
            'faqAvailable'=>$result['ok'],'structuredData'=>$json,
        ]);
    }
}
