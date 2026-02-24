<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.gacetaflipbook
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgContentGacetaflipbook extends CMSPlugin
{
    /** @var bool */
    private static $assetsLoaded = false;

    /**
     * Replace {gacetaflip ...} tags.
     *
     * Embed mode (closest to FlipHTML5 look):
     * {gacetaflip mode="embed" url="https://online.fliphtml5.com/.../#p=26"}
     *
     * Native mode (self-hosted PDF rendering):
     * {gacetaflip mode="native" file="images/pdfs/gaceta.pdf" start=26}
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        if ($context === 'com_finder.indexer') {
            return true;
        }

        if (!is_object($article) || !property_exists($article, 'text') || !is_string($article->text)) {
            return true;
        }

        if (stripos($article->text, '{gacetaflip') === false && stripos($article->text, '{gacetaflipbook') === false) {
            return true;
        }

        $regex = '/{\s*(gacetaflip|gacetaflipbook)\s+([^}]*)}/i';

        if (!preg_match_all($regex, $article->text, $matches, PREG_SET_ORDER)) {
            return true;
        }

        foreach ($matches as $match) {
            $tagParams = $this->parseTagParameters((string) $match[2]);
            $replacement = $this->renderFlipbook($tagParams);
            $article->text = preg_replace('/' . preg_quote($match[0], '/') . '/', addcslashes($replacement, '\\$'), $article->text, 1);
        }

        return true;
    }

    /**
     * Parse key=value pairs from the tag body.
     */
    private function parseTagParameters(string $raw): array
    {
        $params = [];

        preg_match_all('/([a-zA-Z0-9_\-]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s]+)/', $raw, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = strtolower((string) $match[1]);
            $value = trim((string) $match[2], " \t\n\r\0\x0B\"'");
            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * Build final HTML for one instance.
     */
    private function renderFlipbook(array $tagParams): string
    {
        $mode = strtolower(trim((string) ($tagParams['mode'] ?? (string) $this->params->get('default_mode', 'native'))));
        if ($mode !== 'embed' && $mode !== 'native') {
            $mode = 'native';
        }

        $assets = '';
        if (!self::$assetsLoaded) {
            $assets = $this->renderAssets();
            self::$assetsLoaded = true;
        }

        if ($mode === 'embed') {
            return $assets . $this->renderEmbedMode($tagParams);
        }

        return $assets . $this->renderNativeMode($tagParams);
    }

    /**
     * Embed external URL in an iframe (closest visual match if using FlipHTML5 URL).
     */
    private function renderEmbedMode(array $tagParams): string
    {
        $url = trim((string) ($tagParams['url'] ?? $tagParams['src'] ?? ''));

        if ($url === '') {
            return '<p class="gacetaflip-error">GacetaFlip: falta el parametro <code>url</code> en modo <code>embed</code>.</p>';
        }

        $safeUrl = $this->normalizeEmbedUrl($url);
        if ($safeUrl === '') {
            return '<p class="gacetaflip-error">GacetaFlip: la URL de <code>embed</code> no es valida.</p>';
        }

        $start = $this->normalizeInt(
            (string) ($tagParams['start'] ?? $this->params->get('default_start_page', 1)),
            1,
            5000,
            1
        );

        $safeUrl = $this->applyStartPageHash($safeUrl, $start);

        $ratio = $this->normalizeFloat(
            (string) ($tagParams['ratio'] ?? $this->params->get('embed_ratio', 75)),
            40.0,
            200.0,
            75.0
        );

        $height = $this->normalizeInt(
            (string) ($tagParams['height'] ?? 0),
            0,
            5000,
            0
        );

        $stylePadding = $height > 0
            ? 'height:' . (int) $height . 'px;'
            : 'height:0;padding-bottom:' . htmlspecialchars(number_format($ratio, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '%;';

        $iframeId = 'gacetaflip-embed-' . substr(sha1(uniqid((string) mt_rand(), true)), 0, 12);

        return '<div class="gacetaflip-shell gacetaflip-shell-embed">'
            . '<div class="gacetaflip-embed-wrap" style="' . $stylePadding . '">'
            . '<iframe'
            . ' id="' . htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8') . '"'
            . ' class="gacetaflip-embed-frame"'
            . ' src="' . htmlspecialchars($safeUrl, ENT_QUOTES, 'UTF-8') . '"'
            . ' loading="lazy"'
            . ' allowfullscreen'
            . ' scrolling="no"'
            . ' referrerpolicy="no-referrer-when-downgrade"'
            . '></iframe>'
            . '</div>'
            . '</div>';
    }

    /**
     * Self-hosted flipbook mode based on PDF.js + StPageFlip.
     */
    private function renderNativeMode(array $tagParams): string
    {
        $file = trim((string) ($tagParams['file'] ?? $tagParams['pdf'] ?? ''));

        if ($file === '') {
            return '<p class="gacetaflip-error">GacetaFlip: falta el parametro <code>file</code> en modo <code>native</code>.</p>';
        }

        $fileUrl = $this->normalizeFileUrl($file);
        if ($fileUrl === '') {
            return '<p class="gacetaflip-error">GacetaFlip: ruta de PDF invalida.</p>';
        }

        $width = $this->normalizeInt(
            (string) ($tagParams['width'] ?? $this->params->get('default_width', 560)),
            320,
            1800,
            560
        );

        $height = $this->normalizeInt(
            (string) ($tagParams['height'] ?? $this->params->get('default_height', 760)),
            360,
            2200,
            760
        );

        $start = $this->normalizeInt(
            (string) ($tagParams['start'] ?? $this->params->get('default_start_page', 1)),
            1,
            5000,
            1
        );

        $maxPages = $this->normalizeInt(
            (string) ($tagParams['maxpages'] ?? $this->params->get('max_pages', 0)),
            0,
            5000,
            0
        );

        $scale = $this->normalizeFloat(
            (string) ($tagParams['scale'] ?? $this->params->get('render_scale', 1.35)),
            0.6,
            3.0,
            1.35
        );

        $zoomStep = $this->normalizeFloat(
            (string) ($tagParams['zoomstep'] ?? $this->params->get('zoom_step', 0.15)),
            0.05,
            0.6,
            0.15
        );

        $showDownload = $this->normalizeBool(
            (string) ($tagParams['download'] ?? $this->params->get('show_download', 1)),
            true
        );

        $updateHash = $this->normalizeBool(
            (string) ($tagParams['hash'] ?? $this->params->get('update_hash', 1)),
            true
        );

        $bookId = 'gacetaflip-native-' . substr(sha1(uniqid((string) mt_rand(), true)), 0, 12);

        return '<div class="gacetaflip-shell gacetaflip-shell-native">'
            . '<div id="' . htmlspecialchars($bookId, ENT_QUOTES, 'UTF-8') . '"'
            . ' class="gacetaflip-native"'
            . ' data-gacetaflip="1"'
            . ' data-file="' . htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-width="' . (int) $width . '"'
            . ' data-height="' . (int) $height . '"'
            . ' data-start="' . (int) $start . '"'
            . ' data-maxpages="' . (int) $maxPages . '"'
            . ' data-scale="' . htmlspecialchars((string) $scale, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-zoomstep="' . htmlspecialchars((string) $zoomStep, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-download="' . ($showDownload ? '1' : '0') . '"'
            . ' data-updatehash="' . ($updateHash ? '1' : '0') . '"'
            . '></div>'
            . '</div>';
    }

    /**
     * Inject CSS/JS once per page.
     */
    private function renderAssets(): string
    {
        $rootPath = rtrim(Uri::root(true), '/');
        $assetBase = ($rootPath === '' ? '' : $rootPath) . '/plugins/content/gacetaflipbook/assets';
        $cssUrl = $assetBase . '/gacetaflipbook.css';
        $jsUrl = $assetBase . '/gacetaflipbook.js';

        return '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '">' . "\n"
            . '<script defer src="https://cdn.jsdelivr.net/npm/page-flip@2.0.7/dist/js/page-flip.browser.min.js"></script>' . "\n"
            . '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>' . "\n"
            . '<script>window.GacetaFlipWorkerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js";</script>' . "\n"
            . '<script defer src="' . htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
    }

    /**
     * Normalize embed URL and block dangerous schemes.
     */
    private function normalizeEmbedUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url) || strpos($url, '//') === 0) {
            return $url;
        }

        if (preg_match('#^[a-z][a-z0-9+\-.]*:#i', $url)) {
            return '';
        }

        return $this->normalizeFileUrl($url);
    }

    /**
     * Add or update #p=XX page hash.
     */
    private function applyStartPageHash(string $url, int $startPage): string
    {
        if ($startPage < 1) {
            return $url;
        }

        if (preg_match('/#.*(?:^|[&#])p=\d+/i', $url)) {
            return (string) preg_replace('/((?:^|[&#])p=)\d+/i', '$1' . $startPage, $url);
        }

        return $url . (strpos($url, '#') !== false ? '&' : '#') . 'p=' . $startPage;
    }

    /**
     * Normalize local/remote PDF path into absolute URL.
     */
    private function normalizeFileUrl(string $file): string
    {
        $file = trim($file);
        if ($file === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $file) || strpos($file, '//') === 0) {
            return $file;
        }

        if (preg_match('#^[a-z][a-z0-9+\-.]*:#i', $file)) {
            return '';
        }

        $relative = ltrim($file, '/');
        if ($relative === '') {
            return '';
        }

        $segments = explode('/', $relative);
        $encodedSegments = [];

        foreach ($segments as $segment) {
            $encodedSegments[] = rawurlencode(rawurldecode($segment));
        }

        return rtrim(Uri::root(), '/') . '/' . implode('/', $encodedSegments);
    }

    private function normalizeInt(string $value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $intValue = (int) $value;
        if ($intValue < $min || $intValue > $max) {
            return $default;
        }

        return $intValue;
    }

    private function normalizeFloat(string $value, float $min, float $max, float $default): float
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $floatValue = (float) $value;
        if ($floatValue < $min || $floatValue > $max) {
            return $default;
        }

        return $floatValue;
    }

    private function normalizeBool(string $value, bool $default): bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return $default;
        }

        $truthy = ['1', 'true', 'yes', 'on', 'si'];
        $falsy = ['0', 'false', 'no', 'off'];

        if (in_array($value, $truthy, true)) {
            return true;
        }

        if (in_array($value, $falsy, true)) {
            return false;
        }

        return $default;
    }
}
