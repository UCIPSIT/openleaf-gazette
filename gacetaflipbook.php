<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.gacetaflipbook
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgContentGacetaflipbook extends CMSPlugin
{
    /** @var bool */
    private static $assetsLoaded = false;

    /**
     * Replace shortcode tags.
     *
     * Supported tags: {gacetaflip ...}, {gacetaflipbook ...}, {openleaf ...}, {openleaf}
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        if ($context === 'com_finder.indexer') {
            return true;
        }

        if (!is_object($article) || !property_exists($article, 'text') || !is_string($article->text)) {
            return true;
        }

        if (stripos($article->text, '{gacetaflip') === false
            && stripos($article->text, '{gacetaflipbook') === false
            && stripos($article->text, '{openleaf') === false) {
            return true;
        }

        $regex = '/{\s*(gacetaflip|gacetaflipbook|openleaf)(?:\s+([^}]*))?}/i';

        if (!preg_match_all($regex, $article->text, $matches, PREG_SET_ORDER)) {
            return true;
        }

        foreach ($matches as $match) {
            $tagParams = $this->parseTagParameters((string) ($match[2] ?? ''));
            $replacement = $this->renderFlipbook($tagParams, (string) $context, $article);
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
    private function renderFlipbook(array $tagParams, string $context, $article): string
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

        return $assets . $this->renderNativeMode($tagParams, $context, $article);
    }

    /**
     * Embed external URL in an iframe (closest visual match if using FlipHTML5 URL).
     */
    private function renderEmbedMode(array $tagParams): string
    {
        $url = trim((string) ($tagParams['url'] ?? $tagParams['src'] ?? $this->params->get('default_embed_url', '')));

        if ($url === '') {
            return '<p class="gacetaflip-error">OpenLeaf Gazette: missing <code>url</code> in <code>embed</code> mode. Configure <strong>Default embed URL</strong> in plugin settings or pass <code>url="..."</code>.</p>';
        }

        $safeUrl = $this->normalizeEmbedUrl($url);
        if ($safeUrl === '') {
            return '<p class="gacetaflip-error">OpenLeaf Gazette: invalid <code>embed</code> URL.</p>';
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
    private function renderNativeMode(array $tagParams, string $context, $article): string
    {
        $file = $this->resolveNativePdfFile($tagParams, $context, $article);

        if ($file === '') {
            return '<p class="gacetaflip-error">OpenLeaf Gazette: missing <code>file</code> in <code>native</code> mode. Configure <strong>Default PDF file</strong>, add entries in <strong>PDF map by section</strong>, or pass <code>file="..."</code>.</p>';
        }

        $fileUrl = $this->normalizeFileUrl($file);
        if ($fileUrl === '') {
            return '<p class="gacetaflip-error">OpenLeaf Gazette: invalid PDF path.</p>';
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

        $fitMode = strtolower(trim((string) ($tagParams['fit'] ?? $this->params->get('default_fit_mode', 'screen'))));
        if ($fitMode !== 'screen' && $fitMode !== 'normal') {
            $fitMode = 'screen';
        }

        $autoFullscreen = $this->normalizeBool(
            (string) ($tagParams['autofullscreen'] ?? $tagParams['startfullscreen'] ?? $this->params->get('auto_fullscreen', 0)),
            false
        );

        $bookId = 'gacetaflip-native-' . substr(sha1(uniqid((string) mt_rand(), true)), 0, 12);
        $shellClass = $fitMode === 'screen' ? ' gacetaflip-fit-screen' : '';

        return '<div class="gacetaflip-shell gacetaflip-shell-native' . $shellClass . '">'
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
            . ' data-fitmode="' . htmlspecialchars($fitMode, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-autofullscreen="' . ($autoFullscreen ? '1' : '0') . '"'
            . '></div>'
            . '</div>';
    }

    /**
     * Resolve native PDF source with precedence:
     * shortcode file/pdf > shortcode section/key map > Itemid map > category map > context/default map > default PDF.
     */
    private function resolveNativePdfFile(array $tagParams, string $context, $article): string
    {
        $directFile = trim((string) ($tagParams['file'] ?? $tagParams['pdf'] ?? ''));
        if ($directFile !== '') {
            return $directFile;
        }

        $mappedFile = $this->resolvePdfFromMap($tagParams, $context, $article);
        if ($mappedFile !== '') {
            return $mappedFile;
        }

        return trim((string) $this->params->get('default_pdf_file', ''));
    }

    /**
     * Resolve PDF path from plugin config map.
     */
    private function resolvePdfFromMap(array $tagParams, string $context, $article): string
    {
        $map = $this->getConfiguredPdfMap();
        if ($map === []) {
            return '';
        }

        $sectionKey = strtolower(trim((string) ($tagParams['section'] ?? $tagParams['key'] ?? '')));
        if ($sectionKey !== '' && isset($map[$sectionKey])) {
            return $map[$sectionKey];
        }

        $app = Factory::getApplication();
        $itemid = (int) $app->input->getInt('Itemid', 0);
        if ($itemid > 0) {
            foreach (['itemid:' . $itemid, 'menu:' . $itemid] as $itemidKey) {
                if (isset($map[$itemidKey])) {
                    return $map[$itemidKey];
                }
            }
        }

        $categoryId = $this->resolveArticleCategoryId($article);
        if ($categoryId > 0) {
            foreach (['catid:' . $categoryId, 'category:' . $categoryId] as $categoryKey) {
                if (isset($map[$categoryKey])) {
                    return $map[$categoryKey];
                }
            }
        }

        $normalizedContext = strtolower(trim($context));
        if ($normalizedContext !== '' && isset($map['context:' . $normalizedContext])) {
            return $map['context:' . $normalizedContext];
        }

        if (isset($map['default'])) {
            return $map['default'];
        }

        if (isset($map['*'])) {
            return $map['*'];
        }

        return '';
    }

    /**
     * Parse mapping lines from plugin config.
     *
     * Accepted formats per line:
     * - key=value
     * - key|value
     */
    private function getConfiguredPdfMap(): array
    {
        $rawMap = (string) $this->params->get('section_pdf_map', '');
        if (trim($rawMap) === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $rawMap) ?: [];
        $map = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                continue;
            }

            $separatorPosition = strpos($line, '=');
            if ($separatorPosition === false) {
                $separatorPosition = strpos($line, '|');
            }

            if ($separatorPosition === false) {
                continue;
            }

            $key = strtolower(trim(substr($line, 0, $separatorPosition)));
            $value = trim(substr($line, $separatorPosition + 1));

            if ($key === '' || $value === '') {
                continue;
            }

            $map[$key] = $value;
        }

        return $map;
    }

    /**
     * Best-effort article category detection for automatic catid mapping.
     */
    private function resolveArticleCategoryId($article): int
    {
        if (!is_object($article) || !property_exists($article, 'catid') || !is_numeric($article->catid)) {
            return 0;
        }

        $catid = (int) $article->catid;

        return $catid > 0 ? $catid : 0;
    }

    /**
     * Inject CSS/JS once per page.
     */
    private function renderAssets(): string
    {
        $rootPath = rtrim(Uri::root(true), '/');
        $assetBase = ($rootPath === '' ? '' : $rootPath) . '/plugins/content/gacetaflipbook/assets';
        $cssPath = __DIR__ . '/assets/gacetaflipbook.css';
        $jsPath = __DIR__ . '/assets/gacetaflipbook.js';
        $assetVersion = (string) max((int) @filemtime($cssPath), (int) @filemtime($jsPath), (int) @filemtime(__FILE__));
        $assetQuery = $assetVersion !== '0' ? '?v=' . rawurlencode($assetVersion) : '';
        $cssUrl = $assetBase . '/gacetaflipbook.css' . $assetQuery;
        $jsUrl = $assetBase . '/gacetaflipbook.js' . $assetQuery;

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
