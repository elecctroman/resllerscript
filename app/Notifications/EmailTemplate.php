<?php

namespace App\Notifications;

use App\Helpers;
use App\Settings;

class EmailTemplate
{
    /**
     * @param string               $title
     * @param string               $intro
     * @param array<int,array>     $items
     * @param array<string,string> $cta
     * @param array<string,mixed>  $meta
     * @return string
     */
    public static function render($title, $intro, array $items = array(), array $cta = null, array $meta = array())
    {
        $siteName = Helpers::siteName();
        $headline = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $introHtml = nl2br(htmlspecialchars($intro, ENT_QUOTES, 'UTF-8'));
        $accent = isset($meta['accent']) ? (string)$meta['accent'] : '#2563eb';
        $bgColor = isset($meta['background']) ? (string)$meta['background'] : '#f4f6fb';
        $cardBg = '#ffffff';

        $detailRows = '';
        foreach ($items as $item) {
            $label = isset($item['label']) ? htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') : '';
            $value = isset($item['value']) ? (string)$item['value'] : '';
            $isHtml = !empty($item['is_html']);
            $valueHtml = $isHtml ? $value : nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
            $detailRows .= '<tr><td class="detail-label">' . $label . '</td><td class="detail-value">' . $valueHtml . '</td></tr>';
        }

        $ctaHtml = '';
        if ($cta && isset($cta['url']) && isset($cta['label'])) {
            $ctaUrl = htmlspecialchars($cta['url'], ENT_QUOTES, 'UTF-8');
            $ctaLabel = htmlspecialchars($cta['label'], ENT_QUOTES, 'UTF-8');
            $ctaSubtext = isset($cta['subtext']) ? '<p class="cta-subtext">' . htmlspecialchars($cta['subtext'], ENT_QUOTES, 'UTF-8') . '</p>' : '';
            $ctaHtml = '<div class="cta">'
                . '<a class="cta-button" href="' . $ctaUrl . '">' . $ctaLabel . '</a>'
                . $ctaSubtext
                . '</div>';
        }

        $footerNote = Settings::get('mail_footer');
        if (isset($meta['footer'])) {
            $footerNote = (string)$meta['footer'];
        }
        $footerHtml = '';
        if ($footerNote) {
            $footerHtml = '<p class="mail-footer">' . nl2br(htmlspecialchars($footerNote, ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$headline}</title>
    <style>
        body { margin:0; padding:24px; background-color: {$bgColor}; font-family: 'Inter', Arial, sans-serif; color:#1f2937; }
        .wrapper { max-width:600px; margin:0 auto; background: {$cardBg}; border-radius:16px; box-shadow:0 15px 45px rgba(15,23,42,0.12); overflow:hidden; }
        .header { padding:32px 32px 16px; background:linear-gradient(135deg, {$accent}, #3b82f6); color:#fff; }
        .brand { font-size:14px; text-transform:uppercase; letter-spacing:0.12em; opacity:0.85; margin:0 0 12px; }
        .headline { font-size:28px; line-height:1.3; margin:0; font-weight:600; }
        .body { padding:32px; }
        .intro { font-size:15px; line-height:1.7; margin:0 0 24px; color:#374151; }
        .details { width:100%; border-collapse:separate; border-spacing:0; margin:0 0 24px; }
        .details tr { border-bottom:1px solid #e5e7eb; }
        .detail-label { width:38%; padding:12px 0; font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280; }
        .detail-value { padding:12px 0; font-size:15px; color:#111827; }
        .cta { text-align:center; margin:24px 0; }
        .cta-button { display:inline-block; background:{$accent}; color:#fff; padding:14px 32px; border-radius:999px; font-size:15px; font-weight:600; text-decoration:none; box-shadow:0 12px 24px rgba(37,99,235,0.25); }
        .cta-button:hover { background:#1d4ed8; }
        .cta-subtext { font-size:13px; color:#6b7280; margin-top:12px; }
        .footer { padding:0 32px 32px; }
        .mail-footer { font-size:12px; color:#6b7280; margin:0; line-height:1.6; }
        @media (max-width: 600px) {
            body { padding:16px; }
            .wrapper { border-radius:12px; }
            .header { padding:24px 24px 12px; }
            .headline { font-size:24px; }
            .body { padding:24px; }
            .detail-label, .detail-value { display:block; width:100%; }
            .detail-label { padding-bottom:4px; }
            .detail-value { padding-top:0; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <p class="brand">{$siteName}</p>
            <h1 class="headline">{$headline}</h1>
        </div>
        <div class="body">
            <p class="intro">{$introHtml}</p>
            <table class="details" role="presentation">{$detailRows}</table>
            {$ctaHtml}
        </div>
        <div class="footer">{$footerHtml}</div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * @param string               $title
     * @param string               $intro
     * @param array<int,array>     $items
     * @param array<string,string> $cta
     * @return string
     */
    public static function renderPlain($title, $intro, array $items = array(), array $cta = null)
    {
        $lines = array();
        $lines[] = $title;
        $lines[] = str_repeat('=', strlen($title));
        $lines[] = $intro;
        $lines[] = '';

        foreach ($items as $item) {
            $label = isset($item['label']) ? (string)$item['label'] : '';
            $value = isset($item['value']) ? (string)$item['value'] : '';
            $lines[] = $label !== '' ? $label . ': ' . $value : $value;
        }

        if ($cta && isset($cta['label']) && isset($cta['url'])) {
            $lines[] = '';
            $lines[] = $cta['label'] . ': ' . $cta['url'];
        }

        $footer = Settings::get('mail_footer');
        if ($footer) {
            $lines[] = '';
            $lines[] = $footer;
        }

        return implode("\n", $lines);
    }
}
