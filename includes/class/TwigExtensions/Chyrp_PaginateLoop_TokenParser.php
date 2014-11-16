<?php

require_once 'Twig_Node_PaginateLoop.php';

/**
*
*/
class Chyrp_PaginateLoop_TokenParser extends Twig_TokenParser
{
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();

        $per_page = $this->parser->getExpressionParser()->parseExpression();
        $items = $this->parser->getExpressionParser()->parseExpression();
        $this->parser->getStream()->expect('in');
        $target = $this->parser->getExpressionParser()->parseExpression();
        $this->parser->getStream()->expect('as');
        $mod = $this->parser->getExpressionParser()->parseExpression();
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decidePaginateFork'));

        if ($this->parser->getStream()->next()->value == 'else') {
            $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
            $else = $this->parser->subparse(array($this, 'decidePaginateEnd'), true);
        } else {
            $else = null;
        }

        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

        return new Twig_Node_PaginateLoop($per_page, $items, $target, $mod, $body, $else, $lineno, $this->getTag());
    }

    public function decidePaginateFork($token)
    {
        return $token->test(array('else', 'endpaginate'));
    }

    public function decidePaginateEnd($token)
    {
        return $token->test('endpaginate');
    }

    public function getTag()
    {
        return 'paginate';
    }
}
