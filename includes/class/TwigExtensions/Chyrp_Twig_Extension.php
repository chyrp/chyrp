<?php

require_once "Chyrp_Url_TokenParser.php";
require_once "Chyrp_AdminUrl_TokenParser.php";
require_once "Chyrp_PaginateLoop_TokenParser.php";
// require_once INCLUDES_DIR."/helpers.php";

class Twig_LoopContextIterator implements Iterator
{
    public $context;
    public $seq;
    public $idx;
    public $length;
    public $parent;

    public function __construct(&$context, $seq, $parent)
    {
        $this->context = $context;
        $this->seq = $seq;
        $this->idx = 0;
        $this->length = count($seq);
        $this->parent = $parent;
    }

    public function rewind() {}

    public function key() {}

    public function valid()
    {
        return $this->idx < $this->length;
    }

    public function next()
    {
        $this->idx++;
    }

    public function current()
    {
        return $this;
    }
}

/*
 * This file is part of Chyrp.
 *
 * (c) 2014 Arian Xhezairi
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Chyrp_Twig_Extension extends Twig_Extension
{
    /**
     * Loader
     *
     * @var FilesystemLoader
     */
    // protected $loader;
    /**
     * Controller
     *
     * @var array
     */
    // protected $controller;

    /**
     * Loader
     *
     * @param FilesystemLoader $loader
     */
    // public function __construct()
    // {
        //FilesystemLoader $loader
        // $this->loader = $loader;
        // if (!class_exists('IntlDateFormatter')) {
        //     throw new RuntimeException('The intl extension is needed to use intl-based filters.');
        // }
    // }

    // /**
    //  * Controller
    //  *
    //  * @param array $controller
    //  */
    // public function setController(array $controller) {
    //         $this->controller = $controller;
    // }

    // public function getGlobals()
    // {
    //     return array(
    //         'text' => new Text(),
    //     );
    // }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        $filters = array(
            // formatting filters
            // new Twig_SimpleFilter('date', array($this, 'twig_date_format_filter')),
            'date' =>  new \Twig_Filter_Method($this, 'twig_date_format_filter'),
            'strftime' =>  new \Twig_Filter_Method($this, 'twig_strftime_format_filter'),
            'strtotime' => new \Twig_Filter_Function('strtotime'),
            'numberformat' => new \Twig_Filter_Function('number_format'),
            'moneyformat' => new \Twig_Filter_Function('money_format'),
            'filesizeformat' => new \Twig_Filter_Method($this, 'twig_filesize_format_filter'),
            'format' => new \Twig_Filter_Function('sprintf'),
            'relative' => new \Twig_Filter_Function('relative_time'),

            // numbers
            'even' => new \Twig_Filter_Method($this, 'twig_is_even_filter'),
            'odd' => new \Twig_Filter_Method($this, 'twig_is_odd_filter'),

            // encoding
            'quotes' => new \Twig_Filter_Method($this, 'twig_quotes_filter'),
            'slashes' => new \Twig_Filter_Function('addslashes'),

            // string filters
            // new Twig_SimpleFilter('translate', array($this, 'twig_translate_string_filter')),
            'translate' => new \Twig_Filter_Method($this, 'twig_translate_string_filter'),
            'translate_plural' => new \Twig_Filter_Method($this, 'twig_translate_plural_string_filter'),

            'normalize' => new \Twig_Filter_Function('normalize'),
            'truncate' => new \Twig_Filter_Method($this, 'twig_truncate_filter'),
            'excerpt' => new \Twig_Filter_Method($this, 'twig_excerpt_filter'),
            'replace' => new \Twig_Filter_Method($this, 'twig_replace_filter'),
            'match' => new \Twig_Filter_Method($this, 'twig_match_filter'),
            'contains' => new \Twig_Filter_Function('substr_count'),
            'camelize' => new \Twig_Filter_Function('camelize'),
            'pluralize' => new \Twig_Filter_Method($this, 'twig_pluralize_string_filter'),
            'depluralize' => new \Twig_Filter_Method($this, 'twig_depluralize_string_filter'),
            'sanitize' => new \Twig_Filter_Function('sanitize'),
            'repeat' => new \Twig_Filter_Function('str_repeat'),
            'rtrim' => new \Twig_Filter_Function('rtrim'),
            'ltrim' => new \Twig_Filter_Function('ltrim'),

            // string/array filters
            'offset' => new \Twig_Filter_Method($this, 'twig_offset_filter'),
            'count' => new \Twig_Filter_Function('count'),

            // iteration and runtime
            'items' => new \Twig_Filter_Method($this, 'twig_get_array_items_filter'),

            // escaping
            'escape' => new \Twig_Filter_Method($this, 'twig_escape_filter'),
            'e' => new \Twig_Filter_Method($this, 'twig_escape_filter'),

            // Chyrp specific filters
            'uploaded' => new \Twig_Filter_Function('uploaded'),
            'fallback' => new \Twig_Filter_Function('oneof'),
            'selected' => new \Twig_Filter_Method($this, 'twig_selected_filter'),
            'checked' => new \Twig_Filter_Method($this, 'twig_checked_filter'),
            'option_selected' => new \Twig_Filter_Method($this, 'twig_option_selected_filter'),
            'show_gravatar' => new \Twig_Filter_Method($this, 'twig_show_gravatar_filter'),
        );

       return $filters;
    }

    public function getFunctions()
    {
        return array(new Twig_SimpleFunction('paginate', 'twig_paginate', array('needs_context' => true)),
                     new Twig_SimpleFunction('twig_iterate', 'twig_iterate', array('needs_context' => true)),
                     new Twig_SimpleFunction('twig_make_array', 'twig_make_array'),
                     new Twig_SimpleFunction('twig_set_loop_context', 'twig_set_loop_context', array('needs_context' => true)),
                     new Twig_SimpleFunction('twig_make_loop_context', 'twig_make_loop_context', array('needs_context' => true)),
                    );
        // new Twig_SimpleFunction('admin', 'twig_admin', array('needs_environment' => true));
        // new Twig_SimpleFunction('paginate', 'twig_paginate', array('needs_environment' => true,
        //     'needs_context' => true,
        //     'is_safe' => array('all')));
    }

    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of Twig_TokenParser instances
     */
    public function getTokenParsers()
    {
        return array(new Chyrp_Url_TokenParser(),
                     new Chyrp_AdminUrl_TokenParser(),
                     new Chyrp_PaginateLoop_TokenParser(),
                    );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'chyrp';
    }

    /* Chyrp Paginate Function
     *
     */
    public function twig_paginate(&$context, $as, $over, $per_page)
    {
        $name = (in_array("page", Paginator::$names)) ? $as."_page" : "page" ;

        if (count($over) == 2 and $over[0] instanceof Model and is_string($over[1]))
            return $context[$as] = $context["::parent"][$as] = new Paginator($over[0]->__getPlaceholders($over[1]), $per_page, $name);
        else
            return $context[$as] = $context["::parent"][$as] = new Paginator($over, $per_page, $name);
    }

    function twig_iterate(&$context, $seq)
    {
        $parent = isset($context['loop']) ? $context['loop'] : null;
        $seq = twig_make_array($seq);
        $context['loop'] = array('parent' => $parent, 'iterated' => false);
        return new Twig_LoopContextIterator($context, $seq, $parent);
    }

    function twig_make_array($object)
    {
        if (is_array($object))
            return array_values($object);
        elseif (is_object($object)) {
            $result = array();
            foreach ($object as $value)
                $result[] = $value;
            return $result;
        }
        return array();
    }

    function twig_set_loop_context(&$context, $iterator, $target)
    {
        $context[$target] = $iterator->seq[$iterator->idx];
        $context['loop'] = twig_make_loop_context($iterator);
    }

    function twig_make_loop_context($iterator)
    {
        return array(
            'parent' =>     $iterator->parent,
            'length' =>     $iterator->length,
            'index0' =>     $iterator->idx,
            'index' =>      $iterator->idx + 1,
            'revindex0' =>  $iterator->length - $iterator->idx - 1,
            'revindex '=>   $iterator->length - $iterator->idx,
            'first' =>      $iterator->idx == 0,
            'last' =>       $iterator->idx + 1 == $iterator->length,
            'iterated' =>   true
        );
    }

    public function twig_date_format_filter($timestamp, $format='F j, Y, G:i')
    {
        return when($format, $timestamp);
    }

    public function twig_strftime_format_filter($timestamp, $format='%x %X')
    {
        return when($format, $timestamp, true);
    }

    function twig_filesize_format_filter($value)
    {
        $value = max(0, (int)$value);
        $places = strlen($value);
        if ($places <= 9 && $places >= 7) {
            $value = number_format($value / 1048576, 1);
            return "$value MB";
        }
        if ($places >= 10) {
            $value = number_format($value / 1073741824, 1);
            return "$value GB";
        }
        $value = number_format($value / 1024, 1);
        return "$value KB";
    }

    function twig_is_even_filter($value)
    {
        return $value % 2 == 0;
    }

    function twig_is_odd_filter($value)
    {
        return $value % 2 == 1;
    }

    function twig_quotes_filter($string) {
        return str_replace(array('"', "'"), array('\"', "\\'"), $string);
    }

    public function twig_translate_string_filter($string, $domain = "theme") {
        $domain = ($domain == "theme" and ADMIN) ? "chyrp" : $domain ;
        return __($string, $domain);
    }

    public function twig_translate_plural_string_filter($single, $plural, $number, $domain = "theme") {
        $domain = ($domain == "theme" and ADMIN) ? "chyrp" : $domain ;
        return _p($single, $plural, $number, $domain);
    }

    function twig_truncate_filter($text, $length = 100, $ending = "...", $exact = false, $html = true) {
        return truncate($text, $length, $ending, $exact, $html);
    }

    function twig_excerpt_filter($text, $length = 200, $ending = "...", $exact = false, $html = true) {
        $paragraphs = preg_split("/(\r?\n\r?\n|\r\r)/", $text);
        if (count($paragraphs) > 1)
            return $paragraphs[0];
        else
            return truncate($text, $length, $ending, $exact, $html);
    }

    function twig_replace_filter($str, $search, $replace, $regex = false)
    {
        if ($regex)
            return preg_replace($search, $replace, $str);
        else
            return str_replace($search, $replace, $str);
    }

    function twig_match_filter($str, $match)
    {
        return preg_match($match, $str);
    }

    function twig_pluralize_string_filter($string, $number = null) {
        if ($number and $number == 1)
            return $string;
        else
            return pluralize($string);
    }

    function twig_depluralize_string_filter($string) {
        return depluralize($string);
    }

    function twig_offset_filter($array, $offset = 0) {
        return $array[$offset];
    }

    function twig_get_array_items_filter($array)
    {
        $result = array();
        foreach ($array as $key => $value)
            $result[] = array($key, $value);
        return $result;
    }

    function twig_escape_filter($string, $quotes = true, $decode = true) {
        if (!is_string($string)) # Certain post attributes might be parsed from YAML to an array,
            return $string;      # in which case the module provides a value. However, the attr
                                 # is still passed to the "fallback" and "fix" filters when editing.

        $safe = fix($string, $quotes);
        return $decode ? preg_replace("/&amp;(#?[A-Za-z0-9]+);/", "&\\1;", $safe) : $safe ;
    }

    function twig_selected_filter($foo) {
        $try = func_get_args();
        array_shift($try);

        $just_class = (end($try) === true);
        if ($just_class)
            array_pop($try);

        if (is_array($try[0])) {
            foreach ($try as $index => $it)
                if ($index)
                    $try[0][] = $it;

            $try = $try[0];
        }

        if (in_array($foo, $try))
            return ($just_class) ? " selected" : ' class="selected"' ;
    }

    function twig_checked_filter($foo) {
        if ($foo)
            return ' checked="checked"';
    }

    function twig_option_selected_filter($foo) {
        $try = func_get_args();
        array_shift($try);

        if (in_array($foo, $try))
            return ' selected="selected"';
    }

    function twig_show_gravatar_filter($email, $size, $img, $attr = array()) {
        return get_gravatar($email, $size, 'mm', 'g', $img, $attr);
    }

    // /**
    //  * nl2br alias
    //  *
    //  * @param mixed $value
    //  * @param string $sep
    //  * @return string
    //  */
    // public function twig_nl2br_filter($value, $sep = '<br />') {
    //         return str_replace("\n", $sep . "\n", $value);
    // }

    // /**
    //  * Truncate filter
    //  *
    //  * @param \Twig_Environment $env
    //  * @param mixed $value
    //  * @param int $length
    //  * @param bool $preserve
    //  * @param string $separator
    //  * @return string
    //  */
    // public function twig_truncate_filter(\Twig_Environment $env, $value, $length = 30, $preserve = false, $separator = '...') {
    //         if (mb_strlen($value, $env->getCharset()) > $length) {
    //                 if ($preserve) {
    //                         if (false !== ($breakpoint = mb_strpos($value, ' ', $length, $env->getCharset()))) {
    //                                 $length = $breakpoint;
    //                         }
    //                 }

    //                 return mb_substr($value, 0, $length, $env->getCharset()) . $separator;
    //         }

    //         return $value;
    // }

    // *
    //  * Wordwrap filter
    //  *
    //  * @param \Twig_Environment $env
    //  * @param mixed $value
    //  * @param int $length
    //  * @param string $separator
    //  * @param bool $preserve
    //  * @return string

    // public function twig_wordwrap_filter(\Twig_Environment $env, $value, $length = 80, $separator = "\n", $preserve = false) {
    //         $sentences = array();

    //         $previous = mb_regex_encoding();
    //         mb_regex_encoding($env->getCharset());

    //         $pieces = mb_split($separator, $value);
    //         mb_regex_encoding($previous);

    //         foreach ($pieces as $piece) {
    //                 while (!$preserve && mb_strlen($piece, $env->getCharset()) > $length) {
    //                         $sentences[] = mb_substr($piece, 0, $length, $env->getCharset());
    //                         $piece = mb_substr($piece, $length, 2048, $env->getCharset());
    //                 }

    //                 $sentences[] = $piece;
    //         }

    //         return implode($separator, $sentences);
    // }

    // /**
    //  * Title function
    //  *
    //  * @see Blog\WebBundle\Templating\Helper\Title
    //  * @return string
    //  */
    // public function twig_title_function() {
    //         return (string)TitleHelper::getInstance();
    // }

    // /**
    //  * Date filter
    //  *
    //  * @param int $unixtimeStamp
    //  * @return string
    //  */
    // public function twig_date_filter($unixtimeStamp) {
    //         if (!$unixtimeStamp) {
    //                 // return null; // ?
    //                 return 'no';
    //         }

    //         if ($unixtimeStamp <= time()) {
    //                 if ($unixtimeStamp >= strtotime('-1 hour')) return 'now';
    //                 if ($unixtimeStamp >= strtotime('-1 day')) return 'today';
    //                 if ($unixtimeStamp >= strtotime('-2 day')) return 'yesterday';
    //         }
    //         if ($unixtimeStamp >= time() && $unixtimeStamp <= strtotime('+1 day')) {
    //                 return 'tomorrow';
    //         }
    //         // current year
    //         if (date('Y', $unixtimeStamp) == date('Y')) {
    //                 return date('j F', $unixtimeStamp);
    //         }
    //         return date('j F Y', $unixtimeStamp);
    // }

}
