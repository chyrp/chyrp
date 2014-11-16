<?php

/**
 * Represents a node that outputs an expression.
 */
class Twig_Node_AdminUrl extends Twig_Node implements Twig_NodeOutputInterface
{
    public function __construct(Twig_Node_Expression $expr, $name, $lineno, $tag = null)
    {
        parent::__construct(array('expr' => $expr), array(), $lineno, $tag);
    }

    public function compile(Twig_Compiler $compiler)
    {
        $compiler->addDebugInfo($this)
                 ->raw('echo fix(Config::current()->chyrp_url."/admin/?action=".(')
                 ->subcompile($this->getNode('expr'))
                 ->raw("));\n")
        ;
    }
}
