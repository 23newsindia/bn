<?php
use voku\helper\HtmlMin;

class MACP_Minify_HTML {
    private static $instance = null;
    private $minifier;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->minifier = new HtmlMin();
        $this->configure_minifier();
    }

    private function configure_minifier() {
        $this->minifier
            ->doOptimizeViaHtmlDomParser(true)
            ->doRemoveComments(true)
            ->doSumUpWhitespace(true)
            ->doRemoveWhitespaceAroundTags(true)
            ->doOptimizeAttributes(true)
            ->doRemoveHttpPrefixFromAttributes(true)
            ->doRemoveDefaultAttributes(true)
            ->doRemoveDeprecatedAnchorName(true)
            ->doRemoveDeprecatedScriptCharsetAttribute(true)
            ->doRemoveDeprecatedTypeFromScriptTag(true)
            ->doRemoveDeprecatedTypeFromStylesheetLink(true)
            ->doRemoveEmptyAttributes(true)
            ->doRemoveValueFromEmptyInput(true)
            ->doSortCssClassNames(true)
            ->doSortHtmlAttributes(true)
            ->doRemoveSpacesBetweenTags(true);
    }

    public function minify($html) {
        if (empty($html)) {
            return $html;
        }

        try {
            return $this->minifier->minify($html);
        } catch (Exception $e) {
            MACP_Debug::log("HTML minification error: " . $e->getMessage());
            return $html;
        }
    }
}