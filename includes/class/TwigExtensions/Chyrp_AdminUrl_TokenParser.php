<?php

require_once 'Twig_Node_AdminUrl.php';

/**
*
*/
class Chyrp_AdminUrl_TokenParser extends Twig_TokenParser
{
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $expr = $this->parser->getExpressionParser()->parseExpression();
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

        return new Twig_Node_AdminUrl($expr, $lineno, $this->getTag());
    }

    public function getTag()
    {
        return 'admin';
    }
}
