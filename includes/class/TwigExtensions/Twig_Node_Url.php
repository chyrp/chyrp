<?php

/**
 * Represents a node that outputs an expression.
 */
class Twig_Node_Url extends Twig_Node implements Twig_NodeOutputInterface
{
    public function __construct(Twig_Node_Expression $expr, $cont, $lineno, $tag = null)
    {
        parent::__construct(array('expr' => $expr), array('cont' => $cont), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->raw('echo url(')
            ->subcompile($this->getNode('expr'))
        ;

        if (!empty($this->getAttribute('cont')) and class_exists($this->getAttribute('cont')->name."Controller") and is_callable(array($this->getAttribute('cont')->name."Controller", "current"))) {
            $compiler->raw(", ".$this->getAttribute('cont')->name."Controller::current()");
        }

        $compiler->raw(");\n");
    }
}
