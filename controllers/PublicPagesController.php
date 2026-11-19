<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PublicContentModel;

final class PublicPagesController
{
    private const PAGES = [
        'platform'=>['title'=>'Responsive Demo Trading Platform','description'=>'Explore the MultiForex responsive web trading interface preview and demo workflow.'],
        'accounts'=>['title'=>'Demo Account Profiles','description'=>'Compare Standard, Pro and ECN demo profiles with virtual funds and transparent demo conditions.'],
        'company'=>['title'=>'About MultiForex','description'=>'Learn about the purpose and technology behind the MultiForex demo platform.'],
        'about'=>['title'=>'Technology-Driven Market Exploration','description'=>'The MultiForex story: a platform concept for exploring financial technology and demo workflows.'],
        'security'=>['title'=>'Security Foundations','description'=>'Review the security controls implemented and planned for the MultiForex demo project.'],
        'terms'=>['title'=>'Terms of Use','description'=>'Demo environment terms, simulated data limitations and responsible use information.'],
        'privacy'=>['title'=>'Privacy Information','description'=>'Learn what this demo project collects through contact messages, sessions and future account features.'],
        'risk-disclosure'=>['title'=>'Demo Risk Disclosure','description'=>'Understand the risks of leveraged markets and the limits of simulated demo results.'],
    ];

    public function show(string $page): void
    {
        if (!isset(self::PAGES[$page])) { http_response_code(404); \render('public/not-found',['title'=>'Page not found']); return; }
        [$title,$description]=[self::PAGES[$page]['title'],self::PAGES[$page]['description']];
        $brand=\site_setting('company_name','MultiForex');
        $data=['pageKey'=>$page,'pageTitle'=>$title,'pageDescription'=>$description,
            'title'=>$title.' · '.$brand,'metaDescription'=>$description,'canonicalUrl'=>\site_url($page),
            'openGraphTitle'=>$title.' · '.$brand,'openGraphDescription'=>$description,
            'companyName'=>$brand,'publicPageAssets'=>true,'supportEmail'=>\site_setting('support_email','')];
        if ($page==='accounts') { $data['configuredDemoBalance']=PublicContentModel::defaultDemoBalance(); }
        if (in_array($page,['terms','privacy','risk-disclosure'],true)) {
            \render('public/legal',['legalType'=>$page]+$data);
            return;
        }
        \render('public/'.$page,$data);
    }
}
