<?php
declare(strict_types=1);

namespace Elhoe;

/**
 * Google Tag Manager helper.
 * Settings key: gtm_container_id
 */
final class GTMHelper
{
    public static function renderHead(): string
    {
        $id = Settings::get('gtm_container_id');
        if ($id === '') return '';
        $idJs = json_encode($id);
        return <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer',{$idJs});</script>
<!-- End Google Tag Manager -->
HTML;
    }

    public static function renderBody(): string
    {
        $id = Settings::get('gtm_container_id');
        if ($id === '') return '';
        return '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . e($id) .
               '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
    }

    /** JS snippet that pushes an event to dataLayer. Use after the result page renders. */
    public static function pushEventJs(string $eventName, array $data = []): string
    {
        $id = Settings::get('gtm_container_id');
        if ($id === '') return '';
        $payload = array_merge(['event' => $eventName], $data);
        $j = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return "<script>window.dataLayer=window.dataLayer||[];dataLayer.push({$j});</script>";
    }
}
