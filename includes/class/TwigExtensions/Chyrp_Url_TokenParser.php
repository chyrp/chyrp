<?php

require_once 'Twig_Node_Url.php';

class Chyrp_Url_TokenParser extends Twig_TokenParser
{
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $expr = $this->parser->getExpressionParser()->parseExpression();
        $stream = $this->parser->getStream();

        if ($stream->test("in")) {
            $this->parser->getExpressionParser()->parseExpression();
            $cont = $this->parser->getExpressionParser()->parseExpression();
        } else
            $cont = null;

        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        return new Twig_Node_Url($expr, $cont, $lineno, $this->getTag());
    }

    public function getTag()
    {
        return 'url';
    }
}
