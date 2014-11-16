<?php

/**
 * Represents a node that outputs an expression.
 */
class Twig_Node_PaginateLoop extends Twig_Node
{
    public function __construct(Twig_Node_Expression_AssignName $per_page, Twig_Node_Expression_AssignName $items,
                                Twig_Node_Expression $target, Twig_Node_Expression_AssignName $mod, Twig_NodeInterface $body,
                                Twig_NodeInterface $else = null, $lineno, $tag = null)
    {
        parent::__construct(array('per_page' => $per_page, 'items' =>  $items,
                                  'seq' => $target, 'mod' => $mod,
                                  'body' => $body, 'else' => $else, ),
                            array(), $lineno, $tag);
    }

    public function compile(Twig_Compiler $compiler)
    {
        # $per_page, $mod, $loop, $item, $body, $else, $lineno, $this->getTag()
        // {% paginate site.comments_per_page comments in post.comments as comment %}
        // {% include "content/comment.twig" %}
        // {% else %}
        // <li class="no_comments"></li> {# Needed for AJAX commenting and XHTML Strict validation. #}
        // {% endpaginate %}

        # {% for post in posts.paginated %}


        // $context['::parent'] = $parent = $context;

        // twig_paginate($context,"comments", array($context["::parent"]["post"],"comments"),
        //     twig_get_attribute((isset($context['site']) ? $context['site'] : NULL), "comments_per_page"));

        // foreach (twig_iterate($context, $context["::parent"]["comments"]->paginated) as $iterator) {
        //     twig_set_loop_context($context, $iterator, "comment");
        //     echo "\n";
        //     twig_get_current_template()->loader->getTemplate("content/comment.twig")->display($context);
        //     echo "\n";
        // }

        // if (!$context['loop']['iterated']) {
        //     echo "\n<li class=\"no_comments\"></li> ";
        //     echo "\n";
        // }

        // $context = $context['::parent'];
        // echo "\n</ol>\n";

        $compiler->addDebugInfo($this)
                 ->raw('$context[\'_parent\'] = $_parent = $context;'."\n") // $context['::parent'] = $parent = $context;
                 ->raw('twig_paginate($context,')                            // twig_paginate($context,
                 ->raw('"'.$this->getNode('items')->name.'", ');             // "comments",

        if (!is_null($this->getNode('seq')) and isset($this->getNode('seq')->attr)) {
            $compiler->raw('array($context["_parent"]["')                    // array($context["::parent"]["
                     ->raw($this->getNode('seq')->name.'"],')                // post"],
                     ->raw('"'.$this->getNode('seq')->attr.'")');            // "comments"),
        } else {
            $compiler->subcompile($this->getNode('seq'));
        }

        $compiler->raw(', ')
                 ->subcompile($this->getNode('per_page'))                      // "comments_per_page")
                 ->raw(");\n")                                                 // );
                 ->raw('foreach (twig_iterate($context,')                      // foreach (twig_iterate($context,
                 ->raw(' $context["_parent"]["'.$this->getNode('items')->name) // $context["::parent"]["comments
                 ->raw("\"]->paginated) as \$iterator) {\n")                   // "]->paginated) as $iterator) {
                 ->write('twig_set_loop_context($context, $iterator, ')        // twig_set_loop_context($context, $iterator,
                 ->repr($this->getNode('mod')->attr->value)                    // "comment"
                 ->raw(");\n")                                                 // );
                 ->subcompile($this->getNode('body'))                          //     twig_get_current_template()->loader->getTemplate("content/comment.twig")->display($context);
                 ->raw("}\n");                                                 // }

        if (!is_null($this->getNode('else'))) {
            $compiler->raw("if (!\$context['loop']['_iterated']) {\n")
                     ->subcompile($this->getNode('else'))
                     ->raw('}');
        }
        $compiler->raw('$context = $context["_parent"];'."\n"); # $compiler->popContext();
    }
}
